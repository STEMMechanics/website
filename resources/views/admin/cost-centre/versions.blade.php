<x-layout>
    <x-finance.cost-centre-mast title="Allocation Plans">
        <x-slot:actions><x-ui.button color="mast" data-record-editor href="{{ route('admin.cost-centre.allocations', ['tab' => 'editor']) }}">Create</x-ui.button></x-slot:actions>
    </x-finance.cost-centre-mast>
    <x-container class="py-5 sm:py-8">
        <x-ui.dynamic-list name="allocation-versions" :showPresets="false"><x-ui.collection-controls label="Search allocation plans" />@include('admin.finance.pricing')
<x-ui.list-pagination :paginator="$versions" label="allocation plans" /></x-ui.dynamic-list>
    </x-container>
</x-layout>
