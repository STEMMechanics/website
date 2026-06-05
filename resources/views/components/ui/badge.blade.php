@props([
    'color' => null,
    'tone' => null,
    'variant' => 'soft',
    'size' => 'xs',
    'uppercase' => false,
    'icon' => null,
    'href' => null,
    'label' => null,
])

@php
    $color = trim((string) ($color ?? $tone ?? 'gray')) ?: 'gray';
    $variant = trim((string) $variant) ?: 'soft';
    $size = trim((string) $size) ?: 'xs';
    $uppercase = filter_var($uppercase, FILTER_VALIDATE_BOOLEAN);
    $iconClass = trim((string) ($icon ?? ''));
    $hasSlot = $slot->isNotEmpty();
    $labelText = trim((string) ($label ?? ''));
    $baseClasses = [
        'inline-flex',
        'items-center',
        'justify-center',
        'gap-1.5',
        'rounded-full',
        'border',
        'font-semibold',
        'whitespace-nowrap',
        'transition',
    ];

    if ($uppercase) {
        $baseClasses[] = 'uppercase';
        $baseClasses[] = 'tracking-wide';
    }

    $sizeMap = [
        'xs' => ['px-2.5', 'py-1', 'text-xs'],
        'sm' => ['px-3', 'py-1.5', 'text-sm'],
    ];

    $toneMap = [
        'gray' => [
            'soft' => ['border-gray-200', 'bg-gray-50', 'text-gray-700'],
            'solid' => ['border-gray-600', 'bg-gray-600', 'text-white'],
            'outline' => ['border-gray-300', 'bg-white', 'text-gray-700'],
        ],
        'slate' => [
            'soft' => ['border-slate-200', 'bg-slate-50', 'text-slate-700'],
            'solid' => ['border-slate-600', 'bg-slate-600', 'text-white'],
            'outline' => ['border-slate-300', 'bg-white', 'text-slate-700'],
        ],
        'primary' => [
            'soft' => ['border-primary-color/20', 'bg-primary-color/10', 'text-primary-color'],
            'solid' => ['border-primary-color', 'bg-primary-color', 'text-white'],
            'outline' => ['border-primary-color', 'bg-white', 'text-primary-color'],
        ],
        'success' => [
            'soft' => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800'],
            'solid' => ['border-emerald-600', 'bg-emerald-600', 'text-white'],
            'outline' => ['border-emerald-300', 'bg-white', 'text-emerald-700'],
        ],
        'warning' => [
            'soft' => ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
            'solid' => ['border-amber-500', 'bg-amber-500', 'text-white'],
            'outline' => ['border-amber-300', 'bg-white', 'text-amber-700'],
        ],
        'danger' => [
            'soft' => ['border-rose-200', 'bg-rose-50', 'text-rose-800'],
            'solid' => ['border-rose-600', 'bg-rose-600', 'text-white'],
            'outline' => ['border-rose-300', 'bg-white', 'text-rose-700'],
        ],
        'purple' => [
            'soft' => ['border-violet-200', 'bg-violet-50', 'text-violet-800'],
            'solid' => ['border-violet-600', 'bg-violet-600', 'text-white'],
            'outline' => ['border-violet-300', 'bg-white', 'text-violet-700'],
        ],
        'sky' => [
            'soft' => ['border-sky-200', 'bg-sky-50', 'text-sky-800'],
            'solid' => ['border-sky-600', 'bg-sky-600', 'text-white'],
            'outline' => ['border-sky-300', 'bg-white', 'text-sky-700'],
        ],
        'amber' => [
            'soft' => ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
            'solid' => ['border-amber-600', 'bg-amber-600', 'text-white'],
            'outline' => ['border-amber-300', 'bg-white', 'text-amber-700'],
        ],
        'emerald' => [
            'soft' => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800'],
            'solid' => ['border-emerald-600', 'bg-emerald-600', 'text-white'],
            'outline' => ['border-emerald-300', 'bg-white', 'text-emerald-700'],
        ],
        'rose' => [
            'soft' => ['border-rose-200', 'bg-rose-50', 'text-rose-800'],
            'solid' => ['border-rose-600', 'bg-rose-600', 'text-white'],
            'outline' => ['border-rose-300', 'bg-white', 'text-rose-700'],
        ],
        'orange' => [
            'soft' => ['border-orange-200', 'bg-orange-50', 'text-orange-700'],
            'solid' => ['border-orange-600', 'bg-orange-600', 'text-white'],
            'outline' => ['border-orange-300', 'bg-white', 'text-orange-700'],
        ],
    ];

    $toneClasses = $toneMap[$color][$variant] ?? $toneMap['gray'][$variant] ?? $toneMap['gray']['soft'];
    $classes = twMerge($baseClasses, $sizeMap[$size] ?? $sizeMap['xs'], $toneClasses, $attributes->get('class'));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        @if($iconClass !== '')
            <i class="{{ $iconClass }} text-[0.85em]"></i>
        @endif
        @if($hasSlot)
            {{ $slot }}
        @elseif($labelText !== '')
            <span>{{ $labelText }}</span>
        @endif
    </a>
@else
    <span {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        @if($iconClass !== '')
            <i class="{{ $iconClass }} text-[0.85em]"></i>
        @endif
        @if($hasSlot)
            {{ $slot }}
        @elseif($labelText !== '')
            <span>{{ $labelText }}</span>
        @endif
    </span>
@endif
