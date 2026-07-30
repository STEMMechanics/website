@php
$password = '';
if(isset($medium) && ($medium->password !== null && $medium->password !== '')) {
    $password = 'yes';
}
$originalFileInfo = collect($mediaFilesInfo ?? [])->firstWhere('variant', '');
$variantFilesInfo = collect($mediaFilesInfo ?? [])->filter(fn ($info) => ($info['variant'] ?? '') !== '')->values();
$storedWorkshopLinks = isset($medium)
    ? $medium->workshopFiles->map(fn ($workshop) => ['workshop_id' => (string) $workshop->id, 'type' => 'file'])
        ->concat($medium->workshopPhotos->map(fn ($workshop) => ['workshop_id' => (string) $workshop->id, 'type' => 'photo']))
        ->unique('workshop_id')
        ->values()
        ->all()
    : [];
$supportsWorkshopPhotoLinks = ! isset($medium) || str_starts_with((string) ($medium->mime_type ?? ''), 'image/');
$selectedWorkshopLinks = collect(old('workshop_links', $storedWorkshopLinks))
    ->map(fn ($link) => [
        'workshop_id' => (string) ($link['workshop_id'] ?? ''),
        'type' => $supportsWorkshopPhotoLinks && ($link['type'] ?? null) === 'photo' ? 'photo' : 'file',
    ])
    ->filter(fn ($link) => $link['workshop_id'] !== '')
    ->values()
    ->all();
$visibilityValue = old('visibility', $medium->visibility ?? 'public');
$visibilityValue = in_array((string) $visibilityValue, ['private', 'protected', 'public'], true) ? (string) $visibilityValue : 'private';
$storageDiskValue = old('storage_disk', $medium->storage_disk ?? 'media');
$storageDiskValue = in_array((string) $storageDiskValue, ['media', 'archive'], true) ? (string) $storageDiskValue : 'media';
$publicUsages = collect($mediaUsages ?? [])->filter(fn ($usage) => (bool) ($usage['public'] ?? false));
$visibilityInfoExpression = $publicUsages->isNotEmpty()
    ? "visibilityInfo() + ' Public usage must be removed before this media can stop being public.'"
    : 'visibilityInfo()';
$protectedDownloadLink = isset($protectedDownloadLink) && is_string($protectedDownloadLink) && $protectedDownloadLink !== '' ? $protectedDownloadLink : null;
$protectedDownloadTokenExpiry = isset($protectedDownloadToken?->expires_at) ? optional($protectedDownloadToken->expires_at)->timezone(config('app.timezone'))->format('j M Y g:i a') : null;
$isEditableImage = isset($medium) && is_string($medium->mime_type ?? null) && str_starts_with((string) $medium->mime_type, 'image/');
$originalDimensions = isset($originalFileInfo['dimensions']) ? (string) $originalFileInfo['dimensions'] : '';
$originalDimensionParts = preg_split('/\s*x\s*/i', $originalDimensions) ?: [];
$originalImageWidth = (int) ($originalDimensionParts[0] ?? 0);
$originalImageHeight = (int) ($originalDimensionParts[1] ?? 0);
$editorImageUrl = isset($medium) ? $medium->url : null;
@endphp

<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">{{ isset($medium) ? 'Edit' : 'Create' }} Media</x-mast>
    <x-container class="mt-4">
        @isset($medium)
            <form id="generate-protected-link-form" method="POST" action="{{ route('admin.media.protected-link.generate', $medium) }}">
                @csrf
            </form>
            <form id="revoke-protected-link-form" method="POST" action="{{ route('admin.media.protected-link.revoke', $medium) }}">
                @csrf
                @method('DELETE')
            </form>
        @endisset
        <form method="POST" action="{{ route('admin.media.' . ( isset($medium) ? 'update' : 'store'), $medium ?? []) }}" enctype="multipart/form-data"
            x-data="{
                visibilityValue: @js($visibilityValue),
                originalVisibilityValue: @js($visibilityValue),
                formDirty: false,
                editRotation: @js((int) old('edit_rotation', 0)),
                editCropTop: @js((int) old('edit_crop_top', 0)),
                editCropRight: @js((int) old('edit_crop_right', 0)),
                editCropBottom: @js((int) old('edit_crop_bottom', 0)),
                editCropLeft: @js((int) old('edit_crop_left', 0)),
                editorOpen: false,
                editorDraft: { rotation: @js((int) old('edit_rotation', 0)), top: @js((int) old('edit_crop_top', 0)), right: @js((int) old('edit_crop_right', 0)), bottom: @js((int) old('edit_crop_bottom', 0)), left: @js((int) old('edit_crop_left', 0)) },
                editorBounds: null,
                openEditor() {
                    this.editorDraft = {
                        rotation: Number(this.editRotation || 0),
                        top: Number(this.editCropTop || 0),
                        right: Number(this.editCropRight || 0),
                        bottom: Number(this.editCropBottom || 0),
                        left: Number(this.editCropLeft || 0),
                    };
                    this.editorBounds = null;
                    this.editorOpen = true;
                },
                closeEditor() {
                    this.editorOpen = false;
                    this.editorBounds = null;
                },
                applyEditor() {
                    this.editRotation = Number(this.editorDraft.rotation || 0);
                    this.editCropTop = Number(this.editorDraft.top || 0);
                    this.editCropRight = Number(this.editorDraft.right || 0);
                    this.editCropBottom = Number(this.editorDraft.bottom || 0);
                    this.editCropLeft = Number(this.editorDraft.left || 0);
                    this.closeEditor();
                },
                rotate(delta) {
                    this.editorDraft = {
                        ...this.editorDraft,
                        rotation: (((Number(this.editorDraft.rotation || 0) + Number(delta || 0)) % 360) + 360) % 360,
                    };
                },
                cropFocusStyle() {
                    const bounds = this.editorBounds;
                    if (!bounds) return 'display:none;';
                    const left = bounds.x + (bounds.width * this.editorDraft.left / 100);
                    const top = bounds.y + (bounds.height * this.editorDraft.top / 100);
                    const width = Math.max(12, bounds.width * (100 - this.editorDraft.left - this.editorDraft.right) / 100);
                    const height = Math.max(12, bounds.height * (100 - this.editorDraft.top - this.editorDraft.bottom) / 100);
                    return `left:${left}px; top:${top}px; width:${width}px; height:${height}px;`;
                },
                editorFrameStyle() {
                    return 'width:min(100%,48rem); height:min(65vh,32rem);';
                },
                cropShadeStyle(edge) {
                    const bounds = this.editorBounds;
                    if (!bounds) return 'display:none;';
                    const left = bounds.x + (bounds.width * this.editorDraft.left / 100);
                    const top = bounds.y + (bounds.height * this.editorDraft.top / 100);
                    const right = bounds.x + bounds.width - (bounds.width * this.editorDraft.right / 100);
                    const bottom = bounds.y + bounds.height - (bounds.height * this.editorDraft.bottom / 100);
                    if (edge === 'top') return `left:${bounds.x}px; top:${bounds.y}px; width:${bounds.width}px; height:${Math.max(0, top - bounds.y)}px;`;
                    if (edge === 'right') return `left:${right}px; top:${top}px; width:${Math.max(0, bounds.x + bounds.width - right)}px; height:${Math.max(0, bottom - top)}px;`;
                    if (edge === 'bottom') return `left:${bounds.x}px; top:${bottom}px; width:${bounds.width}px; height:${Math.max(0, bounds.y + bounds.height - bottom)}px;`;
                    return `left:${bounds.x}px; top:${top}px; width:${Math.max(0, left - bounds.x)}px; height:${Math.max(0, bottom - top)}px;`;
                },
                startCropDrag(mode, event) {
                    const bounds = this.editorBounds;
                    if (!bounds) return;
                    const startX = event.clientX;
                    const startY = event.clientY;
                    const initial = { ...this.editorDraft };
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
                        this.editorDraft = {
                            ...this.editorDraft,
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
                renderPreviewCanvas(canvas, imageUrl, edits) {
                    if (!canvas || !imageUrl) return;
                    const context = canvas.getContext('2d');
                    if (!context) return;
                    const width = Math.max(0, canvas.clientWidth || 0);
                    const height = Math.max(0, canvas.clientHeight || 0);
                    if (width < 20 || height < 20) {
                        requestAnimationFrame(() => this.renderPreviewCanvas(canvas, imageUrl, edits));
                        return;
                    }
                    if (canvas.width !== width || canvas.height !== height) {
                        canvas.width = width;
                        canvas.height = height;
                    }
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
                    image.src = imageUrl;
                },
                renderEditorCanvas(canvas) {
                    if (!canvas) return;
                    const context = canvas.getContext('2d');
                    if (!context) return;

                    const width = Math.max(0, canvas.clientWidth || 0);
                    const height = Math.max(0, canvas.clientHeight || 0);
                    if (width < 20 || height < 20) {
                        requestAnimationFrame(() => this.renderEditorCanvas(canvas));
                        return;
                    }
                    if (canvas.width !== width || canvas.height !== height) {
                        canvas.width = width;
                        canvas.height = height;
                    }

                    const image = new Image();
                    image.onload = () => {
                        context.clearRect(0, 0, width, height);
                        const rotationDegrees = Number(this.editorDraft.rotation || 0);
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

                        this.editorBounds = {
                            x: (width - (boundWidth * scale)) / 2,
                            y: (height - (boundHeight * scale)) / 2,
                            width: boundWidth * scale,
                            height: boundHeight * scale,
                        };
                    };
                    const imageUrl = @js($editorImageUrl);
                    if (!imageUrl) {
                        context.clearRect(0, 0, width, height);
                        return;
                    }
                    image.src = imageUrl;
                },
                resetEdits() {
                    this.editorDraft = { rotation: 0, top: 0, right: 0, bottom: 0, left: 0 };
                },
                visibilityInfo() {
                    if (this.originalVisibilityValue === 'protected' && this.visibilityValue !== 'protected') {
                        return 'Changing this from protected will revoke any protected links to this file.';
                    }

                    if (this.visibilityValue === 'protected') {
                        return 'Protected files require a generated protected URL unless the viewer is the owner or an admin.';
                    }

                    if (this.visibilityValue === 'private') {
                        return 'Private files are only accessible to the owner and admins.';
                    }

                    return 'Public files can be opened directly by anyone with the URL.';
                },
                markDirty(event) {
                    if (event?.target?.form !== this.$root) {
                        return;
                    }

                    this.formDirty = true;
                },
                protectedLinkActionsDisabled() {
                    return this.formDirty;
                },
                protectedLinkActionInfo() {
                    if (this.formDirty) {
                        return 'Save media changes before generating or revoking a protected URL.';
                    }

                    return 'Generate a shareable URL for protected files. Revoking it blocks access immediately.';
                }
            }"
            x-on:input.capture="markDirty($event)"
            x-on:change.capture="markDirty($event)">
            @isset($medium)
                @method('PUT')
            @endisset
            @csrf
            <input type="hidden" name="edit_rotation" :value="editRotation">
            <input type="hidden" name="edit_crop_top" :value="editCropTop">
            <input type="hidden" name="edit_crop_right" :value="editCropRight">
            <input type="hidden" name="edit_crop_bottom" :value="editCropBottom">
            <input type="hidden" name="edit_crop_left" :value="editCropLeft">
            <div class="mb-4">
                <x-ui.input label="Title" name="title" value="{{ $medium->title ?? '' }}"/>
            </div>

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-base font-semibold">Preview</h3>
                    <div class="flex justify-center rounded-lg border border-gray-200 bg-gray-100 p-3">
                        <a href="{{ $medium->url }}" target="_blank" rel="noopener noreferrer" class="inline-block overflow-hidden rounded-lg">
                            <div class="flex max-h-96 min-h-48 min-w-48 items-center justify-center overflow-hidden">
                                <canvas class="block h-full w-full" x-effect="editRotation; editCropTop; editCropRight; editCropBottom; editCropLeft; renderPreviewCanvas($el, @js($editorImageUrl), { rotation: editRotation, top: editCropTop, right: editCropRight, bottom: editCropBottom, left: editCropLeft })"></canvas>
                            </div>
                        </a>
                    </div>
                    @if($isEditableImage)
                        <div class="mt-4 flex justify-end">
                            <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" x-on:click.prevent="openEditor()">
                                <i class="fa-solid fa-pen-to-square mr-2"></i>Edit Image
                            </button>
                        </div>
                        <div x-show="editorOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="closeEditor()">
                            <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl" x-on:click.away="closeEditor()">
                                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                    <div>
                                        <div class="text-base font-semibold text-gray-900">Edit Image</div>
                                        <div class="text-xs text-gray-500">Saving applies these edits to the original image and regenerates variants.</div>
                                    </div>
                                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click.prevent="closeEditor()">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <div class="overflow-y-auto p-4">
                                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]">
                                    <div class="sm-image-crop-preview" :style="editorFrameStyle()">
                                        <div class="absolute inset-0 flex items-center justify-center overflow-hidden">
                                            <canvas x-ref="editorCanvas" x-effect="editorDraft.rotation; editorDraft.top; editorDraft.right; editorDraft.bottom; editorDraft.left; renderEditorCanvas($refs.editorCanvas)" class="block h-full w-full"></canvas>
                                        </div>
                                        <div class="sm-image-crop-preview__shade" :style="cropShadeStyle('top')"></div>
                                        <div class="sm-image-crop-preview__shade" :style="cropShadeStyle('right')"></div>
                                        <div class="sm-image-crop-preview__shade" :style="cropShadeStyle('bottom')"></div>
                                        <div class="sm-image-crop-preview__shade" :style="cropShadeStyle('left')"></div>
                                        <div class="sm-image-crop-preview__focus" :style="cropFocusStyle()" x-on:mousedown.prevent="startCropDrag('move', $event)">
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--n" style="cursor: ns-resize;" x-on:mousedown.prevent.stop="startCropDrag('n', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--ne" style="cursor: nesw-resize;" x-on:mousedown.prevent.stop="startCropDrag('ne', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--e" style="cursor: ew-resize;" x-on:mousedown.prevent.stop="startCropDrag('e', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--se" style="cursor: nwse-resize;" x-on:mousedown.prevent.stop="startCropDrag('se', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--s" style="cursor: ns-resize;" x-on:mousedown.prevent.stop="startCropDrag('s', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--sw" style="cursor: nesw-resize;" x-on:mousedown.prevent.stop="startCropDrag('sw', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--w" style="cursor: ew-resize;" x-on:mousedown.prevent.stop="startCropDrag('w', $event)"></button>
                                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--nw" style="cursor: nwse-resize;" x-on:mousedown.prevent.stop="startCropDrag('nw', $event)"></button>
                                        </div>
                                    </div>
                                    <div class="space-y-4 md:max-w-72">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" title="Rotate left" x-on:click.prevent="rotate(-90)"><i class="fa-solid fa-rotate-left"></i></button>
                                            <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" title="Rotate right" x-on:click.prevent="rotate(90)"><i class="fa-solid fa-rotate-right"></i></button>
                                        </div>
                                        <div class="text-xs text-gray-500">Drag the crop box or its handles directly on the image.</div>
                                        <div class="flex justify-between gap-2">
                                            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" x-on:click.prevent="resetEdits()">Reset</button>
                                            <button type="button" class="rounded bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark" x-on:click.prevent="applyEditor()">Done</button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endisset

            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="mb-3 text-base font-semibold">Usage</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ui.select
                        label="Visibility"
                        name="visibility"
                        value="{{ $visibilityValue }}"
                        x-model="visibilityValue"
                        class="mb-0"
                        info="{{ $visibilityInfoExpression }}"
                    >
                            <option value="private" @selected($visibilityValue === 'private')>Private</option>
                            <option value="protected" @selected($visibilityValue === 'protected')>Protected</option>
                            <option value="public" @selected($visibilityValue === 'public')>Public</option>
                    </x-ui.select>
                    <x-ui.select
                        label="Storage"
                        name="storage_disk"
                        value="{{ $storageDiskValue }}"
                        class="mb-0"
                    >
                            <option value="media" @selected($storageDiskValue === 'media')>Media</option>
                            <option value="archive" @selected($storageDiskValue === 'archive')>Archive</option>
                    </x-ui.select>
                    <div class="md:col-span-2" x-show="visibilityValue === 'public' || visibilityValue === 'protected'" x-cloak>
                        <x-ui.password class="mb-0" label="Password" name="password" value="{{ $password }}"/>
                    </div>
                    @if($isEditableImage)
                        <x-ui.input label="Photographed On" name="photographed_at" type="date" value="{{ old('photographed_at', isset($medium) ? optional($medium->photographed_at)->format('Y-m-d') : '') }}" class="mb-0" />
                    @endif
                    <div class="{{ $isEditableImage ? '' : 'md:col-span-2' }}">
                        <x-ui.tags name="tags" value="{{ old('tags', $medium->tags ?? '') }}" :options="$tagOptions ?? []" noWrapper="true" />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input class="mb-0" label="Caption" name="caption" value="{{ old('caption', $medium->caption ?? '') }}" />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input class="mb-0" label="Notes" name="consent_notes" type="textarea" value="{{ old('consent_notes', $medium->consent_notes ?? '') }}" />
                    </div>
                </div>
            </div>

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4" x-show="visibilityValue === 'protected'" x-cloak>
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold">Protected Link</h3>
                            <p class="mt-1 text-sm text-gray-600" x-text="protectedLinkActionInfo()"></p>
                        </div>
                        <div class="flex items-end gap-2">
                            <x-ui.select label="Expires In" name="expires_in_days" value="30" class="mb-0 min-w-40" selectClass="min-w-40" form="generate-protected-link-form" x-bind:disabled="protectedLinkActionsDisabled()">
                                <option value="1">1 day</option>
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </x-ui.select>
                            <x-ui.button type="submit" color="outline" form="generate-protected-link-form" x-bind:disabled="protectedLinkActionsDisabled()">Generate URL</x-ui.button>
                        </div>
                    </div>

                    @if($protectedDownloadLink)
                        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2">URL</th>
                                        <th class="px-3 py-2">Expires</th>
                                        <th class="px-3 py-2 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr>
                                        <td class="px-3 py-2 align-top">
                                            <div class="max-w-xl overflow-x-auto whitespace-nowrap text-gray-900">
                                                {{ $protectedDownloadLink }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-top whitespace-nowrap text-gray-600">
                                            {{ $protectedDownloadTokenExpiry ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <div class="flex items-center justify-center gap-3">
                                                <a
                                                    href="#"
                                                    class="hover:text-primary-color"
                                                    title="Copy protected URL"
                                                    x-on:click.prevent="SM.copyToClipboard(@js($protectedDownloadLink))"
                                                    x-bind:class="protectedLinkActionsDisabled() ? 'pointer-events-none opacity-50' : ''"
                                                >
                                                    <i class="fa-solid fa-copy"></i>
                                                </a>
                                                <button
                                                    type="submit"
                                                    class="hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                                    title="Revoke protected URL"
                                                    form="revoke-protected-link-form"
                                                    x-bind:disabled="protectedLinkActionsDisabled()"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="mt-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500">
                            No active protected link exists for this file.
                        </div>
                    @endif
                </div>
            @endisset

            <div
                class="mb-6 rounded-lg border border-gray-200 bg-white p-4"
                x-data="{
                    search: '',
                    links: @js($selectedWorkshopLinks),
                    supportsPhotoLinks: @js($supportsWorkshopPhotoLinks),
                    workshops: @js(collect($workshopOptions ?? [])->map(function ($workshopOption) {
                        $locationLabel = $workshopOption->location?->name ?: $workshopOption->getLocationName();
                        $dateLabel = $workshopOption->starts_at ? $workshopOption->starts_at->format('j M Y') : 'No date';
                        return [
                            'id' => (string) $workshopOption->id,
                            'title' => (string) $workshopOption->title,
                            'location' => (string) $locationLabel,
                            'date' => (string) $dateLabel,
                            'label' => (string) ($workshopOption->title.' · '.$locationLabel.' · '.$dateLabel),
                            'search' => strtolower(trim((string) ($workshopOption->title.' '.$locationLabel.' '.$dateLabel))),
                            'edit_url' => route('admin.workshop.edit', $workshopOption),
                            'files_url' => route('admin.workshop.files', $workshopOption),
                            'photos_url' => route('admin.workshop.photos', $workshopOption),
                        ];
                    })->values()->all()),
                    filtered() {
                        const term = this.search.trim().toLowerCase();
                        if (term.length < 2) return [];
                        return this.workshops
                            .filter((workshop) => workshop.search.includes(term) && !this.isLinked(workshop.id))
                            .slice(0, 25);
                    },
                    isLinked(id) {
                        return this.links.some((link) => link.workshop_id === String(id));
                    },
                    add(id) {
                        id = String(id);
                        if (!this.isLinked(id)) {
                            this.links.push({
                                workshop_id: id,
                                type: this.supportsPhotoLinks ? 'photo' : 'file',
                            });
                        }
                        this.search = '';
                    },
                    remove(id) {
                        id = String(id);
                        this.links = this.links.filter((link) => link.workshop_id !== id);
                    },
                    workshopFor(id) {
                        return this.workshops.find((workshop) => workshop.id === String(id));
                    },
                    linkUrl(link) {
                        const workshop = this.workshopFor(link.workshop_id);
                        if (!workshop) return '#';
                        return link.type === 'photo' ? workshop.photos_url : workshop.files_url;
                    },
                }"
            >
                <h3 class="mb-3 text-base font-semibold">Links &amp; Usage</h3>
                <div class="mb-4 overflow-x-auto rounded-lg border border-gray-200" x-show="links.length > 0 || @js(! empty($mediaUsages ?? []))">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Workshop</th>
                                <th class="w-12 px-3 py-2"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($mediaUsages ?? [] as $usage)
                                <tr>
                                    <td class="px-3 py-2">{{ $usage['type'] }}</td>
                                    <td class="px-3 py-2">
                                        @if(! empty($usage['url']))
                                            <a href="{{ $usage['url'] }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:underline">{{ $usage['label'] }}</a>
                                        @else
                                            {{ $usage['label'] }}
                                        @endif
                                        @if(trim((string) ($usage['detail'] ?? '')) !== '')
                                            <span class="text-gray-500"> - {{ $usage['detail'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            @endforeach
                            <template x-for="(link, index) in links" :key="link.workshop_id">
                                <tr>
                                    <td class="px-3 py-2">
                                        <x-ui.select
                                            name=""
                                            label="Workshop link type"
                                            noLabel="true"
                                            class="mb-0 min-w-32"
                                            selectClass="py-1.5"
                                            x-model="link.type"
                                        >
                                            <option value="file">Workshop file</option>
                                            <option value="photo" x-bind:disabled="!supportsPhotoLinks">Workshop photo</option>
                                        </x-ui.select>
                                        <input type="hidden" x-bind:name="`workshop_links[${index}][workshop_id]`" :value="link.workshop_id">
                                        <input type="hidden" x-bind:name="`workshop_links[${index}][type]`" :value="link.type">
                                    </td>
                                    <td class="px-3 py-2">
                                        <a :href="linkUrl(link)" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:underline" x-text="workshopFor(link.workshop_id)?.title || link.workshop_id"></a>
                                        <span class="text-gray-500" x-text="workshopFor(link.workshop_id) ? ` - ${workshopFor(link.workshop_id).date} · ${workshopFor(link.workshop_id).location}` : ''"></span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-gray-500 hover:text-red-600" x-on:click.prevent="remove(link.workshop_id)" title="Disassociate from workshop" aria-label="Disassociate from workshop">
                                            <i class="fa-solid fa-link-slash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mb-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="links.length === 0 && @js(empty($mediaUsages ?? []))" x-cloak>
                    No current links or usage found.
                </div>

                <x-ui.input
                    id="workshop_link_search"
                    type="search"
                    name=""
                    label="Find workshops"
                    x-model="search"
                    placeholder="Search workshop title, location, or date"
                    class="mb-3"
                />

                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-500" x-show="search.trim().length < 2" x-cloak>
                    Enter at least 2 characters to search workshops.
                </div>

                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50" x-show="search.trim().length >= 2" x-cloak>
                    <template x-for="workshop in filtered()" :key="workshop.id">
                        <button type="button" class="flex w-full items-start gap-3 border-b border-gray-200 bg-white px-3 py-2 text-left text-sm last:border-b-0 hover:bg-sky-50" x-on:click.prevent="add(workshop.id)">
                            <i class="fa-solid fa-plus mt-1 text-primary-color"></i>
                            <span class="min-w-0">
                                <span class="block text-gray-900" x-text="workshop.title"></span>
                                <span class="block text-xs text-gray-500" x-text="`${workshop.location} · ${workshop.date}`"></span>
                            </span>
                        </button>
                    </template>
                    <div class="px-3 py-3 text-sm text-gray-500" x-show="filtered().length === 0" x-cloak>No unlinked workshops found.</div>
                </div>
                <p class="mt-1 text-xs text-gray-500">Choose whether the media appears in the workshop’s Files or Photos tab. Results are limited to 25 matches.</p>
            </div>

            @if(isset($mediaOwners))
                <div class="mb-4">
                    <x-admin.user-selector-inline
                        :users="$mediaOwners"
                        fieldName="user_id"
                        lookupName="media_owner_lookup"
                        label="Owner"
                        info="Admins can reassign media ownership."
                        :selectedUserId="old('user_id', $medium->user_id ?? '')"
                    />
                </div>
            @endif

            @isset($medium)
                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="text-base font-semibold mb-3">File Details</h3>
                    @if(trim((string) ($medium->last_processing_error ?? '')) !== '')
                        <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                            <div class="font-semibold">Last processing error</div>
                            <div class="mt-1 whitespace-pre-line">{{ (string) $medium->last_processing_error }}</div>
                            <div class="mt-1 text-xs text-red-700">
                                @if($medium->last_processing_failed_at)
                                    Failed at: {{ $medium->last_processing_failed_at->format('M j, Y g:i a') }}
                                @else
                                    Failed at: Unknown
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Original Name"
                                name="original_name"
                                value="{{ (string) (($medium->name ?? '') !== '' ? $medium->name : '-') }}"
                                disabled />
                        </div>
                        <x-ui.input label="Type" name="type" value="{{ $medium->file_type }}" disabled />
                        <x-ui.input
                            label="MIME Type"
                            name="mime_type"
                            value="{{ (string) (($medium->mime_type ?? '') !== '' ? $medium->mime_type : '-') }}"
                            disabled />
                        <x-ui.input
                            label="Dimensions"
                            name="dimensions"
                            value="{{ (string) (($originalFileInfo['dimensions'] ?? '') !== '' ? $originalFileInfo['dimensions'] : '-') }}"
                            disabled />
                        <x-ui.input
                            label="File Size"
                            name="file_size"
                            value="{{ (string) (($originalFileInfo['size_human'] ?? '') !== '' ? $originalFileInfo['size_human'] : (isset($medium->size) ? \App\Helpers::bytesToString((int) $medium->size) : '-')) }}"
                            disabled />
                        <div class="md:col-span-2">
                            <x-ui.input label="URL" name="url" value="{{ $medium->url }}" disabled />
                        </div>
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Storage Key"
                                name="storage_key"
                                value="{{ (string) (($originalFileInfo['storage_key'] ?? '') !== '' ? $originalFileInfo['storage_key'] : ($medium->hash ?? '-')) }}"
                                disabled />
                        </div>
                        <div class="md:col-span-2">
                            <x-ui.input
                                label="Storage Path"
                                name="storage_path"
                                value="{{ (string) (($originalFileInfo['path'] ?? '') !== '' ? $originalFileInfo['path'] : ($medium->path() ?? '-')) }}"
                                disabled />
                        </div>
                    </div>
                </div>

                <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold">Stored Variants</h3>
                        <div class="flex items-center gap-2">
                            @if($variantFilesInfo->isNotEmpty())
                            <x-ui.button type="button" color="outline" x-data x-on:click.prevent="confirmDeleteVariants()">Delete Variants</x-ui.button>
                            @endif
                            <x-ui.button type="button" color="outline" x-data x-on:click.prevent="confirmRegenerateVariants()">Regenerate Variants</x-ui.button>
                        </div>
                    </div>
                    @if($variantFilesInfo->isNotEmpty())
                        <x-ui.table>
                            <x-slot:header>
                                <th>Variant</th>
                                <th class="text-center">Format</th>
                                <th class="text-center">Dimensions</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </x-slot:header>
                            <x-slot:body>
                                @foreach($variantFilesInfo as $fileInfo)
                                    <tr>
                                        <td class="font-semibold">{{ $fileInfo['label'] }}</td>
                                        <td class="text-center">{{ $fileInfo['format'] ?? '-' }}</td>
                                        <td class="text-center">{{ $fileInfo['dimensions'] ?? '-' }}</td>
                                        <td class="text-center">{{ $fileInfo['size_human'] ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xxs font-semibold {{ $fileInfo['exists'] ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' }}">
                                                {{ $fileInfo['exists'] ? 'Exists' : 'Missing' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="flex items-center justify-center gap-3">
                                                @if(($fileInfo['url'] ?? '-') !== '-' && ($fileInfo['exists'] ?? false))
                                                    <a
                                                        href="{{ $fileInfo['url'] }}"
                                                        title="Open variant"
                                                        class="hover:text-primary-color"
                                                        target="_blank"
                                                        rel="noopener noreferrer">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                @else
                                                    <span class="text-gray-400" title="Variant file missing">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </span>
                                                @endif
                                                <a
                                                    href="#"
                                                    title="Delete this variant"
                                                    class="hover:text-red-600"
                                                    x-data
                                                    x-on:click.prevent="confirmDeleteSingleVariant('{{ (string) ($fileInfo['variant'] ?? '') }}')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </x-slot:body>
                        </x-ui.table>
                    @else
                        <p class="text-sm text-gray-600">No variant files available.</p>
                    @endif
                </div>
            @endisset
            @unless(isset($medium))
                <x-ui.file name="file" onchange="updateTitle" value="" />
            @endunless

            <div class="flex justify-end gap-4 mt-8">
                @isset($medium)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete media?', 'Are you sure you want to delete this file? This action cannot be undone', '{{ route('admin.media.destroy', $medium) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    const regenerateVariantsAction = @json(isset($medium) ? route('admin.media.regenerate-variants', $medium) : null);
    const deleteVariantAction = @json(isset($medium) ? route('admin.media.delete-variant', $medium) : null);
    const deleteVariantsAction = @json(isset($medium) ? route('admin.media.delete-variants', $medium) : null);
    const regenerateVariantsCsrf = @json(csrf_token());

    function updateTitle(file, name) {
        const elem = document.querySelector('input[name="title"]');
        if(elem) {
            if (elem.value === '') {
                elem.value = SM.toTitleCase(name);
            }
        }
    }

    function confirmRegenerateVariants() {
        if (!regenerateVariantsAction || !regenerateVariantsCsrf) {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitRegenerateVariants(regenerateVariantsAction, regenerateVariantsCsrf);
            return;
        }

        window.SM.confirm(
            'Regenerate variants',
            'Delete existing variants and regenerate them now? This may take a few minutes.',
            'Regenerate',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitRegenerateVariants(regenerateVariantsAction, regenerateVariantsCsrf);
            }
        );
    }

    function submitRegenerateVariants(action, csrf) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;

        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    }

    function submitDeleteSingleVariant(action, csrf, variant) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;

        const variantInput = document.createElement('input');
        variantInput.type = 'hidden';
        variantInput.name = 'variant';
        variantInput.value = variant;

        form.appendChild(token);
        form.appendChild(variantInput);
        document.body.appendChild(form);
        form.submit();
    }

    function confirmDeleteVariants() {
        if (!deleteVariantsAction || !regenerateVariantsCsrf) {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitRegenerateVariants(deleteVariantsAction, regenerateVariantsCsrf);
            return;
        }

        window.SM.confirm(
            'Delete variants',
            'Delete all generated variants for this media item? This does not delete the original file.',
            'Delete Variants',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitRegenerateVariants(deleteVariantsAction, regenerateVariantsCsrf);
            }
        );
    }

    function confirmDeleteSingleVariant(variant) {
        const variantName = String(variant || '').trim();
        if (!deleteVariantAction || !regenerateVariantsCsrf || variantName === '') {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            submitDeleteSingleVariant(deleteVariantAction, regenerateVariantsCsrf, variantName);
            return;
        }

        window.SM.confirm(
            'Delete variant',
            `Delete the "${variantName}" variant? This does not delete the original file.`,
            'Delete Variant',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                submitDeleteSingleVariant(deleteVariantAction, regenerateVariantsCsrf, variantName);
            }
        );
    }
</script>
