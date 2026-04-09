@props(['text'])

@php
    $classes = twMerge([
        'sm-highlighter',
        'align-middle',
        'inline-block',
    ], $attributes->get('class'));
@endphp

<span class="{{ $classes }}" {{ $attributes->except(['class']) }}>
    {{ $text }}
</span>
