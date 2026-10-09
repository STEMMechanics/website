<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="\App\Support\WorkshopNavigation::tabs($workshop)">
        <x-slot:description>@include('admin.workshop.partials.mast-context', ['workshop' => $workshop])</x-slot:description>
        <x-slot:actions><x-admin.workshop-public-page-action :workshop="$workshop" /></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-finance.panel title="Cost centre allocation">
            @include('admin.workshop.allocation-form')
        </x-finance.panel>
    </x-container>
</x-layout>
