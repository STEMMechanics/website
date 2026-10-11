@props(['label', 'icon', 'tone' => 'neutral', 'href' => null])
@php
    $colors = ['primary' => 'primary', 'neutral' => 'secondary', 'warning' => 'yellow', 'danger' => 'danger'];
@endphp
<x-ui.button variant="soft" size="icon" :color="$colors[$tone] ?? 'secondary'" :href="$href" :icon="$icon" :label="$label"
    data-action-tone="{{ $tone }}"
    {{ $attributes->class(['sm-row-action rounded-xl']) }}>
    <span class="sm-row-action-label hidden">{{ $label }}</span>
</x-ui.button>
