@props(['contentClass' => 'min-h-80', 'customLinks' => false, 'maxCharacters' => null, 'label' => 'Content'])

<div
    x-data="miniEditor({ customLinks: @js(filter_var($customLinks, FILTER_VALIDATE_BOOLEAN)), maxCharacters: @js($maxCharacters), label: @js($label) })"
    x-modelable="content"
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-300 bg-white']) }}
>
    <div data-mini-editor-toolbar role="toolbar" aria-label="{{ $label }} formatting" x-init="$nextTick(() => SM.initToolbar($el))" class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('bold')" x-bind:class="isActive('bold') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleBold()" title="Bold" aria-label="Bold"><i class="fa-solid fa-bold" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('italic')" x-bind:class="isActive('italic') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleItalic()" title="Italic" aria-label="Italic"><i class="fa-solid fa-italic" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('underline')" x-bind:class="isActive('underline') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleUnderline()" title="Underline" aria-label="Underline"><i class="fa-solid fa-underline" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('highlight')" x-bind:class="isActive('highlight') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleHighlight()" title="Highlight" aria-label="Highlight"><i class="fa-solid fa-highlighter" aria-hidden="true"></i></x-ui.button>
        <span class="mx-1 h-5 border-l border-gray-300"></span>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('bulletList')" x-bind:class="isActive('bulletList') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleBulletList()" title="Bulleted list" aria-label="Bulleted list"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('orderedList')" x-bind:class="isActive('orderedList') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleOrderedList()" title="Numbered list" aria-label="Numbered list"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:aria-pressed="isActive('link')" x-bind:class="isActive('link') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleLink()" title="Link" aria-label="Link"><i class="fa-solid fa-link" aria-hidden="true"></i></x-ui.button>
        <span class="mx-1 h-5 border-l border-gray-300"></span>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-on:click="undo()" title="Undo" aria-label="Undo"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-on:click="redo()" title="Redo" aria-label="Redo"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></x-ui.button>
        @isset($toolbarActions)
            <span class="mx-1 h-5 border-l border-gray-300"></span>
            {{ $toolbarActions }}
        @endisset
    </div>
    <div x-ref="element" class="{{ $contentClass }}"></div>
    @if($maxCharacters)
        <div class="flex justify-end border-t border-gray-100 px-3 py-1.5 text-xs">
            <span x-bind:class="characterCount > maxCharacters ? 'font-medium text-red-600' : 'text-slate-500'" x-text="characterCount.toLocaleString() + ' / ' + maxCharacters.toLocaleString() + ' characters'"></span>
        </div>
    @endif
</div>

@pushOnce('head', 'mini-editor-assets')
    @vite('resources/js/editor/MiniEditor.js')
@endPushOnce
