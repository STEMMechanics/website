@props(['innerClass' => '', 'selectClass' => '', 'name', 'label', 'value' => '', 'readonly' => false, 'disabled' => false, 'info', 'error' => null, 'noLabel' => false, 'inlineLabel' => false])

@php
    if ($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $classes = 'disabled:bg-gray-100 bg-white w-full block px-2.5 pb-2.5 text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 '.($noLabel ? '' : 'mt-1 ').($hasError ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300');
    $value = old($name, $value);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $noLabel = filter_var($noLabel, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="{{ twMerge(['mb-4'], $inlineLabel ? ['flex', 'items-center'] : '', $attributes->get('class')) }} {{ $attributes->only('x-show') }}">
    @if(!$noLabel && !$inlineLabel)
        <div class="flex items-center justify-between mb-1">
                <label for="{{ $name }}" class="block text-sm pl-1">{{ $label }}</label>
                <div class="text-xs text-gray-500">{{ $labelRight ?? '' }}</div>
        </div>
    @elseif($inlineLabel)
        <label for="{{ $name }}" class="inline-block text-sm mr-3">{{ $label }}</label>
    @endif
    <div class="{{ twMerge(['relative'], $inlineLabel ? 'inline-block flex-1' : '', $innerClass) }}">
        <select class="{{ twMerge(['pt-2.5'], $classes, $selectClass) }}" name="{{ $name }}" {{ $readonly ? 'readonly' : '' }} @disabled($disabled) {{ $attributes->except(['x-show','style']) }}>
            {{ $slot }}
        </select>
        <i class="fa-solid fa-caret-down absolute text-gray-700 text-2xl right-3 bottom-2.25 pointer-events-none"></i>
    </div>
    @if(isset($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
    @if ($hasError)
        <div class="text-xs text-red-600 ml-2 mt-2">{{ $error }}</div>
    @endif
</div>
