@props(['supplyRules', 'invoiceLayout' => false, 'isLocked' => false])
@php
    $travelWrapperClass = 'sm-finance-workshops'.($invoiceLayout ? ' sm-invoice-workshops' : '');
    $travelTableClass = 'sm-finance-workshops-table'.($invoiceLayout ? ' sm-invoice-workshops-table' : '').' w-full min-w-[40rem]';
@endphp
<div x-show="item.kind === 'multi_travel'" class="space-y-3" x-init="$SM.hydrateMultiTravelLine(item)">
    <x-ui.table variant="listing" class="{{ $travelWrapperClass }}" table-class="{{ $travelTableClass }}">
        <thead><tr><th>Workshop / location</th><th class="w-40">Date</th><th class="w-32 text-center">Travel hours</th><th x-cloak x-show="hasSuppliedCategories('travel')">Supplied</th><th class="w-28 text-center">Actions</th></tr></thead>
        <tbody>
            <template x-for="(row, rowIndex) in item.travel_rows" :key="lineItemKey(row)">
                <tr>
                    <td data-label="Workshop / location" data-mobile-wide class="min-w-64" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''" x-on:change="commitWorkshopRow(item, row)">
                        <x-finance.workshop-funding-fields model="row" :billing-locked="$isLocked" :allow-spacing="true" :travel-row="true" />
                    </td>
                    <td data-label="Date" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)"><x-ui.input-control aria-label="Travel date" type="date" x-model="row.travel_date" x-on:change="handleLineChange(item)" /></div>
                    </td>
                    <td data-label="Travel hours" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)"><x-ui.input-control aria-label="Travel hours" type="number" min="0" max="2500" step="0.25" x-model="row.travel_hours" x-on:input="updateTravelHours(item, row)" /></div>
                    </td>
                    <td x-cloak x-show="hasSuppliedCategories('travel')" data-label="Supplied" data-mobile-wide class="space-y-2" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)">
                            @foreach($supplyRules as $rule)
                                <div x-show="hasPlanCategory(@js($rule['category_id']), 'travel')">
                                    <x-ui.checkbox :small="true" :noWrapper="true" :label="($rule['category_name'] ?? 'Cost centre').' supplied'" x-model="row.supplied_categories['{{ $rule['category_id'] }}']" x-bind:disabled="isLocked" x-on:change="handleLineChange(item)" />
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Actions" data-mobile-actions class="{{ $invoiceLayout ? 'text-right sm-invoice-workshop-actions' : 'text-center' }}" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        @if(! $isLocked)
                            <x-ui.row-actions :menu="false">
                                <x-ui.row-action label="Move up" icon="fa-arrow-up" x-bind:disabled="rowIndex === 0" x-on:click.prevent="moveWorkshopRowAndSave(item, rowIndex, -1)" />
                                <x-ui.row-action label="Move down" icon="fa-arrow-down" x-bind:disabled="rowIndex === item.travel_rows.length - 1" x-on:click.prevent="moveWorkshopRowAndSave(item, rowIndex, 1)" />
                                <x-ui.row-action label="Remove" icon="fa-trash" tone="danger" class="sm-invoice-remove-workshop" x-on:click.prevent="confirmRemoveLineItemRow(item, 'travel_rows', rowIndex)" />
                            </x-ui.row-actions>
                        @endif
                    </td>
                </tr>
            </template>
        </tbody>
    </x-ui.table>
    @if(! $isLocked)
        <div class="flex justify-end"><x-ui.row-action label="Add travel row" icon="fa-plus" tone="primary" x-on:click="addTravelRowAndSave(item)" /></div>
    @endif
</div>
