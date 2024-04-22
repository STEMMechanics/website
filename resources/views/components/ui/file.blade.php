@props(['type' => 'text', 'name', 'label' => 'File', 'info', 'value' => '', 'fileName' => '', 'fileSize' => '', 'fileType' => '', 'fileUrl' => '', 'readonly' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="{{ twMerge('mb-4', $attributes->get('class')) }}">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div class="flex flex-col align-middle items-center">
        <i id="{{ $name }}_placeholder" class="fa-regular fa-image text-9xl text-gray-400"></i>
        <img class="hidden rounded-lg max-w-72 max-h-36 my-4" id="{{ $name }}_preview" alt="preview" />
        <div id="{{ $name }}_name" class="text-sm text-gray-500">{{ $fileName }}</div>
        <div id="{{ $name }}_size" class="text-xs text-gray-500">{{ $fileSize != '' ? \App\Helpers::bytesToString($fileSize) : '' }}</div>
        @if (!$readonly)
            <label for="{{ $name }}" class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition">Select File</label>
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
        <input class="hidden" value="" type="file" name="{{ $name }}" id="{{ $name }}" onchange="updatePreview(event)" />
    @endif
{{--    <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}"></input>--}}
</div>

<script>
    function updateDetails(fileName, fileType, fileSize, fileUrl) {
        document.getElementById('{{ $name }}_name').innerText = fileName;

        document.getElementById('{{ $name }}_size').innerText = SM.bytesToString(fileSize);

        if(fileType.startsWith('image/')) {
            document.getElementById('{{ $name }}_preview').src = fileUrl;
        } else {
            const extension = fileName.split('.').pop();
            document.getElementById('{{ $name }}_preview').src = `/fileext/${extension}.webp`;
        }

        document.getElementById('{{ $name }}_preview').classList.remove('hidden');
        document.getElementById('{{ $name }}_placeholder').classList.add('hidden');
    }

    function updatePreview(event) {
        const file = event.target.files[0];

        {{--document.getElementById('{{ $name }}_input').value = '';--}}

        const reader = new FileReader();
        reader.onload = function(e) {
            updateDetails(file.name, file.type, file.size, e.target.result);

            document.getElementById('{{ $name }}_name').classList.add('italic');
            const newFileInfo = document.createElement('i');
            newFileInfo.classList.add('fa-solid', 'fa-info-circle', 'text-gray-500', 'ml-2');
            newFileInfo.setAttribute('data-tooltip', 'The filename may change from the actual file name if a file with the same name already exists.');
            document.getElementById('{{ $name }}_name').appendChild(newFileInfo);

            if('{{ $onchange }}' !== '' && typeof window['{{ $onchange }}'] === 'function') {
                window['{{ $onchange }}'](file, file.name);
            }
        }
        reader.readAsDataURL(file);
    }

    if('{{ $fileName }}' !== '' && '{{ $fileSize }}' !== '' && '{{ $fileType }}' !== '' && '{{ $fileUrl }}' !== '') {
        updateDetails('{{ $fileName }}', '{{ $fileType }}', '{{ $fileSize }}', '{{ $fileUrl }}');
    } else if('{{ $value }}' !== '') {
        SM.mediaDetails('{{ $value }}', (details) => {
            updateDetails(details.name, details.mime_type, details.size, details.url);
        });
    }
</script>
