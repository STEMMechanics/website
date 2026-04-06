@props([
    'label' => '',
    'iconClass' => 'fa-solid fa-tag',
    'href' => null,
])

@php
    $label = trim((string) $label);
    $iconClass = trim((string) $iconClass) !== '' ? trim((string) $iconClass) : 'fa-solid fa-tag';
    $classes = 'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-200';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i class="{{ $iconClass }} text-[0.7em] text-gray-500"></i>
        <span>{{ $label }}</span>
    </a>
@else
    <span {{ $attributes->merge(['class' => $classes]) }}>
        <i class="{{ $iconClass }} text-[0.7em] text-gray-500"></i>
        <span>{{ $label }}</span>
    </span>
@endif
