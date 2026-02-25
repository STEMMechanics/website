@props(['type' => 'text', 'name', 'label' => '', 'value' => '', 'floating' => false, 'noLabel' => false, 'readonly' => false, 'disabled' => false, 'info', 'error' => null, 'labelNotice' => null, 'placeholder' => '', 'fieldClasses' => '', 'suggestions' => [], 'moneyFormat' => false ])

@php
    if ($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $classes = 'disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 '.($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = old($name, $value);
    $moneyFormat = filter_var($moneyFormat, FILTER_VALIDATE_BOOLEAN);
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $suggestions = collect(is_array($suggestions) ? $suggestions : [])->map(fn ($item) => trim((string) $item))->filter(fn ($item) => $item !== '')->unique()->values()->all();
    $hasSuggestions = $type === 'text' && !filter_var($readonly, FILTER_VALIDATE_BOOLEAN) && ! $disabled && count($suggestions) > 0;
    $xModelBinding = trim((string) ($attributes->get('x-model') ?? ''));
    $isFileInput = $type === 'file';
    $inputId = (string) ($attributes->get('id') ?? $name);
    $fileUiUid = substr(md5($name.'-'.$inputId), 0, 12);
    $fileNameId = 'file-name-'.$fileUiUid;
    $fileMetaId = 'file-meta-'.$fileUiUid;
    $fileStateId = 'file-state-'.$fileUiUid;
    $fileClearId = 'file-clear-'.$fileUiUid;
    $maxUploadSize = \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize());
    $maxUploadBytes = (int) \App\Helpers::getMaxUploadSize();

    $autocompleteValue = (string) $value;
    $moneyFormatOnBlur = "const raw = String(this.value || '').trim(); if (raw === '') { return; } const amount = parseFloat(raw); if (!Number.isFinite(amount) || amount < 0) { this.value = ''; return; } this.value = amount.toFixed(2);";
@endphp

<div class="{{ twMerge(['mb-4'], $attributes->get('class')) }}">
    @if($isFileInput)
        @if(!$noLabel && !$floating)
            <label for="{{ $inputId }}" class="block text-sm pl-1">{{ $label }}</label>
        @endif

        <div class="{{ twMerge(['relative mt-1'], $fieldClasses) }}">
            <input
                type="file"
                name="{{ $name }}"
                id="{{ $inputId }}"
                class="sr-only peer"
                @disabled($disabled || $readonly)
                {{ $attributes->except(['class', 'id']) }}
            />

            <label
                for="{{ $inputId }}"
                class="{{ twMerge([
                    'group flex w-full cursor-pointer items-center justify-between gap-4 rounded-lg border bg-white px-4 py-3 text-left text-sm transition',
                    $hasError ? 'border-red-600' : 'border-gray-300',
                    ($disabled || $readonly) ? 'cursor-not-allowed bg-gray-100 text-gray-500' : 'hover:border-primary-color hover:bg-sky-50',
                ]) }}"
            >
                <div class="min-w-0 grow">
                    <div id="{{ $fileNameId }}" class="truncate font-medium text-gray-800">{{ $placeholder !== '' ? $placeholder : 'Choose a file' }}</div>
                    <div id="{{ $fileMetaId }}" class="mt-1 text-xs text-gray-500">Max upload size: {{ $maxUploadSize }}</div>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-md border border-primary-color px-3 py-1.5 text-xs font-semibold text-primary-color transition group-hover:bg-primary-color group-hover:text-white">Browse</span>
            </label>

            <div class="mt-2 flex items-center justify-between gap-3">
                <button type="button" id="{{ $fileClearId }}" class="hidden text-xs font-medium text-gray-500 hover:text-danger-color">
                    Clear file
                </button>
                <div id="{{ $fileStateId }}" class="hidden text-xs text-primary-color">
                    <span class="inline-flex items-center gap-2 rounded-full bg-primary-color/10 px-2.5 py-1 font-medium">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>Uploading...</span>
                    </span>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const input = document.getElementById(@js($inputId));
                const nameElement = document.getElementById(@js($fileNameId));
                const metaElement = document.getElementById(@js($fileMetaId));
                const stateElement = document.getElementById(@js($fileStateId));
                const clearElement = document.getElementById(@js($fileClearId));
                const placeholderText = @js($placeholder !== '' ? $placeholder : 'Choose a file');
                const defaultMetaText = @js('Max upload size: '.$maxUploadSize);
                const maxUploadBytes = Number(@js($maxUploadBytes));

                if (!input || !nameElement || !metaElement || !stateElement || !clearElement) {
                    return;
                }

                const resetUi = (metaText = defaultMetaText, hasError = false) => {
                    nameElement.textContent = placeholderText;
                    metaElement.textContent = metaText;
                    metaElement.classList.toggle('text-red-600', hasError);
                    metaElement.classList.toggle('text-gray-500', !hasError);
                    stateElement.classList.add('hidden');
                    clearElement.classList.add('hidden');
                };

                const clearFile = (triggerChange = true) => {
                    input.value = '';
                    input.setCustomValidity('');
                    resetUi();
                    if (triggerChange) {
                        input.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                };

                input.addEventListener('change', () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file) {
                        input.setCustomValidity('');
                        resetUi();
                        return;
                    }

                    if (Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && file.size > maxUploadBytes) {
                        const errorMessage = `File is too large. Maximum upload size is ${@js($maxUploadSize)}.`;
                        input.setCustomValidity(errorMessage);
                        input.reportValidity();
                        resetUi(errorMessage, true);
                        input.value = '';
                        return;
                    }

                    const sizeText = (window.SM && typeof window.SM.bytesToString === 'function')
                        ? window.SM.bytesToString(file.size)
                        : `${Math.round(file.size / 1024)} KB`;

                    input.setCustomValidity('');
                    nameElement.textContent = file.name;
                    metaElement.textContent = `${file.type || 'File'} - ${sizeText}`;
                    metaElement.classList.remove('text-red-600');
                    metaElement.classList.add('text-gray-500');
                    stateElement.classList.add('hidden');
                    clearElement.classList.remove('hidden');
                });

                clearElement.addEventListener('click', () => {
                    clearFile(true);
                });

                const parentForm = input.closest('form');
                if (!parentForm) {
                    return;
                }

                parentForm.addEventListener('submit', () => {
                    if (!input.files || input.files.length === 0) {
                        return;
                    }
                    stateElement.classList.remove('hidden');
                });
            });
        </script>
    @elseif($floating)
        @if($type === 'textarea')
            <div class="relative">
                <textarea class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }}>{{ $value }}</textarea>
                <label for="{{ $name }}" class="absolute text-sm text-gray-500 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-white px-2 peer-focus:px-2 peer-focus:text-blue-600 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 rtl:peer-focus:translate-x-1/4 rtl:peer-focus:left-auto start-1">{{ $label }}</label>
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
                x-effect="
                    @if($xModelBinding !== '')
                        let __incoming = '';
                        try {
                            __incoming = {{ $xModelBinding }};
                        } catch (e) {
                            __incoming = '';
                        }
                        if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                            rawValue = (__incoming ?? '');
                        }
                    @endif
                "
                x-on:click.away="open = false"
            >
                <input
                    x-ref="inputEl"
                    class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}"
                    autocomplete="off"
                    placeholder=" "
                    type="{{ $type }}"
                    name="{{ $name }}"
                    x-model="rawValue"
                    x-on:focus="open = false"
                    x-on:input="hasTyped = true; refresh()"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="confirm()"
                    x-on:keydown.escape.prevent="open = false"
                    x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                    @disabled($disabled)
                    {{ $attributes->except(['class', 'id', 'x-model']) }}
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
                <input class="{{ twMerge(['pt-4'], $classes, $attributes->get('fieldClasses')) }}" autocomplete="off" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }} />
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
                    x-effect="
                        @if($xModelBinding !== '')
                            let __incoming = '';
                            try {
                                __incoming = {{ $xModelBinding }};
                            } catch (e) {
                                __incoming = '';
                            }
                            if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                                rawValue = (__incoming ?? '');
                            }
                        @endif
                    "
                    x-on:click.away="open = false"
                >
                    <input
                        x-ref="inputEl"
                        class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}"
                        autocomplete="off"
                        placeholder="{{ $label }}"
                        type="{{ $type }}"
                        name="{{ $name }}"
                        x-model="rawValue"
                        x-on:focus="open = false"
                        x-on:input="hasTyped = true; refresh()"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="confirm()"
                        x-on:keydown.escape.prevent="open = false"
                        x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                        @disabled($disabled)
                        {{ $attributes->except(['x-model']) }}
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
                <input class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}" autocomplete="off" placeholder="{{ $label }}" value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }} />
            @endif
        </div>
    @else
        <div>
            <label for="{{ $name }}" class="block text-sm pl-1">{{ $label }}{!! isset($labelNotice) && $labelNotice !== '' ? '<i class="fa-solid fa-triangle-exclamation ml-1 text-gray-500 hover:text-black" data-tooltip="' . $labelNotice . '"></i>' : '' !!}</label>
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
                    x-effect="
                        @if($xModelBinding !== '')
                            let __incoming = '';
                            try {
                                __incoming = {{ $xModelBinding }};
                            } catch (e) {
                                __incoming = '';
                            }
                            if (!hasTyped && String(rawValue ?? '') !== String(__incoming ?? '')) {
                                rawValue = (__incoming ?? '');
                            }
                        @endif
                    "
                    x-on:click.away="open = false"
                >
                    <input
                        x-ref="inputEl"
                        class="{{ twMerge(['pt-2.5'], $classes, $fieldClasses) }}"
                        autocomplete="off"
                        placeholder=" "
                        type="{{ $type }}"
                        name="{{ $name }}"
                        x-model="rawValue"
                        x-on:focus="open = false"
                        x-on:input="hasTyped = true; refresh()"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="confirm()"
                        x-on:keydown.escape.prevent="open = false"
                        x-on:blur="formatMoney(); setTimeout(() => { open = false }, 120)"
                        @disabled($disabled)
                        {{ $attributes->except(['x-model']) }}
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
                <input class="{{ twMerge(['pt-2.5','mt-1'], $classes, $fieldClasses) }}" autocomplete="off" placeholder=" " value="{{ $value }}" type="{{ $type }}" name="{{ $name }}" @if($moneyFormat) onblur="{{ $moneyFormatOnBlur }}" @endif {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes }} />
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
