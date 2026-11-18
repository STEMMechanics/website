@props(['supplyRules', 'invoiceLayout' => false, 'isLocked' => false])
@php
    $workshopsWrapperClass = 'sm-finance-workshops'.($invoiceLayout ? ' sm-invoice-workshops' : '');
    $workshopsTableClass = 'sm-finance-workshops-table'.($invoiceLayout ? ' sm-invoice-workshops-table' : '').' w-full min-w-[40rem]';
@endphp
<div x-show="item.kind === 'multi_workshop'" class="space-y-3" x-init="$SM.hydrateMultiWorkshopLine(item)">
    <x-ui.table variant="listing" class="{{ $workshopsWrapperClass }}" table-class="{{ $workshopsTableClass }}">
        <thead><tr><th>Workshop</th><th class="w-40">Date</th><th class="w-24 text-center">Hours</th><th class="w-32 text-center">Seats</th><th x-cloak x-show="hasSuppliedCategories('workshop')">Supplied</th><th class="w-28 text-center">Actions</th></tr></thead>
        <tbody>
            <template x-for="(row, rowIndex) in item.workshops" :key="lineItemKey(row)">
                <tr>
                    <td data-label="Workshop" data-mobile-wide class="min-w-64" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''" x-on:change="commitWorkshopRow(item, row)">
                        <x-finance.workshop-funding-fields model="row" :allow-spacing="true" :allow-locations="true" />
                    </td>
                    <td data-label="Date" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)"><x-ui.input-control aria-label="Workshop date" type="date" x-model="row.workshop_date" x-on:change="handleLineChange(item)" /></div>
                    </td>
                    <td data-label="Hours" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)"><x-ui.input-control aria-label="Workshop hours" type="number" min="0.01" max="24" step="0.01" x-model="row.workshop_hours" x-on:change="handleLineChange(item)" /></div>
                    </td>
                    <td data-label="Seats" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)"><x-finance.workshop-seats-field model="row" /></div>
                    </td>
                    <td x-cloak x-show="hasSuppliedCategories('workshop')" data-label="Supplied" data-mobile-wide class="space-y-2" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        <div x-show="!$SM.isWorkshopSpacing(row)">
                            @foreach($supplyRules as $rule)
                                <div x-show="hasPlanCategory({{ \Illuminate\Support\Js::encode($rule['category_id']) }}, 'workshop')">
                                    <x-ui.checkbox :small="true" :noWrapper="true" :label="($rule['category_name'] ?? 'Cost centre').' supplied'" x-model="row.supplied_categories['{{ $rule['category_id'] }}']" x-bind:disabled="isLocked" x-on:change="handleLineChange(item)" />
                                </div>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Actions" data-mobile-actions class="{{ $invoiceLayout ? 'text-right sm-invoice-workshop-actions' : 'text-center' }}" x-bind:class="$SM.isWorkshopSpacing(row) ? 'bg-slate-50' : ''">
                        @if(! $isLocked)
                            <x-ui.row-actions :menu="false">
                                <x-ui.row-action label="Move up" icon="fa-arrow-up" x-bind:disabled="rowIndex === 0" x-on:click.prevent="moveWorkshopRowAndSave(item, rowIndex, -1)" />
                                <x-ui.row-action label="Move down" icon="fa-arrow-down" x-bind:disabled="rowIndex === item.workshops.length - 1" x-on:click.prevent="moveWorkshopRowAndSave(item, rowIndex, 1)" />
                                <x-ui.row-action label="Remove" icon="fa-trash" tone="danger" class="sm-invoice-remove-workshop" x-on:click.prevent="confirmRemoveLineItemRow(item, 'workshops', rowIndex)" />
                            </x-ui.row-actions>
                        @endif
                    </td>
                </tr>
            </template>
        </tbody>
    </x-ui.table>
    @if(! $isLocked)
        <div class="flex justify-end gap-2">
            <x-ui.row-action label="Add workshop" icon="fa-plus" tone="primary" x-on:click="addWorkshopRowAndSave(item)" />
        </div>
    @endif
</div>
