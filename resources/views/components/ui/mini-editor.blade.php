@props(['contentClass' => 'min-h-80'])

<div
    x-data="miniEditor"
    x-modelable="content"
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-300 bg-white']) }}
>
    <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('bold') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleBold()" title="Bold"><i class="fa-solid fa-bold"></i></button>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('italic') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleItalic()" title="Italic"><i class="fa-solid fa-italic"></i></button>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('underline') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleUnderline()" title="Underline"><i class="fa-solid fa-underline"></i></button>
        <span class="mx-1 h-5 border-l border-gray-300"></span>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('bulletList') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleBulletList()" title="Bulleted list"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('orderedList') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleOrderedList()" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-bind:class="isActive('link') ? 'bg-gray-200 text-primary-color' : ''" x-on:click="toggleLink()" title="Link"><i class="fa-solid fa-link"></i></button>
        <span class="mx-1 h-5 border-l border-gray-300"></span>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-on:click="undo()" title="Undo"><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" class="rounded px-2 py-1 text-sm hover:bg-gray-200" x-on:click="redo()" title="Redo"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
    <div x-ref="element" class="{{ $contentClass }}"></div>
</div>
