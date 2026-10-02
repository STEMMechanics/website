@props(['isLocked' => false, 'invoiceLayout' => false])
@php
    $lineItemsWrapperClass = $invoiceLayout ? 'sm-invoice-line-items' : '';
    $lineItemsTableClass = $invoiceLayout ? 'sm-invoice-line-items-table min-w-[44rem] w-full' : 'min-w-[44rem] w-full';
@endphp
<div data-finance-line-items class="mt-4 mb-4 border-y border-slate-200 py-5" x-data="{
    mobileLineItemIndex: null,
    mobileWorkshopRows(item) {
        return item?.kind === 'multi_workshop' ? (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []) : [];
    },
    mobileWorkshopSummary(item) {
        return this.mobileWorkshopRows(item).map(row => String(row.description || '').trim()).filter(Boolean).join(', ');
    },
    openMobileLineItem(index) {
        this.mobileLineItemIndex = index;
        this.$nextTick(() => {
            const dialog = this.$refs.mobileLineItemDialog;
            if (dialog && !dialog.open) dialog.showModal();
        });
    },
    closeMobileLineItem() {
        const dialog = this.$refs.mobileLineItemDialog;
        if (dialog?.open) dialog.close();
        this.mobileLineItemIndex = null;
    },
}" x-init="lineItems.forEach(item => { SM.defaultWorkshopDescription(item); SM.initializeWorkshopNotes(item); }); serializeLineItems()">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="font-bold text-lg">Line Items</h3>
    </div>

    @if($errors->has('line_items_json'))
        <div class="mb-3 text-xs text-red-600">{{ $errors->first('line_items_json') }}</div>
    @endif
    <template x-if="lineItems.length === 0">
        <div class="text-sm text-gray-500">No line items yet.</div>
    </template>

    <x-ui.table variant="listing" class="{{ $lineItemsWrapperClass }}" table-class="{{ $lineItemsTableClass }}">
        <thead><tr><th>Description</th><th class="w-28 text-center whitespace-nowrap">HRS / QTY</th><th class="w-36 text-center whitespace-nowrap">Unit price (inc GST)</th><th class="w-16 text-center">GST</th><th class="w-28 text-center whitespace-nowrap">Total (inc GST)</th><th class="w-16 text-center">Actions</th></tr></thead>
        <template x-for="(item, index) in lineItems" :key="index">
            <tbody x-on:workshop-line-changed.stop="SM.updateWorkshopLine(item); serializeLineItems()" x-data="{ expanded: item.kind === 'product' || ({{ $isLocked ? 'false' : 'true' }} &amp;&amp; item.kind === 'workshop') }" class="[&>tr>td]:bg-white!">
                <tr>
                    <td data-label="Description" data-mobile-wide class="min-w-64">
                        <div class="flex items-center gap-2">
                            <x-ui.button href="#" role="button" variant="plain" class="flex h-11 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-sky-50 hover:text-primary-color" x-on:click.prevent="expanded = !expanded" x-on:keydown.space.prevent="expanded = !expanded" x-bind:aria-expanded="expanded" x-bind:aria-label="expanded ? 'Collapse details and notes' : 'Expand details and notes'">
                                <i class="fa-solid text-sm" x-bind:class="expanded ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                            </x-ui.button>
                        <x-finance.line-type-picker />
                        </div>
                    </td>
                    <td data-label="HRS / QTY"><div class="relative"><x-ui.input-control aria-label="Hours or quantity" type="number" step="any" class="h-11 pr-11!" x-model="item.quantity" x-bind:readonly="item.kind === 'multi_workshop' || item.kind === 'workshop' &amp;&amp; !!item.workshop_hours &amp;&amp; !!item.workshop_seats" x-on:input="if (item.kind === 'travel') { item.travel_hours = item.quantity; SM.updateWorkshopLine(item); } serializeLineItems()" /><x-finance.line-refresh /></div></td>
                    <td data-label="Unit price (inc GST)"><div class="relative"><span class="pointer-events-none absolute left-2 top-3">$</span><x-ui.input-control aria-label="Unit price including GST" type="number" step="any" class="h-11 pl-6! pr-11!" x-model="item.unit_price_inc_tax" x-on:input="item.auto_pricing = false; delete item.details_json.inclusive_unit_price; serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price_inc_tax')" /><x-finance.line-refresh :price="true" /></div></td>
                    <td data-label="GST" class="text-center"><x-ui.checkbox :bare="true" :small="true" aria-label="GST applies" x-model="item.gst_applicable" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" /></td>
                    <td data-label="Total (inc GST)" class="text-center whitespace-nowrap font-semibold">$<span x-text="normalizeMoney(SM.lineAmounts(item).gross)"></span></td>
                    <td data-label="Actions" data-mobile-actions class="text-center">@if(! $isLocked)<x-ui.row-action label="Remove line item" icon="fa-trash" tone="danger" x-on:click.prevent="removeLineItem(index)" />@endif</td>
                </tr>
                <tr x-show="expanded" x-cloak><td colspan="6" class="border-t-0! pt-0!">
                    <x-finance.line-item-details :invoice-layout="$invoiceLayout" class="ml-10" />
                </td></tr>
            </tbody>
        </template>
    </x-ui.table>
    @if($invoiceLayout)
        <template x-if="lineItems.length === 0">
            <div class="sm-invoice-mobile-empty text-sm text-gray-500">No line items yet.</div>
        </template>
        <div class="sm-invoice-mobile-line-items" x-show="lineItems.length > 0" x-cloak>
            <template x-for="(item, index) in lineItems" :key="'mobile-line-item-' + index">
                <article class="sm-invoice-mobile-line-item">
                    <div class="sm-invoice-mobile-line-item-header">
                        <div class="min-w-0">
                            <div class="break-words text-sm leading-snug text-slate-600" x-text="itemTypeLabel(item.kind)"></div>
                            <div x-show="String(item.description || '').trim() !== ''" class="mt-1 break-words text-sm leading-snug text-slate-500" x-text="item.description"></div>
                        </div>
                        <x-ui.button type="button" color="outline" class="shrink-0 gap-2 px-3! py-2!" x-on:click="openMobileLineItem(index)" x-bind:aria-label="'{{ $isLocked ? 'View' : 'Edit' }} line item ' + (index + 1)">
                            <i class="fa-solid {{ $isLocked ? 'fa-eye' : 'fa-pen-to-square' }}" aria-hidden="true"></i><span>{{ $isLocked ? 'View' : 'Edit' }}</span>
                        </x-ui.button>
                    </div>
                    <dl class="sm-invoice-mobile-line-item-totals">
                        <div><dt>HRS / QTY</dt><dd x-text="normalizeMoney(item.quantity)"></dd></div>
                        <div><dt>Unit price (inc GST)</dt><dd>$<span x-text="normalizeMoney(item.unit_price_inc_tax)"></span></dd></div>
                        <div><dt>GST</dt><dd x-text="item.gst_applicable ? 'Included' : 'Not applied'"></dd></div>
                        <div><dt>Total (inc GST)</dt><dd>$<span x-text="normalizeMoney(SM.lineAmounts(item).gross)"></span></dd></div>
                    </dl>
                    <template x-if="item.kind === 'multi_workshop'">
                        <div class="sm-invoice-mobile-workshop-summary">
                            <div class="flex items-center gap-2 font-semibold text-slate-800">
                                <i class="fa-solid fa-layer-group text-sky-600" aria-hidden="true"></i>
                                <span x-text="mobileWorkshopRows(item).length + (mobileWorkshopRows(item).length === 1 ? ' workshop' : ' workshops')"></span>
                            </div>
                            <p class="mt-1 break-words text-sm text-slate-600" x-show="mobileWorkshopSummary(item)" x-text="mobileWorkshopSummary(item)"></p>
                        </div>
                    </template>
                </article>
            </template>
        </div>
        <dialog x-ref="mobileLineItemDialog" class="sm-invoice-line-item-dialog" aria-labelledby="sm-invoice-line-item-dialog-title" x-on:cancel.prevent="closeMobileLineItem()" x-on:close="mobileLineItemIndex = null" x-on:click.self="closeMobileLineItem()">
            <div class="sm-invoice-line-item-dialog-header">
                <div class="min-w-0">
                    <h2 id="sm-invoice-line-item-dialog-title" class="break-words text-sm font-medium leading-snug text-slate-600" x-text="mobileLineItemIndex === null ? 'Line item' : itemTypeLabel(lineItems[mobileLineItemIndex]?.kind)"></h2>
                </div>
                <x-ui.button type="button" variant="plain" class="h-11 w-11 shrink-0 rounded-lg p-0! text-slate-500" x-on:click="closeMobileLineItem()" aria-label="Close line item editor"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
            </div>
            <template x-if="mobileLineItemIndex !== null">
                <div x-data="{
                    expanded: true,
                    get item() { return lineItems[mobileLineItemIndex]; },
                    get index() { return mobileLineItemIndex; },
                }" x-on:workshop-line-changed.stop="SM.updateWorkshopLine(item); serializeLineItems()" class="sm-invoice-line-item-dialog-content">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm">Item type</label>
                            <x-finance.line-type-picker />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-sm">HRS / QTY</label>
                                <div class="relative"><x-ui.input-control aria-label="Hours or quantity" type="number" step="any" class="h-11 pr-11!" x-model="item.quantity" x-bind:readonly="item.kind === 'multi_workshop' || item.kind === 'workshop' &amp;&amp; !!item.workshop_hours &amp;&amp; !!item.workshop_seats" x-on:input="if (item.kind === 'travel') { item.travel_hours = item.quantity; SM.updateWorkshopLine(item); } serializeLineItems()" /><x-finance.line-refresh /></div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">Unit price (inc GST)</label>
                                <div class="relative"><span class="pointer-events-none absolute left-2 top-3">$</span><x-ui.input-control aria-label="Unit price including GST" type="number" step="any" class="h-11 pl-6! pr-11!" x-model="item.unit_price_inc_tax" x-on:input="item.auto_pricing = false; delete item.details_json.inclusive_unit_price; serializeLineItems()" x-on:blur="normalizeLineItem(index, 'unit_price_inc_tax')" /><x-finance.line-refresh :price="true" /></div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">GST</label>
                                <x-ui.checkbox :bare="true" :small="true" aria-label="GST applies" x-model="item.gst_applicable" x-on:change="SM.updateWorkshopLine(item); serializeLineItems()" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">Total (inc GST)</label>
                                <div class="pt-2 text-lg font-semibold text-slate-900">$<span x-text="normalizeMoney(SM.lineAmounts(item).gross)"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 border-t border-slate-200 pt-5">
                        <x-finance.line-item-details :invoice-layout="true" />
                    </div>
                </div>
            </template>
            <div class="sm-invoice-line-item-dialog-footer">
                @if(! $isLocked)
                    <x-ui.button type="button" color="danger-outline" class="mr-auto" x-on:click="const index = mobileLineItemIndex; closeMobileLineItem(); removeLineItem(index)">Remove</x-ui.button>
                @endif
                <x-ui.button type="button" x-on:click="closeMobileLineItem()">{{ $isLocked ? 'Close' : 'Done' }}</x-ui.button>
            </div>
        </dialog>
    @endif
    @if(! $isLocked)
        <div class="mt-4 flex justify-end">
            <x-ui.button type="button" x-on:click.prevent="addLineItem()">Add Item</x-ui.button>
        </div>
    @endif
</div>
