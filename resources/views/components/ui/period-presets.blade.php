@props(['name', 'options', 'value', 'label' => 'Period'])
@php($items = collect($options)->map(fn ($title, $key) => ['title' => $title, 'active' => (string) $value === (string) $key, 'route' => request()->fullUrlWithQuery([$name => $key, 'page' => null])])->values()->all())
<x-ui.preset-views :items="$items" />
<div class="my-3 flex flex-wrap items-center gap-2">
    <x-ui.filter-chip :label="$label.': '.($options[$value] ?? $value)" :url="request()->fullUrlWithQuery([$name => array_key_first($options), 'page' => null])" />
    {{ $slot }}
</div>
