@props(['name', 'options', 'value', 'label' => 'Period', 'showFilter' => true])
@php($items = collect($options)->map(fn ($title, $key) => ['title' => $title, 'active' => (string) $value === (string) $key, 'route' => request()->fullUrlWithQuery([$name => $key, 'page' => null])])->values()->all())
@isset($actions)
    <div class="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-stretch lg:gap-0 lg:border-b lg:border-slate-300">
        <x-ui.preset-views :items="$items" class="min-w-0 lg:flex-1 lg:border-b-0!" />
        <div class="flex shrink-0 items-center justify-end lg:pl-6">{{ $actions }}</div>
    </div>
@else
    <x-ui.preset-views :items="$items" />
@endisset
<div class="my-3 flex flex-wrap items-center gap-2">
    @if($showFilter)
    <x-ui.filter-chip :label="$label.': '.($options[$value] ?? $value)" :url="request()->fullUrlWithQuery([$name => array_key_first($options), 'page' => null])" />
    @endif
    {{ $slot }}
</div>
