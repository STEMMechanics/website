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
'info' => '',
'mixed' => false,
])

@php
$resolvedId = $id ?: ($name ?: null);
$isMixed = filter_var($mixed, FILTER_VALIDATE_BOOLEAN);
$hasBoundValue = $attributes->has('value') || $attributes->has('x-bind:value') || $attributes->has(':value');
$sizeClasses = $small
? ['h-6', 'min-w-6', 'w-6', 'rounded-md', 'text-xs']
: ['h-8', 'min-w-8', 'w-8', 'rounded-lg'];
$wrapperClasses = $noWrapper ? '' : 'mb-4';
$hasLabel = ($label !== null && $label !== '') || $labelHidden;
$containerClasses = twMerge([($inline ? 'inline-flex' : 'flex'), ($info ? 'items-start' : 'items-center'), ($noWrapper ? $attributes->get('class') : '')]);
@endphp

@if(!$noWrapper)
<div class="{{ twMerge([$wrapperClasses, $attributes->get('class')]) }}">
@endif
    @if($hasLabel)
        <label class="sm-ui-checkbox {{ $small ? 'small ' : '' }}{{ $containerClasses }}">
            <input
                type="checkbox"
                @if($checked) checked @endif
                @if($disabled) disabled @endif
                @if($resolvedId) id="{{ $resolvedId }}" @endif
                @if($name) name="{{ $name }}" @endif
                @if(!$hasBoundValue) value="1" @endif
                @if($isMixed && !$attributes->has('aria-checked')) aria-checked="mixed" @endif
                @if($isMixed) data-indeterminate="true" @endif
                @if($isMixed && !$attributes->has('x-init')) x-init="$el.indeterminate = true" @endif
                class="{{ twMerge(['bg-white','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300','disabled:bg-gray-100','disabled:border-gray-200','disabled:cursor-not-allowed'], $sizeClasses, $inputClass) }}"
                {{ $attributes->except('class') }} />

            <div>
                <div class="{{ twMerge(['text-sm','pl-2'], $small ? 'pl-1 text-xs' : '', $labelHidden ? 'sr-only' : '', $disabled ? 'text-gray-400 cursor-not-allowed' : '', $labelClass) }}">{{ $label ?? '' }}</div>
                @if($info)
                    <div class="text-xs pl-2 text-gray-500">{{ $info }}</div>
                @endif
            </div>
        </label>
    @else
        <div class="sm-ui-checkbox {{ $small ? 'small ' : '' }}{{ $containerClasses }}">
            <input
                type="checkbox"
                @if($checked) checked @endif
                @if($disabled) disabled @endif
                @if($resolvedId) id="{{ $resolvedId }}" @endif
                @if($name) name="{{ $name }}" @endif
                @if(!$hasBoundValue) value="1" @endif
                @if($isMixed && !$attributes->has('aria-checked')) aria-checked="mixed" @endif
                @if($isMixed) data-indeterminate="true" @endif
                @if($isMixed && !$attributes->has('x-init')) x-init="$el.indeterminate = true" @endif
                class="{{ twMerge(['bg-white','border','border-gray-300','appearance-none','focus:outline-none','focus:ring-0','focus:border-blue-600','peer','focus:ring-indigo-300','disabled:bg-gray-100','disabled:border-gray-200','disabled:cursor-not-allowed'], $sizeClasses, $inputClass) }}"
                {{ $attributes->except('class') }} />
        </div>
    @endif
@if(!$noWrapper)
</div>
@endif
