<form method="POST" action="{{ route('admin.shop.order.invoice.store', $order) }}" class="space-y-5">
    @csrf
    <input type="hidden" name="invoice_id" value="{{ $selectedInvoice->id }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 tabindex="-1" class="text-lg font-bold">Invoice {{ $selectedInvoice->invoice_number }} · {{ money($selectedInvoice->total_amount) }}</h3>
            <p class="mt-1 text-sm text-gray-600">{{ $selectedInvoice->billing_name }}@if($selectedInvoice->billing_company) · {{ $selectedInvoice->billing_company }}@endif</p>
            <p class="mt-1 text-sm text-gray-600">Review the items below. Invoice prices and payments stay as recorded.</p>
        </div>
        <x-ui.button color="outline" size="small" href="{{ route('admin.invoice.edit', $selectedInvoice) }}" target="_blank">Open invoice</x-ui.button>
    </div>
    <div class="space-y-3">
        @foreach($selectedInvoice->lines as $line)
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ $line->description }}</div>
                        <div class="mt-1 text-sm text-gray-600">Quantity {{ (float) $line->quantity }} · {{ money($line->line_total_inc_tax) }}</div>
                    </div>
                    <x-ui.badge color="gray">{{ ucfirst($line->kind) }}</x-ui.badge>
                </div>
                @if(in_array($line->kind, ['product', 'generic'], true) && (float) $line->quantity > 0)
                    <input type="hidden" name="items[{{ $line->id }}][invoice_line_id]" value="{{ $line->id }}">
                    @if($lineSelections[$line->id] !== '')
                        <input type="hidden" name="items[{{ $line->id }}][selection]" value="{{ $lineSelections[$line->id] }}">
                        <p class="mt-2 text-sm text-gray-600">Product to fulfil: {{ $productOptions[$lineSelections[$line->id]] ?? $line->description }}</p>
                    @else
                        <div class="mt-3">
                            <x-ui.select name="items[{{ $line->id }}][selection]" label="Product to fulfil" value="" info="Match this description to a product, or keep it as an invoice charge only.">
                                @foreach($productOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old('items.'.$line->id.'.selection', '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif
                @else
                    <p class="mt-2 text-sm text-gray-600">Included in the order total; no item to fulfil.</p>
                @endif
            </div>
        @endforeach
    </div>
    <x-ui.select name="shipment_plan" label="Shipping plan" value="same_shipment" info="Any additional postage must already be included on this invoice.">
        <option value="same_shipment">{{ $order->usesPickup() ? 'Collect with the existing order' : 'Send with the existing order' }}</option>
        @unless($order->usesPickup())
            <option value="separate_shipment">Send as a separate shipment</option>
        @endunless
    </x-ui.select>
    <div class="flex justify-end border-t border-gray-100 pt-4">
        <x-ui.button type="submit">Attach invoice and add items</x-ui.button>
    </div>
</form>
