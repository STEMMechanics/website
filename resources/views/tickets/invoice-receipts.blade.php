<x-layout>
    <x-mast
        backUrl="{{ route('tickets.magic', ['token' => $accessToken]) }}"
        backTitle="Tickets"
    >
        Invoice Receipts
    </x-mast>

    <x-container>
        <x-ui.dynamic-list name="tickets-invoice-receipts">

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
                    <th class="text-center!">Type</th>
                    <th class="text-center!">Date</th>
                    <th>Method</th>
                    <th class="text-center!">Amount</th>
                    <th class="text-center!">Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($receipts as $receipt)
                        @php
                            $isRefund = $receipt->isRefund();
                        @endphp
                        <tr>
                            <td>{{ $receipt->id }}</td>
                            <td class="text-center!">{{ $isRefund ? 'Refund' : 'Payment' }}</td>
                            <td class="text-center!"><x-ui.date-time>{{ $receipt->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></td>
                            <td>{{ \App\Models\Payment::paymentMethodLabel((string) ($receipt->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</td>
                            <td class="text-center!">{{ money($isRefund ? -((float) $receipt->total_amount) : (float) $receipt->total_amount) }}</td>
                            <td class="text-center!">
                                <x-ui.row-actions>
                                    <x-ui.row-action label="View PDF" icon="fa-regular fa-file-lines" tone="neutral" href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $ticket, 'payment' => $receipt, 'token' => $accessToken]) }}" target="_blank" />
                                    <x-ui.row-action label="Download PDF" icon="fa-solid fa-download" tone="neutral" href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $ticket, 'payment' => $receipt, 'token' => $accessToken, 'download' => 1]) }}" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $receipts->appends(request()->query())->links() }}
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
