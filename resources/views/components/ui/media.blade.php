@props(['type' => 'text', 'name', 'label' => 'File', 'value' => null, 'info', 'mime_type' => 'image/*', 'allow_multiple' => 'false', 'allow_uploads' => 'false'])

@php
    $selectedValue = old($name, $value);
    if (is_array($selectedValue)) {
        $selectedValue = '';
    }
    $hasError = $errors->has($name);
    $allowUploads = filter_var($allow_uploads, FILTER_VALIDATE_BOOLEAN);
    $mediaUiUid = substr(md5($name), 0, 12);
    $dropzoneId = $name.'_dropzone_'.$mediaUiUid;
    $previewId = $name.'_preview_'.$mediaUiUid;
    $placeholderId = $name.'_placeholder_'.$mediaUiUid;
    $nameId = $name.'_name_'.$mediaUiUid;
    $sizeId = $name.'_size_'.$mediaUiUid;
    $clearButtonId = $name.'_clear_'.$mediaUiUid;
    $maxUploadSize = \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize());
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div
        id="{{ $dropzoneId }}"
        data-media-name="{{ $name }}"
        data-mime-type="{{ $mime_type }}"
        data-allow-uploads="{{ $allowUploads ? '1' : '0' }}"
        class="mt-1 rounded-2xl border-2 border-dashed {{ $hasError ? 'border-red-600' : 'border-gray-300' }} bg-white p-5 text-center transition {{ $allowUploads ? 'hover:border-primary-color hover:bg-sky-50' : '' }}"
    >
        <div class="flex flex-col items-center">
            <i id="{{ $placeholderId }}" class="fa-regular fa-image text-8xl text-gray-400"></i>
            <img class="hidden rounded-lg max-w-72 max-h-40 my-4" id="{{ $previewId }}" alt="preview" />
            <div id="{{ $nameId }}" class="text-sm text-gray-500"></div>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <button
                    x-data
                    type="button"
                    class="bg-white border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 px-5 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                    x-on:click.prevent="SMMediaPicker.open(document.getElementById(@js($name)).value, {require_mime_type:@js($mime_type), allow_multiple:{{ $allow_multiple }}, allow_uploads:{{ $allow_uploads }}}, (value)=>updateMedia(@js($name), value))"
                >
                    Select Image
                </button>
                <button
                    type="button"
                    id="{{ $clearButtonId }}"
                    class="hidden bg-white border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 px-5 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                    onclick="updateMedia(@js($name), '')"
                >
                    Clear Image
                </button>
            </div>
            <div class="text-xs text-gray-500 mt-2">Max upload size: {{ $maxUploadSize }}</div>
            @if(isset($info) && $info !== '')
                <div class="text-xs text-gray-500 mt-2">{{ $info }}</div>
            @endif
            @if ($hasError)
                <div class="text-xs text-red-600 mt-2">{{ $errors->first($name) }}</div>
            @endif
        </div>
    </div>

    <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $selectedValue }}">
</div>

<script>
    function revokeLocalMediaPreview(name) {
        const input = document.getElementById(name);
        const localPreviewUrl = input?.dataset?.localPreviewUrl || '';

        if (localPreviewUrl !== '') {
            URL.revokeObjectURL(localPreviewUrl);
            input.dataset.localPreviewUrl = '';
        }
    }

    function setMediaPreviewSource(name, src) {
        const input = document.getElementById(name);
        if (!input) {
            return;
        }

        const preview = document.getElementById(input.dataset.previewId);
        const placeholder = document.getElementById(input.dataset.placeholderId);
        if (preview && src) {
            preview.src = src;
            preview.classList.remove('hidden');
        }
        if (placeholder) {
            placeholder.classList.add('hidden!');
        }
    }

    function waitForMediaPreview(name, mediaName) {
        const input = document.getElementById(name);
        if (!input || !mediaName) {
            return;
        }

        SM.mediaDetails(mediaName, (details) => {
            if (!details) {
                return;
            }

            const sizeEl = document.getElementById(input.dataset.sizeId);
            if (details.status === 'processing' || details.status === 'queued') {
                if (sizeEl) {
                    sizeEl.textContent = `${SM.bytesToString(details.size)} • Generating preview...`;
                    sizeEl.classList.remove('text-red-600');
                    sizeEl.classList.add('text-gray-500');
                }

                window.setTimeout(() => waitForMediaPreview(name, mediaName), 3000);
                return;
            }

            revokeLocalMediaPreview(name);
            setMediaPreviewSource(name, details.thumbnail);
            if (sizeEl) {
                sizeEl.textContent = SM.bytesToString(details.size);
                sizeEl.classList.remove('text-red-600');
                sizeEl.classList.add('text-gray-500');
            }
        });
    }

    function showLocalMediaPreview(name, file) {
        const input = document.getElementById(name);
        if (!input || !file || !String(file.type || '').startsWith('image/')) {
            return;
        }

        const nameEl = document.getElementById(input.dataset.nameId);
        const sizeEl = document.getElementById(input.dataset.sizeId);
        revokeLocalMediaPreview(name);

        const localPreviewUrl = URL.createObjectURL(file);
        input.dataset.localPreviewUrl = localPreviewUrl;
        setMediaPreviewSource(name, localPreviewUrl);

        if (nameEl) {
            nameEl.textContent = file.name || '';
        }
        if (sizeEl) {
            sizeEl.textContent = `${SM.bytesToString(file.size)} • Uploading...`;
            sizeEl.classList.remove('text-red-600');
            sizeEl.classList.add('text-gray-500');
        }
    }

    function syncMediaClearButton(name) {
        const input = document.getElementById(name);
        if (!input) {
            return;
        }

        const clearButton = document.getElementById(input.dataset.clearButtonId);
        if (!clearButton) {
            return;
        }

        const hasValue = String(input.value || '').trim() !== '';
        clearButton.classList.toggle('hidden', !hasValue);
    }

    function resetMediaPreview(name) {
        const input = document.getElementById(name);
        if (!input) {
            return;
        }

        const preview = document.getElementById(input.dataset.previewId);
        const placeholder = document.getElementById(input.dataset.placeholderId);
        const nameEl = document.getElementById(input.dataset.nameId);
        const sizeEl = document.getElementById(input.dataset.sizeId);

        revokeLocalMediaPreview(name);

        if (preview) {
            preview.classList.add('hidden');
            preview.removeAttribute('src');
        }
        if (placeholder) {
            placeholder.classList.remove('hidden!');
        }
        if (nameEl) {
            nameEl.textContent = '';
        }
        if (sizeEl) {
            sizeEl.textContent = '';
            sizeEl.classList.remove('text-red-600');
            sizeEl.classList.add('text-gray-500');
        }

        syncMediaClearButton(name);
    }

    function updateMedia(name, value) {
        const input = document.getElementById(name);
        if (!input) {
            return;
        }

        input.value = value || '';

        if (!value) {
            resetMediaPreview(name);
            return;
        }

        SM.mediaDetails(value, (details) => {
            if (!details) {
                return;
            }

            const nameEl = document.getElementById(input.dataset.nameId);
            const sizeEl = document.getElementById(input.dataset.sizeId);
            const preview = document.getElementById(input.dataset.previewId);
            const placeholder = document.getElementById(input.dataset.placeholderId);
            const hasLocalPreview = String(input.dataset.localPreviewUrl || '') !== '';

            if (nameEl) {
                nameEl.innerText = details.name;
            }
            if (sizeEl) {
                sizeEl.innerText = details.status === 'processing' || details.status === 'queued'
                    ? `${SM.bytesToString(details.size)} • Generating preview...`
                    : SM.bytesToString(details.size);
                sizeEl.classList.remove('text-red-600');
                sizeEl.classList.add('text-gray-500');
            }
            if (preview && (!hasLocalPreview || (details.status !== 'processing' && details.status !== 'queued'))) {
                preview.src = details.thumbnail;
                preview.classList.remove('hidden');
            }
            if (placeholder) {
                placeholder.classList.add('hidden!');
            }

            if (details.status === 'processing' || details.status === 'queued') {
                waitForMediaPreview(name, value);
            } else {
                revokeLocalMediaPreview(name);
            }

            window.dispatchEvent(new CustomEvent('sm-media-updated', {
                detail: {
                    name,
                    value,
                    details,
                },
            }));

            syncMediaClearButton(name);
        });
    }

    function uploadMediaSelection(name, files) {
        const input = document.getElementById(name);
        if (!input || !files || files.length === 0 || !window.SM || typeof window.SM.upload !== 'function') {
            return;
        }

        const sizeEl = document.getElementById(input.dataset.sizeId);
        const mimeType = input.dataset.mimeType || '';
        const fileList = Array.from(files);

        if (mimeType === 'image/*' && fileList.some((file) => !String(file.type || '').startsWith('image/'))) {
            if (sizeEl) {
                sizeEl.textContent = 'Only image files can be uploaded here.';
                sizeEl.classList.remove('text-gray-500');
                sizeEl.classList.add('text-red-600');
            }
            return;
        }

        showLocalMediaPreview(name, fileList[0]);

        if (sizeEl) {
            sizeEl.textContent = 'Uploading...';
            sizeEl.classList.remove('text-red-600');
            sizeEl.classList.add('text-gray-500');
        }

        const titles = fileList.map((file) => {
            if (window.SM && typeof window.SM.toTitleCase === 'function') {
                return window.SM.toTitleCase(file.name);
            }

            return file.name;
        });

        SM.upload(fileList, (result) => {
            if (!result || result.success !== true) {
                return;
            }

            const uploaded = result.files && result.files[0] && result.files[0].data
                ? result.files[0].data
                : null;

            if (uploaded && typeof uploaded.name === 'string' && uploaded.name !== '') {
                updateMedia(name, uploaded.name);
            }
        }, titles, {
            showModal: false,
            onError: (message) => {
                if (!sizeEl) {
                    return;
                }

                sizeEl.textContent = message;
                sizeEl.classList.remove('text-gray-500');
                sizeEl.classList.add('text-red-600');
            },
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById(@js($name));
        if (!input) {
            return;
        }

        input.dataset.previewId = @js($previewId);
        input.dataset.placeholderId = @js($placeholderId);
        input.dataset.nameId = @js($nameId);
        input.dataset.sizeId = @js($sizeId);
        input.dataset.clearButtonId = @js($clearButtonId);
        input.dataset.mimeType = @js($mime_type);

        @if($allowUploads)
            const dropzone = document.getElementById(@js($dropzoneId));

            if (dropzone) {
                const preventDefaults = (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                };

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                    dropzone.addEventListener(eventName, preventDefaults);
                });

                ['dragenter', 'dragover'].forEach((eventName) => {
                    dropzone.addEventListener(eventName, () => {
                        dropzone.classList.add('ring-2', 'ring-primary-color', 'border-primary-color');
                    });
                });

                ['dragleave', 'drop'].forEach((eventName) => {
                    dropzone.addEventListener(eventName, () => {
                        dropzone.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color');
                    });
                });

                dropzone.addEventListener('drop', (event) => {
                    const droppedFiles = event.dataTransfer && event.dataTransfer.files
                        ? event.dataTransfer.files
                        : null;

                    uploadMediaSelection(@js($name), droppedFiles);
                });
            }
        @endif

        const currentValue = @js((string) $selectedValue);
        if (currentValue !== '') {
            updateMedia(@js($name), currentValue);
        } else {
            syncMediaClearButton(@js($name));
        }
    });
</script>
