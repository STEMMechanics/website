<x-layout>
    <x-mast>Tickets Confirmed</x-mast>

    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">Checkout Complete</h2>

                @php
                $paymentMethodLabel = match ((string) ($session['payment_method'] ?? '')) {
                'credit_card' => 'Credit Card',
                'pay_at_door' => 'Pay at Door',
                'bank_transfer' => 'Bank Transfer',
                'free' => 'Free',
                default => ucwords(str_replace('_', ' ', (string) ($session['payment_method'] ?? '-'))),
                };
                $summaryRows = [
                ['label' => 'Payment Method', 'value' => $paymentMethodLabel],
                ];
                if ($invoice) {
                $summaryRows[] = [
                'label' => 'Invoice',
                'value' => $invoice->invoice_number.' ('.ucfirst($invoice->status).')',
                ];
                }
                @endphp
                @include('workshop.tickets.partials.summary', [
                'workshop' => $workshop,
                'rows' => $summaryRows,
                ])

                @php($hasReceipt = isset($payment) && $payment instanceof \App\Models\Payment)

                @if($sentToEmail !== '')
                <div class="text-sm my-6">
                    <i class="fa-solid fa-check-circle text-green-600 mr-1"></i>
                    Your tickets have been emailed to <strong>{{ $sentToEmail }}</strong>.
                    @if(($holderRecipientCount ?? 0) > 0)
                    Ticket holder{{ (int) $holderRecipientCount === 1 ? '' : 's' }} with a different email {{ (int) $holderRecipientCount === 1 ? 'has' : 'have' }} also been sent their ticket PDF{{ (int) $holderRecipientCount === 1 ? '' : 's' }}.
                    @endif
                </div>
                @endif

                @if($tickets->isNotEmpty())
                <div class="border border-gray-300 rounded-lg">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm rounded-t-lg">
                                <th class="px-3 py-2">ID</th>
                                <th class="px-3 py-2">Ticket</th>
                                <th class="px-3 py-2 text-center">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                            <tr class="border-t border-t-gray-300 text-sm">
                                <td class="px-3 py-2">{{ $ticket->reference_code ?: $ticket->id }}</td>
                                <td class="px-3 py-2">{{ trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')) ?: '-' }}<br><span class="text-gray-500 text-xs">{{ $ticket->email ?: '-' }}</span></td>
                                <td class="px-3 py-2 text-center">
                                    <a
                                        href="{{ route('tickets.pdf', ['ticket' => $ticket, 'token' => $accessToken ?? null]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="hover:text-primary-color"
                                        title="Download Ticket PDF">
                                        <i class="fa-regular fa-file-pdf text-lg"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @if($invoice && $tickets->isNotEmpty())
                <div class="mt-6 text-sm leading-8">
                    <div><i class="fa-solid fa-angles-right text-xxs text-gray-500 mr-1"></i><a href="{{ route('tickets.invoice.pdf', ['ticket' => $tickets->first(), 'token' => $accessToken ?? null]) }}" target="_blank" rel="noopener noreferrer" class="hover:text-primary-color inline-block">View Invoice (PDF)</a></div>
                    @if($hasReceipt)
                        <div><i class="fa-solid fa-angles-right text-xxs text-gray-500 mr-1"></i><a href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $tickets->first(), 'payment' => $payment, 'token' => $accessToken ?? null]) }}" target="_blank" rel="noopener noreferrer" class="hover:text-primary-color inline-block">View Receipt (PDF)</a></div>
                    @endif
                </div>
                @endif

                <div class=" flex flex-wrap justify-between gap-3 mt-6">
                    <x-ui.button
                        type="link"
                        href="{{ route('workshop.ticket.flow.complete.download-all', $workshop) }}"
                        color="secondary"
                        target="_blank">
                        Download All
                    </x-ui.button>
                    <x-ui.button type="link" href="{{ route('workshop.show', $workshop) }}">Back to Workshop</x-ui.button>
                </div>
            </div>
            <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
