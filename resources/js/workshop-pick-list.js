let fabricModulePromise = null;

const DEFAULT_VIEWPORT = Object.freeze([1, 0, 0, 1, 64, 64]);
const MIN_ZOOM = 0.2;
const MAX_ZOOM = 4;
const HISTORY_LIMIT = 60;

const loadFabric = async () => {
    if (!fabricModulePromise) {
        fabricModulePromise = import('fabric');
    }

    return fabricModulePromise;
};

const cloneJson = (value) => JSON.parse(JSON.stringify(value));

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const normalizedString = (value) => String(value ?? '').trim();

class PickListCanvasController {
    constructor(config = {}) {
        this.canvasElement = config.canvasElement;
        this.viewportElement = config.viewportElement;
        this.initialData = normalizedString(config.initialData);
        this.initialColor = normalizedString(config.initialColor) || '#dc2626';
        this.initialBrushSize = Number.parseInt(String(config.initialBrushSize ?? 4), 10) || 4;
        this.onDirty = typeof config.onDirty === 'function' ? config.onDirty : () => {};
        this.onStateChange = typeof config.onStateChange === 'function' ? config.onStateChange : () => {};

        this.canvas = null;
        this.fabric = null;
        this.tool = 'draw';
        this.brushColor = this.initialColor;
        this.brushSize = clamp(this.initialBrushSize, 1, 48);
        this.isPanning = false;
        this.lastPanPoint = null;
        this.activeTouchPointers = new Map();
        this.isTouchGestureActive = false;
        this.pinchState = null;
        this.history = [];
        this.historyIndex = -1;
        this.suspendHistory = false;
        this.cachedSavePayload = null;
        this.resizeObserver = null;
        this.windowResizeHandler = null;
        this.pointerDownHandler = null;
        this.pointerMoveHandler = null;
        this.pointerUpHandler = null;
    }

    async init() {
        if (!(this.canvasElement instanceof HTMLCanvasElement)) {
            throw new Error('Pick list canvas element is missing.');
        }

        this.fabric = await loadFabric();

        this.canvas = new this.fabric.Canvas(this.canvasElement, {
            selection: false,
            preserveObjectStacking: true,
            stopContextMenu: true,
            renderOnAddRemove: true,
            enableRetinaScaling: true,
            allowTouchScrolling: false,
            isDrawingMode: true,
            backgroundColor: '#ffffff',
        });

        this.canvas.skipTargetFind = true;
        this.decorateCanvasSurface();
        this.bindCanvasEvents();
        this.bindTouchGestures();
        this.bindResizeHandling();
        this.resizeToViewport();

        if (this.initialData !== '') {
            await this.loadSerializedData(this.initialData);
        } else {
            this.resetView({ markDirty: false });
        }

        this.applyBrushSettings();
        this.setTool(this.tool, { markDirty: false });
        this.captureHistorySnapshot({ replace: true });
        this.emitState();

        return this;
    }

    decorateCanvasSurface() {
        const targetElements = [
            this.viewportElement,
            this.canvas.wrapperEl,
            this.canvas.upperCanvasEl,
            this.canvas.lowerCanvasEl,
        ].filter((element) => element instanceof HTMLElement);

        targetElements.forEach((element) => {
            element.style.touchAction = 'none';
            element.style.webkitUserSelect = 'none';
            element.style.userSelect = 'none';
        });
    }

    bindCanvasEvents() {
        this.canvas.on('path:created', ({ path }) => {
            if (!path) {
                return;
            }

            path.set({
                selectable: false,
                evented: false,
                hasControls: false,
                hasBorders: false,
                perPixelTargetFind: false,
            });

            if (this.tool === 'erase') {
                path.set({
                    globalCompositeOperation: 'destination-out',
                    stroke: '#000000',
                    smIsEraser: true,
                });
            } else {
                path.set({
                    globalCompositeOperation: 'source-over',
                    smIsEraser: false,
                });
            }

            this.canvas.requestRenderAll();
            this.invalidateExportCache();
            this.captureHistorySnapshot();
            this.onDirty();
            this.emitState();
        });

        this.canvas.on('mouse:wheel', (event) => {
            const pointerEvent = event.e;
            if (!(pointerEvent instanceof WheelEvent)) {
                return;
            }

            pointerEvent.preventDefault();
            pointerEvent.stopPropagation();

            const delta = pointerEvent.deltaY;
            const currentZoom = this.canvas.getZoom();
            const nextZoom = clamp(currentZoom * Math.pow(0.999, delta), MIN_ZOOM, MAX_ZOOM);
            const point = this.fabricPointFromClient(pointerEvent.clientX, pointerEvent.clientY);

            this.canvas.zoomToPoint(point, nextZoom);
            this.canvas.requestRenderAll();
            this.invalidateExportCache();
            this.onDirty();
            this.emitState();
        });

        this.canvas.on('mouse:down', (event) => {
            if (this.tool !== 'pan' || this.pinchState) {
                return;
            }

            const pointerEvent = event.e;
            if (!(pointerEvent instanceof MouseEvent || pointerEvent instanceof PointerEvent || pointerEvent instanceof TouchEvent)) {
                return;
            }

            this.isPanning = true;
            this.lastPanPoint = this.clientPointFromEvent(pointerEvent);
            this.canvas.defaultCursor = 'grabbing';

            if (typeof pointerEvent.preventDefault === 'function') {
                pointerEvent.preventDefault();
            }
        });

        this.canvas.on('mouse:move', (event) => {
            if (!this.isPanning || this.tool !== 'pan' || this.pinchState) {
                return;
            }

            const pointerEvent = event.e;
            const nextPoint = this.clientPointFromEvent(pointerEvent);
            if (!nextPoint || !this.lastPanPoint) {
                return;
            }

            const viewport = Array.isArray(this.canvas.viewportTransform)
                ? [...this.canvas.viewportTransform]
                : [...DEFAULT_VIEWPORT];

            viewport[4] += nextPoint.x - this.lastPanPoint.x;
            viewport[5] += nextPoint.y - this.lastPanPoint.y;

            this.canvas.setViewportTransform(viewport);
            this.canvas.requestRenderAll();
            this.lastPanPoint = nextPoint;

            if (typeof pointerEvent.preventDefault === 'function') {
                pointerEvent.preventDefault();
            }
        });

        this.canvas.on('mouse:up', () => {
            if (!this.isPanning) {
                return;
            }

            this.isPanning = false;
            this.lastPanPoint = null;
            this.canvas.defaultCursor = 'grab';
            this.invalidateExportCache();
            this.onDirty();
            this.emitState();
        });
    }

    bindTouchGestures() {
        const upperCanvas = this.canvas.upperCanvasEl;
        if (!(upperCanvas instanceof HTMLCanvasElement)) {
            return;
        }

        this.pointerDownHandler = (event) => {
            if (!(event instanceof PointerEvent) || event.pointerType !== 'touch') {
                return;
            }

            this.activeTouchPointers.set(event.pointerId, {
                x: event.clientX,
                y: event.clientY,
            });

            if (this.activeTouchPointers.size === 2) {
                this.beginTouchGesture();
            }

            if (this.activeTouchPointers.size > 1) {
                event.preventDefault();
            }
        };

        this.pointerMoveHandler = (event) => {
            if (!(event instanceof PointerEvent) || event.pointerType !== 'touch') {
                return;
            }

            if (!this.activeTouchPointers.has(event.pointerId)) {
                return;
            }

            this.activeTouchPointers.set(event.pointerId, {
                x: event.clientX,
                y: event.clientY,
            });

            if (this.activeTouchPointers.size < 2 || !this.pinchState) {
                return;
            }

            event.preventDefault();

            const distance = this.touchDistance();
            if (distance <= 0) {
                return;
            }

            const zoom = clamp(this.pinchState.zoom * (distance / this.pinchState.distance), MIN_ZOOM, MAX_ZOOM);
            const midpoint = this.touchMidpoint();
            const scale = zoom / this.pinchState.zoom;
            const viewport = [...this.pinchState.viewportTransform];

            viewport[0] = zoom;
            viewport[1] = 0;
            viewport[2] = 0;
            viewport[3] = zoom;
            viewport[4] = midpoint.x - (scale * this.pinchState.midpoint.x) + (scale * this.pinchState.viewportTransform[4]);
            viewport[5] = midpoint.y - (scale * this.pinchState.midpoint.y) + (scale * this.pinchState.viewportTransform[5]);

            this.canvas.setViewportTransform(viewport);
            this.canvas.requestRenderAll();
            this.invalidateExportCache();
            this.emitState();
        };

        this.pointerUpHandler = (event) => {
            if (!(event instanceof PointerEvent) || event.pointerType !== 'touch') {
                return;
            }

            this.activeTouchPointers.delete(event.pointerId);

            if (this.activeTouchPointers.size < 2) {
                this.pinchState = null;
            }

            if (this.activeTouchPointers.size === 0 && this.isTouchGestureActive) {
                this.isTouchGestureActive = false;
                this.applyToolMode();
            }

            if (this.activeTouchPointers.size < 2) {
                this.invalidateExportCache();
                this.onDirty();
                this.emitState();
            }
        };

        upperCanvas.addEventListener('pointerdown', this.pointerDownHandler, { passive: false });
        upperCanvas.addEventListener('pointermove', this.pointerMoveHandler, { passive: false });
        upperCanvas.addEventListener('pointerup', this.pointerUpHandler, { passive: false });
        upperCanvas.addEventListener('pointercancel', this.pointerUpHandler, { passive: false });
        upperCanvas.addEventListener('pointerleave', this.pointerUpHandler, { passive: false });
    }

    bindResizeHandling() {
        this.windowResizeHandler = () => this.resizeToViewport();
        window.addEventListener('resize', this.windowResizeHandler);

        if (typeof ResizeObserver === 'function' && this.viewportElement instanceof HTMLElement) {
            this.resizeObserver = new ResizeObserver(() => {
                this.resizeToViewport();
            });
            this.resizeObserver.observe(this.viewportElement);
        }
    }

    resizeToViewport() {
        if (!(this.viewportElement instanceof HTMLElement) || !this.canvas) {
            return;
        }

        const rect = this.viewportElement.getBoundingClientRect();
        const width = Math.max(320, Math.round(rect.width || this.viewportElement.clientWidth || 0));
        const height = Math.max(360, Math.round(rect.height || this.viewportElement.clientHeight || 0));

        this.canvas.setDimensions({
            width,
            height,
        });
        this.canvas.calcOffset();
        this.canvas.requestRenderAll();
    }

    setTool(tool, options = {}) {
        const nextTool = ['draw', 'erase', 'pan'].includes(tool) ? tool : 'draw';
        this.tool = nextTool;
        this.applyToolMode();

        if (options.markDirty !== false) {
            this.invalidateExportCache();
        }

        this.emitState();
    }

    setColor(color) {
        const nextColor = normalizedString(color) || '#dc2626';
        this.brushColor = nextColor;
        this.applyBrushSettings();
        this.emitState();
    }

    setBrushSize(size) {
        this.brushSize = clamp(Number.parseInt(String(size), 10) || 4, 1, 48);
        this.applyBrushSettings();
        this.emitState();
    }

    zoomIn() {
        this.zoomBy(1.15);
    }

    zoomOut() {
        this.zoomBy(1 / 1.15);
    }

    zoomBy(multiplier) {
        const center = new this.fabric.Point(this.canvas.getWidth() / 2, this.canvas.getHeight() / 2);
        const zoom = clamp(this.canvas.getZoom() * multiplier, MIN_ZOOM, MAX_ZOOM);
        this.canvas.zoomToPoint(center, zoom);
        this.canvas.requestRenderAll();
        this.invalidateExportCache();
        this.onDirty();
        this.emitState();
    }

    resetView(options = {}) {
        this.canvas.setViewportTransform([...DEFAULT_VIEWPORT]);
        this.canvas.requestRenderAll();

        if (options.markDirty !== false) {
            this.invalidateExportCache();
            this.onDirty();
        }

        this.emitState();
    }

    clearCanvas(options = {}) {
        this.removeUserObjects();
        this.canvas.requestRenderAll();
        this.invalidateExportCache();
        this.captureHistorySnapshot();

        if (options.markDirty !== false) {
            this.onDirty();
        }

        this.emitState();
    }

    undo() {
        if (this.historyIndex <= 0) {
            return;
        }

        this.historyIndex -= 1;
        this.restoreHistorySnapshot(this.history[this.historyIndex]);
    }

    redo() {
        if (this.historyIndex >= this.history.length - 1) {
            return;
        }

        this.historyIndex += 1;
        this.restoreHistorySnapshot(this.history[this.historyIndex]);
    }

    async exportForSave() {
        if (this.cachedSavePayload) {
            return cloneJson(this.cachedSavePayload);
        }

        const serialized = this.serializeCanvasState();
        if (serialized === null) {
            this.cachedSavePayload = {
                json: null,
                thumbnailDataUrl: '',
                hasContent: false,
            };

            return cloneJson(this.cachedSavePayload);
        }

        this.cachedSavePayload = {
            json: JSON.stringify(serialized),
            thumbnailDataUrl: this.canvas.toDataURL({
                format: 'png',
                multiplier: 0.35,
            }),
            hasContent: true,
        };

        return cloneJson(this.cachedSavePayload);
    }

    hasContent() {
        return this.userObjects().length > 0;
    }

    async loadSerializedData(rawJson) {
        const parsed = this.parseSerializedData(rawJson);
        if (!parsed) {
            this.removeUserObjects();
            this.resetView({ markDirty: false });
            return;
        }

        const fabricJson = parsed.canvas ?? parsed;
        const viewportTransform = Array.isArray(parsed.viewport?.transform)
            ? this.normalizeViewportTransform(parsed.viewport.transform)
            : [...DEFAULT_VIEWPORT];

        this.suspendHistory = true;
        this.removeUserObjects();
        await this.canvas.loadFromJSON(fabricJson);
        this.userObjects().forEach((object) => {
            object.set({
                selectable: false,
                evented: false,
                hasControls: false,
                hasBorders: false,
            });
        });
        this.canvas.setViewportTransform(viewportTransform);
        this.canvas.requestRenderAll();
        this.suspendHistory = false;
    }

    serializeCanvasState() {
        if (!this.hasContent()) {
            return null;
        }

        return {
            schema_version: 1,
            viewport: {
                transform: this.normalizeViewportTransform(this.canvas.viewportTransform),
            },
            brush: {
                color: this.brushColor,
                size: this.brushSize,
            },
            canvas: this.canvas.toJSON([
                'globalCompositeOperation',
                'smIsEraser',
                'selectable',
                'evented',
                'hasControls',
                'hasBorders',
            ]),
        };
    }

    parseSerializedData(rawJson) {
        const json = normalizedString(rawJson);
        if (json === '') {
            return null;
        }

        try {
            const parsed = JSON.parse(json);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            console.warn('Could not parse saved pick list canvas data.', error);
            return null;
        }
    }

    normalizeViewportTransform(transform) {
        if (!Array.isArray(transform) || transform.length !== 6) {
            return [...DEFAULT_VIEWPORT];
        }

        return transform.map((value, index) => {
            const numeric = Number.parseFloat(String(value));
            if (!Number.isFinite(numeric)) {
                return DEFAULT_VIEWPORT[index];
            }

            if (index === 0 || index === 3) {
                return clamp(numeric, MIN_ZOOM, MAX_ZOOM);
            }

            return numeric;
        });
    }

    applyBrushSettings() {
        if (!this.canvas || this.tool === 'pan') {
            return;
        }

        const brush = new this.fabric.PencilBrush(this.canvas);
        brush.width = this.brushSize;
        brush.color = this.tool === 'erase' ? '#000000' : this.brushColor;
        this.canvas.freeDrawingBrush = brush;
    }

    applyToolMode() {
        if (!this.canvas) {
            return;
        }

        if (this.tool === 'pan' || this.isTouchGestureActive) {
            this.canvas.isDrawingMode = false;
            this.canvas.defaultCursor = this.tool === 'pan' ? 'grab' : 'crosshair';
            this.canvas.hoverCursor = this.tool === 'pan' ? 'grab' : 'crosshair';
            this.canvas.freeDrawingCursor = this.tool === 'erase' ? 'cell' : 'crosshair';
            return;
        }

        this.canvas.isDrawingMode = true;
        this.canvas.defaultCursor = 'crosshair';
        this.canvas.hoverCursor = 'crosshair';
        this.canvas.freeDrawingCursor = this.tool === 'erase' ? 'cell' : 'crosshair';
        this.applyBrushSettings();
    }

    beginTouchGesture() {
        this.cancelCurrentStroke();
        this.isTouchGestureActive = true;
        this.isPanning = false;
        this.lastPanPoint = null;
        this.pinchState = {
            distance: this.touchDistance(),
            midpoint: this.touchMidpoint(),
            zoom: this.canvas.getZoom(),
            viewportTransform: Array.isArray(this.canvas.viewportTransform)
                ? [...this.canvas.viewportTransform]
                : [...DEFAULT_VIEWPORT],
        };
        this.applyToolMode();
    }

    cancelCurrentStroke() {
        if (!this.canvas) {
            return;
        }

        this.canvas._isCurrentlyDrawing = false;
        this.canvas.clearContext(this.canvas.contextTop);

        if (typeof this.canvas.freeDrawingBrush?._reset === 'function') {
            this.canvas.freeDrawingBrush._reset();
        }
    }

    removeUserObjects() {
        [...this.canvas.getObjects()].forEach((object) => {
            this.canvas.remove(object);
        });
    }

    userObjects() {
        return this.canvas.getObjects();
    }

    invalidateExportCache() {
        this.cachedSavePayload = null;
    }

    captureHistorySnapshot(options = {}) {
        if (this.suspendHistory) {
            return;
        }

        const snapshot = JSON.stringify(this.serializeCanvasState());
        const currentSnapshot = this.historyIndex >= 0 ? this.history[this.historyIndex] : null;
        if (!options.replace && snapshot === currentSnapshot) {
            return;
        }

        if (options.replace) {
            this.history = [snapshot];
            this.historyIndex = 0;
            return;
        }

        this.history = this.history.slice(0, this.historyIndex + 1);
        this.history.push(snapshot);
        if (this.history.length > HISTORY_LIMIT) {
            this.history.shift();
        }
        this.historyIndex = this.history.length - 1;
    }

    async restoreHistorySnapshot(snapshot) {
        const parsed = snapshot === 'null' ? null : snapshot;
        this.suspendHistory = true;

        if (parsed === null) {
            this.removeUserObjects();
            this.resetView({ markDirty: false });
        } else {
            await this.loadSerializedData(parsed);
        }

        this.suspendHistory = false;
        this.invalidateExportCache();
        this.onDirty();
        this.emitState();
    }

    clientPointFromEvent(event) {
        if (!event) {
            return null;
        }

        if ('clientX' in event && 'clientY' in event) {
            return { x: event.clientX, y: event.clientY };
        }

        const touch = event.touches?.[0] || event.changedTouches?.[0];
        if (!touch) {
            return null;
        }

        return { x: touch.clientX, y: touch.clientY };
    }

    fabricPointFromClient(clientX, clientY) {
        const rect = this.canvas.upperCanvasEl.getBoundingClientRect();

        return new this.fabric.Point(clientX - rect.left, clientY - rect.top);
    }

    touchDistance() {
        const points = Array.from(this.activeTouchPointers.values());
        if (points.length < 2) {
            return 0;
        }

        const [first, second] = points;
        return Math.hypot(second.x - first.x, second.y - first.y);
    }

    touchMidpoint() {
        const points = Array.from(this.activeTouchPointers.values());
        if (points.length < 2) {
            return { x: 0, y: 0 };
        }

        const [first, second] = points;
        return {
            x: (first.x + second.x) / 2,
            y: (first.y + second.y) / 2,
        };
    }

    emitState() {
        this.onStateChange({
            tool: this.tool,
            color: this.brushColor,
            brushSize: this.brushSize,
            canUndo: this.historyIndex > 0,
            canRedo: this.historyIndex >= 0 && this.historyIndex < this.history.length - 1,
            zoomPercent: Math.round(this.canvas.getZoom() * 100),
            hasContent: this.hasContent(),
        });
    }

    dispose() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }

        if (this.windowResizeHandler) {
            window.removeEventListener('resize', this.windowResizeHandler);
            this.windowResizeHandler = null;
        }

        if (this.canvas?.upperCanvasEl instanceof HTMLCanvasElement) {
            const upperCanvas = this.canvas.upperCanvasEl;
            if (this.pointerDownHandler) {
                upperCanvas.removeEventListener('pointerdown', this.pointerDownHandler);
            }
            if (this.pointerMoveHandler) {
                upperCanvas.removeEventListener('pointermove', this.pointerMoveHandler);
            }
            if (this.pointerUpHandler) {
                upperCanvas.removeEventListener('pointerup', this.pointerUpHandler);
                upperCanvas.removeEventListener('pointercancel', this.pointerUpHandler);
                upperCanvas.removeEventListener('pointerleave', this.pointerUpHandler);
            }
        }

        if (this.canvas) {
            this.canvas.dispose();
            this.canvas = null;
        }
    }
}

const pickListToolbarButtonClasses = (isActive = false) => {
    const base = 'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition';

    if (isActive) {
        return `${base} border-primary-color bg-primary-color text-white`;
    }

    return `${base} border-gray-300 bg-white text-gray-700 hover:bg-gray-50`;
};

const registerWorkshopPickListPage = () => {
    window.SM = window.SM || {};

    window.SM.workshopPickListPage = (config = {}) => ({
        saveUrl: config.saveUrl || '',
        csrfToken: config.csrfToken || '',
        templateItems: Array.isArray(config.templateItems) ? config.templateItems : [],
        customItems: Array.isArray(config.customItems) ? config.customItems : [],
        itemSuggestions: Array.isArray(config.itemSuggestions) ? config.itemSuggestions : [],
        isCustomized: Boolean(config.isCustomized),
        itemsEditMode: false,
        customItemsDirty: false,
        resetCustomization: false,
        nextCustomItemId: 1,
        checkedIds: Array.isArray(config.checkedItemIds) ? config.checkedItemIds : [],
        participantsInput: String(config.participantsInput ?? ''),
        notes: String(config.notes ?? ''),
        defaultParticipants: Number.parseInt(String(config.defaultParticipants ?? 1), 10) || 1,
        pickListCanvasDataJson: normalizedString(config.pickListCanvasDataJson),
        pickListCanvasThumbnailData: '',
        pickListCanvasThumbnailUrl: normalizedString(config.pickListCanvasThumbnailUrl),
        submitting: false,
        saving: false,
        saveError: '',
        autosaveTimer: null,
        relativeTimer: null,
        lastSavedAtIso: config.lastSavedAtIso || null,
        lastSavedAbsolute: config.lastSavedAbsolute || null,
        lastSavedRelative: '',
        canvasController: null,
        canvasLoading: false,
        canvasReady: false,
        canvasError: '',
        canvasTool: 'draw',
        canvasColor: '#dc2626',
        canvasBrushSize: 4,
        canvasCanUndo: false,
        canvasCanRedo: false,
        canvasZoomPercent: 100,
        init() {
            this.customItems = this.cloneItems(this.customItems);
            this.templateItems = this.cloneItems(this.templateItems);
            this.itemSuggestions = this.itemSuggestions
                .map((item) => String(item))
                .map((item) => item.trim())
                .filter((item) => item !== '');

            const resolvedItemIds = this.currentItems()
                .map((item) => String(item?.id ?? ''))
                .filter((id) => id !== '');
            this.checkedIds = this.checkedIds
                .map((id) => String(id))
                .filter((id) => resolvedItemIds.includes(id));
            this.nextCustomItemId = this.computeNextCustomItemId(this.currentItems());

            this.relativeTimer = window.SM.startRelativeTimeTicker(() => {
                this.refreshSavedRelative();
            });

            this.$nextTick(() => {
                this.resizeNotesField();
                this.initCanvas();
            });
        },
        destroy() {
            this.relativeTimer = window.SM.clearInterval(this.relativeTimer);
            this.autosaveTimer = window.SM.clearTimer(this.autosaveTimer);

            if (this.canvasController) {
                this.canvasController.dispose();
                this.canvasController = null;
            }
        },
        cloneItems(items) {
            return Array.isArray(items)
                ? items.map((item) => this.normalizeItem(item)).filter((item) => item !== null)
                : [];
        },
        previousItemForNewRow(items) {
            if (!Array.isArray(items) || items.length === 0) {
                return null;
            }

            for (let index = items.length - 1; index >= 0; index -= 1) {
                const item = items[index];
                if (!this.isBlankCustomItem(item)) {
                    return item;
                }
            }

            return null;
        },
        isBlankCustomItem(item) {
            return String(item?.item_name ?? '').trim() === '';
        },
        normalizeItem(item) {
            if (!item || typeof item !== 'object') {
                return null;
            }

            const id = Number.parseInt(String(item.id ?? 0), 10) || 0;
            const itemName = String(item.item_name ?? '').trim();
            const quantityType = String(item.quantity_type ?? 'per_participant');
            const quantityValue = Math.max(1, Number.parseInt(String(item.quantity_value ?? 1), 10) || 1);
            const sortOrder = Math.max(0, Number.parseInt(String(item.sort_order ?? 0), 10) || 0);

            return {
                id,
                item_name: itemName,
                quantity_type: ['fixed', 'per_participant'].includes(quantityType) ? quantityType : 'per_participant',
                quantity_value: quantityValue,
                sort_order: sortOrder,
            };
        },
        computeNextCustomItemId(items) {
            const maxId = Array.isArray(items)
                ? items.reduce((result, item) => {
                    const itemId = Number.parseInt(String(item?.id ?? 0), 10) || 0;
                    return itemId > result ? itemId : result;
                }, 0)
                : 0;

            return maxId + 1;
        },
        currentItems() {
            if (this.itemsEditMode) {
                return this.customItems;
            }

            return this.isCustomized ? this.customItems : this.templateItems;
        },
        customItemsEnabled() {
            return this.isCustomized || this.customItemsDirty;
        },
        resetToTemplate() {
            if (!this.isCustomized) {
                return;
            }

            this.resetCustomization = true;
            this.isCustomized = false;
            this.itemsEditMode = false;
            this.customItems = [];
            this.customItemsDirty = false;
            this.scheduleAutosave();
        },
        hasTrailingBlankCustomItem() {
            if (this.customItems.length === 0) {
                return false;
            }

            const lastItem = this.customItems[this.customItems.length - 1];
            return this.isBlankCustomItem(lastItem);
        },
        ensureTrailingBlankCustomItem() {
            const nonBlankItems = this.customItems.filter((item) => !this.isBlankCustomItem(item));
            const previousItem = nonBlankItems.length > 0 ? nonBlankItems[nonBlankItems.length - 1] : null;
            this.customItems = [...nonBlankItems, this.newCustomItem(previousItem)];
            this.nextCustomItemId = this.computeNextCustomItemId(this.customItems);
        },
        handleCustomItemChange(index) {
            this.customItemsDirty = true;
            const lastIndex = this.customItems.length - 1;

            if (index !== lastIndex || this.isBlankCustomItem(this.customItems[lastIndex])) {
                this.scheduleAutosave();
                return;
            }

            if (!this.hasTrailingBlankCustomItem()) {
                this.customItems.push(this.newCustomItem(this.customItems[lastIndex]));
            }

            this.scheduleAutosave();
        },
        newCustomItem(previousItem = null) {
            const id = this.nextCustomItemId++;
            const previousType = String(previousItem?.quantity_type ?? '');
            const previousValue = Number.parseInt(String(previousItem?.quantity_value ?? 1), 10) || 1;

            return {
                id,
                item_name: '',
                quantity_type: ['fixed', 'per_participant'].includes(previousType) ? previousType : 'per_participant',
                quantity_value: Math.max(1, previousValue),
                sort_order: this.customItems.length * 10,
            };
        },
        startItemEditing() {
            if (!this.itemsEditMode) {
                this.customItems = this.cloneItems(this.isCustomized ? this.customItems : this.templateItems);
                this.ensureTrailingBlankCustomItem();
                if (this.customItems.length === 0) {
                    this.customItems = [this.newCustomItem()];
                }
                this.itemsEditMode = true;
                this.customItemsDirty = false;
            }
        },
        async stopItemEditing() {
            if (!this.itemsEditMode) {
                return;
            }

            const hasPendingChanges = this.customItemsDirty || this.resetCustomization;
            if (hasPendingChanges) {
                this.autosaveTimer = window.SM.clearTimer(this.autosaveTimer);
                await this.autosave({
                    showFailure: true,
                });

                if (this.saveError !== '') {
                    return;
                }
            }

            this.itemsEditMode = false;
            if (!this.isCustomized) {
                this.customItems = [];
            }
            this.customItemsDirty = false;
        },
        addCustomItem() {
            if (!this.itemsEditMode) {
                this.startItemEditing();
            }

            this.customItems.push(this.newCustomItem(this.previousItemForNewRow(this.customItems)));
            this.customItemsDirty = true;
            this.scheduleAutosave();
        },
        removeCustomItem(index) {
            this.customItems.splice(index, 1);
            this.checkedIds = this.checkedIds.filter((id) => this.customItems.some((item) => String(item.id) === String(id)));
            this.customItemsDirty = true;
            this.ensureTrailingBlankCustomItem();
            this.scheduleAutosave();
        },
        moveCustomItemUp(index) {
            if (index <= 0) {
                return;
            }

            const previous = this.customItems[index - 1];
            this.customItems[index - 1] = this.customItems[index];
            this.customItems[index] = previous;
            this.customItemsDirty = true;
            this.scheduleAutosave();
        },
        moveCustomItemDown(index) {
            if (index >= this.customItems.length - 1) {
                return;
            }

            const next = this.customItems[index + 1];
            this.customItems[index + 1] = this.customItems[index];
            this.customItems[index] = next;
            this.customItemsDirty = true;
            this.scheduleAutosave();
        },
        normalizeCustomItems() {
            return this.customItems
                .map((item, index) => {
                    const normalized = this.normalizeItem(item);
                    if (!normalized || normalized.item_name === '') {
                        return null;
                    }

                    return {
                        ...normalized,
                        sort_order: (index + 1) * 10,
                    };
                })
                .filter((item) => item !== null)
                .map((item) => ({
                    id: item.id,
                    item_name: item.item_name,
                    quantity_type: item.quantity_type,
                    quantity_value: item.quantity_value,
                    sort_order: item.sort_order,
                }));
        },
        normalizeParticipants() {
            return window.SM.toBoundedInt(this.participantsInput, {
                min: 1,
                max: 5000,
                allowNull: true,
            });
        },
        effectiveParticipants() {
            return this.normalizeParticipants() ?? this.defaultParticipants;
        },
        quantityFor(item) {
            const participants = this.effectiveParticipants();
            const quantityValue = Math.max(1, Number.parseInt(String(item.quantity_value ?? 1), 10) || 1);
            if (String(item.quantity_type) === 'per_participant') {
                return Math.max(0, quantityValue * participants);
            }

            return quantityValue;
        },
        itemLabel(item) {
            const quantity = this.quantityFor(item);
            const name = String(item.item_name ?? '').trim();
            const label = window.SM.pluralize(name, quantity);
            return `${quantity} x ${label}`;
        },
        typeNote(item) {
            if (String(item.quantity_type) !== 'per_participant') {
                return '';
            }

            const quantityValue = Math.max(1, Number.parseInt(String(item.quantity_value ?? 1), 10) || 1);
            return `(${quantityValue} per participant)`;
        },
        clearAllChecks() {
            this.checkedIds = [];
            this.scheduleAutosave();
        },
        checkAllItems() {
            this.checkedIds = this.currentItems().map((item) => String(item.id));
            this.scheduleAutosave();
        },
        resizeNotesField() {
            const textarea = this.$refs.pickListNotes;
            if (!(textarea instanceof HTMLTextAreaElement)) {
                return;
            }

            textarea.style.height = 'auto';

            const computedStyle = window.getComputedStyle(textarea);
            const minHeight = Number.parseFloat(computedStyle.minHeight || '0') || 0;
            textarea.style.height = `${Math.max(textarea.scrollHeight, Math.ceil(minHeight))}px`;
        },
        canvasToolButtonClass(tool) {
            return pickListToolbarButtonClasses(this.canvasTool === tool);
        },
        canvasActionButtonClass() {
            return pickListToolbarButtonClasses(false);
        },
        setCanvasTool(tool) {
            if (!this.canvasController) {
                return;
            }

            this.canvasController.setTool(tool);
        },
        setCanvasColor(color) {
            this.canvasColor = color;
            if (!this.canvasController) {
                return;
            }

            this.canvasController.setColor(color);
        },
        setCanvasBrushSize(size) {
            this.canvasBrushSize = clamp(Number.parseInt(String(size), 10) || 4, 1, 48);
            if (!this.canvasController) {
                return;
            }

            this.canvasController.setBrushSize(this.canvasBrushSize);
        },
        zoomCanvasIn() {
            this.canvasController?.zoomIn();
        },
        zoomCanvasOut() {
            this.canvasController?.zoomOut();
        },
        resetCanvasView() {
            this.canvasController?.resetView();
        },
        clearCanvasDrawing() {
            this.canvasController?.clearCanvas();
        },
        undoCanvas() {
            this.canvasController?.undo();
        },
        redoCanvas() {
            this.canvasController?.redo();
        },
        scheduleAutosave() {
            if (this.submitting) {
                return;
            }

            this.autosaveTimer = window.SM.scheduleDebounce(this.autosaveTimer, () => {
                this.autosave();
            }, 900);
        },
        async syncCanvasStateForSave() {
            if (!this.canvasController) {
                return;
            }

            const exported = await this.canvasController.exportForSave();
            this.pickListCanvasDataJson = exported.json || '';
            this.pickListCanvasThumbnailData = exported.thumbnailDataUrl || '';
            this.pickListCanvasThumbnailUrl = exported.hasContent ? normalizedString(exported.thumbnailDataUrl) : '';
        },
        buildSavePayload() {
            const normalizedCustomItems = this.normalizeCustomItems();
            const shouldPersistCustomItems = (this.isCustomized || this.customItemsDirty) && ! this.resetCustomization;
            const payload = {
                pick_list_participants: this.normalizeParticipants(),
                pick_list_notes: this.notes,
                checked_item_ids: this.checkedIds,
                reset_pick_list_customization: this.resetCustomization ? 1 : 0,
                pick_list_canvas_data: this.pickListCanvasDataJson || null,
                pick_list_canvas_thumbnail_data: this.pickListCanvasThumbnailData || '',
            };

            if (shouldPersistCustomItems) {
                payload.pick_list_custom_items = normalizedCustomItems;
            }

            return payload;
        },
        async autosave(options = {}) {
            if (this.submitting || this.saving) {
                return;
            }

            this.saving = true;
            this.saveError = '';

            try {
                await this.syncCanvasStateForSave();

                const data = await window.SM.autosaveJson(this.saveUrl, this.csrfToken, this.buildSavePayload());
                this.lastSavedAtIso = data.saved_at_iso ?? null;
                this.lastSavedAbsolute = data.saved_at_display ?? null;
                if (Array.isArray(data.checked_item_ids)) {
                    this.checkedIds = data.checked_item_ids.map((id) => String(id));
                }
                if (typeof data.pick_list_is_customized === 'boolean') {
                    this.isCustomized = data.pick_list_is_customized;
                }
                const shouldSyncCustomItems = !this.itemsEditMode || this.customItemsDirty || this.isCustomized;
                if (shouldSyncCustomItems && Array.isArray(data.pick_list_custom_items)) {
                    this.customItems = this.cloneItems(data.pick_list_custom_items);
                    this.nextCustomItemId = this.computeNextCustomItemId(this.customItems);
                }
                this.customItemsDirty = false;
                this.resetCustomization = false;
                this.pickListCanvasThumbnailUrl = normalizedString(data.pick_list_canvas_thumbnail_url);
                this.refreshSavedRelative();

                if (options.showSuccess === true && window.SM?.notice) {
                    window.SM.notice('Saved', 'Pick list updates have been saved.', 'success', {
                        toast: true,
                        timer: 2500,
                    });
                }
            } catch (error) {
                this.saveError = 'Autosave failed. Use Save to retry.';

                if (options.showFailure === true && window.SM?.notice) {
                    window.SM.notice('Save failed', this.saveError, 'danger', {
                        toast: true,
                        timer: 3500,
                    });
                }
            } finally {
                this.saving = false;
            }
        },
        async manualCanvasSave() {
            this.autosaveTimer = window.SM.clearTimer(this.autosaveTimer);
            await this.autosave({
                showSuccess: true,
                showFailure: true,
            });
        },
        refreshSavedRelative() {
            this.lastSavedRelative = window.SM.relativeTimeFromIso(this.lastSavedAtIso);
        },
        async submitForm(event) {
            if (!(event?.target instanceof HTMLFormElement)) {
                return;
            }

            this.submitting = true;
            this.autosaveTimer = window.SM.clearTimer(this.autosaveTimer);
            this.saveError = '';

            try {
                await this.syncCanvasStateForSave();
                event.target.submit();
            } catch (error) {
                this.submitting = false;
                this.saveError = 'Could not prepare the canvas data for saving.';
            }
        },
    });
};

registerWorkshopPickListPage();
