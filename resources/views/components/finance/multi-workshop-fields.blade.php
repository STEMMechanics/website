@props(['supplyRules', 'supplyCategories', 'invoiceLayout' => false])
@php
    $workshopsWrapperClass = $invoiceLayout ? 'sm-invoice-workshops' : '';
    $workshopsTableClass = $invoiceLayout ? 'sm-invoice-workshops-table w-full min-w-[40rem]' : 'w-full min-w-[40rem]';
@endphp
<div x-show="item.kind === 'multi_workshop'" class="space-y-3" x-init="item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []))">
    <x-ui.table variant="listing" class="{{ $workshopsWrapperClass }}" table-class="{{ $workshopsTableClass }}">
        <thead><tr><th>Workshop</th><th class="w-40">Date</th><th class="w-24 text-center">Hours</th><th class="w-32 text-center">Seats</th><th>Supplied</th><th class="w-14 text-center">Actions</th></tr></thead>
        <tbody>
            <template x-for="(row, rowIndex) in item.workshops" :key="rowIndex">
                <tr>
                    <td data-label="Workshop" data-mobile-wide class="min-w-64"><x-finance.workshop-funding-fields model="row" /></td>
                    <td data-label="Date"><x-ui.input-control aria-label="Workshop date" type="date" x-model="row.workshop_date" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    <td data-label="Hours"><x-ui.input-control aria-label="Workshop hours" type="number" min="0.01" max="24" step="0.01" x-model="row.workshop_hours" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    <td data-label="Seats"><x-finance.workshop-seats-field model="row" /></td>
                    <td data-label="Supplied" data-mobile-wide class="space-y-2">
                        @foreach($supplyRules->where('basis', '!=', 'travel') as $rule)
                            <div x-init="row.supplied_categories ??= {}; row.supplied_categories['{{ $rule['category_id'] }}'] ??= {{ ($rule['venue_default'] ?? false) || $rule['basis'] === 'venue_hour' ? '!!row.venue_supplied' : 'false' }}">
                                <x-ui.checkbox :small="true" :noWrapper="true" :label="$supplyCategories[$rule['category_id']] ?? 'Cost centre'" x-model="row.supplied_categories['{{ $rule['category_id'] }}']" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" />
                            </div>
                        @endforeach
                    </td>
                    @if($invoiceLayout)
                        <td data-mobile-actions class="text-right sm-invoice-workshop-actions"><x-ui.row-action label="Remove workshop" icon="fa-trash" tone="danger" class="sm-invoice-remove-workshop" x-on:click.prevent="item.workshops.splice(rowIndex, 1); SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    @else
                        <td data-label="Actions" data-mobile-actions class="text-center"><x-ui.row-action label="Remove workshop" icon="fa-trash" tone="danger" x-on:click.prevent="item.workshops.splice(rowIndex, 1); SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    @endif
                </tr>
            </template>
        </tbody>
    </x-ui.table>
    <div class="flex justify-end"><x-ui.row-action label="Add workshop" icon="fa-plus" tone="primary" x-on:click="SM.addWorkshopRow(item); SM.updateWorkshopLine(item); serializeLineItems()" /></div>
</div>
