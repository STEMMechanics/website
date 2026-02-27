@php
    $refundQtyOld = old('refund_qty', []);
    if (! is_array($refundQtyOld)) {
        $refundQtyOld = [];
    }
    $lineCalcMeta = collect($refundableLines)->map(function ($row) use ($refundQtyOld) {
        $line = $row['line'];
        $lineId = (int) $line->id;
        $remainingQty = round((float) ($row['remaining_qty'] ?? 0), 2);
        $initialQty = isset($refundQtyOld[$lineId]) ? round((float) $refundQtyOld[$lineId], 2) : 0.0;

        return [
            'id' => $lineId,
            'unit_ex' => round(abs((float) ($line->unit_price_ex_tax ?? 0)), 2),
            'tax_rate' => round(max(0, (float) ($line->tax_rate ?? 0)), 4),
            'remaining_qty' => $remainingQty,
            'initial_qty' => max(0, min($remainingQty, $initialQty)),
        ];
    })->values()->all();
@endphp

<x-layout>
    <x-mast
        backRoute="admin.invoice.edit"
        :backRouteParams="['invoice' => $invoice]"
        backTitle="Invoice {{ $invoice->invoice_number }}"
    >Create Tax Adjustment Note</x-mast>

    <x-container class="mt-4">
        <div class="mb-4 rounded-lg border border-gray-200 p-3 text-sm">
            <div><strong>Invoice:</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Total:</strong> ${{ number_format((float) $invoice->total_amount, 2) }}</div>
            <div><strong>Max Refund Remaining:</strong> ${{ number_format((float) $maxAllowedCreditAmount, 2) }}</div>
        </div>

        <form method="POST"
              action="{{ route('admin.tax_adjustment.store', ['invoice' => $invoice]) }}"
              x-data="{
                lineMeta: @js($lineCalcMeta),
                refundQty: {},
                init() {
                    this.lineMeta.forEach((row) => {
                        this.refundQty[row.id] = Number(row.initial_qty || 0).toFixed(2);
                    });
                },
                parseQty(value, max) {
                    const parsed = parseFloat(value || 0);
                    if (!Number.isFinite(parsed) || parsed < 0) {
                        return 0;
                    }
                    return Math.min(parsed, Number(max || 0));
                },
                normalizeQty(lineId) {
                    const row = this.lineMeta.find((entry) => Number(entry.id) === Number(lineId));
                    if (!row) {
                        this.refundQty[lineId] = '0.00';
                        return;
                    }
                    this.refundQty[lineId] = this.parseQty(this.refundQty[lineId], row.remaining_qty).toFixed(2);
                },
                lineRefundEx(row) {
                    return this.parseQty(this.refundQty[row.id], row.remaining_qty) * Number(row.unit_ex || 0);
                },
                lineRefundGst(row) {
                    return this.lineRefundEx(row) * Number(row.tax_rate || 0);
                },
                lineRefundInc(row) {
                    return this.lineRefundEx(row) + this.lineRefundGst(row);
                },
                totalsEx() {
                    return this.lineMeta.reduce((sum, row) => sum + this.lineRefundEx(row), 0);
                },
                totalsGst() {
                    return this.lineMeta.reduce((sum, row) => sum + this.lineRefundGst(row), 0);
                },
                totalsInc() {
                    return this.lineMeta.reduce((sum, row) => sum + this.lineRefundInc(row), 0);
                },
                money(value) {
                    return Number(value || 0).toFixed(2);
                }
              }">
            @csrf
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-2 pr-3 pl-3">Line</th>
                        <th class="text-right py-2 pr-3">Original Qty</th>
                        <th class="text-right py-2 pr-3">Already Refunded</th>
                        <th class="text-right py-2 pr-3">Remaining</th>
                        <th class="text-right py-2 pr-3">Unit (Ex GST)</th>
                        <th class="text-right py-2 pr-3">Refund Qty Now</th>
                        <th class="text-right py-2 pr-3">Refund Ex GST</th>
                        <th class="text-right py-2 pr-3">Refund GST</th>
                        <th class="text-right py-2 pr-3">Refund Total (incl GST)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($refundableLines as $row)
                        @php
                            $line = $row['line'];
                            $remainingQty = (float) $row['remaining_qty'];
                            $lineUnitEx = round(abs((float) ($line->unit_price_ex_tax ?? 0)), 2);
                            $lineTaxRate = round(max(0, (float) ($line->tax_rate ?? 0)), 4);
                            $lineMeta = [
                                'id' => (int) $line->id,
                                'unit_ex' => $lineUnitEx,
                                'tax_rate' => $lineTaxRate,
                                'remaining_qty' => round($remainingQty, 2),
                            ];
                        @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-3 pl-3">
                                <div class="font-medium">{{ $line->description }}</div>
                                @if((string) ($line->notes ?? '') !== '')
                                    <div class="text-xs text-gray-600">{{ $line->notes }}</div>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right">{{ number_format((float) $row['original_qty'], 2) }}</td>
                            <td class="py-2 pr-3 text-right">{{ number_format((float) $row['refunded_qty'], 2) }}</td>
                            <td class="py-2 pr-3 text-right">{{ number_format($remainingQty, 2) }}</td>
                            <td class="py-2 pr-3 text-right">${{ number_format($lineUnitEx, 2) }}</td>
                            <td class="py-2 pr-3 text-right">
                                <input
                                    type="number"
                                    step="1.0"
                                    min="0"
                                    max="{{ number_format($remainingQty, 2, '.', '') }}"
                                    name="refund_qty[{{ $line->id }}]"
                                    value="{{ old('refund_qty.'.$line->id, '0.00') }}"
                                    x-model="refundQty[{{ (int) $line->id }}]"
                                    x-on:blur="normalizeQty({{ (int) $line->id }})"
                                    :disabled="@js($remainingQty <= 0.0001)"
                                    class="disabled:bg-gray-100 bg-white inline-block px-2.5 py-2 w-28 text-sm text-gray-900 rounded-lg border border-gray-300"
                                />
                            </td>
                            <td class="py-2 pr-3 text-right" x-data="{ row: @js($lineMeta) }">
                                $<span x-text="money(lineRefundEx(row))"></span>
                            </td>
                            <td class="py-2 pr-3 text-right" x-data="{ row: @js($lineMeta) }">
                                $<span x-text="money(lineRefundGst(row))"></span>
                            </td>
                            <td class="py-2 pr-3 text-right font-medium" x-data="{ row: @js($lineMeta) }">
                                $<span x-text="money(lineRefundInc(row))"></span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->has('refund_qty'))
                <div class="text-xs text-red-600 mt-2">{{ $errors->first('refund_qty') }}</div>
            @endif
            @if($errors->has('refund_qty.*'))
                <div class="text-xs text-red-600 mt-2">{{ $errors->first('refund_qty.*') }}</div>
            @endif

            <div class="mt-4 rounded-lg border border-gray-200 p-3 text-sm bg-gray-50">
                <div class="font-semibold mb-2">Refund Totals</div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><strong>Refund Ex GST:</strong> $<span x-text="money(totalsEx())"></span></div>
                    <div><strong>Refund GST:</strong> $<span x-text="money(totalsGst())"></span></div>
                    <div><strong>Total Refund (incl GST):</strong> $<span x-text="money(totalsInc())"></span></div>
                </div>
            </div>

            <div class="mt-3">
                <x-ui.input type="textarea" label="Adjustment Notes (optional)" name="reason" :value="old('reason', '')" />
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <x-ui.button type="link" color="secondary" href="{{ route('admin.invoice.edit', ['invoice' => $invoice]) }}">Cancel</x-ui.button>
                <x-ui.button type="submit" color="danger">Create Tax Adjustment Note</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
