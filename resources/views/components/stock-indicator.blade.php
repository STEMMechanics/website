@props([
    'tone' => 'neutral',
    'label' => '',
    'stackDetails' => false,
])

@php
    $parts = $stackDetails && preg_match('/^(Available to order|Low stock)\. (.+)$/', $label, $matches) ? [$matches[1], $matches[2]] : [$label];
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
    <span>
        {{ $parts[0] }}
        @if(isset($parts[1]))
            <span class="block">{{ $parts[1] }}</span>
        @endif
    </span>
</span>
