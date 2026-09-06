@props([
'name' => null,
'bare' => false,
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
$resolvedId = $id ?: ($bare ? null : ($name ?: null));
$inputClass = $bare ? twMerge($inputClass, $attributes->get('class')) : $inputClass;
$isMixed = filter_var($mixed, FILTER_VALIDATE_BOOLEAN);
$hasBoundValue = $attributes->has('value') || $attributes->has('x-bind:value') || $attributes->has(':value');
$sizeClasses = $small
? ['h-6', 'min-w-6', 'w-6', 'rounded-md', 'text-xs']
: ['h-8', 'min-w-8', 'w-8', 'rounded-lg'];
$wrapperClasses = $noWrapper ? '' : 'mb-4';
$hasLabel = ($label !== null && $label !== '') || $labelHidden;
$containerClasses = twMerge([($inline ? 'inline-flex' : 'flex'), ($info ? 'items-start' : 'items-center'), ($noWrapper ? $attributes->get('class') : '')]);
@endphp

@if($bare)
    @include('components.ui.partials.checkbox-input')
@else
@if(!$noWrapper)
<div class="{{ twMerge([$wrapperClasses, $attributes->get('class')]) }}">
@endif
    @if($hasLabel)
        <label class="sm-ui-checkbox {{ $small ? 'small ' : '' }}{{ $containerClasses }}">
            @include('components.ui.partials.checkbox-input')

            <div>
                <div class="{{ twMerge(['text-sm','pl-2'], $small ? 'pl-1 text-xs' : '', $labelHidden ? 'sr-only' : '', $disabled ? 'text-gray-400 cursor-not-allowed' : '', $labelClass) }}">{{ $label ?? '' }}</div>
                @if($info)
                    <div class="text-xs pl-2 text-gray-500">{{ $info }}</div>
                @endif
            </div>
        </label>
    @else
        <div class="sm-ui-checkbox {{ $small ? 'small ' : '' }}{{ $containerClasses }}">
            @include('components.ui.partials.checkbox-input')
        </div>
    @endif
@if(!$noWrapper)
</div>
@endif

@endif
