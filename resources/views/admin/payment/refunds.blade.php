@php
    $manualRefundCount = (int) ($manualRefundCount ?? 0);
    $actionRequiredCount = (int) ($actionRequiredCount ?? 0);
    $pendingCount = (int) ($pendingCount ?? 0);
    $completedCount = (int) ($completedCount ?? 0);
    $manualRefundTotal = (float) ($manualRefundTotal ?? 0);
    $hideCompleted = (bool) ($hideCompleted ?? false);
@endphp

<x-layout>
    <x-mast>Refunds</x-mast>

    <x-container>
        <x-ui.dynamic-list name="admin-payment-refunds">
        <x-ui.collection-controls class="my-4" />

        @if($manualRefunds->isEmpty())
            <x-none-found item="manual refund items" search="{{ request()->get('search') }}" />
        @else
            <div data-list-results class="space-y-4 md:hidden">
                @foreach($manualRefunds as $manualRefund)
                    @php
                        $ticket = $manualRefund->ticket;
                        $invoice = $manualRefund->invoice;
                        $order = $invoice?->storeOrders?->first();
                        $payment = $manualRefund->customerPayment;
                        $statusKey = (string) $manualRefund->status;
                        $statusLabel = match ($statusKey) {
                            \App\Models\SquareRefundOperation::STATUS_PENDING => 'Pending',
                            \App\Models\SquareRefundOperation::STATUS_COMPLETED => 'Completed',
                            \App\Models\SquareRefundOperation::STATUS_FAILED => 'Failed',
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED => 'Manual',
                            default => ucfirst(str_replace(' required', '', str_replace('_', ' ', $statusKey))),
                        };
                        $statusTone = match ($statusKey) {
                            \App\Models\SquareRefundOperation::STATUS_PENDING => 'sky',
                            \App\Models\SquareRefundOperation::STATUS_COMPLETED => 'success',
                            \App\Models\SquareRefundOperation::STATUS_FAILED => 'danger',
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED => 'warning',
                            default => 'gray',
                        };
                        $needsManualAction = in_array($statusKey, [
                            \App\Models\SquareRefundOperation::STATUS_FAILED,
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                        ], true);
                        $ticketReference = (string) ($ticket?->reference_code ?: ($ticket?->id ? '#'.$ticket->id : '-'));
                        $invoiceNumber = (string) ($invoice?->invoice_number ?: '-');
                        $orderNumber = $order?->order_number ? '#'.$order->order_number : '-';
                        $customerName = trim((string) (($invoice?->billing_name ?? '') ?: ($payment?->user?->getName() ?? '')));
                        $customerEmail = trim((string) ($invoice?->billing_email ?? $payment?->user?->email ?? ''));
                        $paymentUrl = $payment ? route('admin.payment.edit', $payment) : null;
                        $invoiceUrl = $invoice ? route('admin.invoice.edit', $invoice) : null;
                        $workshopUrl = $ticket?->workshop ? route('admin.workshop.tickets', $ticket->workshop) : null;
                        $orderUrl = $order ? route('admin.shop.order.edit', $order) : null;
                        $markCompletedUrl = route('admin.payment.refunds.complete', $manualRefund);
                        $refundAmount = round(((int) $manualRefund->requested_cents) / 100, 2);
                        $refundReceivedOn = now()->format('Y-m-d\TH:i');
                        $refundPaymentId = (int) data_get($manualRefund->payload, 'manual_refund.refund_payment_id', 0);
                        if ($refundPaymentId <= 0 && $payment) {
                            $refundPaymentId = (int) optional($payment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0)->first())->id;
                        }
                        $displayNumber = $refundPaymentId > 0 ? $refundPaymentId : $manualRefund->id;
                        $queueNumber = '#'.$manualRefund->id;
                        $resolutionLabel = match ((string) data_get($manualRefund->payload, 'manual_refund.resolution', '')) {
                            'credit_retained' => 'Left as account credit',
                            'refund_paid_out' => $refundPaymentId > 0 ? 'Refund payment #'.$refundPaymentId : 'Refund paid out',
                            default => '',
                        };
                        $defaultReason = trim((string) $manualRefund->failure_message);
                        $defaultReason = $defaultReason !== ''
                            ? 'Square refund failed for refund queue item #'.((int) $manualRefund->id).($payment ? ' (payment #'.((int) $payment->id).($payment->square_payment_id ? ' Square payment '.(string) $payment->square_payment_id : '').')' : '').': '.$defaultReason
                            : 'Manual refund recorded for refund queue item #'.((int) $manualRefund->id).($payment ? ' (payment #'.((int) $payment->id).($payment->square_payment_id ? ' Square payment '.(string) $payment->square_payment_id : '').')' : '');
                        if ($payment && $refundPaymentId > 0) {
                            $paymentUrl = route('admin.payment.edit', ['payment' => $payment, 'highlight_refund' => $refundPaymentId]);
                        }
                    @endphp
                    <div x-data="{ refundModalOpen: false, leaveAsCredit: false }" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="whitespace-nowrap font-semibold">{{ $displayNumber }}</div>
                                <div class="text-xs text-gray-500">{{ $manualRefund->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            </div>
                            <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Refund Payment</div>
                                <div class="mt-1 font-medium text-gray-900">
                                    @if($ticketReference !== '-')
                                        <a href="{{ $workshopUrl ?? $invoiceUrl ?? '#' }}" class="text-primary-color hover:underline">{{ $ticketReference }}</a>
                                    @elseif($orderNumber !== '-')
                                        <a href="{{ $orderUrl ?? $invoiceUrl ?? '#' }}" class="text-primary-color hover:underline">Order {{ $orderNumber }}</a>
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-gray-600">
                                    @if($invoiceUrl)
                                        <a href="{{ $invoiceUrl }}" class="text-primary-color hover:underline">Invoice #{{ $invoiceNumber }}</a>
                                    @else
                                        Invoice #{{ $invoiceNumber }}
                                    @endif
                                </div>
                                @if($orderNumber !== '-')
                                    <div class="text-xs text-gray-600">
                                        @if($orderUrl)
                                            <a href="{{ $orderUrl }}" class="text-primary-color hover:underline">Order {{ $orderNumber }}</a>
                                        @else
                                            Order {{ $orderNumber }}
                                        @endif
                                    </div>
                                @endif
                                @if($customerName !== '' || $customerEmail !== '')
                                    <div class="text-xs text-gray-600">
                                        {{ $customerName !== '' ? $customerName : 'Customer' }}
                                        @if($customerEmail !== '')
                                            ({{ $customerEmail }})
                                        @endif
                                    </div>
                                @endif
                                @if(trim((string) $manualRefund->failure_message) !== '')
                                    <div class="mt-1 text-xs text-amber-700">{{ $manualRefund->failure_message }}</div>
                                @endif
                            </div>

                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Amounts</div>
                                <div class="mt-1 font-semibold text-gray-950">{{ money(((int) $manualRefund->requested_cents) / 100) }}</div>
                                @if((int) $manualRefund->refunded_cents > 0)
                                    <div class="text-xs text-gray-600">Refunded: {{ money(((int) $manualRefund->refunded_cents) / 100) }}</div>
                                @endif
                                @if($resolutionLabel !== '')
                                    <div class="mt-1 text-xs font-medium text-gray-600">{{ $resolutionLabel }}</div>
                                @endif
                                @if($payment)
                                    <div class="mt-1 text-xs text-gray-600">Payment #{{ $payment->id }}</div>
                                @endif
                            </div>
                        </div>

                        <x-ui.row-actions class="mt-4">
                            @if($paymentUrl)
                                <x-ui.row-action label="Open payment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ $paymentUrl }}" />
                            @endif
                            @if($invoiceUrl)
                                <x-ui.row-action label="Open invoice" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $invoiceUrl }}" />
                            @endif
                            @if($workshopUrl)
                                <x-ui.row-action label="Open workshop tickets" icon="fa-solid fa-ticket" tone="neutral" href="{{ $workshopUrl }}" />
                            @endif
                            @if($needsManualAction)
                                <x-ui.button variant="plain"
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                    x-on:click="leaveAsCredit = false; refundModalOpen = true"
                                >
                                    Record refund
                                </x-ui.button>
                            @endif
                        </x-ui.row-actions>

                        <div
                            x-cloak
                            x-show="refundModalOpen"
                            x-on:keydown.escape.window="refundModalOpen = false"
                            class="fixed inset-0 z-220 flex items-center justify-center p-4"
                            role="dialog"
                            aria-modal="true"
                        >
                            <div class="absolute inset-0 bg-black/40" x-on:click="refundModalOpen = false"></div>
                            <div class="relative w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                <div class="mb-4 flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-950">Record manual refund</h3>
                                        <p class="text-sm text-gray-600">Mark this item complete and record the actual refund details.</p>
                                    </div>
                                    <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-700" x-on:click="refundModalOpen = false">
                                        <i class="fa-solid fa-xmark"></i>
                                    </x-ui.button>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ $markCompletedUrl }}"
                                    class="space-y-4"
                                    x-data
                                    x-on:submit.prevent="$el.submit()"
                                >
                                    @csrf
                                    <input type="hidden" name="amount" value="{{ number_format($refundAmount, 2, '.', '') }}">
                                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                        <x-ui.checkbox bare small name="leave_as_credit" value="1" x-model="leaveAsCredit" class="mt-1" />
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">No refund, leave as account credit</span>
                                            <span class="block text-xs text-gray-600">Use this when the amount should remain on the customer account instead of being paid out.</span>
                                        </span>
                                    </label>

                                    <div x-show="!leaveAsCredit" x-cloak class="space-y-4">
                                        <x-ui.select label="Refund Method" name="payment_method">
                                            <option value="{{ \App\Models\Payment::PAYMENT_METHOD_CASH }}">Cash</option>
                                            <option value="{{ \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER }}" selected>Bank Transfer</option>
                                        </x-ui.select>
                                        <x-ui.input type="datetime-local" label="Refund Date/Time" name="received_on" value="{{ $refundReceivedOn }}" />
                                        <x-ui.input label="Transfer / Cash Reference" name="reference" value="" info="Optional receipt number, transfer note, or cash reference." />
                                    </div>
                                    <x-ui.input label="Internal Notes" name="reason" value="{{ $defaultReason }}" />

                                    <div class="flex justify-end gap-3 pt-1">
                                        <x-ui.button type="button" color="secondary" x-on:click="refundModalOpen = false">Cancel</x-ui.button>
                                        <x-ui.button type="submit" color="dark">Mark complete</x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hidden md:block">
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <x-ui.list-heading label="Refund Payment" />
                        <x-ui.list-heading label="Details" />
                        <x-ui.list-heading class="text-center!" label="Amount" />
                        <x-ui.list-heading class="text-center!" label="Status" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($manualRefunds as $manualRefund)
                            @php
                                $ticket = $manualRefund->ticket;
                                $invoice = $manualRefund->invoice;
                                $order = $invoice?->storeOrders?->first();
                                $payment = $manualRefund->customerPayment;
                        $statusKey = (string) $manualRefund->status;
                        $statusLabel = match ($statusKey) {
                            \App\Models\SquareRefundOperation::STATUS_PENDING => 'Pending',
                            \App\Models\SquareRefundOperation::STATUS_COMPLETED => 'Completed',
                            \App\Models\SquareRefundOperation::STATUS_FAILED => 'Failed',
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED => 'Manual',
                            default => ucfirst(str_replace(' required', '', str_replace('_', ' ', $statusKey))),
                        };
                        $statusTone = match ($statusKey) {
                            \App\Models\SquareRefundOperation::STATUS_PENDING => 'sky',
                            \App\Models\SquareRefundOperation::STATUS_COMPLETED => 'success',
                            \App\Models\SquareRefundOperation::STATUS_FAILED => 'danger',
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED => 'warning',
                            default => 'gray',
                        };
                        $needsManualAction = in_array($statusKey, [
                            \App\Models\SquareRefundOperation::STATUS_FAILED,
                            \App\Models\SquareRefundOperation::STATUS_MANUAL_REQUIRED,
                        ], true);
                                $ticketReference = (string) ($ticket?->reference_code ?: ($ticket?->id ? '#'.$ticket->id : '-'));
                                $invoiceNumber = (string) ($invoice?->invoice_number ?: '-');
                                $orderNumber = $order?->order_number ? '#'.$order->order_number : '-';
                                $customerName = trim((string) (($invoice?->billing_name ?? '') ?: ($payment?->user?->getName() ?? '')));
                                $customerEmail = trim((string) ($invoice?->billing_email ?? $payment?->user?->email ?? ''));
                        $paymentUrl = $payment ? route('admin.payment.edit', $payment) : null;
                        $invoiceUrl = $invoice ? route('admin.invoice.edit', $invoice) : null;
                        $workshopUrl = $ticket?->workshop ? route('admin.workshop.tickets', $ticket->workshop) : null;
                        $orderUrl = $order ? route('admin.shop.order.edit', $order) : null;
                        $markCompletedUrl = route('admin.payment.refunds.complete', $manualRefund);
                        $refundAmount = round(((int) $manualRefund->requested_cents) / 100, 2);
                        $refundReceivedOn = now()->format('Y-m-d\TH:i');
                        $refundPaymentId = (int) data_get($manualRefund->payload, 'manual_refund.refund_payment_id', 0);
                        if ($refundPaymentId <= 0 && $payment) {
                            $refundPaymentId = (int) optional($payment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0)->first())->id;
                        }
                        $displayNumber = $refundPaymentId > 0 ? $refundPaymentId : $manualRefund->id;
                        $queueNumber = '#'.$manualRefund->id;
                        $resolutionLabel = match ((string) data_get($manualRefund->payload, 'manual_refund.resolution', '')) {
                            'credit_retained' => 'Left as account credit',
                            'refund_paid_out' => $refundPaymentId > 0 ? 'Refund payment #'.$refundPaymentId : 'Refund paid out',
                            default => '',
                        };
                        $defaultReason = trim((string) $manualRefund->failure_message);
                        $defaultReason = $defaultReason !== ''
                            ? 'Square refund failed for refund queue item #'.((int) $manualRefund->id).($payment ? ' (payment #'.((int) $payment->id).($payment->square_payment_id ? ' Square payment '.(string) $payment->square_payment_id : '').')' : '').': '.$defaultReason
                            : 'Manual refund recorded for refund queue item #'.((int) $manualRefund->id).($payment ? ' (payment #'.((int) $payment->id).($payment->square_payment_id ? ' Square payment '.(string) $payment->square_payment_id : '').')' : '');
                        if ($payment && $refundPaymentId > 0) {
                            $paymentUrl = route('admin.payment.edit', ['payment' => $payment, 'highlight_refund' => $refundPaymentId]);
                        }
                    @endphp
                            <tr x-data="{ refundModalOpen: false, leaveAsCredit: false }">
                                <td class="align-top">
                                    <div class="whitespace-nowrap font-semibold">{{ $displayNumber }}</div>
                                    <div class="text-xs text-gray-500"><x-ui.date-time>{{ $manualRefund->created_at?->format('M j, Y g:i a') ?? '-' }}</x-ui.date-time></div>
                                </td>
                                <td class="align-top">
                                    <div class="font-medium text-gray-900">
                                        @if($ticketReference !== '-')
                                            <a href="{{ $workshopUrl ?? $invoiceUrl ?? '#' }}" class="text-primary-color hover:underline">{{ $ticketReference }}</a>
                                        @elseif($orderNumber !== '-')
                                            <a href="{{ $orderUrl ?? $invoiceUrl ?? '#' }}" class="text-primary-color hover:underline">Order {{ $orderNumber }}</a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        @if($invoiceUrl)
                                            <a href="{{ $invoiceUrl }}" class="text-primary-color hover:underline">Invoice #{{ $invoiceNumber }}</a>
                                        @else
                                            Invoice #{{ $invoiceNumber }}
                                        @endif
                                    </div>
                                    @if($orderNumber !== '-')
                                        <div class="text-xs text-gray-600">
                                            @if($orderUrl)
                                                <a href="{{ $orderUrl }}" class="text-primary-color hover:underline">Order {{ $orderNumber }}</a>
                                            @else
                                                Order {{ $orderNumber }}
                                            @endif
                                        </div>
                                    @endif
                                    @if($customerName !== '' || $customerEmail !== '')
                                        <div class="text-xs text-gray-600">
                                            {{ $customerName !== '' ? $customerName : 'Customer' }}
                                            @if($customerEmail !== '')
                                                ({{ $customerEmail }})
                                            @endif
                                        </div>
                                    @endif
                                    @if(trim((string) $manualRefund->failure_message) !== '')
                                        <div class="mt-1 text-xs text-amber-700">{{ $manualRefund->failure_message }}</div>
                                    @endif
                                </td>
                                <td class="align-top text-center!">
                                    <div class="font-semibold text-gray-950">{{ money(((int) $manualRefund->requested_cents) / 100) }}</div>
                                @if((int) $manualRefund->refunded_cents > 0)
                                    <div class="text-xs text-gray-600">Refunded: {{ money(((int) $manualRefund->refunded_cents) / 100) }}</div>
                                @endif
                                @if($resolutionLabel !== '')
                                    <div class="mt-1 text-xs font-medium text-gray-600">{{ $resolutionLabel }}</div>
                                @endif
                                @if($payment)
                                    <div class="mt-1 text-xs text-gray-600">Payment #{{ $payment->id }}</div>
                                @endif
                                </td>
                                <td class="align-top text-center!">
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                </td>
                                <td class="align-top">
                                    <x-ui.row-actions class="whitespace-nowrap">
                                        @if($paymentUrl)
                                            <x-ui.row-action label="Open payment" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ $paymentUrl }}" />
                                        @endif
                                        @if($invoiceUrl)
                                            <x-ui.row-action label="Open invoice" icon="fa-regular fa-file-lines" tone="neutral" href="{{ $invoiceUrl }}" />
                                        @endif
                                        @if($workshopUrl)
                                            <x-ui.row-action label="Open workshop tickets" icon="fa-solid fa-ticket" tone="neutral" href="{{ $workshopUrl }}" />
                                        @endif
                                        @if($needsManualAction)
                                            <x-ui.button variant="plain"
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                                x-on:click="leaveAsCredit = false; refundModalOpen = true"
                                            >
                                                Record refund
                                            </x-ui.button>
                                        @endif
                                    </x-ui.row-actions>

                                    <div
                                        x-cloak
                                        x-show="refundModalOpen"
                                        x-on:keydown.escape.window="refundModalOpen = false"
                                        class="fixed inset-0 z-220 flex items-center justify-center p-4"
                                        role="dialog"
                                        aria-modal="true"
                                    >
                                        <div class="absolute inset-0 bg-black/40" @click="refundModalOpen = false"></div>
                                        <div class="relative w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                                            <div class="mb-4 flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-950">Record manual refund</h3>
                                                    <p class="text-sm text-gray-600">Mark this item complete and record the actual refund details.</p>
                                                </div>
                                                <x-ui.row-action label="Close dialog" icon="fa-solid fa-xmark" tone="neutral" type="button" x-on:click="refundModalOpen = false" />
                                            </div>

                                            <form
                                                method="POST"
                                                action="{{ $markCompletedUrl }}"
                                                class="space-y-4"
                                                x-data
                                                x-on:submit.prevent="$el.submit()"
                                            >
                                                @csrf
                                                <input type="hidden" name="amount" value="{{ number_format($refundAmount, 2, '.', '') }}">
                                                <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                                    <x-ui.checkbox bare small name="leave_as_credit" value="1" x-model="leaveAsCredit" class="mt-1" />
                                                    <span>
                                                        <span class="block text-sm font-semibold text-gray-900">No refund, leave as account credit</span>
                                                        <span class="block text-xs text-gray-600">Use this when the amount should remain on the customer account instead of being paid out.</span>
                                                    </span>
                                                </label>

                                                <div x-show="!leaveAsCredit" x-cloak class="space-y-4">
                                                    <x-ui.select label="Refund Method" name="payment_method">
                                                        <option value="{{ \App\Models\Payment::PAYMENT_METHOD_CASH }}">Cash</option>
                                                        <option value="{{ \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER }}" selected>Bank Transfer</option>
                                                    </x-ui.select>
                                                    <x-ui.input type="datetime-local" label="Refund Date/Time" name="received_on" value="{{ $refundReceivedOn }}" />
                                                    <x-ui.input label="Transfer / Cash Reference" name="reference" value="" info="Optional receipt number, transfer note, or cash reference." />
                                                </div>
                                                <x-ui.input label="Internal Notes" name="reason" value="{{ $defaultReason }}" />

                                                <div class="flex justify-end gap-3 pt-1">
                                                    <x-ui.button type="button" color="secondary" x-on:click="refundModalOpen = false">Cancel</x-ui.button>
                                                    <x-ui.button type="submit" color="dark">Mark complete</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>
            </div>

            <x-ui.list-pagination :paginator="$manualRefunds" />
        @endif

        </x-ui.dynamic-list>
    </x-container>
</x-layout>
