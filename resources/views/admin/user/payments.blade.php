@php
    $accountCredit = (float) ($accountCredit ?? 0);
    $cardRefundableCredit = (float) ($cardRefundableCredit ?? 0);
    $manualCredit = (float) ($manualCredit ?? max(0, round($accountCredit - $cardRefundableCredit, 2)));
@endphp

<x-layout>
    <x-mast backRoute="admin.user.edit" :backRouteParams="['user' => $user]" backTitle="User">Payments</x-mast>

    <x-container>
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account Credit</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950">{{ money($accountCredit) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Card-refundable</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950">{{ money($cardRefundableCredit) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Manual / Non-card</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950">{{ money($manualCredit) }}</div>
            </div>
        </div>

        @if($payments->isEmpty())
            <x-none-found item="payments" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th class="whitespace-nowrap">Payment</th>
                    <th>Details</th>
                    <th>Amounts</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($payments as $payment)
                        @php
                            $allocated = (float) ($payment->allocated_amount_sum ?? 0);
                            $unallocated = max(0, round(((float) $payment->total_amount) - $allocated - (float) $payment->refunds->sum('total_amount'), 2));
                            $squareRefundable = max(0, round((float) ($payment->card_refundable_amount ?? 0), 2));
                            $isRefundableSquare = $squareRefundable > 0.0001;
                            $reference = trim((string) ($payment->reference ?? ''));
                            $linkedInvoiceContexts = collect($payment->linked_invoice_contexts ?? []);
                        @endphp
                        <tr>
                            <td class="align-top">
                                <div class="whitespace-nowrap font-semibold">#{{ $payment->id }}</div>
                                <div class="text-xs text-gray-600">{{ $payment->received_on?->format('M j, Y g:i a') ?? '-' }}</div>
                            </td>
                            <td class="align-top">
                                <div>{{ \App\Models\Payment::paymentMethodLabel((string) ($payment->payment_method ?? '')) }}</div>
                                @if($reference !== '' && $linkedInvoiceContexts->isEmpty() && ! preg_match('/^Store order\s+\d+$/i', $reference))
                                    <div class="text-xs text-gray-600">{{ $reference }}</div>
                                @endif
                                @if($linkedInvoiceContexts->isNotEmpty())
                                    <div class="mt-1 space-y-1 text-xs text-gray-600">
                                        @foreach($linkedInvoiceContexts as $linkedInvoiceContext)
                                            @php
                                                $linkedInvoice = $linkedInvoiceContext['invoice'];
                                                $ticketSummary = trim((string) ($linkedInvoiceContext['ticket_summary'] ?? ''));
                                                $ticketLabel = trim((string) ($linkedInvoiceContext['ticket_label'] ?? 'Ticket'));
                                                $relationLabel = trim((string) ($linkedInvoiceContext['relation_label'] ?? 'Linked'));
                                            @endphp
                                                <div class="rounded-md border border-gray-100 bg-white px-2 py-1.5">
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <a href="{{ route('admin.invoice.edit', $linkedInvoice) }}" class="text-primary-color hover:underline">
                                                            #{{ $linkedInvoice->invoice_number }}
                                                        </a>
                                                        <span class="text-gray-400">|</span>
                                                        <span class="font-medium text-gray-800">{{ $relationLabel }}</span>
                                                        @if($linkedInvoice->storeOrders->isNotEmpty())
                                                            <span class="text-gray-400">|</span>
                                                            <span>Order:</span>
                                                            <span>
                                                                {{ $linkedInvoice->storeOrders->map(fn ($storeOrder) => '#'.$storeOrder->order_number)->implode(', ') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($ticketSummary !== '')
                                                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1">
                                                            <span>{{ $ticketLabel }}:</span>
                                                            <span>{{ $ticketSummary }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if($payment->refunds->isNotEmpty())
                                    <div class="mt-2 text-xs text-gray-600">
                                        Refunds:
                                        {{ $payment->refunds->map(fn ($refund) => '#'.$refund->id.' $'.number_format((float) $refund->total_amount, 2))->implode(', ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="align-top">
                                <div class="space-y-1 text-xs text-gray-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Total</span>
                                        <span class="font-semibold text-gray-950">{{ money((float) $payment->total_amount) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Allocated</span>
                                        <span>{{ money((float) $allocated) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Refundable by card</span>
                                        <span>{{ money($squareRefundable) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="align-top">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.payment.edit', $payment) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open payment">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span class="sr-only">Open payment</span>
                                    </a>
                                    <a href="{{ route('admin.payment.receipt', $payment) }}" target="_blank" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open receipt">
                                        <i class="fa-solid fa-receipt"></i>
                                        <span class="sr-only">Open receipt</span>
                                    </a>
                                    @if($isRefundableSquare)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.payment.square.refund', $payment) }}"
                                            x-data
                                            x-on:submit.prevent="SM.confirm('Refund card credit?', 'Refund {{ money($squareRefundable) }} to the customer using Square?', 'Refund', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })"
                                        >
                                            @csrf
                                            <input type="hidden" name="amount" value="{{ number_format($squareRefundable, 2, '.', '') }}">
                                            <input type="hidden" name="reason" value="Account credit refund">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-red-600 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-600 hover:text-white">Refund {{ money($squareRefundable) }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @foreach($payment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0) as $refund)
                            @php
                                $refundLinkedInvoiceContexts = collect($refund->linked_invoice_contexts ?? []);
                            @endphp
                            <tr class="bg-gray-50">
                                <td class="align-top">
                                    <div class="whitespace-nowrap font-semibold">#{{ $refund->id }}</div>
                                    <div class="text-xs text-gray-600">{{ $refund->received_on?->format('M j, Y g:i a') ?? $refund->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                </td>
                                <td class="align-top">
                                    <div>Refund</div>
                                    @if(trim((string) ($refund->reference ?? '')) !== '')
                                        <div class="text-xs text-gray-600">{{ (string) $refund->reference }}</div>
                                    @endif
                                    @if($refundLinkedInvoiceContexts->isNotEmpty())
                                        <div class="mt-1 space-y-1 text-xs text-gray-600">
                                            @foreach($refundLinkedInvoiceContexts as $refundInvoiceContext)
                                                @php
                                                    $refundInvoice = $refundInvoiceContext['invoice'];
                                                    $refundTicketSummary = trim((string) ($refundInvoiceContext['ticket_summary'] ?? ''));
                                                    $refundTicketLabel = trim((string) ($refundInvoiceContext['ticket_label'] ?? 'Ticket'));
                                                    $refundRelationLabel = trim((string) ($refundInvoiceContext['relation_label'] ?? 'Linked'));
                                                @endphp
                                                <div class="rounded-md border border-gray-100 bg-white px-2 py-1.5">
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <a href="{{ route('admin.invoice.edit', $refundInvoice) }}" class="text-primary-color hover:underline">
                                                            #{{ $refundInvoice->invoice_number }}
                                                        </a>
                                                        <span class="text-gray-400">|</span>
                                                        <span class="font-medium text-gray-800">{{ $refundRelationLabel }}</span>
                                                        @if($refundInvoice->storeOrders->isNotEmpty())
                                                            <span class="text-gray-400">|</span>
                                                            <span>Order:</span>
                                                            <span>
                                                                {{ $refundInvoice->storeOrders->map(fn ($storeOrder) => '#'.$storeOrder->order_number)->implode(', ') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($refundTicketSummary !== '')
                                                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1">
                                                            <span>{{ $refundTicketLabel }}:</span>
                                                            <span>{{ $refundTicketSummary }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="align-top">
                                    <div class="space-y-1 text-xs text-gray-700">
                                        <div class="flex items-center justify-between gap-3">
                                            <span>Total</span>
                                            <span class="font-semibold text-gray-950">{{ money(-((float) $refund->total_amount)) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.payment.edit', $refund) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open refund record">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            <span class="sr-only">Open refund record</span>
                                        </a>
                                        <a href="{{ route('admin.payment.receipt', $refund) }}" target="_blank" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open refund receipt">
                                            <i class="fa-solid fa-receipt"></i>
                                            <span class="sr-only">Open refund receipt</span>
                                        </a>
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
