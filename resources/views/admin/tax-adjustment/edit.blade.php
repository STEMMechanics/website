<x-layout>
    <x-mast
        backRoute="admin.invoice.edit"
        :backRouteParams="['invoice' => $invoice]"
        backTitle="Invoice {{ $invoice->invoice_number }}"
    >Edit Tax Adjustment {{ $taxAdjustment->adjustment_number }}</x-mast>

    <x-container class="mt-4">
        <div class="mb-4 flex justify-end">
            <x-ui.button type="button" x-data x-on:click.prevent="window.open('{{ route('admin.tax_adjustment.pdf', ['invoice' => $invoice, 'taxAdjustment' => $taxAdjustment]) }}', '_blank', 'noopener,noreferrer')">Open PDF</x-ui.button>
        </div>

        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
            Tax adjustments are immutable once created.
        </div>

            <div class="mb-4 rounded-lg border border-gray-200 p-3">
                <div class="font-semibold mb-2">Refunded Line Items</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 pr-3">Description</th>
                                <th class="text-right py-2 pr-3">Qty</th>
                                <th class="text-right py-2 pr-3">Unit (Ex GST)</th>
                                <th class="text-right py-2">Total (incl GST)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($taxAdjustment->lines as $line)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 pr-3">{{ $line->description }}</td>
                                    <td class="py-2 pr-3 text-right">{{ number_format((float) $line->quantity, 2) }}</td>
                                    <td class="py-2 pr-3 text-right">-${{ number_format(abs((float) $line->unit_price_ex_tax), 2) }}</td>
                                    <td class="py-2 text-right">-${{ number_format(abs((float) $line->line_total_inc_tax), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-2 text-gray-600">No refunded line items recorded for this adjustment.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <x-ui.input label="Adjustment Number" name="adjustment_number_display" :value="$taxAdjustment->adjustment_number" readonly="true" />

            <x-ui.input
                type="date"
                label="Issue Date"
                name="issue_date"
                :value="old('issue_date', optional($taxAdjustment->issue_date)->format('Y-m-d'))"
                readonly="true"
            />

            <div class="mb-3 text-xs text-gray-600">
                Tax adjustments are recorded as customer credit notes.
            </div>
            <div class="flex gap-8">
                <div class="flex-1">
                    <x-ui.input
                        type="number"
                        step="0.01"
                        label="Subtotal (Ex GST)"
                        name="subtotal_amount"
                        :value="old('subtotal_amount', number_format(abs((float) $taxAdjustment->subtotal_amount), 2, '.', ''))"
                        :moneyFormat="true"
                        readonly="true"
                    />
                </div>
                <div class="flex-1">
                    <x-ui.input
                        type="number"
                        step="0.01"
                        label="GST Amount"
                        name="gst_amount"
                        :value="old('gst_amount', number_format(abs((float) $taxAdjustment->gst_amount), 2, '.', ''))"
                        :moneyFormat="true"
                        readonly="true"
                    />
                </div>
            </div>
            <x-ui.input
                    type="number"
                    step="0.01"
                    label="Total Credit Amount (incl GST)"
                    name="total_amount"
                    :value="old('total_amount', number_format(abs((float) $taxAdjustment->total_amount), 2, '.', ''))"
                    :moneyFormat="true"
                    readonly="true"
            />
            <x-ui.input type="textarea" label="Notes" name="notes" :value="old('notes', (string) ($taxAdjustment->notes ?? ''))" readonly="true" />
    </x-container>
</x-layout>
