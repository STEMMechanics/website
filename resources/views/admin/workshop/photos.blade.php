@php
    $workshopTabs = [
        [
            'title' => 'Details',
            'route' => route('admin.workshop.edit', $workshop),
        ],
        [
            'title' => 'Attendance',
            'route' => route('admin.workshop.attendance', $workshop),
        ],
        [
            'title' => 'Files',
            'route' => route('admin.workshop.files', $workshop),
        ],
        [
            'title' => 'Photos',
            'route' => route('admin.workshop.photos', $workshop),
            'active' => true,
        ],
    ];
    $dateLabel = $workshop->starts_at
        ? $workshop->starts_at->format('D j M Y, g:ia').($workshop->ends_at ? ' – '.$workshop->ends_at->format('g:ia') : '')
        : 'No date set';
    $locationLabel = $workshop->getLocationDisplay();
@endphp

<x-layout title="Workshop Media - {{ $workshop->title }}">
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops" :tabs="$workshopTabs">Workshop Media</x-mast>

    <x-container>
        <div class="mb-4">
            <div class="rounded-b-xl border border-slate-200 bg-slate-50 px-4 py-3 lg:flex lg:items-start lg:justify-between lg:gap-4">
                <div>
                    <div class="text-lg font-semibold text-gray-900">{{ $workshop->title }}</div>
                    <div class="mt-2 grid gap-1 text-sm text-gray-700">
                        <div><span class="font-semibold">Date:</span> {{ $dateLabel }}</div>
                        <div><span class="font-semibold">Location:</span> {{ $locationLabel }}</div>
                    </div>
                </div>
                <div class="hidden max-w-lg items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 lg:flex" role="note">
                    <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
                    <p>Photos are not displayed on the workshop page.</p>
                </div>
            </div>
            <div class="mt-4 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 lg:hidden" role="note">
                <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
                <p>Photos are not displayed on the workshop page.</p>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <form
                method="POST"
                action="{{ route('admin.workshop.photos.store', $workshop) }}"
                enctype="multipart/form-data"
                class="space-y-4"
                x-data="{
                    workshopDate: @js(optional($workshop->starts_at)->format('Y-m-d') ?? now()->format('Y-m-d')),
                    attachedPhotoNames: @js($attachedPhotoNames ?? []),
                    previews: [],
                    existingMedia: [],
                    preparing: false,
                    preparationIndex: 0,
                    preparationTotal: 0,
                    uploading: false,
                    uploadIndex: 0,
                    uploadProgress: 0,
                    currentFileName: '',
                    editingIndex: null,
                    editingDraft: null,
                    editingBounds: null,
                    uploadError: '',
                    metadataItem: null,
                    metadataKind: '',
                    openMetadataEditor(item, kind) {
                        if (!Array.isArray(item.tags)) item.tags = this.bulkTagList(item.tags);
                        if (typeof item.tagDraft !== 'string') item.tagDraft = '';
                        this.metadataItem = item;
                        this.metadataKind = kind;
                    },
                    closeMetadataEditor() {
                        this.metadataItem = null;
                        this.metadataKind = '';
                    },
                    bulkOpen: false,
                    bulkScope: 'existing',
                    bulkStorage: '',
                    bulkVisibility: '',
                    bulkTags: [],
                    bulkTagDraft: '',
                    bulkInitialTags: [],
                    bulkMixedTags: [],
                    existingSelectionCount: 0,
                    refreshExistingSelectionCount() {
                        this.existingSelectionCount = document.querySelectorAll('#workshop-existing-photos-form [data-photo-select]:checked').length;
                        this.notifyBulkSelectionChanged();
                    },
                    notifyBulkSelectionChanged() {
                        window.dispatchEvent(new CustomEvent('photo-bulk-selection-count', {
                            detail: {
                                count: this.stagedSelectionCount() + this.existingSelectionCount,
                                total: this.previews.length + this.existingMedia.length + document.querySelectorAll('#workshop-existing-photos-form [data-photo-select]').length,
                            },
                        }));
                    },
                    selectAllMedia(checked) {
                        [...this.previews, ...this.existingMedia].forEach((item) => item.bulkSelected = checked);
                        document.querySelectorAll('#workshop-existing-photos-form [data-photo-select]').forEach((input) => input.checked = checked);
                        this.refreshExistingSelectionCount();
                    },
                    stagedSelectionCount() {
                        return [...this.previews, ...this.existingMedia].filter((item) => item.bulkSelected).length;
                    },
                    selectAllStaged(checked) {
                        [...this.previews, ...this.existingMedia].forEach((item) => item.bulkSelected = checked);
                        this.notifyBulkSelectionChanged();
                    },
                    bulkSelectionCount() {
                        if (this.bulkScope === 'staged') return this.stagedSelectionCount();
                        if (this.bulkScope === 'existing') return this.existingSelectionCount;
                        return this.stagedSelectionCount() + this.existingSelectionCount;
                    },
                    bulkTagList(value) {
                        if (Array.isArray(value)) return value.map((tag) => String(tag).trim()).filter(Boolean);
                        return String(value || '').split(/[,\s]+/).map((tag) => tag.trim()).filter(Boolean);
                    },
                    prepareBulkTags() {
                        const tagSets = [];
                        if (this.bulkScope === 'staged' || this.bulkScope === 'both') {
                            [...this.previews, ...this.existingMedia].filter((item) => item.bulkSelected)
                                .forEach((item) => tagSets.push(this.bulkTagList(item.tags)));
                        }
                        if (this.bulkScope === 'existing' || this.bulkScope === 'both') {
                            [...document.querySelectorAll('#workshop-existing-photos-form [data-photo-row]')]
                                .filter((row) => row.querySelector('[data-photo-select]:checked'))
                                .forEach((row) => tagSets.push(this.bulkTagList(row.querySelector('[data-photo-tags]')?.value || '')));
                        }
                        const entries = new Map();
                        tagSets.forEach((tags) => [...new Set(tags.map((tag) => tag.toLowerCase()))].forEach((key) => {
                            const original = tags.find((tag) => tag.toLowerCase() === key) || key;
                            const entry = entries.get(key) || { name: original, count: 0 };
                            entry.count++;
                            entries.set(key, entry);
                        }));
                        this.bulkTags = [...entries.values()].map((entry) => entry.name);
                        this.bulkInitialTags = [...this.bulkTags];
                        this.bulkMixedTags = [...entries.values()].filter((entry) => entry.count < tagSets.length).map((entry) => entry.name);
                        this.bulkTagDraft = '';
                    },
                    openBulkEditor() {
                        this.bulkScope = 'existing';
                        this.prepareBulkTags();
                        this.bulkOpen = true;
                    },
                    mergeBulkTags(current) {
                        const currentBulkKeys = this.bulkTags.map((tag) => tag.toLowerCase());
                        const initialKeys = this.bulkInitialTags.map((tag) => tag.toLowerCase());
                        const removed = initialKeys.filter((key) => !currentBulkKeys.includes(key));
                        const result = (Array.isArray(current) ? current : this.bulkTagList(current))
                            .filter((tag) => !removed.includes(String(tag).toLowerCase()));
                        this.bulkTags.filter((tag) => !initialKeys.includes(tag.toLowerCase())).forEach((tag) => {
                            if (!result.some((existing) => String(existing).toLowerCase() === tag.toLowerCase())) result.push(tag);
                        });
                        return result;
                    },
                    applyBulkEdit() {
                        if (this.bulkScope === 'staged' || this.bulkScope === 'both') {
                            [...this.previews, ...this.existingMedia].filter((item) => item.bulkSelected).forEach((item) => {
                                if (this.bulkStorage) item.storageDisk = this.bulkStorage;
                                if (this.bulkVisibility) item.visibility = this.bulkVisibility;
                                const current = Array.isArray(item.tags) ? item.tags : this.bulkTagList(item.tags);
                                const tags = this.mergeBulkTags(current);
                                item.tags = Array.isArray(item.tags) ? tags : tags.join(', ');
                            });
                        }
                        if (this.bulkScope === 'existing' || this.bulkScope === 'both') {
                            [...document.querySelectorAll('#workshop-existing-photos-form [data-photo-row]')]
                                .filter((row) => row.querySelector('[data-photo-select]:checked'))
                                .forEach((row) => {
                                if (this.bulkStorage && row.querySelector('[data-photo-storage]')) row.querySelector('[data-photo-storage]').value = this.bulkStorage;
                                if (this.bulkVisibility && row.querySelector('[data-photo-visibility]')) row.querySelector('[data-photo-visibility]').value = this.bulkVisibility;
                                if (this.bulkStorage) row.querySelectorAll('[data-photo-storage-label]').forEach((label) => label.textContent = this.bulkStorage);
                                if (this.bulkVisibility) row.querySelectorAll('[data-photo-visibility-label]').forEach((label) => {
                                    label.textContent = this.bulkVisibility;
                                    label.classList.toggle('bg-emerald-100', this.bulkVisibility === 'public');
                                    label.classList.toggle('text-emerald-800', this.bulkVisibility === 'public');
                                    label.classList.toggle('bg-slate-100', this.bulkVisibility !== 'public');
                                    label.classList.toggle('text-slate-700', this.bulkVisibility !== 'public');
                                });
                                const tagsInput = row.querySelector('[data-photo-tags]');
                                if (tagsInput) {
                                    const tags = this.mergeBulkTags(tagsInput.value);
                                    tagsInput.value = tags.join(', ');
                                    row.querySelectorAll('[data-photo-tags-label]').forEach((label) => {
                                        label.textContent = tags.join(', ') || 'No tags';
                                        label.classList.toggle('italic', tags.length === 0);
                                        label.classList.toggle('text-gray-400', tags.length === 0);
                                    });
                                    window.dispatchEvent(new CustomEvent('photo-bulk-tags', { detail: { name: tagsInput.name, tags } }));
                                }
                                row.querySelectorAll('select').forEach((field) => field.dispatchEvent(new Event('change', { bubbles: true })));
                            });
                            document.getElementById('workshop-existing-photos-form')?.requestSubmit();
                        }
                        this.bulkOpen = false;
                    },
                    defaultPhotoDate(file = null) {
                        if (file && Number.isFinite(file.lastModified) && file.lastModified > 0) {
                            const lastModified = new Date(file.lastModified);
                            if (!Number.isNaN(lastModified.getTime())) {
                                return lastModified.toISOString().slice(0, 10);
                            }
                        }

                        return this.workshopDate;
                    },
                    titleFromFileName(fileName) {
                        return String(fileName || '')
                            .replace(/\.[^.]+$/, '')
                            .replace(/[-_]+/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim()
                            .replace(/\b\w/g, (char) => char.toUpperCase());
                    },
                    buildPreview(file, index) {
                        return {
                            index,
                            file,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            url: URL.createObjectURL(file),
                            title: this.titleFromFileName(file.name),
                            visibility: 'public',
                            storageDisk: 'media',
                            bulkSelected: false,
                            photographedAt: this.defaultPhotoDate(file),
                            tags: [],
                            tagDraft: '',
                            caption: '',
                            consentNotes: '',
                            editRotation: 0,
                            editCropTop: 0,
                            editCropRight: 0,
                            editCropBottom: 0,
                            editCropLeft: 0,
                            imageWidth: 0,
                            imageHeight: 0,
                        };
                    },
                    nextPaint() {
                        return new Promise((resolve) => {
                            requestAnimationFrame(() => requestAnimationFrame(resolve));
                        });
                    },
                    async appendFiles(files) {
                        const incoming = Array.from(files || []);
                        if (incoming.length === 0 || this.preparing || this.uploading) {
                            return;
                        }

                        this.uploadProgress = 0;
                        this.currentFileName = '';
                        this.preparing = true;
                        this.preparationIndex = 0;
                        this.preparationTotal = incoming.length;
                        await this.nextPaint();

                        const batchSize = 3;
                        try {
                            for (let offset = 0; offset < incoming.length; offset += batchSize) {
                                const files = incoming.slice(offset, offset + batchSize);
                                const startIndex = this.previews.length;
                                const batch = files.map((file, index) => this.buildPreview(file, startIndex + index));
                                this.previews = [...this.previews, ...batch];
                                await Promise.all(batch.map((preview) => this.populatePreviewMetadata(preview)));
                                this.preparationIndex = Math.min(offset + batch.length, this.preparationTotal);
                                await this.nextPaint();
                            }
                        } finally {
                            this.preparing = false;
                        }

                        await this.uploadAll();
                    },
                    async update(files) {
                        this.clear();
                        await this.appendFiles(files);
                    },
                    async populatePreviewMetadata(preview) {
                        if (!(preview?.file instanceof File)) {
                            return;
                        }

                        let dimensionsPromise = Promise.resolve();
                        if (this.isImage(preview)) {
                            dimensionsPromise = new Promise((resolve) => {
                                const img = new Image();
                                img.onload = () => {
                                    preview.imageWidth = Number(img.naturalWidth || 0);
                                    preview.imageHeight = Number(img.naturalHeight || 0);
                                    resolve();
                                };
                                img.onerror = resolve;
                                img.src = preview.url;
                            });
                        }

                        const photographedAtPromise = this.readPhotographedAt(preview.file)
                            .then((date) => {
                                if (date) {
                                    preview.photographedAt = date;
                                }
                            })
                            .catch(() => {});

                        await Promise.all([dimensionsPromise, photographedAtPromise]);
                    },
                    async readPhotographedAt(file) {
                        const mimeType = String(file?.type || '').toLowerCase();
                        if (!mimeType.startsWith('image/jpeg')) {
                            return this.defaultPhotoDate(file);
                        }

                        const exifDate = await this.readJpegExifDate(file);
                        return exifDate || this.defaultPhotoDate(file);
                    },
                    async readJpegExifDate(file) {
                        const buffer = await file.arrayBuffer();
                        const view = new DataView(buffer);
                        if (view.byteLength < 4 || view.getUint16(0) !== 0xFFD8) {
                            return null;
                        }

                        let offset = 2;
                        while (offset + 4 <= view.byteLength) {
                            const marker = view.getUint16(offset);
                            offset += 2;

                            if (marker === 0xFFDA || marker === 0xFFD9) {
                                break;
                            }

                            const segmentLength = view.getUint16(offset);
                            if (segmentLength < 2 || offset + segmentLength > view.byteLength) {
                                break;
                            }

                            if (marker === 0xFFE1 && segmentLength >= 10) {
                                const exifHeader = this.readAscii(view, offset + 2, 4);
                                if (exifHeader === 'Exif') {
                                    return this.extractExifDate(view, offset + 8, segmentLength - 8);
                                }
                            }

                            offset += segmentLength;
                        }

                        return null;
                    },
                    extractExifDate(view, tiffOffset, availableLength) {
                        if (tiffOffset + 8 > view.byteLength || availableLength < 8) {
                            return null;
                        }

                        const byteOrder = this.readAscii(view, tiffOffset, 2);
                        const littleEndian = byteOrder === 'II';
                        if (!littleEndian && byteOrder !== 'MM') {
                            return null;
                        }

                        const firstIfdOffset = view.getUint32(tiffOffset + 4, littleEndian);
                        const exifIfd = this.findExifIfdOffset(view, tiffOffset, firstIfdOffset, littleEndian);
                        if (exifIfd === null) {
                            return null;
                        }

                        return this.findExifDateValue(view, tiffOffset, exifIfd, littleEndian);
                    },
                    findExifIfdOffset(view, tiffOffset, ifdOffset, littleEndian) {
                        const directoryOffset = tiffOffset + ifdOffset;
                        if (directoryOffset + 2 > view.byteLength) {
                            return null;
                        }

                        const entryCount = view.getUint16(directoryOffset, littleEndian);
                        for (let index = 0; index < entryCount; index += 1) {
                            const entryOffset = directoryOffset + 2 + (index * 12);
                            if (entryOffset + 12 > view.byteLength) {
                                return null;
                            }

                            const tag = view.getUint16(entryOffset, littleEndian);
                            if (tag === 0x8769) {
                                return view.getUint32(entryOffset + 8, littleEndian);
                            }
                        }

                        return null;
                    },
                    findExifDateValue(view, tiffOffset, ifdOffset, littleEndian) {
                        const directoryOffset = tiffOffset + ifdOffset;
                        if (directoryOffset + 2 > view.byteLength) {
                            return null;
                        }

                        const entryCount = view.getUint16(directoryOffset, littleEndian);
                        for (let index = 0; index < entryCount; index += 1) {
                            const entryOffset = directoryOffset + 2 + (index * 12);
                            if (entryOffset + 12 > view.byteLength) {
                                return null;
                            }

                            const tag = view.getUint16(entryOffset, littleEndian);
                            if (![0x9003, 0x9004, 0x0132].includes(tag)) {
                                continue;
                            }

                            const count = view.getUint32(entryOffset + 4, littleEndian);
                            const valueOffset = view.getUint32(entryOffset + 8, littleEndian);
                            const textOffset = count <= 4 ? entryOffset + 8 : tiffOffset + valueOffset;
                            const value = this.readAscii(view, textOffset, count).replace(/\0/g, '').trim();
                            const match = value.match(/^(\d{4}):(\d{2}):(\d{2})/);
                            if (match) {
                                return `${match[1]}-${match[2]}-${match[3]}`;
                            }
                        }

                        return null;
                    },
                    readAscii(view, start, length) {
                        let output = '';
                        const max = Math.min(view.byteLength, start + Math.max(0, length));
                        for (let offset = start; offset < max; offset += 1) {
                            output += String.fromCharCode(view.getUint8(offset));
                        }
                        return output;
                    },
                    isImage(preview) {
                        return String(preview?.type || '').startsWith('image/');
                    },
                    currentEdits(preview) {
                        return {
                            rotation: Number(preview?.editRotation || 0),
                            top: Number(preview?.editCropTop || 0),
                            right: Number(preview?.editCropRight || 0),
                            bottom: Number(preview?.editCropBottom || 0),
                            left: Number(preview?.editCropLeft || 0),
                        };
                    },
                    openEditor(index) {
                        const preview = this.previews[index] || null;
                        this.editingIndex = preview ? index : null;
                        this.editingDraft = preview ? this.currentEdits(preview) : null;
                        this.editingBounds = null;
                    },
                    closeEditor() {
                        this.editingIndex = null;
                        this.editingDraft = null;
                        this.editingBounds = null;
                    },
                    editingPreview() {
                        if (this.editingIndex === null) return null;
                        return this.previews[this.editingIndex] || null;
                    },
                    applyEditor() {
                        const preview = this.editingPreview();
                        if (!preview || !this.editingDraft) {
                            this.closeEditor();
                            return;
                        }
                        preview.editRotation = Number(this.editingDraft.rotation || 0);
                        preview.editCropTop = Number(this.editingDraft.top || 0);
                        preview.editCropRight = Number(this.editingDraft.right || 0);
                        preview.editCropBottom = Number(this.editingDraft.bottom || 0);
                        preview.editCropLeft = Number(this.editingDraft.left || 0);
                        this.closeEditor();
                    },
                    cropFocusStyle() {
                        const draft = this.editingDraft;
                        const bounds = this.editingBounds;
                        if (!draft || !bounds) return 'display:none;';
                        const left = bounds.x + (bounds.width * draft.left / 100);
                        const top = bounds.y + (bounds.height * draft.top / 100);
                        const width = Math.max(12, bounds.width * (100 - draft.left - draft.right) / 100);
                        const height = Math.max(12, bounds.height * (100 - draft.top - draft.bottom) / 100);
                        return `left:${left}px; top:${top}px; width:${width}px; height:${height}px;`;
                    },
                    editorFrameStyle() {
                        return 'width:min(100%,48rem); height:min(65vh,32rem);';
                    },
                    cropShadeStyle(edge) {
                        const draft = this.editingDraft;
                        const bounds = this.editingBounds;
                        if (!draft || !bounds) return 'display:none;';
                        const left = bounds.x + (bounds.width * draft.left / 100);
                        const top = bounds.y + (bounds.height * draft.top / 100);
                        const right = bounds.x + bounds.width - (bounds.width * draft.right / 100);
                        const bottom = bounds.y + bounds.height - (bounds.height * draft.bottom / 100);
                        if (edge === 'top') return `left:${bounds.x}px; top:${bounds.y}px; width:${bounds.width}px; height:${Math.max(0, top - bounds.y)}px;`;
                        if (edge === 'right') return `left:${right}px; top:${top}px; width:${Math.max(0, bounds.x + bounds.width - right)}px; height:${Math.max(0, bottom - top)}px;`;
                        if (edge === 'bottom') return `left:${bounds.x}px; top:${bottom}px; width:${bounds.width}px; height:${Math.max(0, bounds.y + bounds.height - bottom)}px;`;
                        return `left:${bounds.x}px; top:${top}px; width:${Math.max(0, left - bounds.x)}px; height:${Math.max(0, bottom - top)}px;`;
                    },
                    startCropDrag(mode, event) {
                        const bounds = this.editingBounds;
                        const draft = this.editingDraft;
                        if (!bounds || !draft) return;
                        const startX = event.clientX;
                        const startY = event.clientY;
                        const initial = { ...draft };
                        const onMove = (moveEvent) => {
                            const dx = ((moveEvent.clientX - startX) / bounds.width) * 100;
                            const dy = ((moveEvent.clientY - startY) / bounds.height) * 100;
                            let next = { ...initial };
                            if (mode === 'move') {
                                const shiftX = Math.max(-initial.left, Math.min(initial.right, dx));
                                const shiftY = Math.max(-initial.top, Math.min(initial.bottom, dy));
                                next.left = initial.left + shiftX;
                                next.right = initial.right - shiftX;
                                next.top = initial.top + shiftY;
                                next.bottom = initial.bottom - shiftY;
                            } else {
                                if (mode.includes('n')) next.top = Math.max(0, Math.min(90, initial.top + dy));
                                if (mode.includes('s')) next.bottom = Math.max(0, Math.min(90, initial.bottom - dy));
                                if (mode.includes('w')) next.left = Math.max(0, Math.min(90, initial.left + dx));
                                if (mode.includes('e')) next.right = Math.max(0, Math.min(90, initial.right - dx));
                            }
                            if (next.left + next.right > 95) {
                                const overflow = next.left + next.right - 95;
                                if (mode.includes('w')) next.left -= overflow;
                                else if (mode.includes('e')) next.right -= overflow;
                            }
                            if (next.top + next.bottom > 95) {
                                const overflow = next.top + next.bottom - 95;
                                if (mode.includes('n')) next.top -= overflow;
                                else if (mode.includes('s')) next.bottom -= overflow;
                            }
                            this.editingDraft = {
                                ...this.editingDraft,
                                top: Math.round(Math.max(0, Math.min(90, next.top))),
                                right: Math.round(Math.max(0, Math.min(90, next.right))),
                                bottom: Math.round(Math.max(0, Math.min(90, next.bottom))),
                                left: Math.round(Math.max(0, Math.min(90, next.left))),
                            };
                        };
                        const onUp = () => {
                            window.removeEventListener('mousemove', onMove);
                            window.removeEventListener('mouseup', onUp);
                        };
                        window.addEventListener('mousemove', onMove);
                        window.addEventListener('mouseup', onUp);
                    },
                    rotate(delta) {
                        if (!this.editingDraft) return;
                        this.editingDraft = {
                            ...this.editingDraft,
                            rotation: (((Number(this.editingDraft.rotation || 0) + Number(delta || 0)) % 360) + 360) % 360,
                        };
                    },
                    renderPreviewCanvas(canvas, preview) {
                        if (!canvas || !preview) return;
                        const context = canvas.getContext('2d');
                        if (!context) return;
                        const width = Math.max(0, canvas.clientWidth || 0);
                        const height = Math.max(0, canvas.clientHeight || 0);
                        if (width < 20 || height < 20) {
                            requestAnimationFrame(() => this.renderPreviewCanvas(canvas, preview));
                            return;
                        }
                        if (canvas.width !== width || canvas.height !== height) {
                            canvas.width = width;
                            canvas.height = height;
                        }
                        const edits = this.currentEdits(preview);
                        const image = new Image();
                        image.onload = () => {
                            context.clearRect(0, 0, width, height);
                            const sx = (Math.max(0, Math.min(90, Number(edits.left || 0))) / 100) * image.naturalWidth;
                            const sy = (Math.max(0, Math.min(90, Number(edits.top || 0))) / 100) * image.naturalHeight;
                            const sw = Math.max(1, ((100 - Math.max(0, Math.min(90, Number(edits.left || 0))) - Math.max(0, Math.min(90, Number(edits.right || 0)))) / 100) * image.naturalWidth);
                            const sh = Math.max(1, ((100 - Math.max(0, Math.min(90, Number(edits.top || 0))) - Math.max(0, Math.min(90, Number(edits.bottom || 0)))) / 100) * image.naturalHeight);
                            const rotationDegrees = Number(edits.rotation || 0);
                            const rotation = (rotationDegrees * Math.PI) / 180;
                            const quarterTurns = ((Math.round(rotationDegrees / 90) % 4) + 4) % 4;
                            const boundWidth = quarterTurns % 2 === 1 ? sh : sw;
                            const boundHeight = quarterTurns % 2 === 1 ? sw : sh;
                            const scale = Math.min(width / boundWidth, height / boundHeight);
                            const drawWidth = sw * scale;
                            const drawHeight = sh * scale;
                            context.save();
                            context.translate(width / 2, height / 2);
                            context.rotate(rotation);
                            context.drawImage(image, sx, sy, sw, sh, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                            context.restore();
                        };
                        image.src = preview.url;
                    },
                    renderEditorCanvas(canvas, preview) {
                        if (!canvas || !preview) return;
                        const context = canvas.getContext('2d');
                        if (!context) return;

                        const width = Math.max(0, canvas.clientWidth || 0);
                        const height = Math.max(0, canvas.clientHeight || 0);
                        if (width < 20 || height < 20) {
                            requestAnimationFrame(() => this.renderEditorCanvas(canvas, preview));
                            return;
                        }
                        if (canvas.width !== width || canvas.height !== height) {
                            canvas.width = width;
                            canvas.height = height;
                        }

                        const image = new Image();
                        image.onload = () => {
                            context.clearRect(0, 0, width, height);
                            const rotationDegrees = Number(this.editingDraft?.rotation || 0);
                            const rotation = (rotationDegrees * Math.PI) / 180;
                            const quarterTurns = ((Math.round(rotationDegrees / 90) % 4) + 4) % 4;
                            const sourceWidth = image.naturalWidth || 1;
                            const sourceHeight = image.naturalHeight || 1;
                            const boundWidth = quarterTurns % 2 === 1 ? sourceHeight : sourceWidth;
                            const boundHeight = quarterTurns % 2 === 1 ? sourceWidth : sourceHeight;
                            const scale = Math.min(width / boundWidth, height / boundHeight);
                            const drawWidth = sourceWidth * scale;
                            const drawHeight = sourceHeight * scale;

                            context.save();
                            context.translate(width / 2, height / 2);
                            context.rotate(rotation);
                            context.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                            context.restore();

                            this.editingBounds = {
                                x: (width - (boundWidth * scale)) / 2,
                                y: (height - (boundHeight * scale)) / 2,
                                width: boundWidth * scale,
                                height: boundHeight * scale,
                            };
                        };
                        image.src = preview.url;
                    },
                    resetEdits() {
                        if (!this.editingDraft) return;
                        this.editingDraft = { rotation: 0, top: 0, right: 0, bottom: 0, left: 0 };
                    },
                    addTag(preview, value = null) {
                        const tag = String(value ?? preview.tagDraft).trim().replace(/,$/, '');
                        if (tag === '') return;
                        if (!preview.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
                            preview.tags.push(tag);
                        }
                        preview.tagDraft = '';
                    },
                    removeTag(preview, index) {
                        preview.tags.splice(index, 1);
                    },
                    remove(index) {
                        const removed = this.previews[index];
                        if (removed) {
                            URL.revokeObjectURL(removed.url);
                        }
                        this.previews = this.previews.filter((preview, previewIndex) => previewIndex !== index).map((preview, nextIndex) => ({
                            ...preview,
                            index: nextIndex,
                        }));
                        if (this.editingIndex === index) {
                            this.closeEditor();
                        }
                    },
                    clear() {
                        this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                        this.previews = [];
                        this.existingMedia = [];
                        this.$refs.photosInput.value = '';
                        this.closeEditor();
                    },
                    sizeLabel(bytes) {
                        if (!Number.isFinite(bytes)) return '';
                        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
                        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
                        return bytes + ' B';
                    },
                    fileTypeLabel(mimeType) {
                        const value = String(mimeType || '').trim();
                        if (value === '') return 'File';
                        const [group, subtypeRaw] = value.split('/');
                        const subtype = String(subtypeRaw || '').replace(/^x-/, '').replace(/^vnd\./, '').replace(/[.+]/g, ' ');
                        const normalized = subtype.replace(/\b\w/g, (char) => char.toUpperCase());
                        if (group === 'image') return `Image (${normalized || 'File'})`;
                        if (group === 'video') return `Video (${normalized || 'File'})`;
                        if (group === 'audio') return `Audio (${normalized || 'File'})`;
                        if (value === 'application/pdf') return 'PDF';
                        return normalized || value;
                    },
                    openExistingMediaPicker() {
                        const attached = [...new Set([
                            ...this.attachedPhotoNames,
                            ...this.existingMedia.map((item) => item.name),
                        ])];

                        SMMediaPicker.open([], {
                            allow_multiple: true,
                            allow_uploads: false,
                            public_usable_only: false,
                            require_mime_type: 'image/*,video/*',
                            disabled: attached,
                            disabled_item_text: 'Selected',
                            title: 'Select Existing Photos or Videos',
                            confirm_button_text: 'Add Media',
                        }, (result) => this.addExistingMedia(result));
                    },
                    async addExistingMedia(result) {
                        const names = Array.isArray(result) ? result : [result];
                        const selectedNames = [];
                        names.forEach((name) => {
                            const mediaName = String(name || '').trim();
                            if (!mediaName
                                || this.attachedPhotoNames.includes(mediaName)
                                || this.existingMedia.some((item) => item.name === mediaName)) {
                                return;
                            }

                            const item = {
                                name: mediaName,
                                title: mediaName,
                                type: '',
                                size: 0,
                                thumbnail: '/media/' + encodeURIComponent(mediaName),
                                visibility: 'public',
                                storageDisk: 'media',
                                bulkSelected: false,
                                photographedAt: '',
                                tags: '',
                                caption: '',
                                consentNotes: '',
                                editUrl: '',
                            };
                            this.existingMedia.push(item);
                            selectedNames.push(mediaName);
                            SM.mediaDetails(mediaName, (details) => {
                                if (!details || typeof details !== 'object') return;
                                const index = this.existingMedia.findIndex((existing) => existing.name === mediaName);
                                if (index === -1) return;
                                const mimeType = String(details.mime_type || '');
                                if (!mimeType.startsWith('image/') && !mimeType.startsWith('video/')) {
                                    this.existingMedia.splice(index, 1);
                                    this.uploadError = `${details.title || mediaName} is not an image or video.`;
                                    return;
                                }
                                this.existingMedia[index] = {
                                    ...this.existingMedia[index],
                                    title: String(details.title || mediaName),
                                    type: mimeType,
                                    size: Number(details.size || 0),
                                    thumbnail: String(details.thumbnail || details.url || this.existingMedia[index].thumbnail),
                                    visibility: String(details.visibility || 'public'),
                                    storageDisk: ['media', 'archive'].includes(String(details.storage_disk || '')) ? String(details.storage_disk) : 'media',
                                    photographedAt: String(details.photographed_at || ''),
                                    tags: String(details.tags || ''),
                                    caption: String(details.caption || ''),
                                    consentNotes: String(details.consent_notes || ''),
                                    editUrl: String(details.edit_url || ''),
                                };
                            });
                        });
                        if (selectedNames.length === 0) return;
                        await this.uploadAll();
                    },
                    removeExistingMedia(index) {
                        this.existingMedia.splice(index, 1);
                    },
                    async uploadAll() {
                        if (this.preparing || this.uploading || (this.previews.length === 0 && this.existingMedia.length === 0)) return;
                        this.uploading = true;
                        this.uploadError = '';
                        this.uploadIndex = 0;
                        this.uploadProgress = 0;
                        this.currentFileName = '';
                        const files = this.previews.map((preview) => preview.file).filter((file) => file instanceof File);
                        const totalBytes = files.reduce((sum, file) => sum + (Number(file?.size) || 0), 0);
                        let uploadedBytes = 0;

                        try {
                            await new Promise((resolve) => requestAnimationFrame(resolve));
                            for (let index = 0; index < this.previews.length; index++) {
                                const preview = this.previews[index];
                                const file = files[index];
                                if (!file) continue;
                                const maxUploadSize = window.SM && typeof window.SM.maxUploadSize === 'function' ? window.SM.maxUploadSize() : 0;
                                if (maxUploadSize > 0 && file.size > maxUploadSize) {
                                    const sizeLabel = window.SM && typeof window.SM.bytesToString === 'function' ? window.SM.bytesToString(file.size) : `${file.size} bytes`;
                                    const maxLabel = window.SM && typeof window.SM.bytesToString === 'function' ? window.SM.bytesToString(maxUploadSize) : `${maxUploadSize} bytes`;
                                    throw new Error(`${file.name} is ${sizeLabel}, which exceeds the upload limit of ${maxLabel}.`);
                                }
                                this.uploadIndex = index + 1;
                                this.currentFileName = file.name;

                                const formData = new FormData();
                                formData.append('_token', @js(csrf_token()));
                                formData.append('photos[0]', file);
                                formData.append('photos_meta[0][title]', preview.title || '');
                                formData.append('photos_meta[0][visibility]', preview.visibility || 'private');
                                formData.append('photos_meta[0][storage_disk]', preview.storageDisk || 'media');
                                formData.append('photos_meta[0][photographed_at]', preview.photographedAt || '');
                                formData.append('photos_meta[0][tags]', preview.tags.join(', '));
                                formData.append('photos_meta[0][caption]', preview.caption || '');
                                formData.append('photos_meta[0][consent_notes]', preview.consentNotes || '');
                                formData.append('photos_meta[0][edit_rotation]', preview.editRotation || 0);
                                formData.append('photos_meta[0][edit_crop_top]', preview.editCropTop || 0);
                                formData.append('photos_meta[0][edit_crop_right]', preview.editCropRight || 0);
                                formData.append('photos_meta[0][edit_crop_bottom]', preview.editCropBottom || 0);
                                formData.append('photos_meta[0][edit_crop_left]', preview.editCropLeft || 0);

                                const stallTimeoutMs = 90 * 1000;
                                const uploadController = new AbortController();
                                let lastUploadActivityAt = Date.now();
                                const stallTimer = window.setInterval(() => {
                                    if (Date.now() - lastUploadActivityAt >= stallTimeoutMs) {
                                        uploadController.abort('upload-stalled');
                                    }
                                }, 5000);

                                try {
                                    await axios.post(@js(route('admin.workshop.photos.store', $workshop)), formData, {
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                        signal: uploadController.signal,
                                        onUploadProgress: (progressEvent) => {
                                            lastUploadActivityAt = Date.now();
                                            const currentLoaded = Math.max(0, Math.min(Number(progressEvent.loaded) || 0, file.size));
                                            const percent = totalBytes > 0
                                                ? Math.round(((uploadedBytes + currentLoaded) / totalBytes) * 100)
                                                : 0;
                                            this.uploadProgress = Math.max(0, Math.min(100, percent));
                                        },
                                    });
                                } catch (error) {
                                    let message = 'Upload failed.';
                                    const payload = error?.response?.data;
                                    if (uploadController.signal.aborted) {
                                        message = 'The server stopped responding for 90 seconds. This file may still have been saved; refresh the page to check before trying it again.';
                                    } else if (error?.response?.status === 413) {
                                        message = 'This file was rejected because it exceeds the server or proxy upload limit.';
                                    } else if (payload) {
                                        message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || message;
                                    } else if (error?.code === 'ERR_NETWORK') {
                                        message = 'The server did not return a usable response. This file may still have been saved; refresh the page to check before trying it again.';
                                    }

                                    throw new Error(`${file.name}: ${message}`);
                                } finally {
                                    window.clearInterval(stallTimer);
                                }

                                uploadedBytes += file.size;
                                this.uploadProgress = totalBytes > 0
                                    ? Math.max(0, Math.min(100, Math.round((uploadedBytes / totalBytes) * 100)))
                                    : 100;
                            }

                            if (this.existingMedia.length > 0) {
                                const formData = new FormData();
                                formData.append('_token', @js(csrf_token()));
                                this.existingMedia.forEach((item, index) => {
                                    formData.append('existing_media_names[]', item.name);
                                    formData.append(`existing_media_meta[${index}][storage_disk]`, item.storageDisk || 'media');
                                    formData.append(`existing_media_meta[${index}][visibility]`, item.visibility || 'public');
                                    formData.append(`existing_media_meta[${index}][tags]`, item.tags || '');
                                });
                                await axios.post(@js(route('admin.workshop.photos.store', $workshop)), formData, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                });
                            }

                            const addedCount = files.length + this.existingMedia.length;
                            sessionStorage.setItem('workshop-media-upload-toast', JSON.stringify({
                                title: 'Media added',
                                message: `${addedCount} workshop media item${addedCount === 1 ? '' : 's'} added.`,
                                type: 'success',
                            }));
                            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                            window.location.href = @js(route('admin.workshop.photos', $workshop));
                        } catch (error) {
                            this.uploadError = error?.response?.status === 413
                                ? 'The upload is too large for the server request limit. The PHP post_max_size must be larger than upload_max_filesize.'
                                : (error.message || 'Upload failed.');
                            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                            this.previews = [];
                            this.existingMedia = [];
                            if (this.$refs.photosInput) this.$refs.photosInput.value = '';
                            this.uploading = false;
                        }
                    },
                }"
                x-on:submit.prevent="uploadAll()"
                x-on:photo-existing-selection-changed.window="refreshExistingSelectionCount()"
                x-on:select-all-photo-media.window="selectAllMedia(Boolean($event.detail.checked))"
                x-on:open-photo-bulk.window="openBulkEditor()"
                x-init="requestAnimationFrame(() => refreshExistingSelectionCount())"
            >
                @csrf
                <div>
                    <label class="mb-1 block text-sm pl-1" for="photos">Upload Workshop Media</label>
                    <x-ui.media-uploader
                        input-id="photos"
                        input-name="photos[]"
                        input-ref="photosInput"
                        accept="image/*,video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-m4v,.mov,.mp4,.webm,.avi,.m4v"
                        count="previews.length + existingMedia.length"
                        empty-text="Drop photos or videos here"
                        description="Upload new media or select media already in the library."
                        supported-types="Supports JPG, PNG, WebP, GIF, MP4, MOV, WebM, AVI, and M4V files."
                        on-files="appendFiles"
                        on-browse-existing="openExistingMediaPicker"
                        disabled="uploading || preparing"
                        clear-after-change="false"
                        :showSubmit="false"
                    >
                        <div class="flex flex-col gap-4">
                            @error('photos') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                            @error('photos.*') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                            @error('existing_media_names') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                            <div x-show="uploadError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="uploadError"></div>
                        </div>
                    </x-ui.media-uploader>
                </div>

                <template x-teleport="body">
                    <div
                        x-show="preparing"
                        x-cloak
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="workshop-photos-preparing-title"
                        x-on:keydown.escape.prevent.stop
                    >
                        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" role="status" aria-live="polite">
                            <div class="mb-4 flex items-center gap-3">
                                <i class="fa-solid fa-circle-notch animate-spin text-xl text-primary-color"></i>
                                <div>
                                    <div id="workshop-photos-preparing-title" class="text-lg font-semibold text-gray-900">Preparing media</div>
                                    <div class="mt-1 text-sm text-gray-500">Reading photo details and creating previews.</div>
                                </div>
                            </div>
                            <div class="mb-2 flex justify-between text-sm text-gray-700">
                                <span>Please keep this page open</span>
                                <span x-text="`${preparationIndex} of ${preparationTotal}`"></span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-sky-100">
                                <div class="h-3 rounded-full bg-primary-color transition-[width] duration-200" x-bind:style="`width: ${preparationTotal > 0 ? Math.round((preparationIndex / preparationTotal) * 100) : 0}%`"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-teleport="body">
                    <div
                        x-show="uploading"
                        x-cloak
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="workshop-photos-progress-title"
                        x-on:keydown.escape.prevent.stop
                    >
                        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" role="status" aria-live="polite">
                            <div class="mb-4 flex items-center gap-3">
                                <i class="fa-solid fa-circle-notch animate-spin text-xl text-primary-color"></i>
                                <div>
                                    <div id="workshop-photos-progress-title" class="text-lg font-semibold text-gray-900">Uploading media</div>
                                    <div class="mt-1 text-sm text-gray-500">Please keep this page open until the operation finishes.</div>
                                </div>
                            </div>
                            <div class="mb-2 min-h-10 break-words text-sm text-gray-700">
                                <span x-text="currentFileName || ''"></span>
                                <span x-show="uploading && previews.length" x-text="` (${uploadIndex} of ${previews.length})`"></span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-sky-100">
                                <div class="h-3 rounded-full bg-primary-color transition-[width] duration-200" x-bind:style="`width: ${uploadProgress}%`"></div>
                            </div>
                            <div class="mt-2 text-right text-sm font-semibold text-gray-700" x-text="`${uploadProgress}%`"></div>
                        </div>
                    </div>
                </template>
                <template x-teleport="body">
                    <div x-show="bulkOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4" role="dialog" aria-modal="true" x-on:keydown.escape.window="bulkOpen = false">
                        <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl" x-on:click.outside="bulkOpen = false">
                            <h2 class="text-lg font-semibold text-gray-900">Bulk edit workshop media</h2>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <x-ui.select label="Storage" :name="null" x-model="bulkStorage">
                                    <option value="">No change</option><option value="media">Media</option><option value="archive">Archive</option>
                                </x-ui.select>
                                <x-ui.select label="Visibility" :name="null" x-model="bulkVisibility">
                                    <option value="">No change</option><option value="public">Public</option><option value="private">Private</option>
                                </x-ui.select>
                                <div class="sm:col-span-2">
                                <x-ui.tags
                                    label="Tags"
                                    :name="null"
                                    :options="$tagOptions ?? []"
                                    :show-help="false"
                                    x-model-tags="bulkTags"
                                    x-model-draft="bulkTagDraft"
                                    x-mixed-tags="bulkMixedTags"
                                />
                                <div class="mt-1 text-xs text-gray-500">Hatched tags are currently present on only some selected items.</div>
                                </div>
                            </div>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" color="outline" x-on:click.prevent="bulkOpen = false">Cancel</x-ui.button>
                                <x-ui.button type="button" x-bind:disabled="bulkSelectionCount() === 0" x-on:click.prevent="applyBulkEdit()">Apply changes</x-ui.button>
                            </div>
                        </div>
                    </div>
                </template>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const payload = sessionStorage.getItem('workshop-media-upload-toast');
                if (!payload || !window.SM || typeof window.SM.notice !== 'function') {
                    return;
                }

                sessionStorage.removeItem('workshop-media-upload-toast');

                try {
                    const toast = JSON.parse(payload);
                    window.SM.notice(toast.title || 'Success', toast.message || 'Upload complete.', toast.type || 'success', { toast: true });
                } catch (error) {
                }
            });
        </script>

        <x-ui.toolbar>
            <x-slot:right>
                <form method="GET" action="{{ route('admin.workshop.photos', $workshop) }}" class="flex flex-wrap items-center justify-end gap-2">
                    <x-ui.input name="search" label="Search photos" value="{{ request('search') }}" class="mb-0 min-w-64" noLabel="true" />
                    <x-ui.select name="visibility" label="Visibility" class="mb-0 min-w-40" selectClass="min-w-40" noLabel="true">
                        <option value="" @selected(request('visibility') === null || request('visibility') === '')>Any visibility</option>
                        <option value="private" @selected(request('visibility') === 'private')>Private</option>
                        <option value="public" @selected(request('visibility') === 'public')>Public</option>
                    </x-ui.select>
                    <x-ui.button type="submit" color="outline">Filter</x-ui.button>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($photos->isEmpty())
            <x-none-found item="photos" search="{{ request()->get('search') }}" />
        @else
            <form
                method="POST"
                action="{{ route('admin.workshop.photos.bulk-update', $workshop) }}"
                id="workshop-existing-photos-form"
                x-data="{ dirty: false, saving: false, existingSelected: 0, existingTotal: {{ $photos->count() }}, refreshSelection() { this.existingSelected = $el.querySelectorAll('[data-photo-select]:checked').length }, selectAllExisting(checked) { $el.querySelectorAll('[data-photo-select]').forEach((input) => input.checked = checked); this.refreshSelection(); window.dispatchEvent(new CustomEvent('photo-existing-selection-changed')); } }"
                x-on:input="dirty = true"
                x-on:change="dirty = true"
                x-on:photo-existing-selection-changed.window="refreshSelection()"
                x-on:submit.prevent="saving = true; axios.post($el.action, new FormData($el), { headers: { Accept: 'application/json' } }).then(() => window.SM?.notice?.('Photos updated', 'Selected media changes were saved.', 'success', { toast: true })).catch(error => window.SM?.notice?.('Save failed', error.response?.data?.message || 'Could not save selected media.', 'danger', { toast: true })).finally(() => saving = false)"
            >
                @csrf
                @method('PUT')
                <div class="w-full overflow-x-auto">
                    <table class="table">
                        <thead><tr><th class="w-10 text-center !border-r-0"><x-ui.checkbox id="workshop-existing-select-all" aria-label="Select all existing media" :small="true" :noWrapper="true" inputClass="mx-auto" x-bind:checked="existingTotal > 0 && existingSelected === existingTotal" x-effect="$el.indeterminate = existingSelected > 0 && existingSelected < existingTotal" x-on:change="selectAllExisting($el.checked)" /></th><th class="!border-l-0">Media</th><th class="hidden text-center lg:table-cell">Tags</th><th class="hidden w-24 text-center md:table-cell">Storage</th><th class="hidden w-24 text-center md:table-cell">Visibility</th><th class="w-36 text-center">Actions</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($photos as $photo)
                        <tr data-photo-row x-data="{ editing: false, saving: false, error: '', title: @js($photo->title), photographedAt: @js(optional($photo->photographed_at)->format('Y-m-d')), tags: @js(collect(explode(',', (string) $photo->tags))->map(fn($tag) => trim($tag))->filter()->values()->all()), tagDraft: '', storage: @js($photo->storageDiskName()), visibility: @js(in_array($photo->visibility, ['private','public'], true) ? $photo->visibility : 'private'), caption: @js($photo->caption ?? ''), notes: @js($photo->consent_notes ?? ''), async save() { this.saving = true; this.error = ''; try { await axios.put(@js(route('admin.workshop.photos.update', [$workshop, $photo])), { _token: @js(csrf_token()), title: this.title, photographed_at: this.photographedAt, tags: this.tags.join(', '), storage_disk: this.storage, visibility: this.visibility, caption: this.caption, consent_notes: this.notes }, { headers: { Accept: 'application/json' } }); this.editing = false; window.SM?.notice?.('Photo updated', 'Workshop photo metadata updated.', 'success', { toast: true }); } catch (error) { this.error = error.response?.data?.message || 'Could not save this media item.'; } finally { this.saving = false; } } }">
                            <td class="text-center !border-r-0"><x-ui.checkbox aria-label="Select {{ $photo->title }}" :small="true" :noWrapper="true" inputClass="mx-auto" data-photo-select data-photo-name="{{ $photo->name }}" x-on:change="window.dispatchEvent(new CustomEvent('photo-existing-selection-changed'))" /></td>
                            <td class="px-3 py-3"><div class="flex min-w-0 items-center gap-3"><a href="{{ route('admin.workshop.photos.media', [$workshop, $photo]) }}" target="_blank" class="shrink-0"><img src="{{ route('admin.workshop.photos.media', [$workshop, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->title }}" class="h-12 w-16 rounded object-cover"></a><div class="min-w-0"><div class="font-medium text-gray-900" x-text="title"></div><div class="max-w-xs truncate text-xs text-gray-500">{{ $photo->name }}</div><div class="text-xs text-gray-400">{{ \App\Helpers::bytesToString((int) $photo->size) }} · {{ $photo->file_type }}</div><div class="md:hidden"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize" data-photo-visibility-label :class="visibility === 'public' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="visibility"></span></div><div class="md:hidden text-xs text-gray-500">Storage: <span class="capitalize" data-photo-storage-label x-text="storage"></span></div><div class="lg:hidden max-w-xs truncate text-xs text-gray-500" data-photo-tags-label x-text="tags.join(', ') || 'No tags'"></div></div></div></td>
                            <td class="hidden px-3 py-3 text-center text-gray-600 lg:table-cell"><span data-photo-tags-label x-text="tags.join(', ') || 'No tags'" :class="tags.length ? '' : 'italic text-gray-400'"></span></td><td class="hidden px-3 py-3 text-center capitalize md:table-cell" data-photo-storage-label x-text="storage"></td><td class="hidden px-3 py-3 text-center md:table-cell"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize" data-photo-visibility-label :class="visibility === 'public' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="visibility"></span></td>
                            <td class="px-3 py-3"><div class="flex justify-end gap-3"><button type="button" class="text-primary-color" title="Edit" x-on:click="editing = true"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <a href="{{ route('admin.media.edit', $photo) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-primary-color-dark" title="Open media editor">
                                            <i class="fa-solid fa-up-right-from-square"></i>
                                        </a>
                                        <a href="{{ route('admin.workshop.photos.media', [$workshop, $photo, 'download' => 1]) }}" class="text-primary-color hover:text-primary-color-dark" title="Download media">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <button
                                            type="button"
                                            class="text-amber-600 hover:text-amber-800"
                                            title="Remove from this workshop only"
                                            x-data
                                            x-on:click.prevent="SM.confirmDelete(
                                                '{{ csrf_token() }}',
                                                'Remove media from workshop?',
                                                'This will remove the media item from this workshop only. The media item will remain in the media library.',
                                                '{{ route('admin.workshop.photos.destroy', [$workshop, $photo]) }}',
                                                'Remove from workshop'
                                            )"
                                        >
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800"
                                            title="Permanently delete photo"
                                            x-data
                                            x-on:click.prevent="SM.confirmDelete(
                                                '{{ csrf_token() }}',
                                                'Delete media permanently?',
                                                'This will remove the media item from the media library and from all workshop associations. This action cannot be undone.',
                                                '{{ route('admin.workshop.photos.delete', [$workshop, $photo]) }}',
                                                'Delete permanently'
                                            )"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                <input type="hidden" name="photos[{{ $photo->name }}][title]" x-bind:value="title"><input type="hidden" name="photos[{{ $photo->name }}][photographed_at]" x-bind:value="photographedAt"><input type="hidden" name="photos[{{ $photo->name }}][tags]" x-bind:value="tags.join(', ')" data-photo-tags><input type="hidden" name="photos[{{ $photo->name }}][storage_disk]" x-bind:value="storage" data-photo-storage><input type="hidden" name="photos[{{ $photo->name }}][visibility]" x-bind:value="visibility" data-photo-visibility><input type="hidden" name="photos[{{ $photo->name }}][caption]" x-bind:value="caption"><input type="hidden" name="photos[{{ $photo->name }}][consent_notes]" x-bind:value="notes">
                                <template x-teleport="body"><div x-show="editing" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"><div class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" x-on:click.outside="editing = false"><div class="mb-5 flex justify-between"><div><h3 class="text-lg font-semibold">Edit media</h3><div class="text-xs text-gray-500">{{ $photo->name }}</div></div><button type="button" x-on:click="editing = false"><i class="fa-solid fa-xmark"></i></button></div><div class="grid gap-4 sm:grid-cols-2"><div><x-ui.input label="Title" :name="null" x-model="title" /><x-ui.input label="Original Name" name="original_name_{{ $loop->index }}" value="{{ $photo->name }}" disabled="true" /><x-ui.input label="Photographed At" type="date" :name="null" x-model="photographedAt" /><x-ui.tags :name="null" :options="$tagOptions ?? []" x-model-tags="tags" x-model-draft="tagDraft" /></div><div><x-ui.select label="Storage" :name="null" x-model="storage"><option value="media">Media</option><option value="archive">Archive</option></x-ui.select><x-ui.select label="Visibility" :name="null" x-model="visibility"><option value="public">Public</option><option value="private">Private</option></x-ui.select><x-ui.input label="Caption" :name="null" x-model="caption" /><x-ui.input label="Notes" type="textarea" :name="null" x-model="notes" /></div></div><div x-show="error" class="mt-3 text-sm text-red-600" x-text="error"></div><div class="mt-5 flex justify-end gap-2"><x-ui.button type="button" color="outline" x-on:click="editing = false">Cancel</x-ui.button><x-ui.button type="button" x-bind:disabled="saving" x-on:click="save()"><span x-show="!saving">Save</span><span x-show="saving">Saving…</span></x-ui.button></div></div></div></template>
                            </td>
                        </tr>
                    @endforeach
                        </tbody>
                    </table>
                </div>

            </form>

            <div class="mt-6">{{ $photos->links() }}</div>
        @endif

        <div
            class="mt-6 flex justify-end"
            x-data="{ selectedCount: 0, totalCount: 0, existingNames: [], refreshExistingNames() { this.existingNames = [...document.querySelectorAll('#workshop-existing-photos-form [data-photo-select]:checked')].map((input) => input.dataset.photoName).filter(Boolean) }, downloadSelected() { if (this.existingNames.length === 0) return; const query = new URLSearchParams(); this.existingNames.forEach((name) => query.append('media_names[]', name)); window.location.href = @js(route('admin.workshop.photos.zip', $workshop)) + '?' + query.toString(); } }"
            x-on:photo-bulk-selection-count.window="selectedCount = Number($event.detail.count || 0); totalCount = Number($event.detail.total || 0); refreshExistingNames()"
        >
            <div class="flex flex-wrap items-center justify-end gap-3">
                <x-ui.button
                    type="button"
                    color="outline"
                    x-bind:disabled="selectedCount === 0"
                    x-on:click.prevent="window.dispatchEvent(new CustomEvent('open-photo-bulk'))"
                >Bulk edit selected</x-ui.button>
                <x-ui.button
                    type="button"
                    color="outline"
                    x-bind:disabled="existingNames.length === 0"
                    x-on:click.prevent="downloadSelected()"
                >Download ZIP</x-ui.button>
            </div>
        </div>
    </x-container>
</x-layout>
