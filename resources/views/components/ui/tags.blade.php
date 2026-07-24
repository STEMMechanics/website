@props([
    'name',
    'label' => 'Tags',
    'value' => '',
    'options' => [],
    'placeholder' => 'tag one, tag two',
    'noWrapper' => false,
])

@php
    $inputId = $attributes->get('id') ?: $name.'_tags';
    $textInputId = $inputId.'_entry';
    $datalistId = $inputId.'_options';
    $noWrapper = filter_var($noWrapper, FILTER_VALIDATE_BOOLEAN);
@endphp

@if(!$noWrapper)
<div class="mb-4">
@endif
    <label for="{{ $textInputId }}" class="block text-sm pl-1">{{ $label }}</label>
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
        x-init="sync()"
        x-on:click="if (!$event.target.closest('button')) { $refs.entry.focus(); }"
    >
        <input
            type="hidden"
            id="{{ $inputId }}"
            name="{{ $name }}"
            x-ref="value"
            {{ $attributes->except(['id']) }}
        >
        <div class="flex min-h-9 flex-wrap items-center gap-1.5">
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
                id="{{ $textInputId }}"
                type="text"
                x-model="draft"
                list="{{ $datalistId }}"
                placeholder="{{ $placeholder }}"
                class="w-32 max-w-full border-0 px-1 py-1 text-sm focus:outline-none focus:ring-0"
                x-on:keydown.enter.prevent="add()"
                x-on:keydown.space.prevent="add()"
                x-on:keydown="if ($event.key === ',') { $event.preventDefault(); add(); }"
                x-on:blur="add()"
            >
        </div>
        <datalist id="{{ $datalistId }}">
            @foreach($options as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
    </div>
    <div class="mt-1 text-xs text-gray-500">Press space, comma, or enter to create a tag.</div>
@if(!$noWrapper)
</div>
@endif
