<x-layout>
    @php
        $isClassroomAccess = $workshop->usesClassroomRegistration();
    @endphp
    <x-mast>{{ $isClassroomAccess ? 'Course Access Confirmed' : 'Tickets Confirmed' }}</x-mast>

    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">{{ $isClassroomAccess ? 'Course Access Complete' : 'Checkout Complete' }}</h2>

                @php
                $creditAppliedAmount = round((float) ($session['credit_applied_amount'] ?? 0), 2);
                $paymentAmount = isset($payment) && $payment instanceof \App\Models\Payment ? round((float) $payment->total_amount, 2) : 0.0;
                $paymentMethodLabel = match ((string) ($session['payment_method'] ?? '')) {
                'credit_card' => 'Credit Card',
                'pay_at_door' => 'Pay at Door',
                'bank_transfer' => 'Bank Transfer',
                'credit' => 'Account Credit',
                'free' => 'Free',
                default => ucwords(str_replace('_', ' ', (string) ($session['payment_method'] ?? '-'))),
                };
                if ($creditAppliedAmount > 0.0001 && (string) ($session['payment_method'] ?? '') !== 'credit') {
                $paymentMethodLabel = 'Account Credit + '.$paymentMethodLabel;
                }
                $summaryRows = [
                ['label' => 'Payment Method', 'value' => $paymentMethodLabel],
                ];
                if ($creditAppliedAmount > 0.0001) {
                $summaryRows[] = [
                'label' => 'Account Credit Applied',
                'value' => '$'.number_format($creditAppliedAmount, 2),
                ];
                if ($paymentAmount > 0.0001) {
                $summaryRows[] = [
                'label' => 'Card Charged',
                'value' => '$'.number_format($paymentAmount, 2),
                ];
                }
                }
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

                @php
                    $hasReceipt = isset($payment) && $payment instanceof \App\Models\Payment;
                @endphp

                @if($sentToEmail !== '')
                <div class="text-sm my-6">
                    <i class="fa-solid fa-check-circle text-green-600 mr-1"></i>
                    @if($hasReceipt)
                        @if($isClassroomAccess)
                            Your invoice, receipt, and course access details have been emailed to <strong>{{ $sentToEmail }}</strong>.
                        @else
                            Your invoice, receipt, and ticket{{ $tickets->count() === 1 ? '' : 's' }} have been emailed to <strong>{{ $sentToEmail }}</strong>.
                        @endif
                    @else
                        @if($isClassroomAccess)
                            Your invoice and course access details have been emailed to <strong>{{ $sentToEmail }}</strong>.
                        @else
                            Your invoice and ticket{{ $tickets->count() === 1 ? '' : 's' }} have been emailed to <strong>{{ $sentToEmail }}</strong>.
                        @endif
                    @endif
                </div>
                @endif

                @if($tickets->isNotEmpty())
                <div class="border border-gray-300 rounded-lg">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm rounded-t-lg">
                                <th class="px-3 py-2">ID</th>
                                <th class="px-3 py-2">{{ $isClassroomAccess ? 'Course Holder' : 'Ticket' }}</th>
                                @unless($isClassroomAccess)
                                    <th class="px-3 py-2 text-center">&nbsp;</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                            <tr class="border-t border-t-gray-300 text-sm">
                                <td class="px-3 py-2">{{ $ticket->reference_code ?: $ticket->id }}</td>
                                <td class="px-3 py-2">{{ trim(($ticket->firstname ?? '').' '.($ticket->surname ?? '')) ?: '-' }}<br><span class="text-gray-500 text-xs">{{ $ticket->email ?: '-' }}</span></td>
                                @unless($isClassroomAccess)
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
                                @endunless
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @if($invoice && $tickets->isNotEmpty())
                <div class="mt-6 text-sm leading-8">
                    <div><i class="fa-solid fa-angles-right text-xxs text-gray-500 mr-1"></i><a href="{{ route('tickets.invoice.pdf', ['ticket' => $tickets->first(), 'token' => $accessToken ?? null]) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-sky-900 inline-block">View Invoice (PDF)</a></div>
                    @if($hasReceipt)
                        <div><i class="fa-solid fa-angles-right text-xxs text-gray-500 mr-1"></i><a href="{{ route('tickets.invoice.receipt.pdf', ['ticket' => $tickets->first(), 'payment' => $payment, 'token' => $accessToken ?? null]) }}" target="_blank" rel="noopener noreferrer" class="text-primary-color hover:text-sky-900 inline-block">View Receipt (PDF)</a></div>
                    @endif
                </div>
                @endif

                <div class=" flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between">
                    @if(! $isClassroomAccess || $invoice)
                        <x-ui.button
                            href="{{ route('workshop.ticket.flow.complete.download-all', $workshop) }}"
                            color="secondary"
                            target="_blank">
                            {{ $isClassroomAccess ? 'Download Documents' : 'Download All' }}
                        </x-ui.button>
                    @else
                        <span></span>
                    @endif
                    <x-ui.button href="{{ route('workshop.show', $workshop) }}">Back to Workshop</x-ui.button>
                </div>
            </div>
            <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $workshop->hero?->url }}')"></div>
        </div>
    </x-container>
</x-layout>
