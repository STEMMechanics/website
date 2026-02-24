<x-layout>
    <x-mast backRoute="account.invoice.show" :backRouteParams="['invoice' => $invoice]" :backTitle="'Invoice ' . $invoice->invoice_number">Invoice Receipts</x-mast>

    <x-container>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm"><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
            <div class="text-sm"><strong>Total:</strong> ${{ number_format((float) $invoice->total_amount, 2) }}</div>
        </div>

        <div class="flex my-4 items-center gap-4">
            <div class="flex-1">
                <x-ui.search name="search" label="Search Receipts" />
            </div>
        </div>

        @if($receipts->isEmpty())
            <x-none-found item="receipts" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>Receipt</th>
                    <th class="hidden md:table-cell">Type</th>
                    <th class="hidden lg:table-cell">Date</th>
                    <th class="hidden md:table-cell">Method</th>
                    <th>Amount</th>
                    <th class="hidden lg:table-cell">Invoices</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($receipts as $receipt)
                        @php
                            $isRefund = $receipt->isRefund();
                            $allocationSource = $isRefund && $receipt->refundOf
                                ? $receipt->refundOf->allocations
                                : $receipt->allocations;
                            $appliedInvoices = $allocationSource
                                ->map(fn ($allocation) => $allocation->invoice)
                                ->filter()
                                ->unique('id')
                                ->values();
                        @endphp
                        <tr>
                            <td>
                                <div class="font-medium">#{{ $receipt->id }}</div>
                                <div class="text-xs text-gray-500 md:hidden">{{ $isRefund ? 'Refund' : 'Payment' }} · {{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</div>
                                <div class="text-xs text-gray-500 lg:hidden">{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                                <div class="text-xs text-gray-500 lg:hidden">
                                    @if($appliedInvoices->isEmpty())
                                        Invoices: -
                                    @else
                                        Invoices: {{ $appliedInvoices->map(fn ($linkedInvoice) => (string) $linkedInvoice->invoice_number)->implode(', ') }}
                                    @endif
                                </div>
                            </td>
                            <td class="hidden md:table-cell">{{ $isRefund ? 'Refund' : 'Payment' }}</td>
                            <td class="hidden lg:table-cell">{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</td>
                            <td>
                                @if($isRefund)
                                    -${{ number_format((float) $receipt->total_amount, 2) }}
                                @else
                                    ${{ number_format((float) $receipt->total_amount, 2) }}
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">
                                @if($appliedInvoices->isEmpty())
                                    -
                                @else
                                    {{ $appliedInvoices->map(fn ($linkedInvoice) => (string) $linkedInvoice->invoice_number)->implode(', ') }}
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('account.invoice.receipt.show', ['invoice' => $invoice, 'payment' => $receipt]) }}" class="hover:text-primary-color" title="View online"><i class="fa-regular fa-eye"></i></a>
                                    <a href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt]) }}" target="_blank" class="hover:text-primary-color" title="View PDF"><i class="fa-regular fa-file-lines"></i></a>
                                    <a href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt, 'download' => 1]) }}" class="hover:text-primary-color" title="Download PDF"><i class="fa-solid fa-download"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $receipts->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
