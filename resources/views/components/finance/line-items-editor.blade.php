@props(['isLocked' => false])
<div data-finance-line-items class="mt-4 mb-4 border-y border-slate-200 py-5" x-init="lineItems.forEach(item => { SM.defaultWorkshopDescription(item); SM.initializeWorkshopNotes(item); }); serializeLineItems()">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="font-bold text-lg">Line Items</h3>
    </div>

    @if($errors->has('line_items_json'))
        <div class="mb-3 text-xs text-red-600">{{ $errors->first('line_items_json') }}</div>
    @endif
    <template x-if="lineItems.length === 0">
        <div class="text-sm text-gray-500">No line items yet.</div>
    </template>

    <x-ui.table variant="listing" table-class="min-w-[44rem] w-full">
        <thead><tr><th>Description</th><th class="w-28 text-center whitespace-nowrap">HRS / QTY</th><th class="w-36 text-center whitespace-nowrap">Unit price (inc GST)</th><th class="w-16 text-center">GST</th><th class="w-28 text-center whitespace-nowrap">Total (inc GST)</th><th class="w-16 text-center">Actions</th></tr></thead>
        <template x-for="(item, index) in lineItems" :key="index">
            <tbody x-data="{ expanded: item.kind === 'product' }" class="[&>tr>td]:bg-white!">
                <tr>
                    <td class="min-w-64">
                        <div class="flex items-center gap-2">
                            <x-ui.button href="#" role="button" variant="plain" class="flex h-11 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-sky-50 hover:text-primary-color" x-on:click.prevent="expanded = !expanded" x-on:keydown.space.prevent="expanded = !expanded" x-bind:aria-expanded="expanded" x-bind:aria-label="expanded ? 'Collapse details and notes' : 'Expand details and notes'">
                                <i class="fa-solid text-sm" x-bind:class="expanded ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                            </x-ui.button>
                        <x-finance.line-type-picker />
                        </div>
                    </td>
                    <td><div class="relative"><x-ui.input-control aria-label="Hours or quantity" type="number" step="any" class="h-11 pr-11!" x-model="item.quantity" x-bind:readonly="item.kind === 'multi_workshop' || item.kind === 'workshop' &amp;&amp; !!item.workshop_hours &amp;&amp; !!item.workshop_seats" x-on:input="if (item.kind === 'travel') { item.travel_hours = item.quantity; SM.updateWorkshopLine(item); } serializeLineItems()" /><x-finance.line-refresh /></div></td>
                    <td><div class="relative"><span class="pointer-events-none absolute left-2 top-3">$</span><x-ui.input-control aria-label="Unit price including GST" type="number" step="any" class="h-11 pl-6! pr-11!" x-model="item.unit_price_inc_tax" x-on:input="item.auto_pricing = false; delete item.details_json.inclusive_unit_price; serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price_inc_tax')" /><x-finance.line-refresh :price="true" /></div></td>
                    <td class="text-center"><x-ui.checkbox :bare="true" :small="true" aria-label="GST applies" x-model="item.gst_applicable" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    <td class="text-center whitespace-nowrap font-semibold">$<span x-text="normalizeMoney(SM.lineAmounts(item).gross)"></span></td>
                    <td class="text-center">@if(! $isLocked)<x-ui.row-action label="Remove line item" icon="fa-trash" tone="danger" x-on:click.prevent="removeLineItem(index)" />@endif</td>
                </tr>
                <tr x-show="expanded" x-cloak><td colspan="6" class="border-t-0! pt-0!">
                    <div class="ml-10">
                    <x-finance.product-line-fields />
                    <div class="mb-3"><x-ui.input label="Description" type="text" x-model="item.description" x-on:input="serializeLineItems()" /></div>
                    <x-finance.workshop-line-fields :inclusive="true" />
                    <div x-show="item.kind === 'workshop'" class="mt-3 max-w-xs"><x-ui.input label="Workshop date" type="date" x-model="item.workshop_date" x-on:change="serializeLineItems()" /></div>
                    <div class="flex items-center justify-between mt-4">
                        <label class="block text-sm">Line item notes</label>
                        <button type="button" class="text-sm text-sky-600 hover:text-sky-800" x-show="['workshop', 'multi_workshop'].includes(item.kind)" x-on:click="SM.refreshWorkshopNotes(item); serializeLineItems()" title="Regenerate notes from workshop data">↻ Refresh</button>
                    </div>
                    <x-ui.textarea-control aria-label="Line item notes" rows="4" class="mt-2 w-full resize-y" x-model="item.notes" x-on:input="SM.markWorkshopNotesEdited(item); serializeLineItems()" />
                    </div>
                </td></tr>
            </tbody>
        </template>
    </x-ui.table>
    @if(! $isLocked)
        <div class="mt-4 flex justify-end">
            <x-ui.button type="button" x-on:click.prevent="addLineItem()">Add Item</x-ui.button>
        </div>
    @endif
</div>
