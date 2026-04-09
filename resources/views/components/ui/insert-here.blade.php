@props([
    'text' => 'insert',
])

@php
    $wrapperClasses = twMerge([
        'sm-insert-here',
    ], $attributes->get('class'));
@endphp

<span class="{{ $wrapperClasses }}" {{ $attributes->except(['class']) }}>
    <span class="sm-insert-here__note">{{ $text }}</span>
    <span class="sm-insert-here__arrow" aria-hidden="true"></span>
</span>
