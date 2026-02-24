<x-layout>
    <x-mast
        backUrl="{{ route('tickets.magic', ['token' => $accessToken]) }}"
        backTitle="Tickets"
    >
        Invoice Receipts
    </x-mast>

    <x-container>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm"><strong>Ticket:</strong> {{ $ticket->reference_code ?: $ticket->id }}</div>
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
            <x-ui.table>
                <x-slot:header>
                    <th>Receipt #</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($receipts as $receipt)
                        @php
                            $isRefund = $receipt->isRefund();
                        @endphp
                        <tr>
                            <td>{{ $receipt->id }}</td>
                            <td>{{ $isRefund ? 'Refund' : 'Payment' }}</td>
                            <td>{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</td>
                            <td>{{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</td>
                            <td>{{ money($isRefund ? -((float) $receipt->total_amount) : (float) $receipt->total_amount) }}</td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $ticket, 'payment' => $receipt, 'token' => $accessToken]) }}" target="_blank" class="hover:text-primary-color" title="View PDF"><i class="fa-regular fa-file-lines"></i></a>
                                    <a href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $ticket, 'payment' => $receipt, 'token' => $accessToken, 'download' => 1]) }}" class="hover:text-primary-color" title="Download PDF"><i class="fa-solid fa-download"></i></a>
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
