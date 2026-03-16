@props(['type' => 'text', 'name' => '', 'label' => 'Files', 'info', 'value' => '', 'editor' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $value = old($name, $value);
    $editor = filter_var($editor, FILTER_VALIDATE_BOOLEAN);

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
<div x-data class="{{ twMerge(['mb-4'], $attributes->get('class')) }}" x-show="currentFileList('{{ $name }}').length > 0 || {{ $editor === true ? 'true' : 'false' }}">
    <h3 class="text-xl font-semibold">{{ $label }}</h3>
    <ul x-show="currentFileList('{{ $name }}').length > 0" class="flex flex-col bg-white p-4 border border-gray-300 rounded-lg gap-4 mt-2 overflow-hidden">
        <template x-for="file in currentFileList('{{ $name }}')" :key="file.name">
            <li class="flex items-center min-h-10">
                <img class="w-10 mr-2" :src="file.thumbnail" src="" alt="thumbnail" />
                <div class="flex grow flex-col">
                    <div>
                        <a class="link break-all" :href="file.url" x-text="file.title" target="_blank"></a>
                        <i x-show="file.password" x-cloak class="fa-solid fa-lock text-xs text-gray-400 -translate-x-0.5 -translate-y-1.5 scale-75"></i>
                    </div>
                    <span class="text-xs text-gray-400" x-text="file.file_type.replace(/\(.*?\)/g, '').trim() + ' (' + SM.bytesToString(file.size) + ')'"></span>
                </div>
                <a class="shrink-0 cursor-pointer text-gray-400 w-7 text-center hover:text-primary-color" :href="file.url + '?download=1'"><i class="fa-solid fa-download"></i></a>
                @if($editor)
                    <i class="shrink-0 text-gray-400 w-7 text-center fa-solid fa-trash hover:text-red-500 cursor-pointer" x-on:click.prevent="removeFile('{{ $name }}', file.name)"></i>
                @endif
            </li>
        </template>
    </ul>

    @if($editor)
        <button class="mt-4 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="SMMediaPicker.open(currentFileList('{{ $name }}').map(file => file.name), {allow_multiple:true,allow_uploads:true}, (result)=>updateFiles('{{ $name }}', result))">Add File</button>
        <div class="text-xs text-gray-500 mb-4 mt-1">Max upload size: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize()) }}</div>
        <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $hiddenValue }}"/>
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
            is_private: false,
            password: null,
            can_delete: false,
            delete_url: null,
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
