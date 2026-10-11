@props(['name', 'value' => '', 'info' => null, 'label' => 'Content', 'allowHeadings' => true])

@php
    $hasError = $errors->has($name);
    $value = old($name, $value);
    $field = new \App\Support\UiField($attributes, filled($info), $hasError);
    $editorOptions = [
        'id' => $field->id,
        'label' => filled($label) ? $label : 'Content',
        'labelledBy' => filled($label) ? $field->id.'-label' : '',
        'describedBy' => $field->controlAttributes()->get('aria-describedby'),
        'invalid' => $hasError,
    ];
@endphp

<x-ui.editor-link-dialog />

<div class="editor-container" data-editor-name="{{ $name }}" data-validation-field="{{ $name }}">
    @if(isset($label) && trim((string) $label) !== '')
    <div id="{{ $field->id }}-label" class="text-sm pl-1">{{ $label }}</div>
    @endif
    <div
        x-data="editor($store[@js($name.'_content')], @js(route('custom-page.link-options')), @js($editorOptions))"
        x-on:sm-editor-set-content.window="if (($event.detail?.name || '') === @js($name)) { if ($event.detail?.append) { appendExternalContent($event.detail?.html || '') } else { setExternalContent($event.detail?.html || '', { focusEnd: !!$event.detail?.focusEnd }) } }"
        data-has-error="{{ $hasError ? 'true' : 'false' }}"
        class="{{ twMerge(['editor','mt-1'], $attributes->get('class')) }}">
        <template x-if="isLoaded()">
            <div
                class="menu"
                role="toolbar"
                aria-label="{{ $editorOptions['label'] }} formatting"
                x-init="$nextTick(() => SM.initToolbar($el))"
            >
                <x-ui.button variant="plain"
                    x-on:click.prevent="setParagraph()"
                    title="Paragraph"
                    aria-label="Paragraph"
                    x-bind:class="{ 'is-active': isActive('paragraph', updatedAt) }" x-bind:aria-pressed="isActive('paragraph', updatedAt)">
                    P
                </x-ui.button>
                @if(filter_var($allowHeadings, FILTER_VALIDATE_BOOLEAN))
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeading({ level: 1 })"
                        title="Heading 1"
                        aria-label="Heading 1"
                        x-bind:class="{ 'is-active': isActive('heading', { level: 1 }, updatedAt) }" x-bind:aria-pressed="isActive('heading', { level: 1 }, updatedAt)">
                        H1
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeading({ level: 2 })"
                        title="Heading 2"
                        aria-label="Heading 2"
                        x-bind:class="{ 'is-active': isActive('heading', { level: 2 }, updatedAt) }" x-bind:aria-pressed="isActive('heading', { level: 2 }, updatedAt)">
                        H2
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeading({ level: 3 })"
                        title="Heading 3"
                        aria-label="Heading 3"
                        x-bind:class="{ 'is-active': isActive('heading', { level: 3 }, updatedAt) }" x-bind:aria-pressed="isActive('heading', { level: 3 }, updatedAt)">
                        H3
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeading({ level: 4 })"
                        title="Heading 4"
                        aria-label="Heading 4"
                        x-bind:class="{ 'is-active': isActive('heading', { level: 4 }, updatedAt) }" x-bind:aria-pressed="isActive('heading', { level: 4 }, updatedAt)">
                        H4
                    </x-ui.button>
                @endif
                <x-ui.button variant="plain"
                    x-on:click.prevent="setSmall()"
                    title="Small text"
                    aria-label="Small text"
                    x-bind:class="{ 'is-active': isActive('small', updatedAt) }" x-bind:aria-pressed="isActive('small', updatedAt)">
                    SM
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="setExtraSmall()"
                    title="Extra small text"
                    aria-label="Extra small text"
                    x-bind:class="{ 'is-active': isActive('extraSmall', updatedAt) }" x-bind:aria-pressed="isActive('extraSmall', updatedAt)">
                    XS
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleBold()"
                    title="Bold"
                    aria-label="Bold"
                    x-bind:class="{ 'is-active' : isActive('bold', updatedAt) }" x-bind:aria-pressed="isActive('bold', updatedAt)">
                    <i class="fa-solid fa-bold" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleItalic()"
                    title="Italic"
                    aria-label="Italic"
                    x-bind:class="{ 'is-active' : isActive('italic', updatedAt) }" x-bind:aria-pressed="isActive('italic', updatedAt)">
                    <i class="fa-solid fa-italic" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleUnderline()"
                    title="Underline"
                    aria-label="Underline"
                    x-bind:class="{ 'is-active' : isActive('underline', updatedAt) }" x-bind:aria-pressed="isActive('underline', updatedAt)">
                    <i class="fa-solid fa-underline" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleStrike()"
                    title="Strikethrough"
                    aria-label="Strikethrough"
                    x-bind:class="{ 'is-active' : isActive('strike', updatedAt) }" x-bind:aria-pressed="isActive('strike', updatedAt)">
                    <i class="fa-solid fa-strikethrough" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleHighlight()"
                    title="Highlight"
                    aria-label="Highlight"
                    x-bind:class="{ 'is-active' : isActive('highlight', updatedAt) }" x-bind:aria-pressed="isActive('highlight', updatedAt)">
                    <i class="fa-solid fa-highlighter" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleSpoiler()"
                    title="Spoiler"
                    aria-label="Spoiler"
                    x-bind:class="{ 'is-active' : isActive('spoiler', updatedAt) }" x-bind:aria-pressed="isActive('spoiler', updatedAt)">
                    <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <x-ui.button variant="plain"
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Alignment"
                        aria-label="Alignment"
                        x-on:click.prevent="open = !open"
                        x-on:keydown.escape.window="if (open) { open = false; $nextTick(() => $el.focus()) }" x-bind:aria-expanded="open"
                        x-bind:class="{ 'is-active': isActive({ textAlign: 'left' }, updatedAt) || isActive({ textAlign: 'center' }, updatedAt) || isActive({ textAlign: 'right' }, updatedAt) || isActive({ textAlign: 'justify' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'left' }, updatedAt) || isActive({ textAlign: 'center' }, updatedAt) || isActive({ textAlign: 'right' }, updatedAt) || isActive({ textAlign: 'justify' }, updatedAt)">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </x-ui.button>
                    <div class="editor-toolbar-dropdown__panel" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="setTextAlign('left'); open = false" x-bind:class="{ 'is-active' : isActive({ textAlign: 'left' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'left' }, updatedAt)">
                            <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                            <span>Left</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="setTextAlign('center'); open = false" x-bind:class="{ 'is-active' : isActive({ textAlign: 'center' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'center' }, updatedAt)">
                            <i class="fa-solid fa-align-center" aria-hidden="true"></i>
                            <span>Center</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="setTextAlign('right'); open = false" x-bind:class="{ 'is-active' : isActive({ textAlign: 'right' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'right' }, updatedAt)">
                            <i class="fa-solid fa-align-right" aria-hidden="true"></i>
                            <span>Right</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="setTextAlign('justify'); open = false" x-bind:class="{ 'is-active' : isActive({ textAlign: 'justify' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'justify' }, updatedAt)">
                            <i class="fa-solid fa-align-justify" aria-hidden="true"></i>
                            <span>Justify</span>
                        </x-ui.button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <x-ui.button variant="plain"
                        x-on:click.prevent="setTextAlign('left')"
                        title="Align left"
                        aria-label="Align left"
                        x-bind:class="{ 'is-active' : isActive({ textAlign: 'left' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'left' }, updatedAt)">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="setTextAlign('center')"
                        title="Align center"
                        aria-label="Align center"
                        x-bind:class="{ 'is-active' : isActive({ textAlign: 'center' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'center' }, updatedAt)">
                        <i class="fa-solid fa-align-center" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="setTextAlign('right')"
                        title="Align right"
                        aria-label="Align right"
                        x-bind:class="{ 'is-active' : isActive({ textAlign: 'right' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'right' }, updatedAt)">
                        <i class="fa-solid fa-align-right" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="setTextAlign('justify')"
                        title="Justify"
                        aria-label="Justify"
                        x-bind:class="{ 'is-active' : isActive({ textAlign: 'justify' }, updatedAt) }" x-bind:aria-pressed="isActive({ textAlign: 'justify' }, updatedAt)">
                        <i class="fa-solid fa-align-justify" aria-hidden="true"></i>
                    </x-ui.button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleSubscript()"
                    title="Subscript"
                    aria-label="Subscript"
                    x-bind:class="{ 'is-active' : isActive('subscript', updatedAt) }" x-bind:aria-pressed="isActive('subscript', updatedAt)">
                    <i class="fa-solid fa-subscript" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleSuperscript()"
                    title="Superscript"
                    aria-label="Superscript"
                    x-bind:class="{ 'is-active' : isActive('superscript', updatedAt) }" x-bind:aria-pressed="isActive('superscript', updatedAt)">
                    <i class="fa-solid fa-superscript" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleCode()"
                    title="Inline code"
                    aria-label="Inline code"
                    x-bind:class="{ 'is-active' : isActive('code', updatedAt) }" x-bind:aria-pressed="isActive('code', updatedAt)">
                    <i class="fa-solid fa-font" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleCodeBlock()"
                    title="Code block"
                    aria-label="Code block"
                    x-bind:class="{ 'is-active' : isActive('codeBlock', updatedAt) }" x-bind:aria-pressed="isActive('codeBlock', updatedAt)">
                    <i class="fa-solid fa-code" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleBulletList()"
                    title="Bullet list"
                    aria-label="Bullet list"
                    x-bind:class="{ 'is-active' : isActive('bulletList', updatedAt) }" x-bind:aria-pressed="isActive('bulletList', updatedAt)">
                    <i class="fa-solid fa-list-ul" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleTickList()"
                    title="Green tick list"
                    aria-label="Green tick list"
                    x-bind:class="{ 'is-active' : isActive('tickList', updatedAt) }" x-bind:aria-pressed="isActive('tickList', updatedAt)">
                    <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleOrderedList()"
                    title="Numbered list"
                    aria-label="Numbered list"
                    x-bind:class="{ 'is-active' : isActive('orderedList', updatedAt) }" x-bind:aria-pressed="isActive('orderedList', updatedAt)">
                    <i class="fa-solid fa-list-ol" aria-hidden="true"></i>
                </x-ui.button>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <x-ui.button variant="plain"
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Table tools"
                        aria-label="Table tools"
                        x-on:click.prevent="open = !open"
                        x-on:keydown.escape.window="if (open) { open = false; $nextTick(() => $el.focus()) }" x-bind:aria-expanded="open"
                        x-bind:class="{ 'is-active' : isActive('table', updatedAt) }" x-bind:aria-pressed="isActive('table', updatedAt)">
                        <i class="fa-solid fa-table" aria-hidden="true"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </x-ui.button>
                    <div class="editor-toolbar-dropdown__panel editor-toolbar-dropdown__panel--wide" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="insertTable(); open = false" x-bind:class="{ 'is-active' : isActive('table', updatedAt) }" x-bind:aria-pressed="isActive('table', updatedAt)">
                            <i class="fa-solid fa-table" aria-hidden="true"></i>
                            <span>Insert table</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="addColumnBefore(); open = false" x-bind:disabled="!canTable('addColumnBefore', updatedAt)">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                            <span>Add column before</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="addColumnAfter(); open = false" x-bind:disabled="!canTable('addColumnAfter', updatedAt)">
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Add column after</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="deleteColumn(); open = false" x-bind:disabled="!canTable('deleteColumn', updatedAt)">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            <span>Delete column</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="addRowBefore(); open = false" x-bind:disabled="!canTable('addRowBefore', updatedAt)">
                            <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                            <span>Add row before</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="addRowAfter(); open = false" x-bind:disabled="!canTable('addRowAfter', updatedAt)">
                            <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                            <span>Add row after</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="deleteRow(); open = false" x-bind:disabled="!canTable('deleteRow', updatedAt)">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            <span>Delete</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleHeaderRow(); open = false" x-bind:disabled="!canTable('toggleHeaderRow', updatedAt)">
                            <i class="fa-solid fa-heading" aria-hidden="true"></i>
                            <span>Toggle header row</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleHeaderColumn(); open = false" x-bind:disabled="!canTable('toggleHeaderColumn', updatedAt)">
                            <i class="fa-solid fa-heading" aria-hidden="true"></i>
                            <span>Toggle header column</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="mergeCells(); open = false" x-bind:disabled="!canTable('mergeCells', updatedAt)">
                            <i class="fa-solid fa-object-group" aria-hidden="true"></i>
                            <span>Merge cells</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="splitCell(); open = false" x-bind:disabled="!canTable('splitCell', updatedAt)">
                            <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                            <span>Split cell</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="deleteTable(); open = false" x-bind:disabled="!canTable('deleteTable', updatedAt)">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            <span>Delete table</span>
                        </x-ui.button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <x-ui.button variant="plain"
                        x-on:click.prevent="insertTable()"
                        title="Insert table"
                        aria-label="Insert table"
                        x-bind:class="{ 'is-active' : isActive('table', updatedAt) }" x-bind:aria-pressed="isActive('table', updatedAt)">
                        <i class="fa-solid fa-table" aria-hidden="true"></i>
                    </x-ui.button>
                    <div class="border-l border-l-gray-300 mx-1"></div>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="addColumnBefore()"
                        title="Add column before"
                        aria-label="Add column before"
                        x-bind:disabled="!canTable('addColumnBefore', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-left text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="addColumnAfter()"
                        title="Add column after"
                        aria-label="Add column after"
                        x-bind:disabled="!canTable('addColumnAfter', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-right text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="deleteColumn()"
                        title="Delete column"
                        aria-label="Delete column"
                        x-bind:disabled="!canTable('deleteColumn', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="addRowBefore()"
                        title="Add row before"
                        aria-label="Add row before"
                        x-bind:disabled="!canTable('addRowBefore', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-up text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="addRowAfter()"
                        title="Add row after"
                        aria-label="Add row after"
                        x-bind:disabled="!canTable('addRowAfter', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-down text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="deleteRow()"
                        title="Delete"
                        aria-label="Delete"
                        x-bind:disabled="!canTable('deleteRow', updatedAt)">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeaderRow()"
                        title="Toggle header row"
                        aria-label="Toggle header row"
                        x-bind:disabled="!canTable('toggleHeaderRow', updatedAt)">
                        <i class="fa-solid fa-heading" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-down text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleHeaderColumn()"
                        title="Toggle header column"
                        aria-label="Toggle header column"
                        x-bind:disabled="!canTable('toggleHeaderColumn', updatedAt)">
                        <i class="fa-solid fa-heading" aria-hidden="true"></i>
                        <i class="fa-solid fa-arrow-right text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="mergeCells()"
                        title="Merge cells"
                        aria-label="Merge cells"
                        x-bind:disabled="!canTable('mergeCells', updatedAt)">
                        <i class="fa-solid fa-object-group" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="splitCell()"
                        title="Split cell"
                        aria-label="Split cell"
                        x-bind:disabled="!canTable('splitCell', updatedAt)">
                        <i class="fa-solid fa-table-cells" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="deleteTable()"
                        title="Delete table"
                        aria-label="Delete table"
                        x-bind:disabled="!canTable('deleteTable', updatedAt)">
                        <i class="fa-solid fa-table" aria-hidden="true"></i>
                        <i class="fa-solid fa-trash text-[10px] align-top" aria-hidden="true"></i>
                    </x-ui.button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                        x-on:click.prevent="insertImage()"
                        title="Insert image"
                        aria-label="Insert image">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleLink()"
                    title="Insert or edit link"
                    aria-label="Insert or edit link"
                    x-bind:class="{ 'is-active' : isActive('link', updatedAt) }" x-bind:aria-pressed="isActive('link', updatedAt)">
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="clearLink()"
                    title="Remove link"
                    aria-label="Remove link">
                    <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="clearNodes();unsetAllMarks()"
                    title="Clear formatting"
                    aria-label="Clear formatting">
                    <i class="fa-solid fa-text-slash" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="toggleBlockquote()"
                    title="Blockquote"
                    aria-label="Blockquote"
                    x-bind:class="{ 'is-active' : isActive('blockquote', updatedAt) }" x-bind:aria-pressed="isActive('blockquote', updatedAt)">
                    <i class="fa-solid fa-quote-right" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="setHorizontalRule()"
                    title="Horizontal rule"
                    aria-label="Horizontal rule">
                    <div class="border-t-2 rounded-lg border-black"></div>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="setHardBreak()"
                    title="Line break"
                    aria-label="Line break">
                    <i class="fa-solid fa-paragraph" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="insertCollapsibleSection()"
                    title="Insert collapsible section"
                    aria-label="Insert collapsible section">
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </x-ui.button>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <div class="editor-toolbar-dropdown hidden md:block" x-data="{ open: false }">
                    <x-ui.button variant="plain"
                        type="button"
                        class="editor-toolbar-dropdown__trigger"
                        title="Notice boxes"
                        aria-label="Notice boxes"
                        x-on:click.prevent="open = !open"
                        x-on:keydown.escape.window="if (open) { open = false; $nextTick(() => $el.focus()) }" x-bind:aria-expanded="open"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) || isActive('box', { type: 'info' }, updatedAt) || isActive('box', { type: 'warning' }, updatedAt) || isActive('box', { type: 'danger' }, updatedAt) || isActive('box', { type: 'bug' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'success' }, updatedAt) || isActive('box', { type: 'info' }, updatedAt) || isActive('box', { type: 'warning' }, updatedAt) || isActive('box', { type: 'danger' }, updatedAt) || isActive('box', { type: 'bug' }, updatedAt)">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    </x-ui.button>
                    <div class="editor-toolbar-dropdown__panel" x-show="open" x-transition.origin.top.left @click.outside="open = false">
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleBox({ type: 'success' }); open = false" x-bind:class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'success' }, updatedAt)">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span>Success</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleBox({ type: 'info' }); open = false" x-bind:class="{ 'is-active': isActive('box', { type: 'info' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'info' }, updatedAt)">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>Info</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleBox({ type: 'warning' }); open = false" x-bind:class="{ 'is-active': isActive('box', { type: 'warning' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'warning' }, updatedAt)">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            <span>Warning</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleBox({ type: 'danger' }); open = false" x-bind:class="{ 'is-active': isActive('box', { type: 'danger' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'danger' }, updatedAt)">
                            <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                            <span>Danger</span>
                        </x-ui.button>
                        <x-ui.button variant="plain" type="button" x-on:click.prevent="toggleBox({ type: 'bug' }); open = false" x-bind:class="{ 'is-active': isActive('box', { type: 'bug' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'bug' }, updatedAt)">
                            <i class="fa-solid fa-bug" aria-hidden="true"></i>
                            <span>Bug</span>
                        </x-ui.button>
                    </div>
                </div>
                <div class="md:hidden contents">
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleBox({ type: 'success' })"
                        title="Success box"
                        aria-label="Success box"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'success' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'success' }, updatedAt)">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleBox({ type: 'info' })"
                        title="Info box"
                        aria-label="Info box"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'info' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'info' }, updatedAt)">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleBox({ type: 'warning' })"
                        title="Warning box"
                        aria-label="Warning box"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'warning' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'warning' }, updatedAt)">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleBox({ type: 'danger' })"
                        title="Danger box"
                        aria-label="Danger box"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'danger' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'danger' }, updatedAt)">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    </x-ui.button>
                    <x-ui.button variant="plain"
                        x-on:click.prevent="toggleBox({ type: 'bug' })"
                        title="Bug box"
                        aria-label="Bug box"
                        x-bind:class="{ 'is-active': isActive('box', { type: 'bug' }, updatedAt) }" x-bind:aria-pressed="isActive('box', { type: 'bug' }, updatedAt)">
                        <i class="fa-solid fa-bug" aria-hidden="true"></i>
                    </x-ui.button>
                </div>
                <div class="border-l border-l-gray-300 mx-1"></div>
                <x-ui.button variant="plain"
                    x-on:click.prevent="undo()"
                    title="Undo"
                    aria-label="Undo">
                    <i class="fa-solid fa-undo" aria-hidden="true"></i>
                </x-ui.button>
                <x-ui.button variant="plain"
                    x-on:click.prevent="redo()"
                    title="Redo"
                    aria-label="Redo">
                    <i class="fa-solid fa-redo" aria-hidden="true"></i>
                </x-ui.button>
                @if(isset($toolbar))
                    <div class="border-l border-l-gray-300 mx-1"></div>
                    {{ $toolbar }}
                @endif
            </div>
        </template>
        <div x-ref="element" class="content"></div>
        <input class="hidden" type="text" name="{{ $name }}" x-model="content">
    </div>
    <x-ui.field-feedback :field="$field" :info="$info" :error="$errors->first($name)" />
</div>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        const registerEditorStore = () => {
            if (!window.Alpine?.store) {
                return false;
            }

            Alpine.store(@js($name.'_content'), @js($value));
            return true;
        };

        if (!registerEditorStore()) {
            document.addEventListener('alpine:init', registerEditorStore, { once: true });
        }
    })();
</script>

@pushOnce('head', 'editor-assets')
    @vite('resources/js/editor/TipTap.js')
@endPushOnce
