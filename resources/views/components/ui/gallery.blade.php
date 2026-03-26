@props(['type' => 'text', 'name' => '', 'label' => 'Gallery', 'info', 'value' => '', 'editor' => false, 'downloads' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $value = old($name, $value);
    $editor = filter_var($editor, FILTER_VALIDATE_BOOLEAN);
@endphp

@if($value !== '' || $editor === true)
<div x-data="{ dragGalleryIndex: -1, dragGalleryDropIndex: -1, galleryDropActive: false }" class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    @if($editor === true)
        <h3 class="text-xl font-semibold">{{ $label }}</h3>
        <p class="text-xs italic" x-text="$store.gallery.length + ' Image' + ($store.gallery.length !== 1 ? 's' : '')"></p>
    @else
        <div
            class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-90 flex items-center justify-center z-50"
            x-cloak
            x-show="$store.modelIndex!=-1"
            x-on:click.prevent="$store.modelIndex=-1">
            <div class="cursor-pointer z-10 flex justify-center items-center fixed top-0 left-0 h-full w-12 hover:scale-125 transition text-gray-400 text-opacity-60 hover:text-opacity-100 text-3xl" x-on:click.prevent.stop="$store.modelIndex<=0?$store.modelIndex=$store.modelCount-1:$store.modelIndex--"><i class="fa-solid fa-circle-chevron-left"></i></div>
            <div class="cursor-pointer z-10 flex justify-center items-center fixed top-0 right-0 h-full w-12 hover:scale-125 transition text-gray-400 text-opacity-60 hover:text-opacity-100 text-3xl" x-on:click.prevent.stop="$store.modelIndex>=$store.modelCount-1?$store.modelIndex=0:$store.modelIndex++"><i class="fa-solid fa-circle-chevron-right"></i></div>
            <ul class="flex flex-wrap justify-center px-14 mt-2">
                <template x-for="(file,index) in $store.gallery" :key="file.name">
                    <li x-show="$store.modelIndex==index" class="flex items-center justify-center relative">
                        <div class="flex gap-4 px-4 py-1 cursor-pointer z-10 fixed top-4 right-14 transition bg-opacity-0 hover:bg-opacity-80 bg-black rounded items-center">
                            @if($downloads)
                            <a :href="file.url ? file.url + '?download' : galleryModalUrl(file) + '?download'" target="_blank" class="cursor-pointer transition text-white text-opacity-80 hover:text-opacity-100 text-2xl" x-on:click.stop><i class="fa-solid fa-download"></i></a>
                            @endif
                            <div class="cursor-pointer transition text-white text-opacity-80 hover:text-opacity-100 text-3xl" x-on:click.prevent.stop="$store.modelIndex=-1"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                        <img class="rounded max-h-[80vh] max-w-[90vw]" :src="galleryModalUrl(file)" :alt="file.title || file.name" x-on:error="handleGalleryImageError($event, file)" />
                    </li>
                </template>
            </ul>
        </div>
    @endif
    <ul class="flex flex-wrap justify-center gap-4 mt-2">
        @if($editor)
            <li
                class="flex items-center justify-center rounded border-2 border-dashed border-gray-300 bg-gray-50 transition hover:border-primary-color hover:bg-sky-50 cursor-pointer select-none"
                :class="$store.gallery.length === 0 ? 'w-full h-40' : 'w-44 h-28'"
                :class="galleryDropActive ? 'ring-2 ring-primary-color ring-offset-2' : ''"
                role="button"
                tabindex="0"
                x-on:click.prevent="SMMediaPicker.open((Array.isArray(Alpine.store('gallery')) ? Alpine.store('gallery') : []).map(file => file.name), {require_mime_type:'image/*',allow_multiple:true,allow_uploads:true}, (result)=>updateGallery(result))"
                x-on:keydown.enter.prevent="SMMediaPicker.open((Array.isArray(Alpine.store('gallery')) ? Alpine.store('gallery') : []).map(file => file.name), {require_mime_type:'image/*',allow_multiple:true,allow_uploads:true}, (result)=>updateGallery(result))"
                x-on:keydown.space.prevent="SMMediaPicker.open((Array.isArray(Alpine.store('gallery')) ? Alpine.store('gallery') : []).map(file => file.name), {require_mime_type:'image/*',allow_multiple:true,allow_uploads:true}, (result)=>updateGallery(result))"
                x-on:dragenter.prevent="galleryDropActive = true"
                x-on:dragover.prevent="galleryDropActive = true"
                x-on:dragleave.prevent="galleryDropActive = false"
                x-on:drop.prevent="galleryDropActive = false; uploadGalleryFiles($event.dataTransfer && $event.dataTransfer.files ? $event.dataTransfer.files : null)"
            >
                <div class="flex flex-col items-center gap-2 px-3 py-2 text-center">
                    <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400"></i>
                    <div
                            :class="$store.gallery.length === 0 ? 'block' : 'hidden'"
                            class="text-sm font-semibold text-gray-900">Drop images here to upload</div>
                    <button
                        type="button"
                        class="mt-1 bg-white border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 px-5 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                        x-on:click.prevent="SMMediaPicker.open((Array.isArray(Alpine.store('gallery')) ? Alpine.store('gallery') : []).map(file => file.name), {require_mime_type:'image/*',allow_multiple:true,allow_uploads:true}, (result)=>updateGallery(result))"
                    >
                        Select Images
                    </button>
                </div>
            </li>
        @endif
        <template x-for="(file,index) in $store.gallery" :key="file.name">
            <li
                class="flex items-center justify-center w-44 h-28 relative {{ !$editor ? 'hover:scale-105 transition-transform' : '' }}"
                :class="dragGalleryDropIndex === index ? 'ring-2 ring-primary-color ring-offset-2' : ''"
                draggable="{{ $editor ? 'true' : 'false' }}"
                x-on:click.prevent="if (dragGalleryIndex === -1) { $store.modelIndex=index; }"
                x-on:dragstart="{{ $editor ? 'dragGalleryIndex=index; dragGalleryDropIndex=index; $event.dataTransfer.effectAllowed=\'move\'; $event.dataTransfer.setData(\'text/plain\', file.name);' : '' }}"
                x-on:dragenter.prevent="{{ $editor ? 'dragGalleryDropIndex=index;' : '' }}"
                x-on:dragover.prevent="{{ $editor ? 'dragGalleryDropIndex=index;' : '' }}"
                x-on:drop.prevent="{{ $editor ? 'reorderGalleryItem(dragGalleryIndex, index); dragGalleryIndex=-1; dragGalleryDropIndex=-1;' : '' }}"
                x-on:dragend="{{ $editor ? 'dragGalleryIndex=-1; dragGalleryDropIndex=-1;' : '' }}"
            >
                <img class="rounded max-w-44 max-h-28 object-contain" :src="galleryThumbnailUrl(file)" :alt="file.title || file.name" x-on:error="handleGalleryImageError($event, file)" />
                @if($editor)
                    <div class="opacity-0 hover:opacity-100 absolute rounded flex items-center justify-center top-0 left-0 h-full w-full bg-opacity-75 bg-red-500 text-white cursor-pointer text-lg transition-opacity" x-on:click.prevent="removeGalleryItem(file.name)">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                @endif
            </li>
        </template>
    </ul>

    @if($editor)
        <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}"/>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>

<script>
    function galleryStore() {
        if (!Array.isArray(Alpine.store('gallery'))) {
            Alpine.store('gallery', []);
        }

        return Alpine.store('gallery');
    }

    function syncGalleryValue() {
        const elem = document.getElementById('{{ $name }}');
        if (elem) {
            elem.value = galleryStore().map((file) => file.name).join(',');
        }
    }

    function galleryFallbackThumbnail() {
        return @js(asset('/thumbnails/unknown.webp'));
    }

    function galleryThumbnailUrl(file) {
        if (!file || typeof file !== 'object') {
            return galleryFallbackThumbnail();
        }

        const thumbnail = String(file.thumbnail || '').trim();
        const url = String(file.url || '').trim();
        const status = String(file.status || '').trim();

        if (thumbnail !== '') {
            return thumbnail;
        }

        if (status === 'ready' && url !== '') {
            return `${url}?sm`;
        }

        return galleryFallbackThumbnail();
    }

    function galleryModalUrl(file) {
        if (!file || typeof file !== 'object') {
            return galleryFallbackThumbnail();
        }

        const thumbnail = String(file.thumbnail || '').trim();
        const url = String(file.url || '').trim();
        const status = String(file.status || '').trim();

        if (status === 'ready' && url !== '') {
            return `${url}?scaled`;
        }

        if (thumbnail !== '') {
            return thumbnail;
        }

        return galleryFallbackThumbnail();
    }

    function handleGalleryImageError(event, file) {
        if (event?.target instanceof HTMLImageElement) {
            event.target.src = galleryFallbackThumbnail();
        }
    }

    function reorderGalleryItem(fromIndex, toIndex) {
        const current = galleryStore().slice();
        const start = Number(fromIndex);
        const end = Number(toIndex);

        if (!Number.isInteger(start) || !Number.isInteger(end) || start === end || start < 0 || end < 0 || start >= current.length || end >= current.length) {
            return;
        }

        const [moved] = current.splice(start, 1);
        const targetIndex = start < end ? end - 1 : end;
        current.splice(targetIndex, 0, moved);

        Alpine.store('gallery', current);
        Alpine.store('modelCount', current.length);
        syncGalleryValue();
    }

    function removeGalleryItem(fileName) {
        const fileList = galleryStore().filter(f => f.name !== fileName);
        Alpine.store('gallery', fileList);
        Alpine.store('modelCount', fileList.length);
        syncGalleryValue();
    }

    function loadGalleryItems(result, append = false) {
        const incoming = Array.isArray(result)
            ? result
            : String(result || '').split(',');

        const names = incoming
            .map((item) => String(item || '').trim())
            .filter((item) => item.length > 0);

        const existing = append ? galleryStore().slice() : [];
        const existingNames = new Set(existing.map((item) => item.name));
        const uniqueNames = [];

        names.forEach((name) => {
            if (!append || !existingNames.has(name)) {
                uniqueNames.push(name);
                existingNames.add(name);
            }
        });

        if (uniqueNames.length === 0) {
            Alpine.store('gallery', existing);
            Alpine.store('modelCount', existing.length);
            syncGalleryValue();
            return;
        }

        const promises = uniqueNames.map((fileName) => {
            return new Promise((resolve) => {
                SM.mediaDetails(fileName, (details) => {
                    if (!details) {
                        resolve(null);
                        return;
                    }

                    details.extension = fileName.split('.').pop();
                    resolve(details);
                });
            });
        });

        Promise.all(promises).then((allDetails) => {
            const nextItems = existing.slice();
            allDetails.filter(Boolean).forEach((details) => {
                nextItems.push(details);
            });

            Alpine.store('gallery', nextItems);
            Alpine.store('modelCount', nextItems.length);
            syncGalleryValue();

            nextItems.forEach((item) => {
                if (item?.status === 'processing' || item?.status === 'queued') {
                    refreshGalleryItem(item.name);
                }
            });
        });
    }

    function updateGallery(result) {
        loadGalleryItems(result, false);
    }

    function appendGalleryItems(result) {
        loadGalleryItems(result, true);
    }

    function uploadGalleryFiles(files) {
        const fileList = Array.from(files || []);
        if (fileList.length === 0 || !window.SM || typeof window.SM.upload !== 'function') {
            return;
        }

        const validFiles = fileList.filter((file) => String(file.type || '').startsWith('image/'));
        if (validFiles.length === 0) {
            window.SM?.notice?.('Upload skipped', 'Only image files can be uploaded here.', 'warning');
            return;
        }

        const titles = validFiles.map((file) => window.SM && typeof window.SM.toTitleCase === 'function'
            ? window.SM.toTitleCase(file.name)
            : file.name);

        SM.upload(validFiles, (response) => {
            if (!response || response.success !== true || !Array.isArray(response.files)) {
                return;
            }

            const uploadedNames = response.files
                .map((file) => file?.data?.name || '')
                .filter((name) => String(name).trim() !== '');

            if (uploadedNames.length === 0) {
                return;
            }

            appendGalleryItems(uploadedNames);
        }, titles, {
            showModal: false,
            successDelayMs: 0,
            onProgress: ({ percent }) => {
                const dropzones = document.querySelectorAll('[data-gallery-dropzone="{{ $name }}"]');
                dropzones.forEach((dropzone) => {
                    dropzone.classList.toggle('ring-2', percent < 100);
                    dropzone.classList.toggle('ring-primary-color', percent < 100);
                    dropzone.classList.toggle('border-primary-color', percent < 100);
                });
            },
            onError: (message) => {
                window.SM?.notice?.('Upload failed', message || 'Unable to upload gallery images.', 'danger');
            },
        });
    }

    function refreshGalleryItem(fileName) {
        if (typeof fileName !== 'string' || fileName.trim() === '') {
            return;
        }

        SM.mediaDetails(fileName, (details) => {
            if (!details) {
                window.setTimeout(() => refreshGalleryItem(fileName), 5000);
                return;
            }

            details.extension = fileName.split('.').pop();

            const nextItems = galleryStore().map((item) => {
                return item.name === fileName ? details : item;
            });

            Alpine.store('gallery', nextItems);
            Alpine.store('modelCount', nextItems.length);
            syncGalleryValue();

            if (details.status === 'processing' || details.status === 'queued') {
                window.setTimeout(() => refreshGalleryItem(fileName), 5000);
            }
        });
    }

    document.addEventListener('alpine:init', () => {
        if (!Array.isArray(Alpine.store('gallery'))) {
            Alpine.store('gallery', []);
        }

        Alpine.store('modelIndex', -1);
        Alpine.store('modelCount', 0);
        updateGallery('{{ $value }}'.split(',') || []);
    });

    document.addEventListener('keyup', (e) => {
        if(e.key === 'Escape') {
            Alpine.store('modelIndex', -1);
        } else if(e.key === 'ArrowLeft' && Alpine.store('modelIndex') !== -1) {
            Alpine.store('modelIndex', Alpine.store('modelIndex') <= 0 ? Alpine.store('modelCount') - 1 : Alpine.store('modelIndex') - 1);
        } else if(e.key === 'ArrowRight' && Alpine.store('modelIndex') !== -1) {
            Alpine.store('modelIndex', Alpine.store('modelIndex') >= Alpine.store('modelCount') - 1 ? 0 : Alpine.store('modelIndex') + 1);
        }
    });
</script>
@endif
