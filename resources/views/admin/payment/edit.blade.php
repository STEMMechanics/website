@php
    $savedAllocations = old('allocations_json');
    $isExisting = isset($customerPayment);
    $isSquareManaged = isset($customerPayment)
        && (
            trim((string) ($customerPayment->square_payment_id ?? '')) !== ''
            || strtolower(trim((string) ($customerPayment->gateway_provider ?? ''))) === 'square'
        );
    $isRefundRecord = isset($customerPayment) && $customerPayment->refund_of_payment_id !== null;
    $isCreditGrant = isset($customerPayment) && (string) ($customerPayment->payment_method ?? '') === \App\Models\Payment::PAYMENT_METHOD_CREDIT;
    $isCoreLocked = $isExisting;
    $canEditAllocations = ! $isExisting && ! $isRefundRecord;
    $existingLockedAllocations = isset($customerPayment)
        ? $customerPayment->allocations
            ->filter(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && ($allocation->invoice || $allocation->taxAdjustment))
            ->values()
        : collect();
    $squareRemainingCents = isset($customerPayment)
        ? max(0, ((int) ($customerPayment->square_paid_money_amount ?? 0)) - ((int) ($customerPayment->square_refunded_money_amount ?? 0)))
        : 0;
    $recordedRefundedAmount = isset($customerPayment)
        ? (float) $customerPayment->refunds()->sum('total_amount')
        : 0.0;
    $allocatedNetAmount = isset($customerPayment)
        ? round((float) $customerPayment->allocations->sum('allocated_amount'), 2)
        : 0.0;
    $unallocatedBeforeRefundAmount = isset($customerPayment)
        ? max(0, round((float) $customerPayment->total_amount - $allocatedNetAmount, 2))
        : 0.0;
    $remainingByRefundHistoryAmount = isset($customerPayment)
        ? max(0, round((float) $customerPayment->total_amount - $recordedRefundedAmount, 2))
        : 0.0;
    $unallocatedAmount = isset($customerPayment)
        ? max(0, round($unallocatedBeforeRefundAmount - $recordedRefundedAmount, 2))
        : 0.0;
    $remainingRefundableAmount = isset($customerPayment)
        ? min($remainingByRefundHistoryAmount, $unallocatedAmount)
        : 0.0;
    $displayRemainingRefundableAmount = $isCreditGrant ? 0.0 : $remainingRefundableAmount;
    $canAddAllocations = $canEditAllocations
        && (! isset($customerPayment) || $unallocatedAmount > 0.0001);
    $refundHistory = isset($customerPayment)
        ? $customerPayment->refunds->sortByDesc(fn ($refund) => optional($refund->received_on)->timestamp ?? optional($refund->created_at)->timestamp ?? 0)
        : collect();
    $receiptAllocations = isset($customerPayment)
        ? $customerPayment->allocations
            ->filter(fn ($allocation) => ((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice)
            ->values()
        : collect();
    $selectedUserId = (string) old('user_id', $customerPayment->user_id ?? ($prefillUserId ?? ''));
    $manualRefundDefaultMethod = isset($customerPayment) && in_array((string) ($customerPayment->payment_method ?? ''), [
        \App\Models\Payment::PAYMENT_METHOD_CASH,
        \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER,
    ], true)
        ? (string) $customerPayment->payment_method
        : '';

    if ($savedAllocations === null) {
        $savedAllocations = isset($customerPayment) && ! $isExisting
            ? json_encode($customerPayment->allocations->map(fn ($allocation) => [
                'invoice_id' => $allocation->invoice_id,
                'allocated_amount' => $allocation->allocated_amount,
            ])->values()->all())
            : json_encode(isset($customerPayment) ? [] : ($initialAllocations ?? []));
    }

    $invoiceViewUrls = collect($invoices ?? [])->mapWithKeys(function ($invoice) {
        return [(string) $invoice->id => route('admin.invoice.edit', $invoice)];
    })->all();
    $invoiceRemainingLookup = collect($invoiceRemainingById ?? [])->mapWithKeys(function ($value, $key) {
        return [(string) $key => round((float) $value, 2)];
    })->all();
@endphp

<x-layout>
    <x-mast backRoute="admin.payment.index" backTitle="Payments">{{ isset($customerPayment) ? 'Edit' : 'Record' }} Payment</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.payment.' . (isset($customerPayment) ? 'update' : 'store'), $customerPayment ?? []) }}"
              x-data="{
                allocations: (() => {
                    try {
                        const parsed = JSON.parse(@js($savedAllocations));
                        if (!Array.isArray(parsed)) {
                            return [];
                        }
                        return parsed.map((item) => {
                            const amount = parseFloat(item?.allocated_amount || 0);
                            return {
                                invoice_id: parseInt(item?.invoice_id || 0),
                                allocated_amount: Number.isFinite(amount) ? amount.toFixed(2) : '0.00',
                            };
                        });
                    } catch (e) {
                        return [];
                    }
                })(),
                serializeAllocations() {
                    const cleaned = this.allocations
                        .map((item) => ({
                            invoice_id: parseInt(item.invoice_id || 0),
                            allocated_amount: parseFloat(item.allocated_amount || 0)
                        }))
                        .filter((item) => item.invoice_id > 0 && item.allocated_amount > 0);

                    this.$refs.allocationsJson.value = JSON.stringify(cleaned);
                },
                addAllocation() {
                    this.allocations.push({ invoice_id: '', allocated_amount: '0.00' });
                },
                removeAllocation(index) {
                    this.allocations.splice(index, 1);
                    this.serializeAllocations();
                },
                normalizeMoney(value) {
                    const parsed = parseFloat(value || 0);
                    return Number.isFinite(parsed) ? parsed.toFixed(2) : '0.00';
                },
                normalizeAllocation(index) {
                    this.allocations[index].allocated_amount = this.normalizeMoney(this.allocations[index]?.allocated_amount);
                    this.serializeAllocations();
                },
                paymentTotal() {
                    const raw = this.$refs.totalAmountInput?.value ?? '0';
                    const value = parseFloat(String(raw).replace(',', '.'));
                    return Number.isFinite(value) ? value : 0;
                },
                availablePaymentAmount(excludingIndex = null) {
                    const total = this.paymentTotal();
                    let allocated = 0;
                    this.allocations.forEach((item, idx) => {
                        if (excludingIndex !== null && idx === excludingIndex) {
                            return;
                        }
                        allocated += parseFloat(item?.allocated_amount || 0);
                    });
                    return Math.max(0, total - allocated);
                },
                autoFillAllocation(index) {
                    const invoiceId = parseInt(this.allocations[index]?.invoice_id || 0);
                    if (!Number.isFinite(invoiceId) || invoiceId <= 0) {
                        this.allocations[index].allocated_amount = '0.00';
                        this.serializeAllocations();
                        return;
                    }
                    const invoiceRemaining = parseFloat(this.invoiceRemainingById[String(invoiceId)] || 0);
                    const paymentRemaining = this.availablePaymentAmount(index);
                    const suggested = Math.max(0, Math.min(invoiceRemaining, paymentRemaining));
                    this.allocations[index].allocated_amount = this.normalizeMoney(suggested);
                    this.serializeAllocations();
                },
                invoiceEditUrl(invoiceId) {
                    const key = String(parseInt(invoiceId || 0));
                    return this.invoiceViewUrls[key] || '';
                },
                invoiceViewUrls: @js($invoiceViewUrls),
                invoiceRemainingById: @js($invoiceRemainingLookup),
              }"
              x-on:submit.prevent="serializeAllocations(); $el.submit()">
            @isset($customerPayment)
                @method('PUT')
            @endisset
            @csrf

            @if(isset($customerPayment))
                <div class="mb-4 text-sm">
                    <strong>Payment #:</strong> {{ $customerPayment->id }}
                    @if($customerPayment->refund_of_payment_id)
                        <span class="ml-2 text-gray-600">(Refund of payment #{{ $customerPayment->refund_of_payment_id }})</span>
                    @endif
                </div>
            @endif

            @if($isSquareManaged)
                <div class="mb-4 rounded-lg border border-indigo-300 bg-indigo-50 p-3 text-sm">
                    This payment is managed by Square. Refunds use the Square refund action and core fields are locked.
                </div>
            @endif
            @if($isExisting)
                <div class="mb-4 rounded-lg border border-sky-300 bg-sky-50 p-3 text-sm">
                    This payment is immutable. Only private notes can be edited.
                </div>
            @endif
            @if($isRefundRecord)
                <div class="mb-4 rounded-lg border border-gray-300 bg-gray-50 p-3 text-sm">
                    This is a refund payment record and is fully locked.
                </div>
            @endif

            <input type="hidden" name="allocations_json" x-ref="allocationsJson" value="{{ $savedAllocations }}" />

            <x-admin.user-selector-inline
                :users="$users ?? collect()"
                :selected-user-id="$selectedUserId"
                field-name="user_id"
                lookup-name="payment_user_lookup"
                label="Customer"
                info="Search by name/company/email. Select a customer for this payment."
                :disabled="$isCoreLocked"
            />

            <div class="flex gap-8">
                <div class="flex-1">
                <x-ui.input type="datetime-local" label="Received On" name="received_on" value="{{ old('received_on', isset($customerPayment) && $customerPayment->received_on ? $customerPayment->received_on->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}" :disabled="$isCoreLocked" />
                </div>
                <div class="flex-1">
                    <x-ui.select label="Payment Method" name="payment_method" :disabled="$isCoreLocked">
                        <option value="" disabled {{ old('payment_method', $customerPayment->payment_method ?? '') === '' ? 'selected' : '' }}>Select payment method</option>
                        @foreach(($paymentMethods ?? \App\Models\Payment::PAYMENT_METHODS) as $paymentMethod)
                            <option value="{{ $paymentMethod }}" {{ old('payment_method', $customerPayment->payment_method ?? '') === $paymentMethod ? 'selected' : '' }}>
                                {{ \App\Models\Payment::paymentMethodLabel((string) $paymentMethod) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>

            <x-ui.input label="Reference" name="reference" value="{{ old('reference', $customerPayment->reference ?? '') }}" :disabled="$isCoreLocked" />

            <x-ui.input type="text" label="Total Amount" name="total_amount" value="{{ old('total_amount', $customerPayment->total_amount ?? (isset($prefillTotalAmount) && $prefillTotalAmount !== null ? number_format((float) $prefillTotalAmount, 2, '.', '') : '0.00')) }}" :moneyFormat="true" :disabled="$isCoreLocked" x-ref="totalAmountInput" />

            <div class="border rounded-lg p-4 mb-4" x-init="serializeAllocations()">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-lg">Invoice Allocations</h3>
                    @if($canAddAllocations)
                        <button type="button" class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white whitespace-nowrap text-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-on:click.prevent="addAllocation()">Add Allocation</button>
                    @endif
                </div>
                @if($errors->has('allocations_json'))
                    <div class="text-xs text-red-600 ml-2 mb-3">{{ $errors->first('allocations_json') }}</div>
                @endif

                @if($existingLockedAllocations->isNotEmpty())
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Existing Allocations (Locked)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 pr-3">Document</th>
                                        <th class="text-right py-2 pr-3">Amount</th>
                                        <th class="text-left py-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($existingLockedAllocations as $allocation)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 pr-3">
                                                @if($allocation->taxAdjustment)
                                                    Tax Adjustment {{ $allocation->taxAdjustment->adjustment_number }} (Invoice {{ $allocation->invoice?->invoice_number ?? '-' }})
                                                @elseif($allocation->invoice)
                                                    Invoice {{ $allocation->invoice->invoice_number }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="py-2 pr-3 text-right">${{ number_format((float) $allocation->allocated_amount, 2) }}</td>
                                            <td class="py-2">
                                                @if($allocation->taxAdjustment && $allocation->invoice)
                                                    <a href="{{ route('admin.tax_adjustment.edit', ['invoice' => $allocation->invoice, 'taxAdjustment' => $allocation->taxAdjustment]) }}" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-primary-color" title="Open tax adjustment">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                @elseif($allocation->invoice)
                                                    <a href="{{ route('admin.invoice.edit', $allocation->invoice) }}" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-primary-color" title="Open invoice">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <template x-if="allocations.length === 0">
                    <div class="text-sm text-gray-500">{{ $existingLockedAllocations->isNotEmpty() ? 'No new allocations yet.' : 'No allocations yet.' }}</div>
                </template>

                <template x-for="(allocation, index) in allocations" :key="index">
                    <div class="grid grid-cols-12 gap-3 items-end mb-3">
                        <div class="col-span-8">
                            <label class="block text-sm pl-1">Invoice</label>
                            <select class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="allocation.invoice_id" x-on:change="autoFillAllocation(index)" {{ $canEditAllocations ? '' : 'disabled' }}>
                                <option value="">Select invoice</option>
                                @foreach($invoices as $invoice)
                                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - {{ $invoice->user?->getName() ?? 'No customer' }} - ${{ number_format((float) $invoice->total_amount, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-sm pl-1">Amount</label>
                            <input type="text" class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="allocation.allocated_amount" x-on:input="serializeAllocations()" x-on:blur="normalizeAllocation(index)" {{ $canEditAllocations ? '' : 'disabled' }} />
                        </div>
                        <div class="col-span-1">
                            <div class="h-[42px] flex items-center gap-3">
                                <a x-show="invoiceEditUrl(allocation.invoice_id)"
                                   x-bind:href="invoiceEditUrl(allocation.invoice_id)"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="text-gray-600 hover:text-primary-color"
                                   title="Open invoice">
                                    <i class="fa-solid fa-up-right-from-square"></i>
                                </a>
                                @if($canEditAllocations)
                                    <button type="button" class="text-red-600 hover:text-red-700" x-on:click.prevent="removeAllocation(index)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <x-ui.input type="textarea" label="Notes (Private)" name="notes" value="{{ old('notes', $customerPayment->notes ?? '') }}" />

            @isset($customerPayment)
                <div class="border rounded-lg p-4 mb-4">
                    <h3 class="font-bold text-lg mb-3">Payment Progress</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                        <div><span class="font-semibold">Original Amount:</span> ${{ number_format((float) $customerPayment->total_amount, 2) }}</div>
                        <div><span class="font-semibold">Refunded:</span> ${{ number_format($recordedRefundedAmount, 2) }}</div>
                        <div><span class="font-semibold">Remaining Refundable:</span> ${{ number_format($displayRemainingRefundableAmount, 2) }}</div>
                        <div><span class="font-semibold">Allocated:</span> ${{ number_format($allocatedNetAmount, 2) }}</div>
                        <div><span class="font-semibold">Unallocated:</span> ${{ number_format($unallocatedAmount, 2) }}</div>
                    </div>
                </div>

                @if($isSquareManaged && ! $isRefundRecord)
                    <div class="border rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-lg mb-3">Square Integration</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div><span class="font-semibold">Gateway:</span> {{ $customerPayment->gateway_provider ?? '-' }}</div>
                            <div><span class="font-semibold">Status:</span> {{ $customerPayment->gateway_status ?? '-' }}</div>
                            <div><span class="font-semibold">Square Payment ID:</span> {{ $customerPayment->square_payment_id ?? '-' }}</div>
                            <div><span class="font-semibold">Order ID:</span> {{ $customerPayment->square_order_id ?? '-' }}</div>
                            <div><span class="font-semibold">Card:</span>
                                @if($customerPayment->square_card_brand || $customerPayment->square_card_last4)
                                    {{ trim(($customerPayment->square_card_brand ?? '').' ****'.($customerPayment->square_card_last4 ?? '')) }}
                                @else
                                    -
                                @endif
                            </div>
                            <div><span class="font-semibold">Paid/Refunded (cents):</span> {{ (int) ($customerPayment->square_paid_money_amount ?? 0) }} / {{ (int) ($customerPayment->square_refunded_money_amount ?? 0) }}</div>
                            <div><span class="font-semibold">Square Created:</span> {{ $customerPayment->square_gateway_created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            <div><span class="font-semibold">Square Updated:</span> {{ $customerPayment->square_gateway_updated_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            <div><span class="font-semibold">Last Webhook:</span> {{ $customerPayment->square_last_event_type ?? '-' }}{{ $customerPayment->square_last_event_at ? ' @ '.$customerPayment->square_last_event_at->format('M j, Y g:i a') : '' }}</div>
                        </div>
                    </div>
                @elseif($isSquareManaged && $isRefundRecord)
                    <div class="border rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-lg mb-3">Square Refund</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div><span class="font-semibold">Gateway:</span> {{ $customerPayment->gateway_provider ?? '-' }}</div>
                            <div><span class="font-semibold">Refund Status:</span> {{ $customerPayment->gateway_status ?? '-' }}</div>
                            <div><span class="font-semibold">Square Refund ID:</span> {{ $customerPayment->gateway_reference_id ?? '-' }}</div>
                            <div><span class="font-semibold">Original Payment #:</span> {{ $customerPayment->refund_of_payment_id ?? '-' }}</div>
                            <div><span class="font-semibold">Square Created:</span> {{ $customerPayment->square_gateway_created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                            <div><span class="font-semibold">Square Updated:</span> {{ $customerPayment->square_gateway_updated_at?->format('M j, Y g:i a') ?? '-' }}</div>
                        </div>
                    </div>
                @endif

                @if(! $isCreditGrant)
                    <div class="border rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-lg mb-3">Receipt Links</h3>
                        <div class="flex flex-wrap gap-3">
                            <a
                                href="{{ route('admin.payment.receipt', $customerPayment) }}"
                                target="_blank"
                                class="inline-flex items-center rounded-md bg-primary-color px-4 py-2 text-sm font-semibold text-white hover:bg-primary-color-dark"
                            >View Payment Receipt</a>
                            <a
                                href="{{ route('admin.payment.receipt', ['payment' => $customerPayment, 'download' => 1]) }}"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            >Download Payment Receipt</a>
                        </div>
                        @if($receiptAllocations->isNotEmpty())
                            <div class="mt-3 text-xs text-gray-600">
                                Linked invoice allocations: {{ $receiptAllocations->map(fn ($allocation) => $allocation->invoice->invoice_number)->implode(', ') }}
                            </div>
                        @endif
                    </div>

                    <div class="border rounded-lg p-4 mb-4">
                        <h3 class="font-bold text-lg mb-3">Refund History</h3>
                        @if($refundHistory->isEmpty())
                            <div class="text-sm text-gray-500">No refund records linked to this payment.</div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="text-left py-2 pr-3">Refund Payment #</th>
                                            <th class="text-left py-2 pr-3">Date</th>
                                            <th class="text-left py-2 pr-3">Method</th>
                                            <th class="text-right py-2 pr-3">Amount</th>
                                            <th class="text-left py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($refundHistory as $refund)
                                            <tr class="border-b border-gray-100">
                                                <td class="py-2 pr-3">#{{ $refund->id }}</td>
                                                <td class="py-2 pr-3">{{ $refund->received_on?->format('M j, Y g:i a') ?? $refund->created_at?->format('M j, Y g:i a') ?? '-' }}</td>
                                                <td class="py-2 pr-3">{{ \App\Models\Payment::paymentMethodLabel((string) ($refund->payment_method ?? \App\Models\Payment::PAYMENT_METHOD_OTHER)) }}</td>
                                                <td class="py-2 pr-3 text-right">${{ number_format((float) $refund->total_amount, 2) }}</td>
                                                <td class="py-2">
                                                    <a href="{{ route('admin.payment.edit', $refund) }}" class="hover:text-primary-color mr-2" title="Open refund record">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                    </a>
                                                    <a href="{{ route('admin.payment.receipt', $refund) }}" target="_blank" class="hover:text-primary-color mr-2" title="View refund receipt">
                                                        <i class="fa-regular fa-file-lines"></i>
                                                    </a>
                                                    <a href="{{ route('admin.payment.receipt', ['payment' => $refund, 'download' => 1]) }}" class="hover:text-primary-color" title="Download refund receipt">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            @endisset

            <div class="flex justify-end mt-8 gap-4">
                @if(! $isRefundRecord)
                    <x-ui.button type="submit">Save</x-ui.button>
                @endif
            </div>
        </form>

        @if(isset($customerPayment) && ! $isRefundRecord)
            <div class="mt-4">
                @if($isCreditGrant)
                    <div class="border rounded-lg p-4 text-sm text-gray-600">
                        This payment method is Credit and is not refundable.
                    </div>
                @elseif($isSquareManaged)
                    @if($squareRemainingCents <= 0 || $displayRemainingRefundableAmount <= 0)
                        <div class="border rounded-lg p-4 text-sm text-gray-600">
                            Square refund is unavailable because there are no unallocated funds available to refund.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('admin.payment.square.refund', $customerPayment) }}"
                              class="border rounded-lg p-4"
                              x-data="{ isSubmitting: false }"
                              x-on:submit.prevent="if (isSubmitting) return; isSubmitting = true; $el.submit();">
                            @csrf
                            <h3 class="font-bold text-lg mb-3">Process Square Refund</h3>
                            <x-ui.input type="number" step="0.01" min="0.01" label="Refund Amount (optional)" name="amount" value="" info="Leave blank to refund remaining amount. This creates a refund payment record against unallocated credit only." :moneyFormat="true" />
                            <x-ui.input label="Reason (optional)" name="reason" value="" />
                            <x-ui.input label="Idempotency Key (optional)" name="idempotency_key" value="" />
                            <div class="mt-4 text-right">
                                <x-ui.button type="submit" color="dark" x-bind:disabled="isSubmitting">
                                    <span x-show="!isSubmitting">Process Refund</span>
                                    <span x-show="isSubmitting" x-cloak>Processing...</span>
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                @else
                    @if($displayRemainingRefundableAmount <= 0)
                        <div class="border rounded-lg p-4 text-sm text-gray-600">
                            Manual refund is unavailable because there are no unallocated funds available to refund.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('admin.payment.refund.manual', $customerPayment) }}"
                              class="border rounded-lg p-4"
                              x-data="{ isSubmitting: false }"
                              x-on:submit.prevent="if (isSubmitting) return; isSubmitting = true; $el.submit();">
                            @csrf
                            <h3 class="font-bold text-lg mb-3">Record Manual Refund</h3>
                            <x-ui.input type="number" step="0.01" min="0.01" label="Refund Amount (optional)" name="amount" value="" info="Leave blank to refund remaining amount. Refunds unallocated credit only and does not alter invoices." :moneyFormat="true" />
                            <x-ui.select label="Refund Method" name="payment_method">
                                <option value="" disabled {{ old('payment_method', $manualRefundDefaultMethod) === '' ? 'selected' : '' }}>Select refund method</option>
                                <option value="{{ \App\Models\Payment::PAYMENT_METHOD_CASH }}" {{ old('payment_method', $manualRefundDefaultMethod) === \App\Models\Payment::PAYMENT_METHOD_CASH ? 'selected' : '' }}>Cash</option>
                                <option value="{{ \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER }}" {{ old('payment_method', $manualRefundDefaultMethod) === \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER ? 'selected' : '' }}>Bank Transfer</option>
                            </x-ui.select>
                            <x-ui.input label="Reason (optional)" name="reason" value="" />
                            <div class="mt-4 text-right">
                                <x-ui.button type="submit" color="dark" x-bind:disabled="isSubmitting">
                                    <span x-show="!isSubmitting">Record Refund</span>
                                    <span x-show="isSubmitting" x-cloak>Processing...</span>
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
        @endif
    </x-container>
</x-layout>
