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
            'active' => true,
        ],
        [
            'title' => 'Photos',
            'route' => route('admin.workshop.photos', $workshop),
        ],
    ];
    $dateLabel = $workshop->starts_at
        ? $workshop->starts_at->format('D j M Y, g:ia').($workshop->ends_at ? ' – '.$workshop->ends_at->format('g:ia') : '')
        : 'No date set';
    $locationLabel = $workshop->getLocationDisplay();
    $attachedFiles = $files ?? $workshop->files()->orderBy('name')->paginate(50);
    $attachedFilesValue = $attachedFiles instanceof \Illuminate\Contracts\Pagination\Paginator
        ? collect($attachedFiles->items())
        : collect($attachedFiles);
@endphp

<x-layout title="Workshop Files - {{ $workshop->title }}">
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops" :tabs="$workshopTabs">Workshop Files</x-mast>

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
                <div class="hidden max-w-lg items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 lg:flex" role="note">
                    <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
                    <p>All public files are displayed on the workshop page.</p>
                </div>
            </div>
            <div class="mt-4 flex items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 lg:hidden" role="note">
                <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
                <p>All public files are displayed on the workshop page.</p>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
            <form
                method="POST"
                action="{{ route('admin.workshop.files.update', $workshop) }}"
                enctype="multipart/form-data"
                class="space-y-4"
                x-data="{
                    stagedWorkshopFiles: @js($attachedFilesValue->map(fn ($file) => [
                        'kind' => 'existing',
                        'key' => 'existing:'.(string) $file->name,
                        'is_pending_attachment' => false,
                        'name' => (string) $file->name,
                        'title' => (string) $file->title,
                        'mime_type' => (string) ($file->mime_type ?? ''),
                        'size' => (int) ($file->size ?? 0),
                        'thumbnail' => (string) ($file->thumbnail ?? asset('/thumbnails/unknown.webp')),
                        'url' => (string) ($file->url ?? ('/media/'.rawurlencode((string) $file->name))),
                        'download_url' => (string) (($file->download_url ?? null) ?: (($file->url ?? ('/media/'.rawurlencode((string) $file->name))).'?download=1')),
                        'edit_url' => auth()->user()?->isAdmin() ? route('admin.media.edit', $file) : null,
                        'visibility' => in_array((string) ($file->visibility ?? 'private'), ['private', 'protected', 'public'], true) ? (string) $file->visibility : 'private',
                        'storage_disk' => (string) $file->storageDiskName(),
                        'file_type' => (string) ($file->file_type ?? 'File'),
                        'notes' => (string) ($file->consent_notes ?? ''),
                        'selected' => false,
                    ])->values()->all()),
                    workshopFilesFallbackThumbnail: @js(asset('/thumbnails/unknown.webp')),
                    nextWorkshopFileId: 1,
                    workshopFilesUploadProgress: 0,
                    workshopFilesUploadMessage: '',
                    workshopFilesUploadIndex: 0,
                    workshopFilesUploadTotal: 0,
                    workshopFilesUploading: false,
                    workshopFilesUploadError: '',
                    editingWorkshopFile: null,
                    bulkPendingOpen: false,
                    bulkPendingVisibility: '',
                    openWorkshopFileEditor(item) { this.editingWorkshopFile = item; },
                    closeWorkshopFileEditor() { this.editingWorkshopFile = null; },
                    selectedPendingFileCount() { return this.pendingWorkshopFiles().filter((item) => item.selected).length; },
                    selectAllPendingFiles(checked) { this.pendingWorkshopFiles().forEach((item) => item.selected = checked); },
                    applyPendingBulkEdit() { if (this.bulkPendingVisibility) this.pendingWorkshopFiles().filter((item) => item.selected && item.kind === 'pending').forEach((item) => item.visibility = this.bulkPendingVisibility); this.bulkPendingOpen = false; },
                    init() {
                        this.syncWorkshopFilesPayload();
                    },
                    titleFromFileName(value) {
                        const fileName = String(value || '').trim();
                        if (fileName === '') {
                            return '';
                        }

                        const withoutExtension = fileName
                            .replace(/\.stopmotion\.zip$/i, '')
                            .replace(/\.[^.]+$/, '');
                        const normalized = withoutExtension
                            .replace(/[_-]+/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim();

                        if (normalized === '') {
                            return fileName;
                        }

                        return normalized.replace(/\b\w/g, (character) => character.toUpperCase());
                    },
                    thumbnailFromFileName(value) {
                        const fileName = String(value || '').trim();
                        if (fileName === '') {
                            return this.workshopFilesFallbackThumbnail;
                        }

                        if (/\.stopmotion\.zip$/i.test(fileName)) {
                            return '/thumbnails/stopmotionstudiomobile.webp';
                        }

                        const parts = fileName.split('.');
                        const extension = parts.length > 1 ? String(parts.pop() || '').trim().toLowerCase() : '';
                        return extension !== '' ? `/thumbnails/${extension}.webp` : this.workshopFilesFallbackThumbnail;
                    },
                    workshopFileThumbnail(item) {
                        if (item?.kind === 'pending' && typeof item?.preview_url === 'string' && item.preview_url.trim() !== '') {
                            return item.preview_url;
                        }

                        const thumbnail = String(item?.thumbnail || '').trim();
                        if (thumbnail !== '' && !thumbnail.startsWith('data:')) {
                            return thumbnail;
                        }

                        return this.thumbnailFromFileName(item?.name || '');
                    },
                    workshopFileTypeLabel(item) {
                        const raw = String(item?.file_type || item?.mime_type || 'File');
                        return raw.replace(/\(.*?\)/g, '').trim() || 'File';
                    },
                    fileTypeFromFile(file) {
                        const mimeType = String(file?.type || '').trim().toLowerCase();
                        const fileName = String(file?.name || '').trim();
                        const extension = fileName.includes('.') ? String(fileName.split('.').pop() || '').trim().toLowerCase() : '';

                        if (/\.stopmotion\.zip$/i.test(fileName)) {
                            return 'Stop Motion Studio Project';
                        }

                        if (mimeType.startsWith('image/')) {
                            return `Image (${extension || 'file'})`;
                        }

                        if (mimeType.startsWith('video/')) {
                            return `Video (${extension || 'file'})`;
                        }

                        if (mimeType.startsWith('audio/')) {
                            return `Audio (${extension || 'file'})`;
                        }

                        if (mimeType === 'application/pdf') {
                            return 'PDF Document';
                        }

                        if (mimeType === 'text/plain') {
                            return 'Text Document';
                        }

                        if (extension === 'sb3') {
                            return 'Scratch 3 Project';
                        }

                        if (extension === 'stopmotionstudio' || extension === 'stopmotionstudiomobile') {
                            return 'Stop Motion Studio Project';
                        }

                        if (extension !== '') {
                            return `File (${extension})`;
                        }

                        return 'File';
                    },
                    workshopFileRowKey(item, index) {
                        return String(item?.key || `${item?.kind || 'file'}:${index}`);
                    },
                    pendingWorkshopFiles() {
                        return this.stagedWorkshopFiles.filter((item) => item.kind === 'pending' || item.is_pending_attachment === true);
                    },
                    async addWorkshopPendingFiles(fileList) {
                        const files = Array.from(fileList || []).filter((file) => file instanceof File);
                        if (files.length === 0) {
                            return;
                        }

                        this.workshopFilesUploading = true;
                        this.workshopFilesUploadProgress = 0;
                        this.workshopFilesUploadMessage = `Preparing ${files.length} file${files.length === 1 ? '' : 's'}… This page may be temporarily unresponsive.`;
                        this.workshopFilesUploadTotal = files.length;
                        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                        const prepared = files.map((file) => {
                            const id = this.nextWorkshopFileId++;
                            const previewUrl = String(file.type || '').startsWith('image/') ? URL.createObjectURL(file) : '';
                            return {
                                kind: 'pending',
                                key: `pending:${id}`,
                                pending_id: id,
                                file,
                                preview_url: previewUrl,
                                title: this.titleFromFileName(file.name),
                                name: file.name,
                                mime_type: file.type || 'application/octet-stream',
                                size: file.size || 0,
                                thumbnail: this.thumbnailFromFileName(file.name),
                                url: '',
                                download_url: '',
                                edit_url: '',
                                visibility: 'public',
                                storage_disk: 'archive',
                                file_type: this.fileTypeFromFile(file),
                                notes: '',
                                selected: false,
                            };
                        });

                        this.stagedWorkshopFiles = [...this.stagedWorkshopFiles, ...prepared];
                        this.syncWorkshopFilesPayload();
                        this.workshopFilesUploading = false;
                        await this.handleSubmit();
                    },
                    syncWorkshopPendingInput() {
                        if (!(this.$refs.workshopFilesPendingInput instanceof HTMLInputElement)) {
                            return;
                        }

                        const transfer = new DataTransfer();
                        this.stagedWorkshopFiles.forEach((item) => {
                            if (item.kind === 'pending' && item.file instanceof File) {
                                transfer.items.add(item.file);
                            }
                        });
                        this.$refs.workshopFilesPendingInput.files = transfer.files;
                    },
                    syncWorkshopFilesPayload() {
                        if (this.$refs.workshopFilesOrder instanceof HTMLInputElement) {
                            this.$refs.workshopFilesOrder.value = JSON.stringify(this.stagedWorkshopFiles.map((item) => ({
                                kind: item.kind,
                                name: item.kind === 'existing' ? item.name : null,
                                pending_id: item.kind === 'pending' ? item.pending_id : null,
                            })));
                        }

                        if (this.$refs.workshopFilesPendingKeys instanceof HTMLInputElement) {
                            this.$refs.workshopFilesPendingKeys.value = JSON.stringify(
                                this.stagedWorkshopFiles
                                    .filter((item) => item.kind === 'pending')
                                    .map((item) => item.pending_id)
                            );
                        }

                        if (this.$refs.workshopFilesExisting instanceof HTMLInputElement) {
                            this.$refs.workshopFilesExisting.value = this.stagedWorkshopFiles
                                .filter((item) => item.kind === 'existing')
                                .map((item) => item.name)
                                .join(',');
                        }
                    },
                    removeWorkshopFile(index) {
                        const [removed] = this.stagedWorkshopFiles.splice(index, 1);
                        if (removed?.kind === 'pending' && removed.preview_url) {
                            URL.revokeObjectURL(removed.preview_url);
                        }
                        this.syncWorkshopFilesPayload();
                    },
                    removeWorkshopFileByKey(fileKey) {
                        const index = this.stagedWorkshopFiles.findIndex((item) => this.workshopFileRowKey(item) === String(fileKey));
                        if (index === -1) {
                            return;
                        }

                        this.removeWorkshopFile(index);
                    },
                    clearPendingFiles() {
                        const pendingKeys = this.pendingWorkshopFiles().map((item, index) => this.workshopFileRowKey(item, index));
                        pendingKeys.forEach((fileKey) => this.removeWorkshopFileByKey(fileKey));
                    },
                    openWorkshopExistingFilePicker() {
                        const attached = @js($attachedFileNames ?? []);
                        const staged = this.stagedWorkshopFiles
                            .filter((item) => item.kind === 'existing')
                            .map((item) => item.name);

                        SMMediaPicker.open([], {
                            allow_multiple: true,
                            allow_uploads: false,
                            public_usable_only: false,
                            disabled: [...new Set([...attached, ...staged])],
                            disabled_item_text: 'Selected',
                            title: 'Add Existing Files',
                            confirm_button_text: 'Add Files',
                        }, (result) => this.attachExistingWorkshopFiles(result));
                    },
                    async attachExistingWorkshopFiles(result) {
                        const names = Array.isArray(result) ? result : [result];
                        const attachNames = [];
                        names.forEach((name) => {
                            const fileName = String(name || '').trim();
                            if (fileName === '' || this.stagedWorkshopFiles.some((item) => item.kind === 'existing' && item.name === fileName)) {
                                return;
                            }

                            const placeholder = {
                                kind: 'existing',
                                key: `existing:${fileName}`,
                                is_pending_attachment: true,
                                name: fileName,
                                title: fileName,
                                mime_type: '',
                                size: 0,
                                thumbnail: this.thumbnailFromFileName(fileName),
                                url: '/media/' + encodeURIComponent(fileName),
                                download_url: '/media/' + encodeURIComponent(fileName) + '?download=1',
                                edit_url: '',
                                visibility: 'private',
                                storage_disk: 'media',
                                file_type: 'File',
                                notes: '',
                                selected: false,
                            };

                            this.stagedWorkshopFiles.push(placeholder);
                            attachNames.push(fileName);
                            SM.mediaDetails(fileName, (details) => {
                                if (!details || typeof details !== 'object') {
                                    return;
                                }
                                const index = this.stagedWorkshopFiles.findIndex((item) => item.kind === 'existing' && item.name === fileName);
                                if (index === -1) {
                                    return;
                                }
                                this.stagedWorkshopFiles[index] = {
                                    ...this.stagedWorkshopFiles[index],
                                    is_pending_attachment: true,
                                    title: String(details.title || fileName),
                                    mime_type: String(details.mime_type || ''),
                                    size: Number(details.size || 0),
                                    thumbnail: String(details.thumbnail || this.workshopFilesFallbackThumbnail),
                                    url: String(details.url || ('/media/' + encodeURIComponent(fileName))),
                                    download_url: String(details.download_url || ((details.url || ('/media/' + encodeURIComponent(fileName))) + '?download=1')),
                                    edit_url: String(details.edit_url || ''),
                                    visibility: ['private', 'protected', 'public'].includes(String(details.visibility || '')) ? String(details.visibility) : 'private',
                                    storage_disk: ['media', 'archive'].includes(String(details.storage_disk || '')) ? String(details.storage_disk) : 'media',
                                    file_type: String(details.file_type || 'File'),
                                    notes: String(details.consent_notes || ''),
                                };
                            });
                        });
                        this.syncWorkshopFilesPayload();
                        if (attachNames.length === 0) return;
                        this.workshopFilesUploading = true;
                        this.workshopFilesUploadMessage = `Attaching ${attachNames.length} existing file${attachNames.length === 1 ? '' : 's'}…`;
                        try {
                            await axios.post(@js(route('admin.workshop.files.attach', $workshop)), { media_names: attachNames }, { headers: { Accept: 'application/json' } });
                            window.location.href = @js(route('admin.workshop.files', $workshop));
                        } catch (error) {
                            this.workshopFilesUploading = false;
                            this.workshopFilesUploadError = error?.response?.data?.message || 'The selected files could not be attached.';
                        }
                    },
                    async handleSubmit() {
                        if (this.workshopFilesUploading) return;
                        this.workshopFilesUploading = true;
                        this.workshopFilesUploadProgress = 0;
                        this.workshopFilesUploadError = '';
                        const pending = this.stagedWorkshopFiles.filter((item) => item.kind === 'pending' && item.file instanceof File);
                        const totalBytes = pending.reduce((sum, item) => sum + Number(item.size || 0), 0);
                        let uploadedBytes = 0;

                        try {
                            await new Promise((resolve) => requestAnimationFrame(resolve));
                            for (let index = 0; index < pending.length; index++) {
                                const item = pending[index];
                                this.workshopFilesUploadMessage = `Uploading: ${item.name}`;
                                this.workshopFilesUploadIndex = `${index + 1}`;
                                this.workshopFilesUploadTotal = `${pending.length}`;

                                const uploaded = await new Promise((resolve, reject) => {
                                    let settled = false;
                                    const rejectOnce = (message) => {
                                        if (settled) return;
                                        settled = true;
                                        reject(new Error(message || 'The file could not be uploaded.'));
                                    };

                                    SM.upload([item.file], (result) => {
                                        if (settled) return;
                                        if (!result?.success || !result.files?.[0]?.data?.upload_token) {
                                            rejectOnce(result?.message || 'The file could not be uploaded.');
                                            return;
                                        }
                                        settled = true;
                                        resolve(result.files[0].data);
                                    }, [item.title || ''], {
                                        showModal: false,
                                        successDelayMs: 0,
                                        deferFinalization: true,
                                        onProgress: (progress) => {
                                            const loaded = Math.min(Number(progress.loaded || 0), Number(item.size || 0));
                                            this.workshopFilesUploadProgress = totalBytes > 0
                                                ? Math.min(99, Math.round(((uploadedBytes + loaded) / totalBytes) * 100))
                                                : 0;
                                        },
                                        onError: rejectOnce,
                                    });
                                });

                                await axios.post(@js(route('admin.workshop.files.upload', $workshop)), {
                                    upload_token: uploaded.upload_token,
                                    filename: item.name,
                                    pending_file_keys: JSON.stringify([item.pending_id]),
                                    pending_files_meta: {
                                        [item.pending_id]: {
                                            title: item.title || '',
                                            visibility: item.visibility || 'public',
                                            notes: item.notes || '',
                                        },
                                    },
                                }, { headers: { Accept: 'application/json' } });
                                uploadedBytes += Number(item.size || 0);
                            }

                            this.workshopFilesUploadProgress = 100;
                            this.workshopFilesUploadMessage = 'Finishing…';
                            await new Promise((resolve) => requestAnimationFrame(resolve));
                            window.location.href = @js(route('admin.workshop.files', $workshop));
                        } catch (error) {
                            this.workshopFilesUploading = false;
                            const payload = error?.response?.data;
                            this.workshopFilesUploadError = error?.response?.status === 413
                                ? 'The server or proxy rejected an upload chunk as too large. Please contact an administrator with the time of this upload.'
                                : (payload?.message || Object.values(payload?.errors || {}).flat().join(' ') || error.message || 'Upload failed.');
                            window.SM?.notice?.('Upload failed', this.workshopFilesUploadError, 'danger');
                            [...this.stagedWorkshopFiles].filter((item) => item.kind === 'pending').forEach((item) => this.removeWorkshopFileByKey(item.key));
                        }
                    }
                }"
                x-init="init()"
                x-ref="workshopFilesForm"
                x-on:submit.prevent="handleSubmit()"
            >
                @csrf
                @method('PUT')

                <input
                    type="file"
                    name="pending_files[]"
                    multiple
                    class="hidden"
                    tabindex="-1"
                    aria-hidden="true"
                    x-ref="workshopFilesPendingInput"
                >
                <x-ui.media-uploader
                    input-id="workshop_files_pending"
                    input-ref="workshopFilesPicker"
                    count="pendingWorkshopFiles().length"
                    item-label="file item"
                    empty-text="Drop files here"
                    description="Upload new files or select files already in the media library."
                    on-files="addWorkshopPendingFiles"
                    on-browse-existing="openWorkshopExistingFilePicker"
                    disabled="workshopFilesUploading"
                    :showSubmit="false"
                >

                    <div x-show="workshopFilesUploadError" x-cloak class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="workshopFilesUploadError"></div>
                </x-ui.media-uploader>

                <template x-teleport="body">
                    <div
                        x-show="workshopFilesUploading"
                        x-cloak
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="workshop-files-progress-title"
                        x-on:keydown.escape.prevent.stop
                    >
                        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" role="status" aria-live="polite">
                            <div class="mb-4 flex items-center gap-3">
                                <i class="fa-solid fa-circle-notch animate-spin text-xl text-primary-color"></i>
                                <div>
                                    <div id="workshop-files-progress-title" class="text-lg font-semibold text-gray-900">Uploading files</div>
                                    <div class="mt-1 text-sm text-gray-500">Please keep this page open until the operation finishes.</div>
                                </div>
                            </div>
                            <div class="mb-2 min-h-10 break-words text-sm text-gray-700" x-text="workshopFilesUploadMessage || ''"></div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-sky-100">
                                <div class="h-3 rounded-full bg-primary-color transition-[width] duration-200" x-bind:style="`width: ${workshopFilesUploadProgress}%`"></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-sm font-semibold text-gray-700"><span x-text="`${workshopFilesUploadIndex} of ${workshopFilesUploadTotal}`"></span><span x-text="`${workshopFilesUploadProgress}%`"></span></div>
                        </div>
                    </div>
                </template>
            </form>
        </div>

        <x-ui.toolbar>
            <x-slot:right>
                <form method="GET" action="{{ route('admin.workshop.files', $workshop) }}" class="flex flex-wrap items-center justify-end gap-2">
                    <x-ui.input name="search" label="Search files" value="{{ request('search') }}" class="mb-0 min-w-64" noLabel="true" />
                    <x-ui.select name="visibility" label="Visibility" class="mb-0 min-w-40" selectClass="min-w-40" noLabel="true">
                        <option value="" @selected(request('visibility') === null || request('visibility') === '')>Any visibility</option>
                        <option value="private" @selected(request('visibility') === 'private')>Private</option>
                        <option value="protected" @selected(request('visibility') === 'protected')>Protected</option>
                        <option value="public" @selected(request('visibility') === 'public')>Public</option>
                    </x-ui.select>
                    <x-ui.button type="submit" color="outline">Filter</x-ui.button>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($attachedFiles->isEmpty())
            <x-none-found item="files" search="{{ request()->get('search') }}" />
        @else
            <div x-data="{ selected: [], allNames: @js($attachedFilesValue->pluck('name')->values()), bulkOpen: false, bulkStorage: '', bulkVisibility: '', bulkSaving: false, toggleAll(checked) { this.selected = checked ? [...this.allNames] : []; }, zipUrl() { const query = new URLSearchParams(); this.selected.forEach((name) => query.append('media_names[]', name)); return @js(route('admin.workshop.files.zip', $workshop)) + '?' + query.toString(); }, async applyBulk() { this.bulkSaving = true; try { await axios.put(@js(route('admin.workshop.files.bulk-update', $workshop)), { media_names: this.selected, storage_disk: this.bulkStorage || null, visibility: this.bulkVisibility || null }, { headers: { Accept: 'application/json' } }); window.location.reload(); } catch (error) { window.SM?.notice?.('Update failed', error.response?.data?.message || 'Could not update selected files.', 'danger'); } finally { this.bulkSaving = false; } } }">
                <div class="w-full overflow-x-auto">
                    <table class="table">
                        <thead><tr><th class="w-10 text-center !border-r-0"><x-ui.checkbox aria-label="Select all existing files" :small="true" :noWrapper="true" inputClass="mx-auto" x-bind:checked="selected.length > 0 && selected.length === allNames.length" x-effect="$el.indeterminate = selected.length > 0 && selected.length < allNames.length" x-on:change="toggleAll($el.checked)" /></th><th class="!border-l-0">Media</th><th class="hidden w-24 text-center md:table-cell">Storage</th><th class="hidden w-24 text-center md:table-cell">Visibility</th><th class="w-36 text-center">Actions</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($attachedFiles as $file)
                                @php($fileVisibility = in_array((string) ($file->visibility ?? ''), ['private', 'protected', 'public'], true) ? (string) $file->visibility : 'private')
                                <tr>
                                    <td class="text-center !border-r-0"><x-ui.checkbox aria-label="Select {{ $file->title }}" value="{{ $file->name }}" :small="true" :noWrapper="true" inputClass="mx-auto" x-model="selected" /></td>
                                    <td class="px-3 py-3"><div class="flex min-w-0 items-center gap-3"><a href="{{ $file->download_url ?? (($file->url ?? '/media/'.rawurlencode((string) $file->name)).'?download=1') }}" target="_blank" class="shrink-0"><img src="{{ $file->thumbnail ?: asset('/thumbnails/unknown.webp') }}" onerror="this.onerror=null;this.src='{{ asset('/thumbnails/unknown.webp') }}';" alt="{{ $file->title }}" class="h-12 w-16 rounded bg-white object-contain p-1"></a><div class="min-w-0"><div class="font-medium text-gray-900">{{ trim((string) ($file->title ?? '')) !== '' ? $file->title : $file->name }}</div><div class="max-w-xs truncate text-xs text-gray-500">{{ $file->name }}</div><div class="text-xs text-gray-400">{{ \App\Helpers::bytesToString((int) ($file->size ?? 0)) }} · {{ $file->file_type }}</div><div class="md:hidden text-xs text-gray-500">Storage: <span class="capitalize">{{ $file->storageDiskName() }}</span></div><div class="md:hidden"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize {{ $fileVisibility === 'public' ? 'bg-emerald-100 text-emerald-800' : ($fileVisibility === 'protected' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $fileVisibility }}</span></div></div></div></td>
                                    <td class="hidden px-3 py-3 text-center capitalize md:table-cell">{{ $file->storageDiskName() }}</td>
                                    <td class="hidden px-3 py-3 text-center md:table-cell"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize {{ $fileVisibility === 'public' ? 'bg-emerald-100 text-emerald-800' : ($fileVisibility === 'protected' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $fileVisibility }}</span></td>
                                    <td class="px-3 py-3"><div class="flex justify-end gap-3"><a href="{{ route('admin.media.edit', $file) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color" title="Edit file"><i class="fa-solid fa-pen-to-square"></i></a><a href="{{ $file->download_url ?? (($file->url ?? '/media/'.rawurlencode((string) $file->name)).'?download=1') }}" class="text-primary-color" title="Download file"><i class="fa-solid fa-download"></i></a><button type="button" class="text-amber-600" title="Remove from this workshop only" x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Remove file from workshop?', 'This will remove the file from this workshop only. The media item will remain in the media library.', '{{ route('admin.workshop.files.destroy', [$workshop, $file]) }}', 'Remove from workshop')"><i class="fa-solid fa-ban"></i></button></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex justify-end gap-2"><x-ui.button type="button" color="outline" x-bind:disabled="selected.length === 0" x-on:click="bulkStorage = ''; bulkVisibility = ''; bulkOpen = true">Bulk edit selected</x-ui.button><x-ui.button type="button" color="outline" x-bind:disabled="selected.length === 0" x-on:click="window.location.href = zipUrl()">Download ZIP</x-ui.button></div>
                <template x-teleport="body"><div x-show="bulkOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="bulkOpen = false"><div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" x-on:click.outside="bulkOpen = false"><div class="mb-5 flex justify-between"><div><h3 class="text-lg font-semibold">Bulk edit selected files</h3><div class="text-xs text-gray-500"><span x-text="selected.length"></span> selected</div></div><button type="button" x-on:click="bulkOpen = false"><i class="fa-solid fa-xmark"></i></button></div><x-ui.select label="Storage" :name="null" x-model="bulkStorage"><option value="">Leave unchanged</option><option value="media">Media</option><option value="archive">Archive</option></x-ui.select><x-ui.select label="Visibility" :name="null" x-model="bulkVisibility"><option value="">Leave unchanged</option><option value="public">Public</option><option value="protected">Protected</option><option value="private">Private</option></x-ui.select><div class="mt-5 flex justify-end gap-2"><x-ui.button type="button" color="outline" x-on:click="bulkOpen = false">Cancel</x-ui.button><x-ui.button type="button" x-bind:disabled="bulkSaving || (!bulkStorage && !bulkVisibility)" x-on:click="applyBulk()"><span x-show="!bulkSaving">Apply changes</span><span x-show="bulkSaving">Saving…</span></x-ui.button></div></div></div></template>
            </div>

            <div class="mt-6">{{ $attachedFiles->links() }}</div>
        @endif
    </x-container>
</x-layout>
