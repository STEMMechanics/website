<x-layout>
    <x-mast>My Payments</x-mast>

    <x-container>
        <x-ui.dynamic-list name="account-payments">

        <div class="my-4 flex flex-wrap items-center gap-3">

                @if($accountCredit > 0.0001)
                    <div class="-mt-8 inline-flex items-center rounded-b-lg border border-emerald-200 bg-emerald-50 py-2 px-4 text-emerald-950 gap-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Account Credit</div>
                        <div class="font-semibold">{{ money($accountCredit ?? 0) }}</div>
                    </div>
                @endif




        </div>
        <x-ui.collection-controls class="my-5" />

        @if($payments->isEmpty())
            <x-none-found item="payments" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="id" class="whitespace-nowrap" style="overflow-wrap: normal; word-break: normal;" label="ID" />
                    <x-ui.list-heading field="received_on" label="Details" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Method" />
                    <x-ui.list-heading field="kind" class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading class="text-center!" label="Total" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Invoices" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Allocated" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Unallocated" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                <div><x-ui.date-time>{{ $payment->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                <div class="text-xs text-gray-600">{{ \App\Models\Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')) }} · {{ $typeLabel }}</div>
                                <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $invoiceNumbers->isNotEmpty() ? 'Invoice #'.$invoiceNumbers->implode(', Invoice #') : '-' }}</div>
                                <div class="lg:hidden text-xs text-gray-600">Alloc: {{ money((float) $allocated) }} · Unalloc: {{ money($unallocated) }}</div>
                            </td>
                            <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')) }}</td>
                            <td class="hidden md:table-cell text-center!">{{ $typeLabel }}</td>
                            <td class="text-center!">{{ money((float) $payment->total_amount) }}</td>
                            <td class="hidden lg:table-cell">{{ $invoiceNumbers->isNotEmpty() ? 'Invoice #'.$invoiceNumbers->implode(', Invoice #') : '-' }}</td>
                            <td class="hidden lg:table-cell">{{ money((float) $allocated) }}</td>
                            <td class="hidden lg:table-cell">{{ money($unallocated) }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ route('account.payment.receipt', $payment) }}" target="_blank" />
                                    <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ route('account.payment.receipt', ['payment' => $payment, 'download' => 1]) }}" />
                                </x-ui.row-actions>
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
                                    <div>↳ <x-ui.date-time>{{ $refund->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                    <div class="text-xs text-gray-600">{{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? '')) }} · Refund</div>
                                    <div class="lg:hidden text-xs text-gray-600 mt-1">{{ $refundInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$refundInvoiceNumbers->implode(', Invoice #') : '-' }}</div>
                                </td>
                                <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? '')) }}</td>
                                <td class="hidden md:table-cell text-center!">Refund</td>
                                <td class="text-center!">{{ money(-((float) $refund->total_amount)) }}</td>
                                <td class="hidden lg:table-cell">{{ $refundInvoiceNumbers->isNotEmpty() ? 'Invoice #'.$refundInvoiceNumbers->implode(', Invoice #') : '-' }}</td>
                                <td class="hidden lg:table-cell">-</td>
                                <td class="hidden lg:table-cell">-</td>
                                <td class="text-center!">
                                    <x-ui.row-actions>
                                        <x-ui.row-action label="View receipt" icon="fa-regular fa-file-lines" tone="neutral" href="{{ route('account.payment.receipt', $refund) }}" target="_blank" />
                                        <x-ui.row-action label="Download receipt" icon="fa-solid fa-download" tone="neutral" href="{{ route('account.payment.receipt', ['payment' => $refund, 'download' => 1]) }}" />
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$payments" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
