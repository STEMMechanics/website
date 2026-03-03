@props(['type' => 'text', 'name' => null, 'label' => '', 'labelInfo' => '', 'value' => '', 'floating' => false, 'noLabel' => false, 'readonly' => false, 'disabled' => false, 'info', 'error' => null, 'labelNotice' => null, 'placeholder' => '', 'fieldClasses' => '', 'suggestions' => [], 'moneyFormat' => false, 'inline' => false, 'showSuggestionsOnFocus' => false ])

@php
    if ($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 '.($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = old($name, $value);
    if (is_array($value)) {
        $value = implode($type === 'textarea' ? PHP_EOL : ', ', array_map(function ($item) {
            if (is_array($item)) {
                return implode(', ', array_map(fn ($nested) => (string) $nested, $item));
            }

            return (string) $item;
        }, $value));
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
    $isFileInput = $type === 'file';
    $inputId = (string) ($attributes->get('id') ?? $name);
    $autocomplete = (string) ($attributes->get('autocomplete') ?? 'off');

    $autocompleteValue = (string) $value;
    $moneyFormatOnBlur = "const raw = String(this.value || '').trim(); if (raw === '') { return; } const amount = parseFloat(raw); if (!Number.isFinite(amount) || amount < 0) { this.value = ''; return; } this.value = amount.toFixed(2);";
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class'), ($inline ? 'w-full' : '')) }}">
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
                <textarea class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }}>{{ $value }}</textarea>
                <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}{!! isset($labelInfo) ? '<span class="text-xs text-gray-500 ml-1">' . $labelInfo . '</span>' : '' !!}</label>
            </div>
        @elseif($hasSuggestions)
            <div
                class="relative"
                x-data="{
                    rawValue: @js($autocompleteValue),
                    options: @js($suggestions),
                    moneyFormat: @js($moneyFormat),
                    hasTyped: false,
                    suppressRefresh: false,
                    filtered: [],
                    open: false,
                    selectedIndex: -1,
                    refresh() {
                        if (this.suppressRefresh) {
                            this.suppressRefresh = false;
                            this.filtered = [];
                            this.selectedIndex = -1;
                            this.open = false;
                            return;
                        }
                        if (!this.hasTyped) {
                            this.filtered = [];
                            this.selectedIndex = -1;
                            this.open = false;
                            return;
                        }
                        const needle = (this.rawValue || '').toLowerCase().trim();
                        if (needle === '') {
                            if (@js($showSuggestionsOnFocus)) {
                                this.filtered = this.options.slice(0, 8);
                                this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                                this.open = this.filtered.length > 0;
                                return;
                            }
                            this.filtered = [];
                            this.selectedIndex = -1;
                            this.open = false;
                            return;
                        }
                        const items = this.options.filter((option) => option.toLowerCase().includes(needle));
                        this.filtered = items.slice(0, 8);
                        this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                        this.open = this.filtered.length > 0;
                    },
                    move(step) {
                        if (!this.open) {
                            this.refresh();
                            return;
                        }
                        const len = this.filtered.length;
                        if (!len) {
                            return;
                        }
                        this.selectedIndex = (this.selectedIndex + step + len) % len;
                    },
                    choose(value) {
                        this.suppressRefresh = true;
                        this.rawValue = value;
                        this.open = false;
                        this.selectedIndex = -1;
                        this.$nextTick(() => {
                            if (!this.$refs.inputEl) {
                                return;
                            }
                            this.$refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                            this.$refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    },
                    confirm() {
                        if (!this.open) {
                            return;
                        }
                        if (this.selectedIndex < 0 || this.selectedIndex >= this.filtered.length) {
                            this.open = false;
                            this.selectedIndex = -1;
                            return;
                        }
                        this.choose(this.filtered[this.selectedIndex]);
                    },
                    formatMoney() {
                        if (!this.moneyFormat) {
                            return;
                        }
                        const raw = String(this.rawValue || '').trim();
                        if (raw === '') {
                            return;
                        }
                        const amount = parseFloat(raw);
                        if (!Number.isFinite(amount) || amount < 0) {
                            this.rawValue = '';
                            return;
                        }
                        this.rawValue = amount.toFixed(2);
                    }
                }"
                x-init="
                    refresh();
                    @if($xModelBinding !== '')
                        let __modelValue = '';
                        try {
                            __modelValue = {{ $xModelBinding }};
                        } catch (e) {
                            __modelValue = '';
                        }
                        if (__modelValue !== null && __modelValue !== undefined && __modelValue !== '') {
                            rawValue = __modelValue;
                        }
                        $watch('rawValue', value => {
                            try {
                                {{ $xModelBinding }} = value;
                            } catch (e) {}
                        });
                    @endif
                "
                @if($xModelBinding !== '')
                x-effect="
                    let __incoming = '';
                    try {
                        __incoming = {{ $xModelBinding }};
                    } catch (e) {
                        __incoming = '';
                    }
                    if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                        rawValue = (__incoming ?? '');
                    }
                "
                @endif
                x-on:click.away="open = false"
            >
                <input
                    x-ref="inputEl"
                    class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}"
                    autocomplete="{{ $autocomplete }}"
                    placeholder=" "
                    type="{{ $type }}"
                    name="{{ $name }}"
                    x-model="rawValue"
                    x-on:focus="if (@js($showSuggestionsOnFocus)) { hasTyped = true; refresh(); } else { open = false; }"
                    x-on:input="hasTyped = true; refresh()"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="confirm()"
                    x-on:keydown.escape.prevent="open = false"
                    x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                    @disabled($disabled)
                    {{ $attributes->except(['class', 'id', 'x-model', 'autocomplete']) }}
                />
                <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}</label>
                <div x-show="open" x-cloak class="absolute z-40 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
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
                <input class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}" autocomplete="{{ $autocomplete }}" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes->except(['autocomplete']) }} />
                <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}</label>
            </div>
        @endif
    @elseif($noLabel)
        <div class="relative">
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}" name="{{ $name }}" placeholder="{{ $label }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }}>{{ $value }}</textarea>
            @elseif($hasSuggestions)
                <div
                    class="relative"
                    x-data="{
                        rawValue: @js($autocompleteValue),
                        options: @js($suggestions),
                        moneyFormat: @js($moneyFormat),
                        hasTyped: false,
                        suppressRefresh: false,
                        filtered: [],
                        open: false,
                        selectedIndex: -1,
                        refresh() {
                            if (this.suppressRefresh) {
                                this.suppressRefresh = false;
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            if (!this.hasTyped) {
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            const needle = (this.rawValue || '').toLowerCase().trim();
                            if (needle === '') {
                                if (@js($showSuggestionsOnFocus)) {
                                    this.filtered = this.options.slice(0, 8);
                                    this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                                    this.open = this.filtered.length > 0;
                                    return;
                                }
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            const items = this.options.filter((option) => option.toLowerCase().includes(needle));
                            this.filtered = items.slice(0, 8);
                            this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                            this.open = this.filtered.length > 0;
                        },
                        move(step) {
                            if (!this.open) {
                                this.refresh();
                                return;
                            }
                            const len = this.filtered.length;
                            if (!len) {
                                return;
                            }
                            this.selectedIndex = (this.selectedIndex + step + len) % len;
                        },
                        choose(value) {
                            this.suppressRefresh = true;
                            this.rawValue = value;
                            this.open = false;
                            this.selectedIndex = -1;
                            this.$nextTick(() => {
                                if (!this.$refs.inputEl) {
                                    return;
                                }
                                this.$refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                                this.$refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        },
                        confirm() {
                            if (!this.open) {
                                return;
                            }
                            if (this.selectedIndex < 0 || this.selectedIndex >= this.filtered.length) {
                                this.open = false;
                                this.selectedIndex = -1;
                                return;
                            }
                            this.choose(this.filtered[this.selectedIndex]);
                        },
                        formatMoney() {
                            if (!this.moneyFormat) {
                                return;
                            }
                            const raw = String(this.rawValue || '').trim();
                            if (raw === '') {
                                return;
                            }
                            const amount = parseFloat(raw);
                            if (!Number.isFinite(amount) || amount < 0) {
                                this.rawValue = '';
                                return;
                            }
                            this.rawValue = amount.toFixed(2);
                        }
                    }"
                    x-init="
                        refresh();
                        @if($xModelBinding !== '')
                            let __modelValue = '';
                            try {
                                __modelValue = {{ $xModelBinding }};
                            } catch (e) {
                                __modelValue = '';
                            }
                            if (__modelValue !== null && __modelValue !== undefined && __modelValue !== '') {
                                rawValue = __modelValue;
                            }
                            $watch('rawValue', value => {
                                try {
                                    {{ $xModelBinding }} = value;
                                } catch (e) {}
                            });
                        @endif
                    "
                    @if($xModelBinding !== '')
                    x-effect="
                        let __incoming = '';
                        try {
                            __incoming = {{ $xModelBinding }};
                        } catch (e) {
                            __incoming = '';
                        }
                        if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                            rawValue = (__incoming ?? '');
                        }
                    "
                    @endif
                    x-on:click.away="open = false"
                >
                    <input
                        x-ref="inputEl"
                        class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}"
                        autocomplete="{{ $autocomplete }}"
                        placeholder="{{ $label }}"
                        type="{{ $type }}"
                        name="{{ $name }}"
                        x-model="rawValue"
                        x-on:focus="if (@js($showSuggestionsOnFocus)) { hasTyped = true; refresh(); } else { open = false; }"
                        x-on:input="hasTyped = true; refresh()"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="confirm()"
                        x-on:keydown.escape.prevent="open = false"
                        x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                        @disabled($disabled)
                        {{ $attributes->except(['x-model', 'autocomplete']) }}
                    />
                    <div x-show="open" x-cloak class="absolute z-40 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
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
                <input class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $label }}" value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes->except(['autocomplete']) }} />
            @endif
        </div>
    @else
        <div class="{{ ($inline ? 'flex items-center gap-2' : '') }}">
            <label for="{{ $name }}" class="block text-sm pl-1">{{ $label }}{!! isset($labelInfo) ? '<span class="text-xs text-gray-500 ml-1">' . $labelInfo . '</span>' : '' !!}{!! isset($labelNotice) && $labelNotice !== '' ? '<i class="fa-solid fa-triangle-exclamation ml-1 text-gray-500 hover:text-black" data-tooltip="' . $labelNotice . '"></i>' : '' !!}</label>
            @if($type === 'textarea')
                <textarea class="{{ twMerge(['pt-2.5','mt-1','h-28'], $classes, $fieldClasses) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes->whereDoesntStartWith('x-') }}>{{ $value }}</textarea>
            @elseif($hasSuggestions)
                <div
                    class="relative mt-1"
                    x-data="{
                        rawValue: @js($autocompleteValue),
                        options: @js($suggestions),
                        moneyFormat: @js($moneyFormat),
                        hasTyped: false,
                        suppressRefresh: false,
                        filtered: [],
                        open: false,
                        selectedIndex: -1,
                        refresh() {
                            if (this.suppressRefresh) {
                                this.suppressRefresh = false;
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            if (!this.hasTyped) {
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            const needle = (this.rawValue || '').toLowerCase().trim();
                            if (needle === '') {
                                if (@js($showSuggestionsOnFocus)) {
                                    this.filtered = this.options.slice(0, 8);
                                    this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                                    this.open = this.filtered.length > 0;
                                    return;
                                }
                                this.filtered = [];
                                this.selectedIndex = -1;
                                this.open = false;
                                return;
                            }
                            const items = this.options.filter((option) => option.toLowerCase().includes(needle));
                            this.filtered = items.slice(0, 8);
                            this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
                            this.open = this.filtered.length > 0;
                        },
                        move(step) {
                            if (!this.open) {
                                this.refresh();
                                return;
                            }
                            const len = this.filtered.length;
                            if (!len) {
                                return;
                            }
                            this.selectedIndex = (this.selectedIndex + step + len) % len;
                        },
                        choose(value) {
                            this.suppressRefresh = true;
                            this.rawValue = value;
                            this.open = false;
                            this.selectedIndex = -1;
                            this.$nextTick(() => {
                                if (!this.$refs.inputEl) {
                                    return;
                                }
                                this.$refs.inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                                this.$refs.inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        },
                        confirm() {
                            if (!this.open) {
                                return;
                            }
                            if (this.selectedIndex < 0 || this.selectedIndex >= this.filtered.length) {
                                this.open = false;
                                this.selectedIndex = -1;
                                return;
                            }
                            this.choose(this.filtered[this.selectedIndex]);
                        },
                        formatMoney() {
                            if (!this.moneyFormat) {
                                return;
                            }
                            const raw = String(this.rawValue || '').trim();
                            if (raw === '') {
                                return;
                            }
                            const amount = parseFloat(raw);
                            if (!Number.isFinite(amount) || amount < 0) {
                                this.rawValue = '';
                                return;
                            }
                            this.rawValue = amount.toFixed(2);
                        }
                    }"
                    x-init="
                        refresh();
                        @if($xModelBinding !== '')
                            let __modelValue = '';
                            try {
                                __modelValue = {{ $xModelBinding }};
                            } catch (e) {
                                __modelValue = '';
                            }
                            if (__modelValue !== null && __modelValue !== undefined && __modelValue !== '') {
                                rawValue = __modelValue;
                            }
                            $watch('rawValue', value => {
                                try {
                                    {{ $xModelBinding }} = value;
                                } catch (e) {}
                            });
                        @endif
                    "
                    @if($xModelBinding !== '')
                    x-effect="
                        let __incoming = '';
                        try {
                            __incoming = {{ $xModelBinding }};
                        } catch (e) {
                            __incoming = '';
                        }
                        if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                            rawValue = (__incoming ?? '');
                        }
                    "
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
                        x-on:focus="if (@js($showSuggestionsOnFocus)) { hasTyped = true; refresh(); } else { open = false; }"
                        x-on:input="hasTyped = true; refresh()"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="confirm()"
                        x-on:keydown.escape.prevent="open = false"
                        x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                        @disabled($disabled)
                        {{ $attributes->except(['x-model', 'autocomplete']) }}
                    />
                    <div x-show="open" x-cloak class="absolute z-40 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg overflow-hidden">
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
                <input class="{{ twMerge(['pt-2.5','mt-1'], $classes, $fieldClasses) }}" autocomplete="{{ $autocomplete }}" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes->except(['autocomplete']) }} />
            @endif
        </div>
    @endif
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $error }}</div>
    @endif
</div>
