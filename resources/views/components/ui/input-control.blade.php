@props(['type' => 'text'])
@php
    // For fields embedded in table cells, steppers or an existing labelled wrapper.
    $base = $type === 'radio'
        ? 'h-5 w-5 shrink-0 border-gray-300 text-primary-color focus:ring-primary-color disabled:cursor-not-allowed'
        : 'block min-w-0 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-blue-600 disabled:bg-gray-100 disabled:cursor-not-allowed';
@endphp
<input type="{{ $type }}" class="{{ twMerge($base, $attributes->get('class')) }}" {{ $attributes->except('class') }} />
