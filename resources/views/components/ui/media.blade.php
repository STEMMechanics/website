@props(['type' => 'text', 'name', 'label' => 'File', 'value' => null, 'info', 'mime_type' => 'image/*', 'allow_multiple' => 'false', 'allow_uploads' => 'false'])

@php
    $hasError = $errors->has($name);
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div class="flex flex-col align-middle items-center">
        <i id="{{ $name }}_placeholder" class="fa-regular fa-image text-9xl text-gray-400"></i>
        <img class="hidden rounded-lg max-w-72 max-h-36 my-4" id="{{ $name }}_preview" alt="preview" />
        <div id="{{ $name }}_name" class="text-sm text-gray-500"></div>
        <div id="{{ $name }}_size" class="text-xs text-gray-500"></div>
        <button x-data class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="SMMediaPicker.open(document.getElementById('{{$name}}').value, {require_mime_type:'{{$mime_type}}', allow_multiple:{{$allow_multiple}}, allow_uploads:{{$allow_uploads}}}, (value)=>updateMedia('{{$name}}', value))">Select File</button>
        <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first($name) }}</div>
    @endif
    </div>
    <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}"></input>
</div>

<script>
    function updateMedia(name, value) {
        document.getElementById(name).value = value;
        SM.mediaDetails(value, (details) => {
            document.getElementById(name + '_name').innerText = details.name;
            document.getElementById(name + '_size').innerText = SM.bytesToString(details.size);

            const imgElement = document.getElementById(name + '_preview');
            const placeholderElement = document.getElementById(name + '_placeholder');

            imgElement.src = details.thumbnail;

            imgElement.classList.remove('hidden');
            placeholderElement.classList.add('hidden!');

            document.getElementById(name).value = value;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if('{{ $value }}' !== '') {
            updateMedia('{{ $name }}', '{{ $value }}');
        }
    });
</script>
