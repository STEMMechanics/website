<x-layout>
    <x-mast backRoute="account.invoice.show" :backRouteParams="['invoice' => $invoice]" :backTitle="'Invoice ' . $invoice->invoice_number">Invoice Receipts</x-mast>

    <x-container>
        <x-ui.dynamic-list name="account-invoice-receipts">

        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm"><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
            <div class="text-sm"><strong>Total:</strong> {{ money((float) $invoice->total_amount) }}</div>
        </div>

        <div class="flex my-4 items-center gap-4">
            <div class="flex-1">
                <x-ui.search name="search" label="Search Receipts" />
            </div>
        </div>

        @if($receipts->isEmpty())
            <x-none-found item="receipts" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading label="Receipt" />
                    <x-ui.list-heading class="hidden md:table-cell text-center!" label="Type" />
                    <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Date" />
                    <x-ui.list-heading class="hidden md:table-cell" label="Method" />
                    <x-ui.list-heading class="text-center!" label="Amount" />
                    <x-ui.list-heading class="hidden lg:table-cell" label="Invoices" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
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
                                <div class="text-xs text-gray-500 lg:hidden"><x-ui.date-time>{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                <div class="text-xs text-gray-500 lg:hidden">
                                    @if($appliedInvoices->isEmpty())
                                        Invoices: -
                                    @else
                                        Invoices: {{ $appliedInvoices->map(fn ($linkedInvoice) => (string) $linkedInvoice->invoice_number)->implode(', ') }}
                                    @endif
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-center!">{{ $isRefund ? 'Refund' : 'Payment' }}</td>
                            <td class="hidden lg:table-cell text-center!"><x-ui.date-time>{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td class="hidden md:table-cell">{{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</td>
                            <td class="text-center!">{{ money($isRefund ? -((float) $receipt->total_amount) : (float) $receipt->total_amount) }}</td>
                            <td class="hidden lg:table-cell">
                                @if($appliedInvoices->isEmpty())
                                    -
                                @else
                                    {{ $appliedInvoices->map(fn ($linkedInvoice) => (string) $linkedInvoice->invoice_number)->implode(', ') }}
                                @endif
                            </td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="View online" icon="fa-regular fa-eye" tone="neutral" href="{{ route('account.invoice.receipt.show', ['invoice' => $invoice, 'payment' => $receipt]) }}" />
                                    <x-ui.row-action label="View PDF" icon="fa-regular fa-file-lines" tone="neutral" href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt]) }}" target="_blank" />
                                    <x-ui.row-action label="Download PDF" icon="fa-solid fa-download" tone="neutral" href="{{ route('account.invoice.receipt.pdf', ['invoice' => $invoice, 'payment' => $receipt, 'download' => 1]) }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            <x-ui.list-pagination :paginator="$receipts" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
