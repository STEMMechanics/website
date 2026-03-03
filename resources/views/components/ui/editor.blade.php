@props(['name', 'value' => '', 'info', 'label' => 'Content', 'allowHeadings' => true])

@php
    $hasError = $errors->has($name);
    $value = old($name, $value);
@endphp

<div class="editor-container">
    @if(isset($label))
    <div class="text-sm pl-1">{{ $label }}</div>
    @endif
    <div
        x-data="editor($store.{{$name}}_content, @js(route('custom-page.link-options')))"
        x-on:sm-editor-set-content.window="if (($event.detail?.name || '') === '{{ $name }}') { setExternalContent($event.detail?.html || '', { focusEnd: !!$event.detail?.focusEnd }) }"
        class="{{ twMerge(['editor','mt-1'], $attributes->get('class')) }}">
        <template x-if="isLoaded()">
            <div
                class="menu"
                x-init="$nextTick(() => { $el.querySelectorAll('button').forEach((button) => { button.tabIndex = -1 }) })"
            >
                <button
                    @click.prevent="setParagraph()"
                    title="Paragraph"
                    aria-label="Paragraph"
                    :class="{ 'is-active': isActive('paragraph', updatedAt) }">
                    P
                </button>
                @if(filter_var($allowHeadings, FILTER_VALIDATE_BOOLEAN))
                    <button
                        @click.prevent="toggleHeading({ level: 1 })"
                        title="Heading 1"
                        aria-label="Heading 1"
                        :class="{ 'is-active': isActive('heading', { level: 1 }, updatedAt) }">
                        H1
                    </button>
                    <button
                        @click.prevent="toggleHeading({ level: 2 })"
                        title="Heading 2"
                        aria-label="Heading 2"
                        :class="{ 'is-active': isActive('heading', { level: 2 }, updatedAt) }">
                        H2
                    </button>
                    <button
                        @click.prevent="toggleHeading({ level: 3 })"
                        title="Heading 3"
                        aria-label="Heading 3"
                        :class="{ 'is-active': isActive('heading', { level: 3 }, updatedAt) }">
                        H3
                    </button>
                    <button
                        @click.prevent="toggleHeading({ level: 4 })"
                        title="Heading 4"
                        aria-label="Heading 4"
                        :class="{ 'is-active': isActive('heading', { level: 4 }, updatedAt) }">
                        H4
                    </button>
                @endif
                <button
                    @click.prevent="setSmall()"
                    title="Small text"
                    aria-label="Small text"
                    :class="{ 'is-active': isActive('small', updatedAt) }">
                    SM
                </button>
                <button
                    @click.prevent="setExtraSmall()"
                    title="Extra small text"
                    aria-label="Extra small text"
                    :class="{ 'is-active': isActive('extraSmall', updatedAt) }">
                    XS
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleBold()"
                    title="Bold"
                    aria-label="Bold"
                    :class="{ 'is-active' : isActive('bold', updatedAt) }">
                    <i class="fa-solid fa-bold"></i>
                </button>
                <button
                    @click.prevent="toggleItalic()"
                    title="Italic"
                    aria-label="Italic"
                    :class="{ 'is-active' : isActive('italic', updatedAt) }">
                    <i class="fa-solid fa-italic"></i>
                </button>
                <button
                    @click.prevent="toggleUnderline()"
                    title="Underline"
                    aria-label="Underline"
                    :class="{ 'is-active' : isActive('underline', updatedAt) }">
                    <i class="fa-solid fa-underline"></i>
                </button>
                <button
                    @click.prevent="toggleStrike()"
                    title="Strikethrough"
                    aria-label="Strikethrough"
                    :class="{ 'is-active' : isActive('strike', updatedAt) }">
                    <i class="fa-solid fa-strikethrough"></i>
                </button>
                <button
                    @click.prevent="toggleHighlight()"
                    title="Highlight"
                    aria-label="Highlight"
                    :class="{ 'is-active' : isActive('highlight', updatedAt) }">
                    <i class="fa-solid fa-highlighter"></i>
                </button>
                <button
                    @click.prevent="toggleSpoiler()"
                    title="Spoiler"
                    aria-label="Spoiler"
                    :class="{ 'is-active' : isActive('spoiler', updatedAt) }">
                    <i class="fa-solid fa-eye-slash"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <button
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Alignment"
                        aria-label="Alignment"
                        @click.prevent="open = !open"
                        @keydown.escape.window="open = false"
                        :class="{ 'is-active': isActive({ textAlign: 'left' }, updatedAt) || isActive({ textAlign: 'center' }, updatedAt) || isActive({ textAlign: 'right' }, updatedAt) || isActive({ textAlign: 'justify' }, updatedAt) }">
                        <i class="fa-solid fa-align-left"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <div class="editor-toolbar-dropdown__panel" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <button type="button" @click.prevent="setTextAlign('left'); open = false" :class="{ 'is-active' : isActive({ textAlign: 'left' }, updatedAt) }">
                            <i class="fa-solid fa-align-left"></i>
                            <span>Left</span>
                        </button>
                        <button type="button" @click.prevent="setTextAlign('center'); open = false" :class="{ 'is-active' : isActive({ textAlign: 'center' }, updatedAt) }">
                            <i class="fa-solid fa-align-center"></i>
                            <span>Center</span>
                        </button>
                        <button type="button" @click.prevent="setTextAlign('right'); open = false" :class="{ 'is-active' : isActive({ textAlign: 'right' }, updatedAt) }">
                            <i class="fa-solid fa-align-right"></i>
                            <span>Right</span>
                        </button>
                        <button type="button" @click.prevent="setTextAlign('justify'); open = false" :class="{ 'is-active' : isActive({ textAlign: 'justify' }, updatedAt) }">
                            <i class="fa-solid fa-align-justify"></i>
                            <span>Justify</span>
                        </button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <button
                        @click.prevent="setTextAlign('left')"
                        title="Align left"
                        aria-label="Align left"
                        :class="{ 'is-active' : isActive({ textAlign: 'left' }, updatedAt) }">
                        <i class="fa-solid fa-align-left"></i>
                    </button>
                    <button
                        @click.prevent="setTextAlign('center')"
                        title="Align center"
                        aria-label="Align center"
                        :class="{ 'is-active' : isActive({ textAlign: 'center' }, updatedAt) }">
                        <i class="fa-solid fa-align-center"></i>
                    </button>
                    <button
                        @click.prevent="setTextAlign('right')"
                        title="Align right"
                        aria-label="Align right"
                        :class="{ 'is-active' : isActive({ textAlign: 'right' }, updatedAt) }">
                        <i class="fa-solid fa-align-right"></i>
                    </button>
                    <button
                        @click.prevent="setTextAlign('justify')"
                        title="Justify"
                        aria-label="Justify"
                        :class="{ 'is-active' : isActive({ textAlign: 'justify' }, updatedAt) }">
                        <i class="fa-solid fa-align-justify"></i>
                    </button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleSubscript()"
                    title="Subscript"
                    aria-label="Subscript"
                    :class="{ 'is-active' : isActive('subscript', updatedAt) }">
                    <i class="fa-solid fa-subscript"></i>
                </button>
                <button
                    @click.prevent="toggleSuperscript()"
                    title="Superscript"
                    aria-label="Superscript"
                    :class="{ 'is-active' : isActive('superscript', updatedAt) }">
                    <i class="fa-solid fa-superscript"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleCode()"
                    title="Inline code"
                    aria-label="Inline code"
                    :class="{ 'is-active' : isActive('code', updatedAt) }">
                    <i class="fa-solid fa-font"></i>
                </button>
                <button
                    @click.prevent="toggleCodeBlock()"
                    title="Code block"
                    aria-label="Code block"
                    :class="{ 'is-active' : isActive('codeBlock', updatedAt) }">
                    <i class="fa-solid fa-code"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="toggleBulletList()"
                    title="Bullet list"
                    aria-label="Bullet list"
                    :class="{ 'is-active' : isActive('bulletList', updatedAt) }">
                    <i class="fa-solid fa-list-ul"></i>
                </button>
                <button
                    @click.prevent="toggleOrderedList()"
                    title="Numbered list"
                    aria-label="Numbered list"
                    :class="{ 'is-active' : isActive('orderedList', updatedAt) }">
                    <i class="fa-solid fa-list-ol"></i>
                </button>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <button
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Table tools"
                        aria-label="Table tools"
                        @click.prevent="open = !open"
                        @keydown.escape.window="open = false"
                        :class="{ 'is-active' : isActive('table', updatedAt) }">
                        <i class="fa-solid fa-table"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <div class="editor-toolbar-dropdown__panel editor-toolbar-dropdown__panel--wide" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <button type="button" @click.prevent="insertTable(); open = false" :class="{ 'is-active' : isActive('table', updatedAt) }">
                            <i class="fa-solid fa-table"></i>
                            <span>Insert table</span>
                        </button>
                        <button type="button" @click.prevent="addColumnBefore(); open = false" x-bind:disabled="!canTable('addColumnBefore', updatedAt)">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Add column before</span>
                        </button>
                        <button type="button" @click.prevent="addColumnAfter(); open = false" x-bind:disabled="!canTable('addColumnAfter', updatedAt)">
                            <i class="fa-solid fa-arrow-right"></i>
                            <span>Add column after</span>
                        </button>
                        <button type="button" @click.prevent="deleteColumn(); open = false" x-bind:disabled="!canTable('deleteColumn', updatedAt)">
                            <i class="fa-solid fa-trash"></i>
                            <span>Delete column</span>
                        </button>
                        <button type="button" @click.prevent="addRowBefore(); open = false" x-bind:disabled="!canTable('addRowBefore', updatedAt)">
                            <i class="fa-solid fa-arrow-up"></i>
                            <span>Add row before</span>
                        </button>
                        <button type="button" @click.prevent="addRowAfter(); open = false" x-bind:disabled="!canTable('addRowAfter', updatedAt)">
                            <i class="fa-solid fa-arrow-down"></i>
                            <span>Add row after</span>
                        </button>
                        <button type="button" @click.prevent="deleteRow(); open = false" x-bind:disabled="!canTable('deleteRow', updatedAt)">
                            <i class="fa-solid fa-trash"></i>
                            <span>Delete row</span>
                        </button>
                        <button type="button" @click.prevent="toggleHeaderRow(); open = false" x-bind:disabled="!canTable('toggleHeaderRow', updatedAt)">
                            <i class="fa-solid fa-heading"></i>
                            <span>Toggle header row</span>
                        </button>
                        <button type="button" @click.prevent="toggleHeaderColumn(); open = false" x-bind:disabled="!canTable('toggleHeaderColumn', updatedAt)">
                            <i class="fa-solid fa-heading"></i>
                            <span>Toggle header column</span>
                        </button>
                        <button type="button" @click.prevent="mergeCells(); open = false" x-bind:disabled="!canTable('mergeCells', updatedAt)">
                            <i class="fa-solid fa-object-group"></i>
                            <span>Merge cells</span>
                        </button>
                        <button type="button" @click.prevent="splitCell(); open = false" x-bind:disabled="!canTable('splitCell', updatedAt)">
                            <i class="fa-solid fa-table-cells"></i>
                            <span>Split cell</span>
                        </button>
                        <button type="button" @click.prevent="deleteTable(); open = false" x-bind:disabled="!canTable('deleteTable', updatedAt)">
                            <i class="fa-solid fa-trash"></i>
                            <span>Delete table</span>
                        </button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <button
                        @click.prevent="insertTable()"
                        title="Insert table"
                        aria-label="Insert table"
                        :class="{ 'is-active' : isActive('table', updatedAt) }">
                        <i class="fa-solid fa-table"></i>
                    </button>
                    <div class="border-l border-l-gray-300 mx-1"></div>
                    <button
                        @click.prevent="addColumnBefore()"
                        title="Add column before"
                        aria-label="Add column before"
                        x-bind:disabled="!canTable('addColumnBefore', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-arrow-left text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="addColumnAfter()"
                        title="Add column after"
                        aria-label="Add column after"
                        x-bind:disabled="!canTable('addColumnAfter', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-arrow-right text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="deleteColumn()"
                        title="Delete column"
                        aria-label="Delete column"
                        x-bind:disabled="!canTable('deleteColumn', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="addRowBefore()"
                        title="Add row before"
                        aria-label="Add row before"
                        x-bind:disabled="!canTable('addRowBefore', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-arrow-up text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="addRowAfter()"
                        title="Add row after"
                        aria-label="Add row after"
                        x-bind:disabled="!canTable('addRowAfter', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-arrow-down text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="deleteRow()"
                        title="Delete row"
                        aria-label="Delete row"
                        x-bind:disabled="!canTable('deleteRow', updatedAt)">
                        <i class="fa-solid fa-table-columns"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="toggleHeaderRow()"
                        title="Toggle header row"
                        aria-label="Toggle header row"
                        x-bind:disabled="!canTable('toggleHeaderRow', updatedAt)">
                        <i class="fa-solid fa-heading"></i>
                        <i class="fa-solid fa-arrow-down text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="toggleHeaderColumn()"
                        title="Toggle header column"
                        aria-label="Toggle header column"
                        x-bind:disabled="!canTable('toggleHeaderColumn', updatedAt)">
                        <i class="fa-solid fa-heading"></i>
                        <i class="fa-solid fa-arrow-right text-[10px] align-top"></i>
                    </button>
                    <button
                        @click.prevent="mergeCells()"
                        title="Merge cells"
                        aria-label="Merge cells"
                        x-bind:disabled="!canTable('mergeCells', updatedAt)">
                        <i class="fa-solid fa-object-group"></i>
                    </button>
                    <button
                        @click.prevent="splitCell()"
                        title="Split cell"
                        aria-label="Split cell"
                        x-bind:disabled="!canTable('splitCell', updatedAt)">
                        <i class="fa-solid fa-table-cells"></i>
                    </button>
                    <button
                        @click.prevent="deleteTable()"
                        title="Delete table"
                        aria-label="Delete table"
                        x-bind:disabled="!canTable('deleteTable', updatedAt)">
                        <i class="fa-solid fa-table"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top"></i>
                    </button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                        @click.prevent="insertImage()"
                        title="Insert image"
                        aria-label="Insert image">
                    <i class="fa-solid fa-image"></i>
                </button>
                <button
                    @click.prevent="toggleLink()"
                    title="Insert or edit link"
                    aria-label="Insert or edit link"
                    :class="{ 'is-active' : isActive('link', updatedAt) }">
                    <i class="fa-solid fa-link"></i>
                </button>
                <button
                    @click.prevent="clearLink()"
                    title="Remove link"
                    aria-label="Remove link">
                    <i class="fa-solid fa-link-slash"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="clearNodes();unsetAllMarks()"
                    title="Clear formatting"
                    aria-label="Clear formatting">
                    <i class="fa-solid fa-text-slash"></i>
                </button>
                <button
                    @click.prevent="toggleBlockquote()"
                    title="Blockquote"
                    aria-label="Blockquote"
                    :class="{ 'is-active' : isActive('blockquote', updatedAt) }">
                    <i class="fa-solid fa-quote-right"></i>
                </button>
                <button
                    @click.prevent="setHorizontalRule()"
                    title="Horizontal rule"
                    aria-label="Horizontal rule">
                    <div class="border-t-2 rounded-lg border-black"></div>
                </button>
                <button
                    @click.prevent="setHardBreak()"
                    title="Line break"
                    aria-label="Line break">
                    <i class="fa-solid fa-paragraph"></i>
                </button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <button
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Notice boxes"
                        aria-label="Notice boxes"
                        @click.prevent="open = !open"
                        @keydown.escape.window="open = false"
                        :class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) || isActive('box', { type: 'info' }, updatedAt) || isActive('box', { type: 'warning' }, updatedAt) || isActive('box', { type: 'danger' }, updatedAt) || isActive('box', { type: 'bug' }, updatedAt) }">
                        <i class="fa-solid fa-circle-info"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <div class="editor-toolbar-dropdown__panel" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <button type="button" @click.prevent="toggleBox({ type: 'success' }); open = false" :class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) }">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Success</span>
                        </button>
                        <button type="button" @click.prevent="toggleBox({ type: 'info' }); open = false" :class="{ 'is-active': isActive('box', { type: 'info' }, updatedAt) }">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Info</span>
                        </button>
                        <button type="button" @click.prevent="toggleBox({ type: 'warning' }); open = false" :class="{ 'is-active': isActive('box', { type: 'warning' }, updatedAt) }">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Warning</span>
                        </button>
                        <button type="button" @click.prevent="toggleBox({ type: 'danger' }); open = false" :class="{ 'is-active': isActive('box', { type: 'danger' }, updatedAt) }">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>Danger</span>
                        </button>
                        <button type="button" @click.prevent="toggleBox({ type: 'bug' }); open = false" :class="{ 'is-active': isActive('box', { type: 'bug' }, updatedAt) }">
                            <i class="fa-solid fa-bug"></i>
                            <span>Bug</span>
                        </button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <button
                        @click.prevent="toggleBox({ type: 'success' })"
                        title="Success box"
                        aria-label="Success box"
                        :class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) }">
                        <i class="fa-solid fa-circle-check"></i>
                    </button>
                    <button
                        @click.prevent="toggleBox({ type: 'info' })"
                        title="Info box"
                        aria-label="Info box"
                        :class="{ 'is-active': isActive('box', { type: 'info' }, updatedAt) }">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                    <button
                        @click.prevent="toggleBox({ type: 'warning' })"
                        title="Warning box"
                        aria-label="Warning box"
                        :class="{ 'is-active': isActive('box', { type: 'warning' }, updatedAt) }">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </button>
                    <button
                        @click.prevent="toggleBox({ type: 'danger' })"
                        title="Danger box"
                        aria-label="Danger box"
                        :class="{ 'is-active': isActive('box', { type: 'danger' }, updatedAt) }">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                    <button
                        @click.prevent="toggleBox({ type: 'bug' })"
                        title="Bug box"
                        aria-label="Bug box"
                        :class="{ 'is-active': isActive('box', { type: 'bug' }, updatedAt) }">
                        <i class="fa-solid fa-bug"></i>
                    </button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <button
                    @click.prevent="undo()"
                    title="Undo"
                    aria-label="Undo">
                    <i class="fa-solid fa-undo"></i>
                </button>
                <button
                    @click.prevent="redo()"
                    title="Redo"
                    aria-label="Redo">
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
