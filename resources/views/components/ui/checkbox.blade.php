@props([
'name' => null,
'id' => null,
'label' => null,
'checked' => false,
'small' => false,
'inline' => false,
'noWrapper' => false,
'labelHidden' => false,
'inputClass' => '',
'labelClass' => '',
'disabled' => false,
])

@php
$resolvedId = $id ?: ($name ?: null);
$sizeClasses = $small
? ['h-6', 'min-w-6', 'w-6', 'rounded-md', 'text-xs']
: ['h-8', 'min-w-8', 'w-8', 'rounded-lg'];
$wrapperClasses = $noWrapper ? '' : 'mb-4';
@endphp

<div class="{{ twMerge([$wrapperClasses, $attributes->get('class')]) }}">
    <div class="sm-ui-checkbox {{ $inline ? 'inline-flex' : 'flex' }} items-center">
        <input
            type="checkbox"
            @if($checked) checked @endif
            @if($disabled) disabled @endif
            @if($resolvedId) id="{{ $resolvedId }}" @endif
            @if($name) name="{{ $name }}" @endif
            class="{{ twMerge(['bg-white','mt-1','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300','disabled:bg-gray-100','disabled:border-gray-200','disabled:cursor-not-allowed'], $sizeClasses, $inputClass) }}"
            {{ $attributes->except('class') }} />

        @if(($label !== null && $label !== '') || $labelHidden)
        <label
            @if($resolvedId) for="{{ $resolvedId }}" @endif
            class="{{ twMerge(['text-sm','pl-2','pt-1'], $small ? 'pl-1 text-xs' : '', $labelHidden ? 'sr-only' : '', $disabled ? 'text-gray-400 cursor-not-allowed' : '', $labelClass) }}">{{ $label ?? '' }}</label>
        @endif
    </div>
</div>
