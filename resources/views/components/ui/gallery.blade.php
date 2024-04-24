@props(['type' => 'text', 'name' => '', 'label' => 'Gallery', 'info', 'value' => '', 'editor' => false])

@php
    $hasError = $errors->has($name);
    $onchange = $attributes->get('onchange');
    $value = old($name, $value);
    $editor = filter_var($editor, FILTER_VALIDATE_BOOLEAN);
@endphp

@if($value !== '' || $editor === true)
<div x-data class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
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
                        <img class="rounded" :src="file.url + '?scaled'" />
                    </li>
                </template>
            </ul>
        </div>
    @endif
    <ul class="flex flex-wrap justify-center gap-4 mt-2">
        <template x-for="(file,index) in $store.gallery" :key="file.name">
            <li class="flex items-center justify-center w-44 h-28 relative {{ !$editor ? 'hover:scale-105 transition-transform' : '' }}" x-on:click.prevent="$store.modelIndex=index">
                <img class="rounded max-w-44 max-h-28" :src="file.url + '?sm'" />
                @if($editor)
                    <div class="opacity-0 hover:opacity-100 absolute rounded flex items-center justify-center top-0 left-0 h-full w-full bg-opacity-75 bg-red-500 text-white cursor-pointer text-lg transition-opacity" x-on:click.prevent="removeGalleryItem(file.name)">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                @endif
            </li>
        </template>
        @if($editor)
            <li class="flex items-center justify-center w-44 h-28 bg-gray-300 cursor-pointer rounded" x-on:click.prevent="SMMediaPicker.open(Alpine.store('gallery').map(file => file.name), {require_mime_type:'image/*',allow_multiple:true,allow_uploads:true}, (result)=>updateGallery(result))">
                <i class="fa-solid fa-circle-plus text-3xl text-gray-800"></i>
            </li>
        @endif
    </ul>

    @if($editor)
        <input class="hidden" type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}"/>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>

<script>
    function removeGalleryItem(fileName) {
        const fileList = Alpine.store('gallery').filter(f => f.name !== fileName);

        Alpine.store('gallery', fileList);

        const elem = document.getElementById('{{ $name }}');
        if(elem) {
            elem.value = fileList.map(f => f.name).join(',');
        }
    }

    function updateGallery(result) {
        Alpine.store('gallery', []);

        result = result.filter((item) => item.length > 0);

        for(const fileName of result) {
            SM.mediaDetails(fileName, (details) => {
                details.extension = fileName.split('.').pop();
                Alpine.store('gallery').push(details);
            });
        }

        Alpine.store('modelCount', result.length)

        const elem = document.getElementById('{{ $name }}');
        if(elem) {
            elem.value = result.join(',');
        }
    }

    document.addEventListener('alpine:init', () => {
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
