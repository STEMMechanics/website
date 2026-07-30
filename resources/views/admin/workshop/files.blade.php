@php
    $workshopTabs = [
        [
            'title' => 'Details',
            'route' => route('admin.workshop.edit', $workshop),
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
        <div class="mb-4 rounded-b-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="text-lg font-semibold text-gray-900">{{ $workshop->title }}</div>
            <div class="mt-2 grid gap-1 text-sm text-gray-700">
                <div><span class="font-semibold">Date:</span> {{ $dateLabel }}</div>
                <div><span class="font-semibold">Location:</span> {{ $locationLabel }}</div>
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
                        'file_type' => (string) ($file->file_type ?? 'File'),
                        'notes' => (string) ($file->consent_notes ?? ''),
                    ])->values()->all()),
                    workshopFilesFallbackThumbnail: @js(asset('/thumbnails/unknown.webp')),
                    nextWorkshopFileId: 1,
                    workshopFilesUploadProgress: 0,
                    workshopFilesUploadMessage: '',
                    workshopFilesUploading: false,
                    init() {
                        this.syncWorkshopPendingInput();
                        this.syncWorkshopFilesPayload();
                    },
                    titleFromFileName(value) {
                        const fileName = String(value || '').trim();
                        if (fileName === '') {
                            return '';
                        }

                        const withoutExtension = fileName.replace(/\.[^.]+$/, '');
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
                    addWorkshopPendingFiles(fileList) {
                        const files = Array.from(fileList || []).filter((file) => file instanceof File);
                        if (files.length === 0) {
                            return;
                        }

                        const attachedNames = new Set(this.stagedWorkshopFiles.filter((item) => item.kind === 'existing').map((item) => item.name));

                        files.forEach((file) => {
                            const id = this.nextWorkshopFileId++;
                            const previewUrl = String(file.type || '').startsWith('image/') ? URL.createObjectURL(file) : '';
                            this.stagedWorkshopFiles.push({
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
                                file_type: this.fileTypeFromFile(file),
                                notes: '',
                            });
                        });

                        this.syncWorkshopPendingInput();
                        this.syncWorkshopFilesPayload();
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
                        this.syncWorkshopPendingInput();
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
                    attachExistingWorkshopFiles(result) {
                        const names = Array.isArray(result) ? result : [result];
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
                                file_type: 'File',
                                notes: '',
                            };

                            this.stagedWorkshopFiles.push(placeholder);
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
                                    file_type: String(details.file_type || 'File'),
                                    notes: String(details.consent_notes || ''),
                                };
                            });
                        });
                        this.syncWorkshopFilesPayload();
                    },
                    async handleSubmit() {
                        this.workshopFilesUploading = true;
                        this.workshopFilesUploadProgress = 15;
                        this.workshopFilesUploadMessage = 'Preparing files…';
                        this.syncWorkshopPendingInput();
                        this.syncWorkshopFilesPayload();
                        await new Promise((resolve) => requestAnimationFrame(() => resolve()));
                        this.workshopFilesUploadProgress = 65;
                        this.workshopFilesUploadMessage = 'Uploading files…';
                        this.$refs.workshopFilesForm.submit();
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
                >

                    <div class="flex flex-col gap-4">
                        <div x-show="pendingWorkshopFiles().length" x-cloak class="mt-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="text-sm font-semibold text-gray-700">Selected Files & Metadata</div>
                                <button type="button" class="text-xs font-medium text-gray-500 hover:text-danger-color" x-on:click.prevent="clearPendingFiles()">Clear files</button>
                            </div>
                            <input type="hidden" name="files" x-ref="workshopFilesExisting">
                            <input type="hidden" name="files_staged_order" x-ref="workshopFilesOrder">
                            <input type="hidden" name="pending_file_keys" x-ref="workshopFilesPendingKeys">
                            <div class="space-y-4">
                                <template x-for="(item, fileIndex) in pendingWorkshopFiles()" :key="workshopFileRowKey(item, fileIndex)">
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex flex-col">
                                            <div class="flex flex-col sm:flex-row gap-4">
                                                <div class="mx-auto shrink">
                                                    <div class="w-32">
                                                        <div class="block overflow-hidden">
                                                            <div class="flex h-24 w-32 items-center justify-center overflow-hidden">
                                                                <img :src="workshopFileThumbnail(item)" x-on:error="if ($el.src !== workshopFilesFallbackThumbnail) { $el.src = workshopFilesFallbackThumbnail }" alt="" class="h-24 w-32 rounded-lg bg-white object-contain p-1" />
                                                            </div>
                                                            <div class="space-y-0.5 px-2 py-1 text-[11px]">
                                                                <div class="text-gray-500" x-text="SM.bytesToString(item.size || 0)"></div>
                                                                <div class="text-gray-500" x-text="workshopFileTypeLabel(item)"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col grow md:flex-row md:gap-4">
                                                    <div class="flex-1">
                                                        <template x-if="item.kind === 'pending'">
                                                            <div>
                                                                <x-ui.input label="Title" :name="null" x-bind:name="`pending_files_meta[${item.pending_id}][title]`" x-model="item.title" />
                                                                <x-ui.select label="Visibility" :name="null" x-bind:name="`pending_files_meta[${item.pending_id}][visibility]`" x-model="item.visibility">
                                                                    <option value="public">Public</option>
                                                                    <option value="protected">Protected</option>
                                                                    <option value="private">Private</option>
                                                                </x-ui.select>
                                                            </div>
                                                        </template>
                                                        <template x-if="item.kind === 'existing'">
                                                            <div>
                                                                <x-ui.input label="Title" :name="null" x-bind:value="item.title || item.name" disabled="true" />
                                                                <x-ui.select label="Visibility" :name="null" x-bind:value="item.visibility" disabled="true" info="File visibility can be changed for existing files from the media editor.">
                                                                    <option value="public">Public</option>
                                                                    <option value="protected">Protected</option>
                                                                    <option value="private">Private</option>
                                                                </x-ui.select>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <div class="flex-1">
                                                        <template x-if="item.kind === 'pending'">
                                                            <x-ui.input label="File Notes" type="textarea" :name="null" x-bind:name="`pending_files_meta[${item.pending_id}][notes]`" x-model="item.notes" />
                                                        </template>
                                                        <template x-if="item.kind === 'existing'">
                                                            <x-ui.input label="File Notes" type="textarea" :name="null" x-bind:value="item.notes || ''" disabled="true" />
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <template x-if="item.kind === 'pending'">
                                                        <x-ui.badge color="sky" icon="fa-solid fa-cloud-arrow-up">To be uploaded</x-ui.badge>
                                                    </template>
                                                    <template x-if="item.kind === 'existing'">
                                                        <x-ui.badge color="gray" icon="fa-solid fa-photo-film">Existing media</x-ui.badge>
                                                    </template>
                                                </div>
                                                <div class="flex items-center gap-3 pt-1">
                                                    <a x-show="item.kind === 'existing' && item.edit_url" :href="item.edit_url" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-primary-color-dark" title="Open media editor">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                    <a x-show="item.kind === 'existing' && item.download_url" :href="item.download_url" class="text-primary-color hover:text-primary-color-dark" title="Download file">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                    <button type="button" class="text-red-600 hover:text-red-800" title="Delete row" x-on:click.prevent="removeWorkshopFileByKey(workshopFileRowKey(item))">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="workshopFilesUploading" x-cloak class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="font-medium">
                                    <i class="fa-solid fa-circle-notch animate-spin mr-2"></i>
                                    Uploading files
                                </div>
                            </div>
                            <div class="mb-2 text-xs text-sky-900" x-text="workshopFilesUploadMessage || ''"></div>
                            <div class="h-2 w-full overflow-hidden rounded bg-sky-100">
                                <div class="h-2 rounded bg-primary-color transition-all" x-bind:style="`width: ${workshopFilesUploadProgress}%`"></div>
                            </div>
                        </div>
                    </div>
                </x-ui.media-uploader>
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
            <div class="space-y-4">
                @foreach($attachedFiles as $file)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-col">
                            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                                <div class="mx-auto shrink">
                                    <div class="w-32">
                                        <a href="{{ $file->download_url ?? (($file->url ?? '/media/'.rawurlencode((string) $file->name)).'?download=1') }}" target="_blank" class="block overflow-hidden">
                                            <img src="{{ $file->thumbnail ?: asset('/thumbnails/unknown.webp') }}" onerror="this.onerror=null;this.src='{{ asset('/thumbnails/unknown.webp') }}';" alt="{{ $file->title }}" class="max-w-32 h-32 object-cover rounded-lg mx-auto">
                                        </a>
                                        <div class="space-y-0.5 px-2 py-1 text-[11px]">
                                            <div class="text-gray-500">{{ \App\Helpers::bytesToString((int) ($file->size ?? 0)) }}</div>
                                            <div class="text-gray-500">{{ $file->file_type }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col grow md:flex-row gap-4">
                                    <div class="flex-1">
                                        <div class="mb-4">
                                            <div class="mb-1 text-sm font-medium text-gray-700">Title</div>
                                            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                                                {{ trim((string) ($file->title ?? '')) !== '' ? (string) $file->title : (string) $file->name }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mb-1 text-sm font-medium text-gray-700">Visibility</div>
                                            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                                                {{ in_array((string) ($file->visibility ?? ''), ['private', 'protected', 'public'], true)
                                                    ? ucfirst((string) $file->visibility)
                                                    : 'Private' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div>
                                            <div class="mb-1 text-sm font-medium text-gray-700">Notes</div>
                                            <div class="min-h-24 whitespace-pre-wrap rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 text-left flex items-start justify-start">
                                                @if(trim((string) ($file->consent_notes ?? '')) !== '')
                                                    <span>{{ (string) $file->consent_notes }}</span>
                                                @else
                                                    <span class="italic text-gray-500">No file notes.</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end items-center">
                                <div class="flex items-center gap-3 pt-1">
                                    <a href="{{ route('admin.media.edit', $file) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-primary-color-dark" title="Open media editor">
                                        <i class="fa-solid fa-up-right-from-square"></i>
                                    </a>
                                    <a href="{{ $file->download_url ?? (($file->url ?? '/media/'.rawurlencode((string) $file->name)).'?download=1') }}" class="text-primary-color hover:text-primary-color-dark" title="Download file">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <button
                                        type="button"
                                        class="text-amber-600 hover:text-amber-800"
                                        title="Remove from this workshop only"
                                        x-data
                                        x-on:click.prevent="SM.confirmDelete(
                                            '{{ csrf_token() }}',
                                            'Remove file from workshop?',
                                            'This will remove the file from this workshop only. The media item will remain in the media library.',
                                            '{{ route('admin.workshop.files.destroy', [$workshop, $file]) }}',
                                            'Remove from workshop'
                                        )"
                                    >
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $attachedFiles->links() }}</div>
        @endif
    </x-container>
</x-layout>
