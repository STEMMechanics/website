<x-layout>
    <x-mast>My Payments</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="text-sm text-gray-700">
                Account Credit:
                <span class="ml-1 font-semibold text-indigo-700">{{ money((float) ($accountCredit ?? 0)) }}</span>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

        @if($payments->isEmpty())
            <x-none-found item="payments" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;">ID</th>
                    <th>Details</th>
                    <th class="hidden md:table-cell">Method</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th>Total</th>
                    <th class="hidden lg:table-cell">Invoices</th>
                    <th class="hidden lg:table-cell">Allocated</th>
                    <th class="hidden lg:table-cell">Unallocated</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($payments as $payment)
                        @php
                            $allocated = (float) ($payment->allocated_amount_sum ?? 0);
                            $unallocatedBeforeRefund = max(0, round(((float) $payment->total_amount) - $allocated, 2));
                            $unallocated = max(0, round($unallocatedBeforeRefund - (float) $payment->refunds->sum('total_amount'), 2));
                            $typeLabel = (string) ($payment->payment_method ?? '') === \App\Models\Payment::PAYMENT_METHOD_CREDIT
                                ? 'Credit'
                                : 'Payment';
                            $invoiceNumbers = $payment->allocations
                                ->map(function ($allocation) {
                                    if ($allocation->invoice) {
                                        return (string) $allocation->invoice->invoice_number;
                                    }

                                    if ($allocation->taxAdjustment?->invoice) {
                                        return (string) $allocation->taxAdjustment->invoice->invoice_number;
                                    }

                                    return null;
                                })
                                ->filter()
                                ->unique()
                                ->values();
                        @endphp
                        <tr>
                            <td>
                                <div class="whitespace-nowrap">#{{ $payment->id }}</div>
                            </td>
                            <td>
                                <div>{{ $payment->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="text-xs text-gray-600">{{ \App\Models\Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')) }} · {{ $typeLabel }}</div>
                                <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $invoiceNumbers->isNotEmpty() ? 'Invoice #'.$invoiceNumbers->implode(', Invoice #') : '-' }}</div>
                                <div class="lg:hidden text-xs text-gray-600">Alloc: {{ money((float) $allocated) }} · Unalloc: {{ money($unallocated) }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')) }}</td>
                            <td class="hidden md:table-cell">{{ $typeLabel }}</td>
                            <td>{{ money((float) $payment->total_amount) }}</td>
                            <td class="hidden lg:table-cell">{{ $invoiceNumbers->isNotEmpty() ? 'Invoice #'.$invoiceNumbers->implode(', Invoice #') : '-' }}</td>
                            <td class="hidden lg:table-cell">{{ money((float) $allocated) }}</td>
                            <td class="hidden lg:table-cell">{{ money($unallocated) }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('account.payment.receipt', $payment) }}" target="_blank" class="hover:text-primary-color" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                                    <a href="{{ route('account.payment.receipt', ['payment' => $payment, 'download' => 1]) }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                                </div>
                            </td>
                        </tr>
                        @foreach($payment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0) as $refund)
                            @php
                                $refundInvoiceNumbers = $refund->allocations
                                    ->map(function ($allocation) {
                                        if ($allocation->invoice) {
                                            return (string) $allocation->invoice->invoice_number;
                                        }

                                        if ($allocation->taxAdjustment?->invoice) {
                                            return (string) $allocation->taxAdjustment->invoice->invoice_number;
                                        }

                                        return null;
                                    })
                                    ->filter()
                                    ->unique()
                                    ->values();

                                if ($refundInvoiceNumbers->isEmpty()) {
                                    $refundInvoiceNumbers = $invoiceNumbers;
                                }
                            @endphp
                            <tr class="bg-gray-50">
                                <td>
                                    <div class="whitespace-nowrap">#{{ $refund->id }}</div>
                                </td>
                                <td>
                                    <div>↳ {{ $refund->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                    <div class="text-xs text-gray-600">{{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? '')) }} · Refund</div>
                                    <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $refundInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$refundInvoiceNumbers->implode(', Invoice #') : '-' }}</div>
                                </td>
                                <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? '')) }}</td>
                                <td class="hidden md:table-cell">Refund</td>
                                <td>{{ money(-((float) $refund->total_amount)) }}</td>
                                <td class="hidden lg:table-cell">{{ $refundInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$refundInvoiceNumbers->implode(', Invoice #') : '-' }}</td>
                                <td class="hidden lg:table-cell">-</td>
                                <td class="hidden lg:table-cell">-</td>
                                <td>
                                    <div class="flex justify-center gap-3">
                                        <a href="{{ route('account.payment.receipt', $refund) }}" target="_blank" class="hover:text-primary-color" title="View receipt"><i class="fa-regular fa-file-lines"></i></a>
                                        <a href="{{ route('account.payment.receipt', ['payment' => $refund, 'download' => 1]) }}" class="hover:text-primary-color" title="Download receipt"><i class="fa-solid fa-download"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $payments->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
