@php
    $workshopTabs = [
        [
            'title' => 'Details',
            'route' => route('admin.workshop.edit', $workshop),
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
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold text-gray-900">{{ $workshop->title }}</div>
            <div class="mt-2 grid gap-1 text-sm text-gray-700">
                <div><span class="font-semibold">Date:</span> {{ $dateLabel }}</div>
                <div><span class="font-semibold">Location:</span> {{ $locationLabel }}</div>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <form
                method="POST"
                action="{{ route('admin.workshop.photos.store', $workshop) }}"
                enctype="multipart/form-data"
                class="space-y-4"
                x-data="{
                    previews: [],
                    uploading: false,
                    uploadIndex: 0,
                    uploadProgress: 0,
                    currentFileName: '',
                    editingIndex: null,
                    editingDraft: null,
                    editingBounds: null,
                    uploadError: '',
                    update(files) {
                        this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                        const today = new Date().toISOString().slice(0, 10);
                        this.previews = Array.from(files || []).map((file, index) => ({
                            index,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            url: URL.createObjectURL(file),
                            title: file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\b\w/g, (char) => char.toUpperCase()),
                            visibility: 'private',
                            photographedAt: today,
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
                        }));
                        this.previews.forEach((preview) => {
                            if (!this.isImage(preview)) return;
                            const img = new Image();
                            img.onload = () => {
                                preview.imageWidth = Number(img.naturalWidth || 0);
                                preview.imageHeight = Number(img.naturalHeight || 0);
                            };
                            img.src = preview.url;
                        });
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
                        const transfer = new DataTransfer();
                        Array.from(this.$refs.photosInput.files || []).forEach((file, fileIndex) => {
                            if (fileIndex !== index) {
                                transfer.items.add(file);
                            }
                        });
                        this.$refs.photosInput.files = transfer.files;
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
                    async uploadAll() {
                        if (this.uploading || this.previews.length === 0) return;
                        this.uploading = true;
                        this.uploadError = '';
                        this.uploadIndex = 0;
                        this.uploadProgress = 0;
                        this.currentFileName = '';
                        const files = Array.from(this.$refs.photosInput.files || []);
                        const totalBytes = files.reduce((sum, file) => sum + (Number(file?.size) || 0), 0);
                        let uploadedBytes = 0;

                        try {
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
                                formData.append('photos_meta[0][photographed_at]', preview.photographedAt || '');
                                formData.append('photos_meta[0][tags]', preview.tags.join(', '));
                                formData.append('photos_meta[0][caption]', preview.caption || '');
                                formData.append('photos_meta[0][consent_notes]', preview.consentNotes || '');
                                formData.append('photos_meta[0][edit_rotation]', preview.editRotation || 0);
                                formData.append('photos_meta[0][edit_crop_top]', preview.editCropTop || 0);
                                formData.append('photos_meta[0][edit_crop_right]', preview.editCropRight || 0);
                                formData.append('photos_meta[0][edit_crop_bottom]', preview.editCropBottom || 0);
                                formData.append('photos_meta[0][edit_crop_left]', preview.editCropLeft || 0);

                                try {
                                    await axios.post(@js(route('admin.workshop.photos.store', $workshop)), formData, {
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                        onUploadProgress: (progressEvent) => {
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
                                    if (payload) {
                                        message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || message;
                                    } else if (error?.response?.status === 413) {
                                        message = 'The selected media file is too large for one upload request.';
                                    }

                                    throw new Error(`${file.name}: ${message}`);
                                }

                                uploadedBytes += file.size;
                                this.uploadProgress = totalBytes > 0
                                    ? Math.max(0, Math.min(100, Math.round((uploadedBytes / totalBytes) * 100)))
                                    : 100;
                            }

                            sessionStorage.setItem('workshop-media-upload-toast', JSON.stringify({
                                title: 'Media uploaded',
                                message: `${files.length} workshop media item${files.length === 1 ? '' : 's'} uploaded.`,
                                type: 'success',
                            }));
                            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                            window.location.href = @js(route('admin.workshop.photos', $workshop));
                        } catch (error) {
                            this.uploadError = error.message || 'Upload failed.';
                            this.uploading = false;
                        }
                    },
                }"
                x-on:submit.prevent="uploadAll()"
            >
                @csrf
                <div>
                    <label class="mb-1 block text-sm pl-1" for="photos">Upload Workshop Media</label>
                    <input
                        id="photos"
                        name="photos[]"
                        type="file"
                        accept="image/*,video/mp4,video/quicktime,video/webm,video/x-msvideo,video/x-m4v,.mov,.mp4,.webm,.avi,.m4v"
                        multiple
                        required
                        class="sr-only"
                        x-ref="photosInput"
                        x-on:change="update($event.target.files)"
                        x-bind:disabled="uploading"
                    >
                    <label
                        for="photos"
                        class="group mt-1 flex w-full cursor-pointer items-center justify-between gap-4 rounded-lg border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-left text-sm transition hover:border-primary-color hover:bg-sky-50"
                        x-on:dragover.prevent="$el.classList.add('ring-2', 'ring-primary-color', 'border-primary-color')"
                        x-on:dragleave.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color')"
                        x-on:drop.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color'); $refs.photosInput.files = $event.dataTransfer.files; update($event.dataTransfer.files)"
                    >
                        <div class="min-w-0 grow">
                            <div class="truncate font-medium text-gray-800" x-text="previews.length ? previews.length + ' media item' + (previews.length === 1 ? '' : 's') + ' selected' : 'Drop photos or videos here or click to browse'"></div>
                            <div class="mt-1 text-xs text-gray-500">Supports JPG, PNG, WebP, GIF, MP4, MOV, WebM, AVI, and M4V files.</div>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-md border border-primary-color px-3 py-1.5 text-xs font-semibold text-primary-color transition group-hover:bg-primary-color group-hover:text-white">Browse</span>
                    </label>
                    @error('photos') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                    @error('photos.*') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror

                    <div x-show="previews.length" x-cloak class="mt-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-gray-700">Selected Media & Metadata</div>
                            <button type="button" class="text-xs font-medium text-gray-500 hover:text-danger-color disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="uploading" x-on:click.prevent="clear()">Clear files</button>
                        </div>
                        <div class="space-y-4">
                            <template x-for="(preview, previewIndex) in previews" :key="preview.url">
                                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                    <div class="flex flex-col">
                                        <div class="flex flex-col sm:flex-row gap-4">
                                            <div class="mx-auto shrink">
                                                <div class="w-32">
                                                    <div class="block overflow-hidden">
                                                        <template x-if="String(preview.type || '').startsWith('video/')">
                                                            <video :src="preview.url" class="h-24 w-32 object-cover" muted playsinline preload="metadata"></video>
                                                        </template>
                                                        <template x-if="!String(preview.type || '').startsWith('video/')">
                                                            <div class="flex h-24 w-32 items-center justify-center overflow-hidden">
                                                                <canvas class="block h-full w-full" x-effect="preview.editRotation; preview.editCropTop; preview.editCropRight; preview.editCropBottom; preview.editCropLeft; renderPreviewCanvas($el, preview)"></canvas>
                                                            </div>
                                                        </template>
                                                        <div class="space-y-0.5 px-2 py-1 text-[11px]">
                                                            <div class="text-gray-500" x-text="sizeLabel(preview.size)"></div>
                                                            <div class="text-gray-500" x-text="fileTypeLabel(preview.type)"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex flex-col grow md:flex-row md:gap-4">
                                                <div class="flex-1">
                                                    <x-ui.input
                                                        label="Title"
                                                        :name="null"
                                                        x-bind:name="`photos_meta[${preview.index}][title]`"
                                                        x-model="preview.title"
                                                        x-bind:disabled="uploading"
                                                    />
                                                    <x-ui.input
                                                        label="Photographed At"
                                                        type="date"
                                                        :name="null"
                                                        x-bind:name="`photos_meta[${preview.index}][photographed_at]`"
                                                        x-model="preview.photographedAt"
                                                        x-bind:disabled="uploading"
                                                        required
                                                    />
                                                    <input type="hidden" x-bind:name="`photos_meta[${preview.index}][edit_rotation]`" x-bind:value="preview.editRotation">
                                                    <input type="hidden" x-bind:name="`photos_meta[${preview.index}][edit_crop_top]`" x-bind:value="preview.editCropTop">
                                                    <input type="hidden" x-bind:name="`photos_meta[${preview.index}][edit_crop_right]`" x-bind:value="preview.editCropRight">
                                                    <input type="hidden" x-bind:name="`photos_meta[${preview.index}][edit_crop_bottom]`" x-bind:value="preview.editCropBottom">
                                                    <input type="hidden" x-bind:name="`photos_meta[${preview.index}][edit_crop_left]`" x-bind:value="preview.editCropLeft">
                                                    <x-ui.tags
                                                        :name="null"
                                                        :options="$tagOptions ?? []"
                                                        x-bind:name="`photos_meta[${preview.index}][tags]`"
                                                        x-model-tags="preview.tags"
                                                        x-model-draft="preview.tagDraft"
                                                    />
                                                </div>
                                                <div class="flex-1">
                                                    <x-ui.select
                                                        label="Visibility"
                                                        :name="null"
                                                        x-bind:name="`photos_meta[${preview.index}][visibility]`"
                                                        x-model="preview.visibility"
                                                        x-bind:disabled="uploading"
                                                    >
                                                        <option value="private">Private</option>
                                                        <option value="public">Public</option>
                                                    </x-ui.select>
                                                    <x-ui.input
                                                        label="Caption"
                                                        type="textarea"
                                                        :name="null"
                                                        x-bind:name="`photos_meta[${preview.index}][caption]`"
                                                        x-model="preview.caption"
                                                        x-bind:disabled="uploading"
                                                    />
                                                    <x-ui.input
                                                        label="Notes"
                                                        type="textarea"
                                                        :name="null"
                                                        x-bind:name="`photos_meta[${preview.index}][consent_notes]`"
                                                        x-model="preview.consentNotes"
                                                        x-bind:disabled="uploading"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end items-center">
                                            <div class="flex items-center gap-3 pt-1">
                                                <template x-if="isImage(preview)">
                                                    <button type="button" class="text-primary-color hover:text-primary-color-dark disabled:cursor-not-allowed disabled:opacity-50" title="Edit image" x-bind:disabled="uploading" x-on:click.prevent="openEditor(previewIndex)">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                </template>
                                                <button type="button" class="text-red-600 hover:text-red-800 disabled:cursor-not-allowed disabled:opacity-50" title="Delete row" x-bind:disabled="uploading" x-on:click.prevent="remove(previewIndex)">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div x-show="editingPreview()" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="closeEditor()">
                        <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl" x-on:click.away="closeEditor()">
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                                <div>
                                    <div class="text-base font-semibold text-gray-900">Edit Image</div>
                                    <div class="text-xs text-gray-500" x-text="editingPreview()?.name || ''"></div>
                                </div>
                                <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click.prevent="closeEditor()">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <div class="overflow-y-auto p-4">
                                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]">
                                <div class="sm-image-crop-preview" :style="editingPreview() ? editorFrameStyle() : ''">
                                    <template x-if="editingPreview()">
                                        <div>
                                            <div class="absolute inset-0 flex items-center justify-center overflow-hidden">
                                                <canvas x-ref="editorCanvas" x-effect="editingDraft.rotation; editingDraft.top; editingDraft.right; editingDraft.bottom; editingDraft.left; renderEditorCanvas($refs.editorCanvas, editingPreview())" class="block h-full w-full"></canvas>
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
                                    </template>
                                </div>
                                <div class="space-y-4 md:max-w-72">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" title="Rotate left" x-on:click.prevent="if (editingPreview()) rotate(-90)"><i class="fa-solid fa-rotate-left"></i></button>
                                        <button type="button" class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" title="Rotate right" x-on:click.prevent="if (editingPreview()) rotate(90)"><i class="fa-solid fa-rotate-right"></i></button>
                                    </div>
                                    <div class="text-xs text-gray-500">Drag the crop box or its handles directly on the image.</div>
                                    <div class="flex justify-between gap-2">
                                        <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" x-on:click.prevent="if (editingPreview()) resetEdits()">Reset</button>
                                        <button type="button" class="rounded bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark" x-on:click.prevent="applyEditor()">Done</button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="uploadError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="uploadError"></div>
                <div x-show="uploading" x-cloak class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="font-medium">
                            <i class="fa-solid fa-circle-notch animate-spin mr-2"></i>
                            Uploading media
                        </div>
                        <div class="text-xs" x-text="uploadIndex + ' / ' + previews.length"></div>
                    </div>
                    <div class="mb-2 text-xs text-sky-900" x-text="currentFileName ? currentFileName + ' — ' + uploadProgress + '%' : ''"></div>
                    <div class="h-2 w-full overflow-hidden rounded bg-sky-100">
                        <div class="h-2 rounded bg-primary-color transition-all" x-bind:style="`width: ${uploadProgress}%`"></div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="submit" x-bind:disabled="uploading">
                        <span x-show="!uploading">Upload Media</span>
                        <span x-show="uploading" x-cloak>Uploading...</span>
                    </x-ui.button>
                </div>
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
                x-data="{ dirty: false, saving: false }"
                x-on:input="dirty = true"
                x-on:change="dirty = true"
                x-on:submit="saving = true"
            >
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    @foreach($photos as $photo)
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-col">
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="mx-auto shrink">
                                        <div class="w-32">
                                            <a href="{{ route('admin.workshop.photos.media', [$workshop, $photo]) }}" target="_blank" class="block overflow-hidden">
                                                <img src="{{ route('admin.workshop.photos.media', [$workshop, $photo, 'variant' => 'thumbnail']) }}" alt="{{ $photo->title }}" class="max-w-32 h-32 object-cover rounded-lg mx-auto">
                                            </a>
                                            <div class="space-y-0.5 px-2 py-1 text-[11px]">
                                                <div class="text-gray-500">{{ \App\Helpers::bytesToString((int) ($photo->size ?? 0)) }}</div>
                                                <div class="text-gray-500">{{ $photo->file_type }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col grow md:flex-row md:gap-4">
                                        <div class="flex-1">
                                            <x-ui.input label="Title" name="photos[{{ $photo->name }}][title]" value="{{ $photo->title }}" />
                                            <x-ui.input label="Photographed At" name="photos[{{ $photo->name }}][photographed_at]" type="date" value="{{ optional($photo->photographed_at)->format('Y-m-d') }}" />
                                            <x-ui.tags name="photos[{{ $photo->name }}][tags]" value="{{ $photo->tags }}" :options="$tagOptions ?? []" />
                                        </div>
                                        <div class="flex-1">
                                            <x-ui.select label="Visibility" name="photos[{{ $photo->name }}][visibility]" value="{{ in_array($photo->visibility, ['private', 'public'], true) ? $photo->visibility : 'private' }}">
                                                <option value="private" @selected(! in_array($photo->visibility, ['private', 'public'], true) || $photo->visibility === 'private')>Private</option>
                                                <option value="public" @selected($photo->visibility === 'public')>Public</option>
                                            </x-ui.select>
                                            <x-ui.input label="Caption" name="photos[{{ $photo->name }}][caption]" type="textarea" value="{{ $photo->caption }}" />
                                            <x-ui.input label="Notes" name="photos[{{ $photo->name }}][consent_notes]" type="textarea" value="{{ $photo->consent_notes }}" />
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end items-center">
                                    <div class="flex items-center gap-3 pt-1">
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
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <x-ui.button color="outline" href="{{ route('admin.workshop.photos.zip', $workshop) }}">Download ZIP</x-ui.button>
                    <x-ui.button type="submit" x-bind:disabled="!dirty || saving">
                        <span x-show="!saving">Save Photo Changes</span>
                        <span x-show="saving" x-cloak>Saving...</span>
                    </x-ui.button>
                </div>
            </form>

            <div class="mt-6">{{ $photos->links() }}</div>
        @endif
    </x-container>
</x-layout>
