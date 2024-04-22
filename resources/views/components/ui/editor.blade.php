@props(['name', 'value' => '', 'info', 'label' => 'Content'])

@php
    $hasError = $errors->has($name);
    $value = old($name, $value);
@endphp

<div class="editor-container">
    <div class="text-sm pl-1">{{ $label }}</div>
    <div x-data="editor($store.{{$name}}_content)" class="editor mt-1">
        <template x-if="isLoaded()">
            <div class="menu">
                <button
                    @click.prevent="setParagraph()"
                    :class="{ 'is-active': isActive('paragraph', updatedAt) }">
                    P
                </button>
                <button
                    @click.prevent="toggleHeading({ level: 1 })"
                    :class="{ 'is-active': isActive('heading', { level: 1 }, updatedAt) }">
                    H1
                </button>
                <button
                    @click.prevent="toggleHeading({ level: 2 })"
                    :class="{ 'is-active': isActive('heading', { level: 2 }, updatedAt) }">
                    H2
                </button>
                <button
                    @click.prevent="toggleHeading({ level: 3 })"
                    :class="{ 'is-active': isActive('heading', { level: 3 }, updatedAt) }">
                    H3
                </button>
                <button
                    @click.prevent="setSmall()"
                    :class="{ 'is-active': isActive('small', updatedAt) }">
                    SM
                </button>
                <button
                    @click.prevent="setExtraSmall()"
                    :class="{ 'is-active': isActive('extraSmall', updatedAt) }">
                    XS
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleBold()"
                    :class="{ 'is-active' : isActive('bold', updatedAt) }">
                    <i class="fa-solid fa-bold"></i>
                </button>
                <button
                    @click.prevent="toggleItalic()"
                    :class="{ 'is-active' : isActive('italic', updatedAt) }">
                    <i class="fa-solid fa-italic"></i>
                </button>
                <button
                    @click.prevent="toggleUnderline()"
                    :class="{ 'is-active' : isActive('underline', updatedAt) }">
                    <i class="fa-solid fa-underline"></i>
                </button>
                <button
                    @click.prevent="toggleStrike()"
                    :class="{ 'is-active' : isActive('strike', updatedAt) }">
                    <i class="fa-solid fa-strikethrough"></i>
                </button>
                <button
                    @click.prevent="toggleHighlight()"
                    :class="{ 'is-active' : isActive('highlight', updatedAt) }">
                    <i class="fa-solid fa-highlighter"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="setTextAlign('left')"
                    :class="{ 'is-active' : isActive({ textAlign: 'left' }, updatedAt) }">
                    <i class="fa-solid fa-align-left"></i>
                </button>
                <button
                    @click.prevent="setTextAlign('center')"
                    :class="{ 'is-active' : isActive({ textAlign: 'center' }, updatedAt) }">
                    <i class="fa-solid fa-align-center"></i>
                </button>
                <button
                    @click.prevent="setTextAlign('right')"
                    :class="{ 'is-active' : isActive({ textAlign: 'right' }, updatedAt) }">
                    <i class="fa-solid fa-align-right"></i>
                </button>
                <button
                    @click.prevent="setTextAlign('justify')"
                    :class="{ 'is-active' : isActive({ textAlign: 'justify' }, updatedAt) }">
                    <i class="fa-solid fa-align-justify"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleSubscript()"
                    :class="{ 'is-active' : isActive('subscript', updatedAt) }">
                    <i class="fa-solid fa-subscript"></i>
                </button>
                <button
                    @click.prevent="toggleSuperscript()"
                    :class="{ 'is-active' : isActive('superscript', updatedAt) }">
                    <i class="fa-solid fa-superscript"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleCode()"
                    :class="{ 'is-active' : isActive('code', updatedAt) }">
                    <i class="fa-solid fa-font"></i>
                </button>
                <button
                    @click.prevent="toggleCodeBlock()"
                    :class="{ 'is-active' : isActive('codeBlock', updatedAt) }">
                    <i class="fa-solid fa-code"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleBulletList()"
                    :class="{ 'is-active' : isActive('bulletList', updatedAt) }">
                    <i class="fa-solid fa-list-ul"></i>
                </button>
                <button
                    @click.prevent="toggleOrderedList()"
                    :class="{ 'is-active' : isActive('orderedList', updatedAt) }">
                    <i class="fa-solid fa-list-ol"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleLink()"
                    :class="{ 'is-active' : isActive('link', updatedAt) }">
                    <i class="fa-solid fa-link"></i>
                </button>
                <button
                    @click.prevent="clearLink()">
                    <i class="fa-solid fa-link-slash"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="clearNodes();unsetAllMarks()">
                    <i class="fa-solid fa-text-slash"></i>
                </button>
                <button
                    @click.prevent="toggleBlockquote()"
                    :class="{ 'is-active' : isActive('blockquote', updatedAt) }">
                    <i class="fa-solid fa-quote-right"></i>
                </button>
                <button
                    @click.prevent="setHorizontalRule()">
                    <div class="border-t-2 rounded-lg border-black"></div>
                </button>
                <button
                    @click.prevent="setHardBreak()">
                    <i class="fa-solid fa-paragraph"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleBox({ type: 'success' })"
                    :class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) }">
                    <i class="fa-solid fa-circle-check"></i>
                </button>
                <button
                    @click.prevent="toggleBox({ type: 'info' })"
                    :class="{ 'is-active': isActive('box', { type: 'info' }, updatedAt) }">
                    <i class="fa-solid fa-circle-info"></i>
                </button>
                <button
                    @click.prevent="toggleBox({ type: 'warning' })"
                    :class="{ 'is-active': isActive('box', { type: 'warning' }, updatedAt) }">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </button>
                <button
                    @click.prevent="toggleBox({ type: 'danger' })"
                    :class="{ 'is-active': isActive('box', { type: 'danger' }, updatedAt) }">
                    <i class="fa-solid fa-circle-xmark"></i>
                </button>
                <button
                    @click.prevent="toggleBox({ type: 'bug' })"
                    :class="{ 'is-active': isActive('box', { type: 'bug' }, updatedAt) }">
                    <i class="fa-solid fa-bug"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="undo()">
                    <i class="fa-solid fa-undo"></i>
                </button>
                <button
                    @click.prevent="redo()">
                    <i class="fa-solid fa-redo"></i>
                </button>
            </div>
        </template>
        <div x-ref="element" class="content"></div>
        <input class="hidden" type="text" name="{{ $name }}" x-model="content">
    </div>
@if(isset($info) && $info !== '')
    <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
@endif
@if ($hasError)
    <div class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first($name) }}</div>
@endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('{{$name}}_content', `{!! $value !!}`);
    });
</script>
