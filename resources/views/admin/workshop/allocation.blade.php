<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="\App\Support\WorkshopNavigation::tabs($workshop)">
        <x-slot:actions><x-ui.button color="mast" :href="route('workshop.show', $workshop)" target="_blank" rel="noopener noreferrer">View public page <i class="fa-solid fa-arrow-up-right-from-square ml-2" aria-hidden="true"></i></x-ui.button></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-finance.panel title="Cost centre allocation">
            @include('admin.workshop.allocation-form')
        </x-finance.panel>
    </x-container>
</x-layout>
