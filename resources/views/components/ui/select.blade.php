@props(['innerClass' => '', 'selectClass' => '', 'labelClass' => '', 'name' => null, 'label', 'value' => '', 'readonly' => false, 'disabled' => false, 'info' => null, 'infoExpression' => null, 'error' => null, 'noLabel' => false, 'inlineLabel' => false, 'multiple' => false, 'options' => []])

@php
    $name = is_string($name) ? trim($name) : $name;
    if ($error === null) {
        $error = ($name !== null && $name !== '') ? $errors->first($name) : '';
    }

    $hasError = $error !== '';
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $noLabel = filter_var($noLabel, FILTER_VALIDATE_BOOLEAN);
    $inlineLabel = filter_var($inlineLabel, FILTER_VALIDATE_BOOLEAN);
    $field = new \App\Support\UiField($attributes, filled($info) || filled($infoExpression), $hasError);
    $controlAttributes = $field->controlAttributes();
    $classes = 'disabled:bg-gray-100 bg-white w-full block pl-2.5 pr-10 pb-2.5 text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 '.($noLabel ? '' : 'mt-1 ').($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = ($name !== null && $name !== '') ? old($name, $value) : $value;
    $multiple = filter_var($multiple, FILTER_VALIDATE_BOOLEAN) || $attributes->has('multiple');
    $multipleOptions = collect($options)->map(function ($option, $key): array {
        if (is_array($option)) {
            return ['value' => (string) ($option['value'] ?? $key), 'label' => (string) ($option['label'] ?? $option['name'] ?? $option['value'] ?? $key)];
        }

        return ['value' => (string) $key, 'label' => (string) $option];
    })->values();
    $selectedValues = collect(is_array($value) ? $value : [])->map(fn ($item) => (string) $item)->values()->all();
@endphp

<div data-validation-field="{{ $name }}" class="{{ twMerge(['mb-4'], $inlineLabel ? ['flex', 'items-center'] : '', $attributes->get('class')) }}" {{ $field->rootAttributes() }} {{ $attributes->only(['x-show', 'x-cloak']) }}>
    @if(!$noLabel && !$inlineLabel)
        <div class="flex items-center justify-between mb-1">
                <x-ui.field-label :label="$label" :class="$labelClass" :label-attributes="$field->labelAttributes()" />
                <div class="text-xs text-gray-500">{{ $labelRight ?? '' }}</div>
        </div>
    @elseif($inlineLabel)
        <x-ui.field-label :label="$label" :class="twMerge('inline-block mr-3', $labelClass)" :label-attributes="$field->labelAttributes()" />
    @elseif(filled($label))
        <x-ui.field-label :label="$label" class="sr-only" :label-attributes="$field->labelAttributes()" />
    @endif
    <div class="{{ twMerge(['relative'], $inlineLabel ? 'inline-block flex-1' : '', $innerClass) }}">
        @if($multiple && $multipleOptions->isNotEmpty())
            <div x-data="{ open: false, selected: @js($selectedValues), options: @js($multipleOptions) }" x-on:click.outside="open = false" x-on:keydown.escape.stop="open = false" class="relative">
                <template x-for="selectedValue in selected" :key="selectedValue">
                    <input type="hidden" name="{{ $name }}" :value="selectedValue">
                </template>
                <x-ui.button variant="plain" :disabled="$disabled" x-on:click="open = !open" x-bind:aria-expanded="open" aria-haspopup="true" :button-attributes="$controlAttributes" class="{{ twMerge(['flex min-h-11 w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-indigo-300 focus:outline-none focus:ring-indigo-300 disabled:bg-gray-100'], $selectClass) }}">
                    <span x-text="selected.length ? options.filter(option => selected.includes(option.value)).map(option => option.label).join(', ') : 'Select options'"></span>
                    <i class="fa-solid fa-caret-down ml-3 text-lg text-gray-700" aria-hidden="true"></i>
                </x-ui.button>
                <div x-cloak x-show="open" class="absolute z-40 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 shadow-xl">
                    <template x-for="option in options" :key="option.value">
                        <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 hover:bg-gray-50">
                            <x-ui.checkbox bare small x-bind:value="option.value" x-model="selected" />
                            <span class="text-sm text-gray-800" x-text="option.label"></span>
                        </label>
                    </template>
                </div>
            </div>
        @else
        <select class="{{ twMerge(['pt-2.5'], $classes, $selectClass) }}" @if($name !== null && $name !== '') name="{{ $name }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes->except('style') }}>
            {{ $slot }}
        </select>
        <i class="fa-solid fa-caret-down absolute text-gray-700 text-2xl right-3 bottom-2.25 pointer-events-none" aria-hidden="true"></i>
        @endif
    </div>
    <x-ui.field-feedback :field="$field" :info="$info" :info-expression="$infoExpression" :error="$error" />
</div>
