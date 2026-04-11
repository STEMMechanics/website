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
        <x-ui.toolbar>
            <x-slot:right>
                <form method="GET" action="{{ url()->current() }}" class="w-full flex flex-col gap-4 md:flex-row md:items-center md:justify-end">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="hide_completed" value="1" {{ $hideCompleted ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
                        Hide completed refunds
                    </label>
                    <div class="flex w-full md:max-w-md">
                        <input
                            type="text"
                            name="search"
                            value="{{ request()->query('search', '') }}"
                            placeholder="Search"
                            autocomplete="off"
                            class="grow rounded-l-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        >
                        <x-ui.button type="submit" class="rounded-l-none px-6">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </x-ui.button>
                    </div>
                </form>
            </x-slot:right>
        </x-ui.toolbar>

        @if($manualRefunds->isEmpty())
            <x-none-found item="manual refund items" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
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
                            <x-ui.badge :color="$statusTone" size="xxs">{{ $statusLabel }}</x-ui.badge>
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

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if($paymentUrl)
                                <a href="{{ $paymentUrl }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open payment">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span class="sr-only">Open payment</span>
                                </a>
                            @endif
                            @if($invoiceUrl)
                                <a href="{{ $invoiceUrl }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open invoice">
                                    <i class="fa-regular fa-file-lines"></i>
                                    <span class="sr-only">Open invoice</span>
                                </a>
                            @endif
                            @if($workshopUrl)
                                <a href="{{ $workshopUrl }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50" title="Open workshop tickets">
                                    <i class="fa-solid fa-ticket"></i>
                                    <span class="sr-only">Open workshop tickets</span>
                                </a>
                            @endif
                            @if($needsManualAction)
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                    x-on:click="leaveAsCredit = false; refundModalOpen = true"
                                >
                                    Record refund
                                </button>
                            @endif
                        </div>

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
                                    <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="refundModalOpen = false">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
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
                                        <input type="checkbox" name="leave_as_credit" value="1" x-model="leaveAsCredit" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
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
                <x-ui.table>
                    <x-slot:header>
                        <th>Refund Payment</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
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
                                    <div class="text-xs text-gray-500">{{ $manualRefund->created_at?->format('M j, Y g:i a') ?? '-' }}</div>
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
                                <td class="align-top">
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
                                <td class="align-top text-center">
                                    <x-ui.badge :color="$statusTone" size="xxs">{{ $statusLabel }}</x-ui.badge>
                                </td>
                                <td class="align-top">
                                    <div class="flex justify-center gap-3 whitespace-nowrap">
                                        @if($paymentUrl)
                                            <a href="{{ $paymentUrl }}" class="hover:text-primary-color" title="Open payment">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                <span class="sr-only">Open payment</span>
                                            </a>
                                        @endif
                                        @if($invoiceUrl)
                                            <a href="{{ $invoiceUrl }}" class="hover:text-primary-color" title="Open invoice">
                                                <i class="fa-regular fa-file-lines"></i>
                                                <span class="sr-only">Open invoice</span>
                                            </a>
                                        @endif
                                        @if($workshopUrl)
                                            <a href="{{ $workshopUrl }}" class="hover:text-primary-color" title="Open workshop tickets">
                                                <i class="fa-solid fa-ticket"></i>
                                                <span class="sr-only">Open workshop tickets</span>
                                            </a>
                                        @endif
                                        @if($needsManualAction)
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white"
                                                x-on:click="leaveAsCredit = false; refundModalOpen = true"
                                            >
                                                Record refund
                                            </button>
                                        @endif
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="refundModalOpen"
                                        x-on:keydown.escape.window="refundModalOpen = false"
                                        class="fixed inset-0 z-[220] flex items-center justify-center p-4"
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
                                                <button type="button" class="text-gray-500 hover:text-gray-700" @click="refundModalOpen = false">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
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
                                                    <input type="checkbox" name="leave_as_credit" value="1" x-model="leaveAsCredit" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color">
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

            {{ $manualRefunds->appends(request()->query())->links() }}
        @endif
    </x-container>
</x-layout>
