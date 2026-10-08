@php
    $workshopTabs = \App\Support\WorkshopNavigation::tabs($workshop);
    $workshopEndAt = $workshop->effectiveEndsAt() ?? $workshop->starts_at;
@endphp
<x-layout>
    <x-mast :title="$workshop->title" backRoute="admin.workshop.index" backTitle="Workshops" :tabs="$workshopTabs">
        Stock reconciliation
        <x-slot:description>@include('admin.workshop.partials.mast-context', ['workshop' => $workshop])</x-slot:description>
        <x-slot:actions>
            <x-admin.workshop-public-page-action :workshop="$workshop" />
        </x-slot:actions>
    </x-mast>

    <x-container class="py-5 sm:py-8">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-950">Reconcile stock used and returned</h2>
                <p class="mt-1 text-sm text-gray-600">Record what was used. Unused reserved stock returns to availability, regardless of attendance.</p>
            </div>
            @if($workshop->stock_reconciled_at)
                <x-ui.badge color="success" class="shrink-0">Stock reconciled</x-ui.badge>
            @elseif($canReconcileWorkshopStock)
                <x-ui.badge color="warning" class="shrink-0">Reconciliation needed</x-ui.badge>
            @endif
        </div>

        @if(! $workshop->stock_reconciled_at && ! $hasReconciliableStock)
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                No stock items are on this workshop’s pick list, so stock reconciliation is not needed. If you add stock items later, reconciliation will be available after the workshop ends.
            </div>
        @elseif(! $workshop->stock_reconciled_at && ! $canReconcileWorkshopStock)
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                @if(in_array((string) $workshop->status, ['draft', 'cancelled'], true))
                    Draft and cancelled workshops do not need stock reconciliation.
                @elseif($workshopEndAt === null)
                    Add an end date before stock can be reconciled.
                @else
                    Stock reconciliation will be available after {{ $workshopEndAt->format('j M Y, g:ia') }}.
                @endif
            </div>
        @endif

        @include('admin.workshop.partials.stock-reconciliation')
    </x-container>
</x-layout>
