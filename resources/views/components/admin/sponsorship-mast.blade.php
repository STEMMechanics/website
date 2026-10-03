@props(['title' => null, 'description' => null])
@php
    $tabs = [
        [
            'title' => 'Sponsorships',
            'route' => route('admin.sponsorship.index'),
            'active' => request()->routeIs('admin.sponsorship.index', 'admin.sponsorship.records', 'admin.sponsorship.sponsors', 'admin.sponsorship.sponsor.*', 'admin.sponsorship.manual-support.*', 'admin.sponsorship.cancel'),
        ],
        [
            'title' => 'Options',
            'route' => route('admin.sponsorship.options'),
            'active' => request()->routeIs('admin.sponsorship.options', 'admin.sponsorship.projects', 'admin.sponsorship.project.*', 'admin.sponsorship.bitcoin.*', 'admin.sponsorship.option.*', 'admin.sponsorship.group.*', 'admin.sponsorship.checkout.*'),
        ],
    ];
@endphp

<x-mast :title="$title" :description="$description" :tabs="$tabs">
    {{ $slot }}
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-mast>
