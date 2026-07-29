@props(['type' => 'text', 'name' => '', 'label' => 'Files', 'info', 'value' => '', 'editor' => false, 'public_usable_only' => 'false', 'upload_fields' => null, 'defer_uploads' => 'false', 'noTags' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $value = old($name, $value);
    $editor = filter_var($editor, FILTER_VALIDATE_BOOLEAN);
    $publicUsableOnly = filter_var($public_usable_only, FILTER_VALIDATE_BOOLEAN);
    $deferUploads = filter_var($defer_uploads, FILTER_VALIDATE_BOOLEAN);
    $uploadFields = is_array($upload_fields) ? $upload_fields : [];

    $normalizeFileNames = function ($input) use (&$normalizeFileNames): array {
        if ($input instanceof \Illuminate\Support\Collection) {
            return $normalizeFileNames($input->all());
        }

        if (is_array($input)) {
            $names = [];
            foreach ($input as $item) {
                $names = array_merge($names, $normalizeFileNames($item));
            }

            return $names;
        }

        if (is_object($input)) {
            if (isset($input->name) && trim((string) $input->name) !== '') {
                return [trim((string) $input->name)];
            }

            return $normalizeFileNames((array) $input);
        }

        if (! is_string($input)) {
            return [];
        }

        $trimmed = html_entity_decode(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $normalizeFileNames($decoded);
        }

        return array_values(array_filter(\App\Helpers::stringToArray($trimmed), fn ($item) => trim((string) $item) !== ''));
    };

    $initialFileNames = array_values(array_unique($normalizeFileNames($value)));
    $hiddenValue = \App\Helpers::arrayToString($initialFileNames);

    if($name === '') {
        $name = \Illuminate\Support\Str::random(8);
    }
@endphp

@if($value !== '' || $editor === true)
<div
    x-data="{
        dragActive: false,
        deferredUploads: {{ $deferUploads ? 'true' : 'false' }},
        uploadFiles(fileList) {
            const files = Array.from(fileList || []).filter((file) => file instanceof File);
            if (files.length === 0) {
                return;
            }

            if (this.deferredUploads) {
                queuePendingFiles('{{ $name }}', files);
                this.dragActive = false;
                return;
            }

            SM.upload(files, (result) => {
                if (!result || result.success !== true || !Array.isArray(result.files)) {
                    return;
                }

                const uploaded = result.files
                    .map((item) => {
                        const data = item?.data || {};
                        const name = typeof data.name === 'string' ? data.name.trim() : '';

                        if (name === '') {
                            return null;
                        }

                        return {
                            name,
                            title: typeof item?.title === 'string' && item.title.trim() !== '' ? item.title.trim() : name,
                            mime_type: typeof data.mime_type === 'string' ? data.mime_type : '',
                            size: Number.isFinite(Number(data.size)) ? Number(data.size) : 0,
                            status: '',
                            url: '/media/' + encodeURIComponent(name),
                            thumbnail: '/thumbnails/unknown.webp',
                            file_type: 'File',
                            is_private: false,
                            password: null,
                            download_url: '/media/' + encodeURIComponent(name) + '?download=1',
                            can_delete: false,
                            delete_url: null,
                        };
                    })
                    .filter((item) => item !== null);

                appendFiles('{{ $name }}', uploaded);
                this.dragActive = false;
            }, [], {
                showModal: false,
                fields: @js($uploadFields),
                onStart: () => {
                    this.dragActive = true;
                },
                onSuccess: ({ files: uploadedFiles }) => {
                    if (window.SM && typeof window.SM.notice === 'function') {
                        const count = Array.isArray(uploadedFiles) ? uploadedFiles.length : files.length;
                        window.SM.notice('Upload complete', `${count} file${count === 1 ? '' : 's'} uploaded successfully.`, 'success', {
                            toast: true,
                            timer: 2500,
                        });
                    }
                },
            });
        },
    }"
    class="{{ twMerge(['mb-4'], $attributes->get('class')) }}"
    x-show="currentFileList('{{ $name }}').length > 0 || {{ $editor === true ? 'true' : 'false' }}"
>
    <h3 class="text-xl font-semibold">{{ $label }}</h3>
    @if($editor)
        <div
            class="mt-4 rounded-lg border-2 border-dashed px-4 py-6 text-center transition"
            :class="dragActive ? 'border-primary-color bg-primary-color-light/10' : 'border-gray-300 bg-gray-50'"
            x-on:dragenter.prevent="dragActive = true"
            x-on:dragover.prevent="dragActive = true"
            x-on:dragleave.prevent="dragActive = false"
            x-on:drop.prevent="dragActive = false; uploadFiles($event.dataTransfer?.files || [])"
        >
            <p class="text-sm font-medium text-gray-700">Drag and drop files here</p>
            <p class="mt-1 text-xs text-gray-500" x-text="deferredUploads ? 'Files stay pending until you press Save.' : 'Uploads add to the list immediately.'"></p>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                <label class="inline-flex cursor-pointer items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-1.5 text-sm font-semibold leading-6 text-gray-700 shadow-sm transition hover:bg-gray-100" for="filelist_upload_{{ $name }}">Select files</label>
                <button
                    x-show="deferredUploads"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-1.5 text-sm font-semibold leading-6 text-gray-700 shadow-sm transition hover:bg-gray-100"
                    x-on:click.prevent="SMMediaPicker.open(currentFileList('{{ $name }}').map(file => file.name), {allow_multiple:true,allow_uploads:false,public_usable_only:{{ $publicUsableOnly ? 'true' : 'false' }}}, (result)=>updateFiles('{{ $name }}', result))"
                >Browse existing files</button>
                <button
                    x-show="!deferredUploads"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-1.5 text-sm font-semibold leading-6 text-gray-700 shadow-sm transition hover:bg-gray-100"
                    x-on:click.prevent="SMMediaPicker.open(currentFileList('{{ $name }}').map(file => file.name), {allow_multiple:true,allow_uploads:true,public_usable_only:{{ $publicUsableOnly ? 'true' : 'false' }},upload_fields:@js($uploadFields)}, (result)=>updateFiles('{{ $name }}', result))"
                >Browse media</button>
            </div>
            <input class="hidden" id="filelist_upload_{{ $name }}" type="file" multiple x-on:change="uploadFiles($event.target.files); $event.target.value = '';">
            <ul x-show="currentStagedFileList('{{ $name }}').length > 0" class="mt-4 flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-3 text-left">
                <template x-for="file in currentStagedFileList('{{ $name }}')" :key="file.pending_id ? ('pending-' + file.pending_id) : file.name">
                    <li class="flex items-center min-h-10">
                        <template x-if="file.pending_id">
                            <div class="mr-2 flex h-10 w-10 shrink-0 items-center justify-center rounded bg-amber-50 text-gray-400">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                        </template>
                        <template x-if="!file.pending_id">
                            <img class="w-10 mr-2" :src="file.thumbnail" src="" alt="thumbnail" />
                        </template>
                        <div class="flex grow flex-col">
                            <div x-show="!file.pending_id">
                                <a class="link break-all" :href="file.url" x-text="file.title" target="_blank"></a>
                                <i x-show="file.password" x-cloak class="fa-solid fa-lock text-xs text-gray-400 -translate-x-0.5 -translate-y-1.5 scale-75"></i>
                            </div>
                            <div x-show="file.pending_id" class="break-all text-sm font-medium text-gray-900" x-text="file.title"></div>
                            <div class="mt-1 flex flex-wrap gap-1 text-[10px]" x-show="!file.pending_id">
                                <span
                                    class="rounded-full px-2 py-0.5"
                                    :class="file.visibility === 'public' ? 'bg-green-100 text-green-700' : (file.visibility === 'protected' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')"
                                    x-text="file.visibility === 'public' ? 'Public' : (file.visibility === 'protected' ? 'Protected' : 'Private')"
                                ></span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700" x-show="file.storage_disk" x-text="file.storage_disk"></span>
                            </div>
                            <span class="text-xs text-gray-400" x-show="file.pending_id" x-text="'Pending upload · ' + SM.bytesToString(file.size)"></span>
                            <span class="text-xs text-gray-400" x-show="!file.pending_id" x-text="file.file_type.replace(/\(.*?\)/g, '').trim() + ' (' + SM.bytesToString(file.size) + ')'"></span>
                        </div>
                        <a class="shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" x-show="!file.pending_id" :href="file.download_url || (file.url + '?download=1')"><i class="fa-solid fa-download"></i></a>
                        <a class="shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" x-show="!file.pending_id && file.edit_url" :href="file.edit_url" title="Edit media"><i class="fa-solid fa-pen-to-square"></i></a>
                        <i class="shrink-0 text-gray-400 w-7 text-center fa-solid fa-trash hover:text-red-500 cursor-pointer" x-on:click.prevent="file.pending_id ? removePendingFile('{{ $name }}', file.pending_id) : removeFile('{{ $name }}', file.name)"></i>
                    </li>
                </template>
            </ul>
        </div>
        <div x-show="currentPendingUploadState('{{ $name }}').uploading" class="mt-3 rounded-lg border border-sky-100 bg-sky-50 px-4 py-3">
            <div class="text-sm font-semibold text-sky-800">Uploading pending files</div>
            <div class="mt-1 text-xs text-sky-700" x-text="currentPendingUploadState('{{ $name }}').message || 'Preparing upload…'"></div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-sky-100">
                <div class="h-full rounded-full bg-sky-600 transition-all duration-200" :style="'width: ' + Math.max(0, Math.min(100, Number(currentPendingUploadState('{{ $name }}').progress || 0))) + '%'"></div>
            </div>
        </div>
        <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $hiddenValue }}" @if($deferUploads) data-filelist-deferred="1" data-filelist-name="{{ $name }}" data-filelist-upload-fields='@json($uploadFields)' @endif />
    @else
    <ul x-show="currentFileList('{{ $name }}').length > 0" class="flex flex-col bg-white p-4 border border-gray-300 rounded-lg gap-4 mt-4 overflow-hidden">
        <template x-for="file in currentFileList('{{ $name }}')" :key="file.name">
            <li class="flex items-center min-h-10">
                <img class="w-10 mr-2" :src="file.thumbnail" src="" alt="thumbnail" />
                <div class="flex grow flex-col">
                    <div>
                        <a class="link break-all" :href="file.url" x-text="file.title" target="_blank"></a>
                        <i x-show="file.password" x-cloak class="fa-solid fa-lock text-xs text-gray-400 -translate-x-0.5 -translate-y-1.5 scale-75"></i>
                    </div>
                    @if(!$noTags)
                    <div class="mt-1 flex flex-wrap gap-1 text-[10px]">
                        <span
                            class="rounded-full px-2 py-0.5"
                            :class="file.visibility === 'public' ? 'bg-green-100 text-green-700' : (file.visibility === 'protected' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')"
                            x-text="file.visibility === 'public' ? 'Public' : (file.visibility === 'protected' ? 'Protected' : 'Private')"
                        ></span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700" x-show="file.storage_disk" x-text="file.storage_disk"></span>
                    </div>
                    @endif
                    <span class="text-xs text-gray-400" x-text="file.file_type.replace(/\(.*?\)/g, '').trim() + ' (' + SM.bytesToString(file.size) + ')'"></span>
                </div>
                <a class="shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" :href="file.download_url || (file.url + '?download=1')"><i class="fa-solid fa-download"></i></a>
                <a class="shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" x-show="file.edit_url" :href="file.edit_url" title="Edit media"><i class="fa-solid fa-pen-to-square"></i></a>
                @if($editor)
                    <i class="shrink-0 text-gray-400 w-7 text-center fa-solid fa-trash hover:text-red-500 cursor-pointer" x-on:click.prevent="removeFile('{{ $name }}', file.name)"></i>
                @endif
            </li>
        </template>
    </ul>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>

@pushonce('scripts')
<script>
    function decodeFileListString(value) {
        if (typeof value !== 'string') {
            return '';
        }

        const textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value.trim();
    }

    function sanitizeFileListEntry(value) {
        if (!value || typeof value !== 'object') {
            return null;
        }

        if (typeof value.name !== 'string' || value.name.trim() === '') {
            return null;
        }

        return value;
    }

    function createFallbackFileListEntry(name) {
        if (typeof name !== 'string' || name.trim() === '') {
            return null;
        }

        const trimmedName = name.trim();

        return {
            name: trimmedName,
            title: trimmedName,
            mime_type: '',
            size: 0,
            status: '',
            url: '/media/' + encodeURIComponent(trimmedName),
            thumbnail: '/thumbnails/unknown.webp',
            file_type: 'File',
            visibility: 'private',
            storage_disk: null,
            is_private: false,
            password: null,
            download_url: '/media/' + encodeURIComponent(trimmedName) + '?download=1',
            can_delete: false,
            delete_url: null,
            edit_url: null,
        };
    }

    function currentFileList(name) {
        const storeName = 'filelist-' + name;
        const current = Alpine.store(storeName);
        const normalized = Array.isArray(current)
            ? current.map(sanitizeFileListEntry).filter(Boolean)
            : [];

        if (!Array.isArray(current) || normalized.length !== current.length) {
            Alpine.store(storeName, normalized);
        }

        return normalized;
    }

    function currentPendingFileList(name) {
        const storeName = 'filelist-pending-' + name;
        const current = Alpine.store(storeName);
        const normalized = Array.isArray(current) ? current.filter((item) => item && typeof item === 'object' && item.pending_id) : [];

        if (!Array.isArray(current) || normalized.length !== current.length) {
            Alpine.store(storeName, normalized);
        }

        return normalized;
    }

    function currentStagedFileList(name) {
        return [
            ...currentPendingFileList(name),
            ...currentFileList(name),
        ];
    }

    function currentPendingUploadState(name) {
        const storeName = 'filelist-pending-state-' + name;
        const current = Alpine.store(storeName);
        if (!current || typeof current !== 'object') {
            const initial = { uploading: false, progress: 0, message: '' };
            Alpine.store(storeName, initial);
            return initial;
        }

        return current;
    }

    function normalizeFileListData(value) {
        if (Array.isArray(value)) {
            return value.flatMap(item => normalizeFileListData(item));
        }

        if (value && typeof value === 'object') {
            if (typeof value.name === 'string' && value.name.trim() !== '') {
                return [value];
            }

            return [];
        }

        if (typeof value !== 'string') {
            return [];
        }

        const trimmed = decodeFileListString(value);
        if (trimmed === '') {
            return [];
        }

        try {
            return normalizeFileListData(JSON.parse(trimmed));
        } catch (_error) {
            return trimmed.split(',').map(item => item.trim()).filter(Boolean);
        }
    }

    function removeFile(name, fileName) {
        const fileList = currentFileList(name).filter(f => f.name !== fileName);

        Alpine.store('filelist-' + name, fileList);

        const elem = document.getElementById(name);
        if(elem) {
            elem.value = fileList.map(f => f.name).join(',');
        }
    }

    function removePendingFile(name, pendingId) {
        const pendingList = currentPendingFileList(name).filter((file) => file.pending_id !== pendingId);
        Alpine.store('filelist-pending-' + name, pendingList);
    }

    function queuePendingFiles(name, files) {
        const existing = currentPendingFileList(name);
        const next = [...existing];

        files.forEach((file) => {
            const pendingId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            next.push({
                pending_id: pendingId,
                file,
                name: '',
                title: file.name,
                size: file.size,
            });
        });

        Alpine.store('filelist-pending-' + name, next);
    }

    function updateFiles(name, result) {
        result = normalizeFileListData(result);
        const fileNames = [];
        Alpine.store('filelist-' + name, []);

        // Check if each item in result is a string or an object
        result.forEach(item => {
            if (typeof item === 'string') {
                // If item is a string, get file details
                const fallbackDetails = createFallbackFileListEntry(item);
                if (fallbackDetails) {
                    Alpine.store('filelist-' + name, [...currentFileList(name), fallbackDetails]);
                }

                SM.mediaDetails(item, (details) => {
                    const safeDetails = sanitizeFileListEntry(details);
                    if (!safeDetails) {
                        return;
                    }

                    const nextList = currentFileList(name)
                        .filter(file => file.name !== safeDetails.name)
                        .concat([safeDetails]);

                    Alpine.store('filelist-' + name, nextList);
                });

                fileNames.push(item);
            } else {
                // If item is an object, directly place it in the store
                const safeItem = sanitizeFileListEntry(item);
                if (!safeItem) {
                    return;
                }

                Alpine.store('filelist-' + name, [...currentFileList(name), safeItem]);
                fileNames.push(safeItem.name);
            }
        });

        const elem = document.getElementById(name);
        if(elem) {
            elem.value = fileNames.join(',');
        }
    }

    function appendFiles(name, result) {
        const current = currentFileList(name);
        const currentNames = current.map((file) => file.name);
        const normalized = normalizeFileListData(result);
        const additions = normalized.filter((item) => {
            const nameValue = typeof item === 'string' ? item.trim() : String(item?.name || '').trim();
            return nameValue !== '' && !currentNames.includes(nameValue);
        });

        if (additions.length === 0) {
            return;
        }

        updateFiles(name, [...current, ...additions]);
    }

    async function uploadPendingFilesForInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const name = String(input.dataset.filelistName || '').trim();
        if (name === '') {
            return;
        }

        const pending = currentPendingFileList(name);
        if (pending.length === 0) {
            return;
        }

        let uploadFields = {};
        try {
            uploadFields = JSON.parse(String(input.dataset.filelistUploadFields || '{}'));
        } catch (_error) {
            uploadFields = {};
        }

        const uploadState = currentPendingUploadState(name);
        uploadState.uploading = true;
        uploadState.progress = 0;
        uploadState.message = 'Preparing upload…';
        Alpine.store('filelist-pending-state-' + name, uploadState);

        await new Promise((resolve, reject) => {
            SM.upload(pending.map((item) => item.file), (result) => {
                if (!result || result.success !== true || !Array.isArray(result.files)) {
                    reject(new Error('Upload failed'));
                    return;
                }

                const uploaded = result.files
                    .map((item) => item?.data?.name || '')
                    .filter((item) => typeof item === 'string' && item.trim() !== '');

                appendFiles(name, uploaded);
                Alpine.store('filelist-pending-' + name, []);
                resolve(result);
            }, pending.map((item) => item.title || ''), {
                showModal: false,
                fields: uploadFields,
                onProgress: ({ file, index, count, percent }) => {
                    uploadState.uploading = true;
                    uploadState.progress = percent;
                    uploadState.message = count > 1
                        ? `Uploading ${index + 1} of ${count}: ${file.name}`
                        : `Uploading ${file.name}`;
                    Alpine.store('filelist-pending-state-' + name, uploadState);
                },
                onError: (message) => {
                    uploadState.uploading = false;
                    uploadState.progress = 0;
                    uploadState.message = '';
                    Alpine.store('filelist-pending-state-' + name, uploadState);
                    reject(new Error(message || 'Upload failed'));
                },
                onSuccess: () => {
                    uploadState.uploading = false;
                    uploadState.progress = 100;
                    uploadState.message = '';
                    Alpine.store('filelist-pending-state-' + name, uploadState);
                },
            });
        });
    }

    async function uploadDeferredFileLists(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const deferredInputs = Array.from(form.querySelectorAll('input[data-filelist-deferred="1"]'));
        for (const input of deferredInputs) {
            await uploadPendingFilesForInput(input);
        }
    }

    window.uploadDeferredFileLists = uploadDeferredFileLists;
</script>
@endpushonce
@push('scripts')
<script>
(function initializeFileList_{{ \Illuminate\Support\Str::slug($name, '_') }}() {
    const initialValue = @js($value ?? []);
    let initialized = false;

    const boot = () => {
        if (initialized || !window.Alpine) {
            return;
        }

        initialized = true;
        updateFiles('{{ $name }}', initialValue);
    };

    if (window.Alpine) {
        boot();
        return;
    }

    document.addEventListener('alpine:init', boot, { once: true });
    document.addEventListener('alpine:initialized', boot, { once: true });
})();
</script>
@endpush
@endif
