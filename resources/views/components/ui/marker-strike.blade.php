@props(['text', 'top' => null, 'thick' => false])

@php
    $wrapperClasses = twMerge([
        'sm-marker-strike-wrap',
        'inline-block',
        'relative',
        'align-middle',
        'leading-none',
    ], $attributes->get('class'));

    $strikeClasses = twMerge([
        'sm-marker-strike',
        filter_var($thick, FILTER_VALIDATE_BOOLEAN) ? 'sm-marker-strike--thick' : '',
    ]);
@endphp

<span class="{{ $wrapperClasses }}" {{ $attributes->except(['class']) }}>
    @if(! is_null($top) && $top !== '')
        <span class="sm-marker-strike__top">{{ $top }}</span>
    @endif

    <span
        class="{{ $strikeClasses }}"
        data-text="{{ $text }}"
        aria-hidden="true"
    ></span>
</span>
