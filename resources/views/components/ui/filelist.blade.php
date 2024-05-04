@props(['type' => 'text', 'name' => '', 'label' => 'Files', 'info', 'value' => '', 'editor' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $value = old($name, $value);
    $editor = filter_var($editor, FILTER_VALIDATE_BOOLEAN);
@endphp

@if($value !== '' || $editor === true)
<div x-data class="{{ twMerge(['mb-4'], $attributes->get('class')) }}" x-show="$store.filelist-{{ $name }}.length > 0 || {{ $editor === true ? 'true' : 'false' }}">
    <h3 class="text-xl font-semibold">{{ $label }}</h3>
    <ul x-show="$store.filelist-{{ $name }}.length > 0" class="flex flex-col bg-white p-4 border border-gray-300 rounded-lg gap-4 mt-2 overflow-hidden">
        <template x-for="file in $store.filelist-{{ $name }}" :key="file.name">
            <li class="flex items-center min-h-10">
                <img class="w-10 mr-2" :src="file.thumbnail" />
                <div class="flex flex-grow flex-col">
                    <div>
                        <a class="link break-all" :href="file.url" x-text="file.title" target="_blank"></a>
                        <i x-show="file.password" x-cloak class="fa-solid fa-lock text-xs text-gray-400 -translate-x-0.5 -translate-y-1.5 scale-75"></i>
                    </div>
                    <span class="text-xs text-gray-400" x-text="file.file_type.replace(/\(.*?\)/g, '').trim() + ' (' + SM.bytesToString(file.size) + ')'"></span>
                </div>
                <a class="flex-shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" :href="file.url + '?download=1'"><i class="fa-solid fa-download"></i></a>
                @if($editor)
                    <i class="flex-shrink-0 text-gray-400 w-7 text-center fa-solid fa-trash hover:text-red-500 cursor-pointer" x-on:click.prevent="removeFile(file.name)"></i>
                @endif
            </li>
        </template>
    </ul>

    @if($editor)
        <button class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="SMMediaPicker.open(Alpine.store('filelist-{{ $name }}').map(file => file.name), {allow_multiple:true,allow_uploads:true}, (result)=>updateFiles(result))">Add File</button>
        <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}"/>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>

<script>
    function removeFile(fileName) {
        const fileList = Alpine.store('filelist-{{ $name }}').filter(f => f.name !== fileName);

        Alpine.store('filelist-{{ $name }}', fileList);

        const elem = document.getElementById('{{ $name }}');
        if(elem) {
            elem.value = fileList.map(f => f.name).join(',');
        }
    }

    function updateFiles(result) {
        const fileNames = [];
        Alpine.store('filelist-{{ $name }}', []);

        // Check if each item in result is a string or an object
        result.forEach(item => {
            if (typeof item === 'string') {
                // If item is a string, get file details
                SM.mediaDetails(item, (details) => {
                    Alpine.store('filelist-{{ $name }}', [...Alpine.store('filelist-{{ $name }}'), details]);
                });

                fileNames.push(item);
            } else {
                // If item is an object, directly place it in the store
                Alpine.store('filelist-{{ $name }}', [...Alpine.store('filelist-{{ $name }}'), item]);
                fileNames.push(item.name);
            }
        });

        const elem = document.getElementById('{{ $name }}');
        if(elem) {
            elem.value = fileNames.join(',');
        }
    }

    document.addEventListener('alpine:init', () => {
        const files = '{!! addslashes($value) !!}';
        let fileData = [];
        try {
            fileData = JSON.parse(files);
        } catch {

        }

        console.log('{{$name}}', files, fileData);
        updateFiles(fileData);
    });
</script>
@endif
