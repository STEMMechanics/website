@props(['title' => 'Cost centres'])
<x-mast :title="$title" description="Manage allocated funds and workshop funding." :tabs="[
    ['title' => 'Cost centres', 'route' => route('admin.cost-centre.index'), 'active' => request()->routeIs('admin.cost-centre.index')],
    ['title' => 'Allocation Plans', 'route' => route('admin.cost-centre.allocations'), 'active' => request()->routeIs('admin.cost-centre.allocations')],
]">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-mast>
