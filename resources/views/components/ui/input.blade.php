@props(['type' => 'text', 'name' => null, 'label' => '', 'labelInfo' => '', 'value' => '', 'floating' => false, 'noLabel' => false, 'readonly' => false, 'disabled' => false, 'info' => null, 'error' => null, 'labelNotice' => null, 'placeholder' => '', 'fieldClasses' => '', 'suggestions' => [], 'moneyFormat' => false, 'inline' => false, 'showSuggestionsOnFocus' => false, 'labelClass' => '' ])

@php
    if ($error === null) {
        $error = ($name !== null && $name !== '') ? $errors->first($name) : '';
    }

    $hasError = $error !== '';
    $floating = filter_var($floating, FILTER_VALIDATE_BOOLEAN);
    $noLabel = filter_var($noLabel, FILTER_VALIDATE_BOOLEAN);
    $inline = filter_var($inline, FILTER_VALIDATE_BOOLEAN);
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 '.($floating ? 'peer ' : '').($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = ($name !== null && $name !== '') ? old($name, $value) : $value;
    if (is_array($value)) {
        $value = implode(
            $type === 'textarea' ? PHP_EOL : ', ',
            array_map(
                static fn ($item): string => is_scalar($item) || $item instanceof \Stringable ? (string) $item : '',
                \Illuminate\Support\Arr::flatten($value),
            ),
        );
    }
    $moneyFormat = filter_var($moneyFormat, FILTER_VALIDATE_BOOLEAN);
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $showSuggestionsOnFocus = filter_var($showSuggestionsOnFocus, FILTER_VALIDATE_BOOLEAN);
    $suggestions = collect($suggestions ?? [])
        ->map(fn ($item) => trim((string) $item))
        ->filter(fn ($item) => $item !== '')
        ->unique()
        ->values()
        ->all();
    $hasSuggestions = $type === 'text' && !filter_var($readonly, FILTER_VALIDATE_BOOLEAN) && ! $disabled && count($suggestions) > 0;
    $xModelBinding = trim((string) ($attributes->get('x-model') ?? ''));
    $suggestionInputHandler = trim((string) ($attributes->get('x-on:input') ?? ''));
    $isFileInput = $type === 'file';
    $field = new \App\Support\UiField($attributes, $info !== null && $info !== '', $hasError);
    $controlAttributes = $field->controlAttributes();
    $inputId = $field->id;
    $autocomplete = (string) ($attributes->get('autocomplete') ?? 'off');

    $autocompleteValue = (string) $value;
@endphp

<div data-validation-field="{{ $name }}" class="{{ twMerge(['mb-4'], $attributes->get('class'), ($inline ? 'w-full' : '')) }}" {{ $field->rootAttributes() }} {{ $attributes->only(['x-show', 'x-cloak']) }}>
    @if($noLabel && !$floating && !$isFileInput && filled($label))
        <x-ui.field-label :label="$label" class="sr-only" :label-attributes="$field->labelAttributes()" />
    @endif
    @if($isFileInput)
        <x-ui.file-upload
            :name="$name"
            :label="$label"
            :id="$inputId"
            :placeholder="$placeholder"
            :readonly="$readonly"
            :disabled="$disabled"
            :info="$info"
            :error="$error"
            :fieldClasses="$fieldClasses"
            :noLabel="$noLabel"
            :floating="$floating"
            :noWrapper="true"
            :inline="$inline"
            {{ $attributes->except(['class', 'id']) }}
        />
    @elseif($floating)
        @if($type === 'textarea')
            <div class="relative">
                <textarea class="{{ twMerge(['pt-4'], $classes, $fieldClasses) }}" @if($name !== null && $name !== '') name="{{ $name }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes }}>{{ $value }}</textarea>
                <x-ui.field-label :label="$label" :info="$labelInfo" :notice="$labelNotice" :floating="true" :label-attributes="$field->labelAttributes()" />
            </div>
        @elseif($hasSuggestions)
            <div
                class="relative"
                x-data="$SM.autocompleteEditor({ value: @js($autocompleteValue), options: @js($suggestions), moneyFormat: @js($moneyFormat), showOnFocus: @js($showSuggestionsOnFocus), modelBinding: @js($xModelBinding), inputHandler: @js($suggestionInputHandler) })"
                @if($xModelBinding !== '')
                x-effect="$SM.syncAutocompleteValue($el)"
                @endif
                x-on:click.away="open = false"
            >
                <input
                    x-ref="inputEl"
                    class="{{ twMerge(['pt-4'], $classes, $fieldClasses) }}"
                    autocomplete="{{ $autocomplete }}"
                    placeholder=" "
                    type="{{ $type }}"
                    name="{{ $name }}"
                    x-model="rawValue"
                    x-on:focus="$SM.autocompleteFocus($el, @js($showSuggestionsOnFocus))"
                    x-on:input="$SM.autocompleteInput($el, $event)"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="applySelection()"
                    x-on:keydown.escape.prevent="open = false"
                    x-on:blur="$SM.autocompleteBlur($el)"
                    @disabled($disabled)
                    {{ $controlAttributes->except(['x-model', 'autocomplete', 'x-on:input']) }}
                />
                <x-ui.field-label :label="$label" :info="$labelInfo" :notice="$labelNotice" :floating="true" :label-attributes="$field->labelAttributes()" />
                <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
                    <ul class="max-h-60 overflow-auto py-1">
                        <template x-for="(item, index) in filtered" :key="item + '-' + index">
                            <li
                                class="cursor-pointer px-3 py-2 text-sm"
                                :class="index === selectedIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-800 hover:bg-gray-100'"
                                x-on:mouseenter="selectedIndex = index"
                                x-on:mousedown.prevent="choose(item)"
                                x-text="item"
                            ></li>
                        </template>
                    </ul>
                </div>
            </div>
        @else
            <div class="relative">
                <input class="{{ twMerge(['pt-4'], $classes, $fieldClasses) }}" autocomplete="{{ $autocomplete }}" placeholder=" " value="{{ $value }}" type="{{ $type }}" @if($name !== null && $name !== '') name="{{ $name }}" @endif @if($moneyFormat) data-money-format @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes->except('autocomplete') }} />
                <x-ui.field-label :label="$label" :info="$labelInfo" :notice="$labelNotice" :floating="true" :label-attributes="$field->labelAttributes()" />
            </div>
        @endif
    @elseif($noLabel)
        <div class="relative">
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}" @if($name !== null && $name !== '') name="{{ $name }}" @endif placeholder="{{ $label }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes }}>{{ $value }}</textarea>
            @elseif($hasSuggestions)
                <div
                    class="relative"
                    x-data="$SM.autocompleteEditor({ value: @js($autocompleteValue), options: @js($suggestions), moneyFormat: @js($moneyFormat), showOnFocus: @js($showSuggestionsOnFocus), modelBinding: @js($xModelBinding), inputHandler: @js($suggestionInputHandler) })"
                    @if($xModelBinding !== '')
                    x-effect="$SM.syncAutocompleteValue($el)"
                    @endif
                    x-on:click.away="open = false"
                    x-on:scroll.window="$SM.autocompletePosition($el)"
                    x-on:resize.window="$SM.autocompletePosition($el)"
                >
                    <input
                        x-ref="inputEl"
                        class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}"
                        autocomplete="{{ $autocomplete }}"
                        placeholder="{{ $label }}"
                    type="{{ $type }}"
                    @if($name !== null && $name !== '') name="{{ $name }}" @endif
                    x-model="rawValue"
                    x-on:focus="$SM.autocompleteFocus($el, @js($showSuggestionsOnFocus))"
                    x-on:input="$SM.autocompleteInput($el, $event)"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="applySelection()"
                    x-on:keydown.escape.prevent="open = false"
                    x-on:blur="$SM.autocompleteBlur($el)"
                    @disabled($disabled)
                    {{ $controlAttributes->except(['x-model', 'autocomplete', 'x-on:input']) }}
                />
                    <template x-teleport="body">
                        <div
                            x-show="open"
                            x-cloak
                            x-on:mousedown.stop
                            class="z-50 rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden"
                            :style="dropdownStyle"
                        >
                            <ul class="overflow-auto py-1" :style="'max-height: ' + dropdownListMaxHeight + 'px'">
                                <template x-for="(item, index) in filtered" :key="item + '-' + index">
                                    <li
                                        class="cursor-pointer px-3 py-2 text-sm"
                                        :class="index === selectedIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-800 hover:bg-gray-100'"
                                        x-on:mouseenter="selectedIndex = index"
                                        x-on:mousedown.prevent="choose(item)"
                                        x-text="item"
                                    ></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            @else
                <input class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $label }}" value="{{ $value }}" type="{{ $type }}" @if($name !== null && $name !== '') name="{{ $name }}" @endif @if($moneyFormat) data-money-format @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes->except('autocomplete') }} />
            @endif
        </div>
    @else
        <div class="{{ ($inline ? 'flex items-center gap-2' : '') }}">
            @if(isset($label) && $label !== '')
            <x-ui.field-label :label="$label" :info="$labelInfo" :notice="$labelNotice" :class="$labelClass" :label-attributes="$field->labelAttributes()" />
            @endif
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5', 'mt-1', $attributes->has('rows') ? 'h-auto' : 'h-28'], $classes, $fieldClasses) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes }}>{{ $value }}</textarea>
            @elseif($hasSuggestions)
                <div
                    class="relative mt-1"
                    x-data="$SM.autocompleteEditor({ value: @js($autocompleteValue), options: @js($suggestions), moneyFormat: @js($moneyFormat), showOnFocus: @js($showSuggestionsOnFocus), modelBinding: @js($xModelBinding), inputHandler: @js($suggestionInputHandler) })"
                    @if($xModelBinding !== '')
                    x-effect="$SM.syncAutocompleteValue($el)"
                    @endif
                    x-on:click.away="open = false"
                >
                    <input
                        x-ref="inputEl"
                        class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}"
                        autocomplete="{{ $autocomplete }}"
                        placeholder=" "
                    type="{{ $type }}"
                    name="{{ $name }}"
                    x-model="rawValue"
                    x-on:focus="$SM.autocompleteFocus($el, @js($showSuggestionsOnFocus))"
                    x-on:input="$SM.autocompleteInput($el, $event)"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="applySelection()"
                    x-on:keydown.escape.prevent="open = false"
                    x-on:blur="$SM.autocompleteBlur($el)"
                    @disabled($disabled)
                    {{ $controlAttributes->except(['x-model', 'autocomplete', 'x-on:input']) }}
                />
                    <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
                        <ul class="max-h-60 overflow-auto py-1">
                            <template x-for="(item, index) in filtered" :key="item + '-' + index">
                                <li
                                    class="cursor-pointer px-3 py-2 text-sm"
                                    :class="index === selectedIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-800 hover:bg-gray-100'"
                                    x-on:mouseenter="selectedIndex = index"
                                    x-on:mousedown.prevent="choose(item)"
                                    x-text="item"
                                ></li>
                            </template>
                        </ul>
                    </div>
                </div>
            @else
                <input class="{{ twMerge(['pt-2.5','mt-1'], $classes, $fieldClasses) }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}" value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) data-money-format @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $controlAttributes->except('autocomplete') }} />
            @endif
        </div>
    @endif
    @if(!$isFileInput)
        <x-ui.field-feedback :field="$field" :info="$info" :error="$error" />
    @endif
</div>
