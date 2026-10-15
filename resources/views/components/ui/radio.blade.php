@props([
    'name' => null,
    'label' => '',
    'value' => null,
    'checked' => false,
    'disabled' => false,
    'noWrapper' => false,
])

@php
    $checked = filter_var($checked, FILTER_VALIDATE_BOOLEAN);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $noWrapper = filter_var($noWrapper, FILTER_VALIDATE_BOOLEAN);
@endphp

<label class="sm-ui-radio inline-flex items-center {{ $noWrapper ? '' : 'mb-4' }} {{ $attributes->get('class') }}">
    <input
        type="radio"
        @if($name) name="{{ $name }}" @endif
        @if($value !== null) value="{{ $value }}" @endif
        @checked($checked)
        @disabled($disabled)
        class="sm-radio-input grid size-8 shrink-0 appearance-none place-items-center rounded-full border border-gray-300 bg-white focus:border-blue-600 focus:outline-none disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100"
        {{ $attributes->except('class') }}
    >
    <span class="pl-2 text-sm">{{ $label }}</span>
</label>
