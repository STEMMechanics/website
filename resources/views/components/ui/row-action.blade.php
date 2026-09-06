@props(['label', 'icon', 'tone' => 'neutral', 'href' => null])
@php
    $tones = [
        'primary' => 'bg-sky-50 text-primary-color hover:bg-sky-100',
        'neutral' => 'bg-slate-100 text-slate-600 hover:bg-slate-200',
        'warning' => 'bg-amber-50 text-amber-700 hover:bg-amber-100',
        'danger' => 'bg-rose-50 text-rose-700 hover:bg-rose-100',
    ];
@endphp
<x-ui.button variant="plain" :href="$href" :aria-label="$label" :title="$label"
    data-action-tone="{{ $tone }}"
    {{ $attributes->class(['sm-row-action inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm transition-colors', $tones[$tone] ?? $tones['neutral']]) }}>
    <i class="{{ str_contains($icon, 'fa-solid') || str_contains($icon, 'fa-regular') || str_contains($icon, 'fa-brands') ? $icon : 'fa-solid '.$icon }}" aria-hidden="true"></i>
<span class="sm-row-action-label hidden">{{ $label }}</span>
</x-ui.button>
