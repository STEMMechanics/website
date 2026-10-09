<x-layout>
    <x-mast :title="$workshop->title" :description="new \Illuminate\Support\HtmlString(view('admin.workshop.partials.mast-context', ['workshop' => $workshop])->render())" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="\App\Support\WorkshopNavigation::tabs($workshop)">
        <x-slot:actions><x-admin.workshop-public-page-action :workshop="$workshop" /></x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <x-finance.panel title="Cost centre allocation">
            @include('admin.workshop.allocation-form')
        </x-finance.panel>
    </x-container>
</x-layout>
