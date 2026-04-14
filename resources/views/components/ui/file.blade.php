@props(['type' => 'text', 'name', 'label' => 'File', 'info', 'value' => '', 'readonly' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
    $dropzoneId = $name . '_dropzone';
    $stateId = $name . '_state';
    $stateTextId = $name . '_state_text';
    $stateProgressBarId = $name . '_state_progress_bar';
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div class="flex flex-col align-middle items-center">
        <i id="{{ $name }}_placeholder" class="fa-regular fa-image text-9xl text-gray-400"></i>
        <img class="hidden rounded-lg max-w-72 max-h-36 my-4" id="{{ $name }}_preview" alt="preview" src="" />
        <div id="{{ $name }}_name" class="text-sm text-gray-500"></div>
        <div id="{{ $name }}_size" class="text-xs text-gray-500"></div>
        @if (!$readonly)
        <label
            for="{{ $name }}_file"
            id="{{ $dropzoneId }}"
            class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
        >
            Select File
        </label>
        <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        <div class="mt-2 flex flex-col items-center gap-2">
            <div id="{{ $stateId }}" class="hidden text-xs text-primary-color">
                <span class="inline-flex items-center gap-2 rounded-full bg-primary-color/10 px-2.5 py-1 font-medium">
                    <i class="fa-solid fa-circle-notch animate-spin"></i>
                    <span id="{{ $stateTextId }}">Uploading...</span>
                </span>
                <div class="mt-2 h-2 w-56 overflow-hidden rounded bg-gray-200">
                    <div id="{{ $stateProgressBarId }}" class="h-2 rounded bg-primary-color transition-all duration-200" style="width:0%"></div>
                </div>
            </div>
        </div>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first($name) }}</div>
    @endif
    </div>
    @if (!$readonly)
        <input class="hidden" value="" type="file" name="{{ $name }}_file" id="{{ $name }}_file" />
    @endif
    <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" />
    <input class="hidden" type="text" id="{{ $name }}_original_filename" name="{{ $name }}_original_filename" value="" />
</div>

<script>
    function updateDetails(media) {
        document.getElementById('{{ $name }}').value = (typeof media.upload_token === 'string' && media.upload_token !== '') ? media.upload_token : media.name;
        const originalNameInput = document.getElementById('{{ $name }}_original_filename');
        if (originalNameInput) {
            originalNameInput.value = media.name || '';
        }
        document.getElementById('{{ $name }}_name').innerText = media.name;
        document.getElementById('{{ $name }}_size').innerText = SM.bytesToString(media.size);

        if(Object.keys(media).includes('status') && (media.status === 'processing' || media.status === 'queued')) {
            SM.updateThumbnail(document.getElementById('{{ $name }}_preview'), media.name);
        }

        if(!media.mime_type.startsWith('image/') && (!media.thumbnail || media.thumbnail.startsWith('data:'))) {
            const extension = media.name.split('.').pop();
            document.getElementById('{{ $name }}_preview').src = '/thumbnails/' + extension + '.webp';
        } else {
            document.getElementById('{{ $name }}_preview').src = media.thumbnail;
        }

        document.getElementById('{{ $name }}_preview').classList.remove('hidden');
        document.getElementById('{{ $name }}_placeholder').classList.add('hidden!');

        const element = document.getElementById('{{ $name }}_file');
        if(element) {
            element.value = '';
        }

    }

    function uploadFile(event) {
        const file = event.target.files[0];

        if(file === undefined) {
            return;
        }

        SM.upload(event.target.files, (media) => {

            const updatePageInfo = (media, thumbnail) => {
                const file = {
                    upload_token: media.files[0].data.upload_token || null,
                    name: media.files[0].data.file.name,
                    size: media.files[0].data.file.size,
                    mime_type: media.files[0].data.file.mime_type,
                    thumbnail: thumbnail
                }

                updateDetails(file);

                document.getElementById('{{ $name }}_name').classList.add('italic');
                const newFileInfo = document.createElement('i');
                newFileInfo.classList.add('fa-solid', 'fa-info-circle', 'text-gray-500', 'ml-2');
                newFileInfo.setAttribute('data-tooltip', 'The filename may change once saved to the server.');
                document.getElementById('{{ $name }}_name').appendChild(newFileInfo);

                if('{{ $onchange }}' !== '' && typeof window['{{ $onchange }}'] === 'function') {
                    window['{{ $onchange }}'](file, file.name);
                }
            }

            if(media.files[0].file.size < (1024 * 1024 * 4)) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    updatePageInfo(media, e.target.result);
                }

                reader.readAsDataURL(media.files[0].file);
            } else {
                updatePageInfo(media, '');
            }
        }, [], {
            showModal: false,
            successDelayMs: 0,
            onStart: () => {
                const stateElement = document.getElementById(@js($stateId));
                const progressBar = document.getElementById(@js($stateProgressBarId));
                const stateText = document.getElementById(@js($stateTextId));

                if (stateElement) {
                    stateElement.classList.remove('hidden');
                }
                if (progressBar) {
                    progressBar.style.width = '0%';
                }
                if (stateText) {
                    stateText.textContent = 'Uploading...';
                }
            },
            onProgress: ({ percent }) => {
                const progress = Math.max(0, Math.min(100, Number(percent) || 0));
                const progressBar = document.getElementById(@js($stateProgressBarId));
                const stateText = document.getElementById(@js($stateTextId));

                if (progressBar) {
                    progressBar.style.width = `${progress}%`;
                }
                if (stateText) {
                    stateText.textContent = `Uploading... ${Math.round(progress)}%`;
                }
            },
            onSuccess: () => {
                const stateElement = document.getElementById(@js($stateId));
                if (stateElement) {
                    stateElement.classList.add('hidden');
                }
            },
            onError: (message) => {
                const stateElement = document.getElementById(@js($stateId));
                const stateText = document.getElementById(@js($stateTextId));

                if (stateElement) {
                    stateElement.classList.remove('hidden');
                }
                if (stateText) {
                    stateText.textContent = message || 'Upload failed';
                }
            },
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('{{ $name }}_file');
        const dropzone = document.getElementById(@js($dropzoneId));
        if(fileInput) {
            fileInput.addEventListener('change', uploadFile);
        }

        if('{{ $value }}' !== '') {
            SM.mediaDetails('{{ $value }}', (media) => {
                updateDetails(media);
            });
        }

        if (dropzone && fileInput && !fileInput.disabled && !fileInput.readOnly) {
            const preventDefaults = (event) => {
                event.preventDefault();
                event.stopPropagation();
            };

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, preventDefaults);
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, () => {
                    dropzone.classList.add('border-primary-color', 'bg-sky-50');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, () => {
                    dropzone.classList.remove('border-primary-color', 'bg-sky-50');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                const droppedFile = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]
                    ? event.dataTransfer.files[0]
                    : null;

                if (!droppedFile) {
                    return;
                }

                const transfer = new DataTransfer();
                transfer.items.add(droppedFile);
                fileInput.files = transfer.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    });
</script>
