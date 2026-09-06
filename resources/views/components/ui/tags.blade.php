@props([
    'name',
    'label' => 'Tags',
    'value' => '',
    'options' => [],
    'placeholder' => 'tag-one tag-two',
    'noWrapper' => false,
    'showHelp' => true,
])

@php
    $inputId = $attributes->get('id') ?: ($name ? $name.'_tags' : '');
    $textInputId = $inputId !== '' ? $inputId.'_entry' : '';
    $datalistId = $inputId !== '' ? $inputId.'_options' : '';
    $noWrapper = filter_var($noWrapper, FILTER_VALIDATE_BOOLEAN);
    $showHelp = filter_var($showHelp, FILTER_VALIDATE_BOOLEAN);
    $externalTagsBinding = trim((string) ($attributes->get('x-model-tags') ?? ''));
    $externalDraftBinding = trim((string) ($attributes->get('x-model-draft') ?? ''));
    $externalMixedBinding = trim((string) ($attributes->get('x-mixed-tags') ?? ''));
@endphp

@if(!$noWrapper)
<div class="mb-4">
@endif
    <label @if($textInputId !== '') for="{{ $textInputId }}" @endif class="block text-sm pl-1">{{ $label }}</label>
    <div
        class="mt-1 rounded-lg border border-gray-300 bg-white px-2 py-1.5 focus-within:border-indigo-300"
        x-data="{
            tags: @js(collect(explode(',', (string) $value))->map(fn ($tag) => trim($tag))->filter()->values()->all()),
            draft: '',
            cursorIndex: 0,
            mixedTags: [],
            isMixed(tag) {
                return this.mixedTags.some((mixed) => String(mixed).toLowerCase() === String(tag).toLowerCase());
            },
            sync() {
                this.$refs.value.value = this.tags.join(', ');
            },
            add(value = null) {
                const tag = String(value ?? this.draft).trim().replace(/,$/, '');
                if (tag === '') return;
                if (!this.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
                    this.tags.splice(this.cursorIndex, 0, tag);
                    this.cursorIndex++;
                }
                this.draft = '';
                this.sync();
            },
            remove(index) {
                this.tags.splice(index, 1);
                if (this.cursorIndex > index) this.cursorIndex--;
                this.sync();
            },
            handleKey(event) {
                if (event.key === 'ArrowLeft' && this.draft === '') {
                    event.preventDefault();
                    this.cursorIndex = Math.max(0, this.cursorIndex - 1);
                } else if (event.key === 'ArrowRight' && this.draft === '') {
                    event.preventDefault();
                    this.cursorIndex = Math.min(this.tags.length, this.cursorIndex + 1);
                } else if (event.key === 'Backspace' && this.draft === '' && this.cursorIndex > 0) {
                    event.preventDefault();
                    this.remove(this.cursorIndex - 1);
                } else if (event.key === 'Delete' && this.draft === '' && this.cursorIndex < this.tags.length) {
                    event.preventDefault();
                    this.remove(this.cursorIndex);
                }
            },
        }"
        x-init="
            @if($externalTagsBinding !== '')
                try {
                    const initialTags = {{ $externalTagsBinding }};
                    if (Array.isArray(initialTags)) {
                        tags = initialTags;
                    }
                } catch (e) {}
                $watch('tags', value => {
                    try {
                        {{ $externalTagsBinding }} = value;
                    } catch (e) {}
                });
            @endif
            @if($externalDraftBinding !== '')
                try {
                    const initialDraft = {{ $externalDraftBinding }};
                    if (typeof initialDraft === 'string') {
                        draft = initialDraft;
                    }
                } catch (e) {}
                $watch('draft', value => {
                    try {
                        {{ $externalDraftBinding }} = value;
                    } catch (e) {}
                });
            @endif
            @if($externalMixedBinding !== '')
                try { mixedTags = Array.isArray({{ $externalMixedBinding }}) ? {{ $externalMixedBinding }} : []; } catch (e) {}
            @endif
            cursorIndex = tags.length;
            sync()
        "
        @if($externalTagsBinding !== '' || $externalDraftBinding !== '' || $externalMixedBinding !== '')
        x-effect="
            @if($externalTagsBinding !== '')
                try {
                    const incomingTags = {{ $externalTagsBinding }};
                    if (Array.isArray(incomingTags) && JSON.stringify(tags) !== JSON.stringify(incomingTags)) {
                        tags = incomingTags;
                        cursorIndex = Math.min(cursorIndex, tags.length);
                    }
                } catch (e) {}
            @endif
            @if($externalMixedBinding !== '')
                try {
                    const incomingMixed = {{ $externalMixedBinding }};
                    if (Array.isArray(incomingMixed) && JSON.stringify(mixedTags) !== JSON.stringify(incomingMixed)) mixedTags = incomingMixed;
                } catch (e) {}
            @endif
            @if($externalDraftBinding !== '')
                try {
                    const incomingDraft = {{ $externalDraftBinding }};
                    if (typeof incomingDraft === 'string' && draft !== incomingDraft) {
                        draft = incomingDraft;
                    }
                } catch (e) {}
            @endif
        "
        @endif
        x-on:click="if (!$event.target.closest('button') && !$event.target.closest('[data-tag-pill]')) { cursorIndex = tags.length; $refs.entry.focus(); }"
    >
        <input
            type="hidden"
            @if($inputId !== '') id="{{ $inputId }}" @endif
            @if($name) name="{{ $name }}" @endif
            x-ref="value"
            {{ $attributes->except(['id', 'x-model-tags', 'x-model-draft', 'x-mixed-tags']) }}
        >
        <div class="flex min-h-7 flex-wrap items-center gap-1.5">
            <template x-for="(tag, index) in tags" :key="tag">
                <span data-tag-pill class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800" :style="`order: ${(index + 1) * 2}`" :class="isMixed(tag) ? 'bg-[repeating-linear-gradient(135deg,#e0f2fe_0,#e0f2fe_5px,#bae6fd_5px,#bae6fd_10px)]' : ''" x-on:click="cursorIndex = index + 1; $refs.entry.focus()">
                    <span x-text="tag"></span>
                    <button type="button" class="text-sky-600 hover:text-red-600" x-on:click.prevent.stop="remove(index)" aria-label="Remove tag">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </span>
            </template>
            <input
                x-ref="entry"
                @if($textInputId !== '') id="{{ $textInputId }}" @endif
                type="text"
                x-model="draft"
                @if($datalistId !== '') list="{{ $datalistId }}" @endif
                x-bind:placeholder="tags.length === 0 ? @js($placeholder) : ''"
                x-bind:style="`order: ${cursorIndex * 2 + 1}; width: ${tags.length === 0 ? '12rem' : Math.max(1, Math.min(20, draft.length + 1)) + 'ch'}`"
                x-bind:class="tags.length ? '-mx-1' : ''"
                class="max-w-full border-0 px-0 text-sm focus:outline-none focus:ring-0"
                x-on:keydown.enter.prevent="add()"
                x-on:keydown.space.prevent="add()"
                x-on:keydown="if ($event.key === ',') { $event.preventDefault(); add(); } else { handleKey($event); }"
                x-on:blur="add()"
            >
        </div>
        @if($datalistId !== '')
            <datalist id="{{ $datalistId }}">
                @foreach($options as $option)
                    <option value="{{ $option }}"></option>
                @endforeach
            </datalist>
        @endif
    </div>
    @if($showHelp)
        <div class="mt-1 text-xs text-gray-500">Press space, comma, or enter to create a tag.</div>
    @endif
@if(!$noWrapper)
</div>
@endif
