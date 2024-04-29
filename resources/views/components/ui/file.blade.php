@props(['type' => 'text', 'name', 'label' => 'File', 'info', 'value' => '', 'readonly' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div class="flex flex-col align-middle items-center">
        <i id="{{ $name }}_placeholder" class="fa-regular fa-image text-9xl text-gray-400"></i>
        <img class="hidden rounded-lg max-w-72 max-h-36 my-4" id="{{ $name }}_preview" alt="preview" />
        <div id="{{ $name }}_name" class="text-sm text-gray-500"></div>
        <div id="{{ $name }}_size" class="text-xs text-gray-500"></div>
        @if (!$readonly)
            <label for="{{ $name }}_file" class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition">Select File</label>
            <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
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
</div>

<script>
    function updateDetails(media) {
        document.getElementById('{{ $name }}').value = media.name;
        document.getElementById('{{ $name }}_name').innerText = media.name;
        document.getElementById('{{ $name }}_size').innerText = SM.bytesToString(media.size);

        if(!media.mime_type.startsWith('image/') && (!media.thumbnail || media.thumbnail.startsWith('data:'))) {
            const extension = media.name.split('.').pop();
            document.getElementById('{{ $name }}_preview').src = '/thumbnails/' + extension + '.webp';
        } else {
            document.getElementById('{{ $name }}_preview').src = media.thumbnail;
        }

        document.getElementById('{{ $name }}_preview').classList.remove('hidden');
        document.getElementById('{{ $name }}_placeholder').classList.add('hidden');
    }

    function uploadFile(event) {
        const file = event.target.files[0];

        if(file === undefined) {
            return;
        }

        SM.upload(event.target.files, (media) => {

            const updatePageInfo = (media, thumbnail) => {
                const file = {
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
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('{{ $name }}_file');
        if(fileInput) {
            fileInput.addEventListener('change', uploadFile);
        }

        if('{{ $value }}' !== '') {
            SM.mediaDetails('{{ $value }}', (media) => {
                updateDetails(media);
            });
        }
    });
</script>
