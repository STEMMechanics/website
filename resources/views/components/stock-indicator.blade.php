@props([
    'tone' => 'neutral',
    'label' => '',
])

@php
    $tone = (string) $tone;
    $classes = match ($tone) {
        'danger' => 'text-red-700',
        'warning' => 'text-amber-700',
        'success' => 'text-emerald-700',
        default => 'text-gray-500',
    };
    $icon = match ($tone) {
        'danger' => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        default => null,
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 text-xs font-medium', $classes]) }}>
    @if($icon)
        <i class="fa-solid {{ $icon }} text-[0.8em]"></i>
    @endif
    <span>{{ $label }}</span>
</span>
