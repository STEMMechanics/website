@php
    $accountCredit = (float) ($accountCredit ?? 0);
    $cardRefundableCredit = (float) ($cardRefundableCredit ?? 0);
    $manualCredit = (float) ($manualCredit ?? max(0, round($accountCredit - $cardRefundableCredit, 2)));
@endphp

<x-layout>
    <x-mast backRoute="admin.user.edit" :backRouteParams="['user' => $user]" backTitle="User">Payments</x-mast>

    <x-container class="mt-4">
        <x-ui.dynamic-list name="admin-user-payments">
        <x-ui.collection-controls class="my-4" />

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
            <x-ui.table variant="listing">
                <x-slot:header>
                    <x-ui.list-heading field="id" class="whitespace-nowrap" label="Payment" />
                    <x-ui.list-heading field="received_on" label="Details" />
                    <x-ui.list-heading field="total_amount" class="text-center!" label="Amounts" />
                    <x-ui.list-heading class="text-center!" label="Actions" />
                </x-slot:header>
                <x-slot:body>
                    @foreach($payments as $payment)
                        @php
                            $allocated = (float) ($payment->allocated_amount_sum ?? 0);
                            $unallocated = max(0, round(((float) $payment->total_amount) - $allocated - (float) $payment->refunds->sum('total_amount'), 2));
                            $squareRefundable = max(0, round((float) ($payment->card_refundable_amount ?? 0), 2));
                            $isRefundableSquare = $squareRefundable > 0.0001;
                            $isCreditGrant = (string) ($payment->payment_method ?? '') === \App\Models\Payment::PAYMENT_METHOD_CREDIT;
                            $creditCashOutAmount = $isCreditGrant ? $unallocated : 0.0;
                            $canCashOutCredit = $isCreditGrant && $creditCashOutAmount > 0.0001;
                            $reference = trim((string) ($payment->reference ?? ''));
                            $linkedInvoiceContexts = collect($payment->linked_invoice_contexts ?? []);
                        @endphp
                        <tr>
                            <td class="align-top">
                                <div class="whitespace-nowrap font-semibold">#{{ $payment->id }}</div>
                                <div class="text-xs text-gray-600"><x-ui.date-time>{{ $payment->received_on?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
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
                            <td class="align-top text-center!">
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
                                        <span>{{ $isCreditGrant ? 'Available credit' : 'Refundable by card' }}</span>
                                        <span>{{ money($isCreditGrant ? $creditCashOutAmount : $squareRefundable) }}</span>
                                    </div>
                                </div>
                            </td>
                    <td class="text-center! align-top">
                                <div x-data="{ createRefundOpen: false, isSubmitting: false }" class="flex flex-wrap items-center gap-2">
                                    <x-ui.row-action label="Open payment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $payment) }}" />
                                    <x-ui.row-action label="Open receipt" icon="fa-solid fa-receipt" tone="neutral" href="{{ route('admin.payment.receipt', $payment) }}" target="_blank" />
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
                                            <x-ui.button variant="plain" type="submit" class="inline-flex items-center rounded-md border border-red-600 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-600 hover:text-white">Refund {{ money($squareRefundable) }}</x-ui.button>
                                        </form>
                                    @endif
                                    @if($canCashOutCredit)
                                        <x-ui.button variant="plain"
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                            x-on:click="createRefundOpen = true"
                                        >
                                            Create refund
                                        </x-ui.button>
                                        <template x-teleport="body">
                                            <div
                                                x-show="createRefundOpen"
                                                x-cloak
                                                x-on:keydown.escape.window="createRefundOpen = false"
                                                class="fixed inset-0 z-220 flex items-center justify-center p-4"
                                                role="dialog"
                                                aria-modal="true"
                                            >
                                                <div class="absolute inset-0 bg-black/40" x-on:click="createRefundOpen = false"></div>
                                                <div class="relative w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                                    <div class="mb-4 flex items-start justify-between gap-4">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-950">Create refund</h3>
                                                            <p class="text-sm text-gray-600">Record a refund payment against this account credit balance.</p>
                                                        </div>
                                                        <x-ui.row-action label="Close dialog" icon="fa-solid fa-xmark" tone="neutral" type="button" x-on:click="createRefundOpen = false" />
                                                    </div>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.payment.refund.manual', $payment) }}"
                                                        class="space-y-4"
                                                        x-on:submit.prevent="if (isSubmitting) return; isSubmitting = true; $el.submit();"
                                                    >
                                                        @csrf
                                                        <x-ui.input
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            label="Refund Amount (optional)"
                                                            name="amount"
                                                            value=""
                                                            info="Leave blank to refund the remaining account credit."
                                                            :moneyFormat="true"
                                                        />
                                                        <x-ui.select label="Payout Method" name="payment_method">
                                                            <option value="" disabled {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === '' ? 'selected' : '' }}>Select payout method</option>
                                                            <option value="{{ \App\Models\Payment::PAYMENT_METHOD_CASH }}" {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === \App\Models\Payment::PAYMENT_METHOD_CASH ? 'selected' : '' }}>Cash</option>
                                                            <option value="{{ \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER }}" {{ old('payment_method', \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER) === \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER ? 'selected' : '' }}>Bank Transfer</option>
                                                        </x-ui.select>
                                                        <x-ui.input type="datetime-local" label="Payout Date/Time" name="received_on" value="{{ now()->format('Y-m-d\TH:i') }}" />
                                                        <x-ui.input label="Transfer / Cash Reference" name="reference" value="" info="Optional receipt number, transfer note, or cash reference." />
                                                        <x-ui.input label="Reason (optional)" name="reason" value="" />

                                                        <div class="flex justify-end gap-3 pt-1">
                                                            <x-ui.button type="button" color="secondary" x-on:click="createRefundOpen = false">Cancel</x-ui.button>
                                                            <x-ui.button type="submit" color="dark" x-bind:disabled="isSubmitting">Create refund</x-ui.button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
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
                                    <div class="text-xs text-gray-600"><x-ui.date-time>{{ $refund->received_on?->format('M j, Y g:i a') ?? $refund->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
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
                                <td class="align-top text-center!">
                                    <div class="space-y-1 text-xs text-gray-700">
                                        <div class="flex items-center justify-between gap-3">
                                            <span>Total</span>
                                            <span class="font-semibold text-gray-950">{{ money(-((float) $refund->total_amount)) }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <x-ui.row-actions>
                                        <x-ui.row-action label="Open refund record" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.payment.edit', $refund) }}" />
                                        <x-ui.row-action label="Open refund receipt" icon="fa-solid fa-receipt" tone="neutral" href="{{ route('admin.payment.receipt', $refund) }}" target="_blank" />
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
