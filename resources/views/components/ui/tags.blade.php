@props([
    'name',
    'label' => 'Tags',
    'value' => '',
    'options' => [],
    'placeholder' => 'tag-one tag-two',
    'noWrapper' => false,
])

@php
    $inputId = $attributes->get('id') ?: ($name ? $name.'_tags' : '');
    $textInputId = $inputId !== '' ? $inputId.'_entry' : '';
    $datalistId = $inputId !== '' ? $inputId.'_options' : '';
    $noWrapper = filter_var($noWrapper, FILTER_VALIDATE_BOOLEAN);
    $externalTagsBinding = trim((string) ($attributes->get('x-model-tags') ?? ''));
    $externalDraftBinding = trim((string) ($attributes->get('x-model-draft') ?? ''));
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
            sync() {
                this.$refs.value.value = this.tags.join(', ');
            },
            add(value = null) {
                const tag = String(value ?? this.draft).trim().replace(/,$/, '');
                if (tag === '') return;
                if (!this.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
                    this.tags.push(tag);
                }
                this.draft = '';
                this.sync();
            },
            remove(index) {
                this.tags.splice(index, 1);
                this.sync();
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
            sync()
        "
        @if($externalTagsBinding !== '' || $externalDraftBinding !== '')
        x-effect="
            @if($externalTagsBinding !== '')
                try {
                    const incomingTags = {{ $externalTagsBinding }};
                    if (Array.isArray(incomingTags) && JSON.stringify(tags) !== JSON.stringify(incomingTags)) {
                        tags = incomingTags;
                    }
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
        x-on:click="if (!$event.target.closest('button')) { $refs.entry.focus(); }"
    >
        <input
            type="hidden"
            @if($inputId !== '') id="{{ $inputId }}" @endif
            @if($name) name="{{ $name }}" @endif
            x-ref="value"
            {{ $attributes->except(['id', 'x-model-tags', 'x-model-draft']) }}
        >
        <div class="flex min-h-7 flex-wrap items-center gap-1.5">
            <template x-for="(tag, index) in tags" :key="tag">
                <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">
                    <span x-text="tag"></span>
                    <button type="button" class="text-sky-600 hover:text-red-600" x-on:click.prevent="remove(index)" aria-label="Remove tag">
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
                class="w-32 max-w-full border-0 text-sm focus:outline-none focus:ring-0"
                x-on:keydown.enter.prevent="add()"
                x-on:keydown.space.prevent="add()"
                x-on:keydown="if ($event.key === ',') { $event.preventDefault(); add(); }"
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
    <div class="mt-1 text-xs text-gray-500">Press space, comma, or enter to create a tag.</div>
@if(!$noWrapper)
</div>
@endif
