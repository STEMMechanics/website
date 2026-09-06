@props(['active' => 'all', 'title' => 'Site settings'])
<x-mast :tabs="[
    ['title' => 'Homepage', 'route' => route('admin.site_option.hero'), 'active' => $active === 'homepage'],
    ['title' => 'All settings', 'route' => route('admin.site_option.index'), 'active' => $active === 'all'],
]">
    {{ $title }}
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-mast>
