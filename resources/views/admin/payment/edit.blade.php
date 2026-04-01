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
    $canEditLinkage = ! $isRefundRecord;
    $canEditAllocations = $canEditLinkage;
    $existingLockedAllocations = isset($customerPayment)
        ? (!$canEditAllocations
            ? $customerPayment->allocations
            ->filter(fn ($allocation) => abs((float) $allocation->allocated_amount) > 0.0001 && ($allocation->invoice || $allocation->taxAdjustment))
            ->values()
            : collect())
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
    $receiptLinkItems = isset($customerPayment) && ! $isCreditGrant
        ? collect([
            [
                'number' => (string) $customerPayment->id,
                'view_url' => route('admin.payment.receipt', $customerPayment),
                'download_url' => route('admin.payment.receipt', ['payment' => $customerPayment, 'download' => 1]),
            ],
        ])
        : collect();
    $selectedUserId = (string) old('user_id', $customerPayment->user_id ?? ($prefillUserId ?? ''));
    $manualRefundDefaultMethod = isset($customerPayment) && in_array((string) ($customerPayment->payment_method ?? ''), [
        \App\Models\Payment::PAYMENT_METHOD_CASH,
        \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER,
    ], true)
        ? (string) $customerPayment->payment_method
        : '';
    $highlightRefundId = (int) request()->query('highlight_refund', 0);

    if ($savedAllocations === null) {
        $savedAllocations = isset($customerPayment) && $canEditAllocations
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
    $selectedCustomerId = (string) old('user_id', $customerPayment->user_id ?? ($prefillUserId ?? ''));
@endphp

<x-layout>
    <x-mast backRoute="admin.payment.index" backTitle="Payments">{{ isset($customerPayment) ? 'Edit' : 'Record' }} Payment</x-mast>

    <x-container class="mt-4">
        <form
            id="payment-edit-form"
            method="POST"
            action="{{ route('admin.payment.' . (isset($customerPayment) ? 'update' : 'store'), $customerPayment ?? []) }}"
              x-data="{
                selectedPaymentMethod: @js(old('payment_method', $customerPayment->payment_method ?? '')),
                bankTransferMethod: @js(\App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER),
                bankTransferCleared: @js(old('bank_transfer_cleared', isset($customerPayment) ? $customerPayment->cleared_at !== null : false)),
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
                addAllAllocations() {
                    const customerId = String(this.selectedCustomerId || '').trim();
                    let remainingFunds = this.paymentTotal();
                    const generated = [];

                    for (const option of (this.invoiceOptions || [])) {
                        if (customerId !== '' && String(option?.user_id || '').trim() !== customerId) {
                            continue;
                        }

                        const invoiceId = parseInt(option?.id || 0);
                        if (!Number.isFinite(invoiceId) || invoiceId <= 0) {
                            continue;
                        }

                        const invoiceRemaining = parseFloat(this.invoiceRemainingById[String(invoiceId)] || option?.remaining_amount || 0);
                        if (!(invoiceRemaining > 0.0001)) {
                            continue;
                        }

                        if (!(remainingFunds > 0.0001)) {
                            break;
                        }

                        const allocatedAmount = Math.max(0, Math.min(invoiceRemaining, remainingFunds));
                        const normalizedAmount = this.normalizeMoney(allocatedAmount);
                        if (!(parseFloat(normalizedAmount) > 0.0001)) {
                            continue;
                        }

                        generated.push({
                            invoice_id: invoiceId,
                            allocated_amount: normalizedAmount,
                        });
                        remainingFunds = Math.max(0, parseFloat((remainingFunds - parseFloat(normalizedAmount)).toFixed(2)));
                    }

                    this.allocations = generated;
                    this.serializeAllocations();
                },
                removeAllocation(index) {
                    this.allocations.splice(index, 1);
                    this.serializeAllocations();
                },
                normalizeMoney(value) {
                    const parsed = parseFloat(value || 0);
                    return Number.isFinite(parsed) ? parsed.toFixed(2) : '0.00';
                },
                formatMoney(value) {
                    const parsed = parseFloat(value || 0);
                    return '$' + (Number.isFinite(parsed) ? parsed.toFixed(2) : '0.00');
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
                selectedCustomerId: @js($selectedCustomerId),
                invoiceOptions: @js($invoiceOptions ?? []),
                existingInvoiceOptionsById: @js($existingInvoiceOptionsById ?? []),
                selectedInvoiceOption(invoiceId) {
                    const key = String(parseInt(invoiceId || 0));
                    return this.invoiceOptions.find((invoice) => String(invoice.id) === key)
                        || this.existingInvoiceOptionsById[key]
                        || null;
                },
                invoiceViewUrls: @js($invoiceViewUrls),
                invoiceRemainingById: @js($invoiceRemainingLookup),
              }"
              x-on:admin-linked-user-changed.window="selectedCustomerId = $event.detail?.userId || ''"
              x-on:payment-add-all.window="addAllAllocations()"
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

            <input type="hidden" name="allocations_json" x-ref="allocationsJson" value="{{ $savedAllocations }}" />

            <x-admin.user-selector-inline
                :users="$users ?? collect()"
                :selected-user-id="$selectedUserId"
                field-name="user_id"
                lookup-name="payment_user_lookup"
                label="Customer"
                info="Search by name/company/email. Select a customer for this payment."
                :disabled="!$canEditLinkage"
            />

            <div class="flex gap-8">
                <div class="flex-1">
                <x-ui.input type="datetime-local" label="Received On" name="received_on" value="{{ old('received_on', isset($customerPayment) && $customerPayment->received_on ? $customerPayment->received_on->format('Y-m-d\\TH:i') : now()->format('Y-m-d\\TH:i')) }}" :disabled="$isCoreLocked" />
                </div>
                <div class="flex-1">
                    <x-ui.select
                        id="payment-method"
                        label="Payment Method"
                        name="payment_method"
                        :disabled="$isCoreLocked"
                        x-model="selectedPaymentMethod"
                        x-on:change="$dispatch('payment-method-updated', { value: $event.target.value })"
                    >
                        <option value="" disabled {{ old('payment_method', $customerPayment->payment_method ?? '') === '' ? 'selected' : '' }}>Select payment method</option>
                        @foreach(($paymentMethods ?? \App\Models\Payment::PAYMENT_METHODS) as $paymentMethod)
                            <option value="{{ $paymentMethod }}" {{ old('payment_method', $customerPayment->payment_method ?? '') === $paymentMethod ? 'selected' : '' }}>
                                {{ \App\Models\Payment::paymentMethodLabel((string) $paymentMethod) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>

            <div
                class="mb-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 flex flex-col gap-4 md:flex-row md:items-center justify-between shadow-sm"
                x-show="selectedPaymentMethod === bankTransferMethod"
                x-cloak
            >
                <label class="flex items-start gap-3">
                    <input
                        id="bank-transfer-cleared"
                        type="checkbox"
                        name="bank_transfer_cleared"
                        value="1"
                        class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                        x-model="bankTransferCleared"
                        x-on:change="$dispatch('bank-transfer-cleared-updated', { checked: $event.target.checked })"
                        x-bind:disabled="@js(isset($customerPayment) && $customerPayment->cleared_at !== null)"
                        @checked(old('bank_transfer_cleared', isset($customerPayment) ? $customerPayment->cleared_at !== null : false))
                    >
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">Mark this bank transfer as cleared</span>
                        <span class="mt-1 block text-xs text-gray-500">Leave this unchecked if the transfer is still waiting to settle.</span>
                    </span>
                </label>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Invoice allocations will not apply until the transfer is marked as cleared.
                </div>
            </div>

            @if((string) old('payment_method', $customerPayment->payment_method ?? '') === \App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER)
                <div class="mb-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Clearance Status</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">
                        {{ isset($customerPayment) && $customerPayment->cleared_at ? 'Cleared' : 'Pending clearance' }}
                    </div>
                </div>
            @endif

            <x-ui.input label="Reference" name="reference" value="{{ old('reference', $customerPayment->reference ?? '') }}" :disabled="!$canEditLinkage" />

            <x-ui.input type="text" label="Total Amount" name="total_amount" value="{{ old('total_amount', $customerPayment->total_amount ?? (isset($prefillTotalAmount) && $prefillTotalAmount !== null ? number_format((float) $prefillTotalAmount, 2, '.', '') : '0.00')) }}" :moneyFormat="true" :disabled="$isCoreLocked" x-ref="totalAmountInput" />

            <div class="border rounded-lg p-4 mb-4 border-gray-400" x-init="serializeAllocations()">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-lg">Invoice Allocations</h3>
                    @if($canAddAllocations)
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-1.5 text-sm font-semibold leading-6 text-gray-700 shadow-sm transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color" x-on:click.prevent="addAllocation()">Add Allocation</button>
                        </div>
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
                                            <td class="py-2 pr-3 text-right">Remaining: ${{ number_format((float) $allocation->allocated_amount, 2) }}</td>
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
                    <div
                        class="grid grid-cols-12 gap-3 items-start mb-3"
                        x-data="{
                            open: false,
                            query: '',
                            focusSearch() {
                                this.$nextTick(() => this.$refs.search?.focus());
                            },
                            currentInvoice() {
                                return this.selectedInvoiceOption(allocation.invoice_id);
                            },
                            selectedInvoiceLabel() {
                                const current = this.currentInvoice();
                                return current ? current.selection_label : 'Select invoice';
                            },
                            selectedInvoiceMeta() {
                                const current = this.currentInvoice();
                                if (!current) {
                                    return '';
                                }

                                return [current.status_label, current.ticket_summary].filter((value) => String(value || '').trim() !== '').join(' · ');
                            },
                            filteredInvoiceOptions() {
                                const customerId = String(this.selectedCustomerId || '').trim();
                                const needle = String(this.query || '').toLowerCase().trim();

                                return (this.invoiceOptions || []).filter((option) => {
                                    if (customerId !== '' && String(option?.user_id || '').trim() !== customerId) {
                                        return false;
                                    }

                                    if (needle === '') {
                                        return true;
                                    }

                                    return String(option?.search || '').toLowerCase().includes(needle);
                                });
                            },
                            selectInvoice(option) {
                                allocation.invoice_id = option.id;
                                this.open = false;
                                this.query = '';
                                this.autoFillAllocation(index);
                            },
                            clearInvoice() {
                                allocation.invoice_id = '';
                                allocation.allocated_amount = '0.00';
                                this.open = false;
                                this.query = '';
                                this.serializeAllocations();
                            },
                        }"
                        @click.outside="open = false"
                        @keydown.escape.window="open = false"
                    >
                        <div class="col-span-8 relative">
                            <label class="block text-sm pl-1">Invoice</label>
                            <button
                                type="button"
                                class="disabled:bg-gray-100 bg-white block mt-1 px-3 py-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300 shadow-sm text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                                @if($canEditAllocations)
                                    x-on:click="open = !open; if (open) { focusSearch(); }"
                                @else
                                    disabled
                                @endif
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate font-medium" x-text="selectedInvoiceLabel()"></div>
                                        <div class="mt-1 text-xs text-gray-500" x-show="selectedInvoiceMeta()" x-cloak x-text="selectedInvoiceMeta()"></div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                                        <template x-if="currentInvoice()">
                                            <span x-text="'Remaining: '+formatMoney(currentInvoice()?.remaining_amount || 0)"></span>
                                        </template>
                                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                    </div>
                                </div>
                            </button>
                            <div
                                x-show="open"
                                x-cloak
                                class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-lg border border-gray-300 bg-white shadow-xl"
                            >
                                <div class="border-b border-gray-200 p-3 space-y-3">
                                    <div class="flex items-end gap-3">
                                        <div class="min-w-0 flex-1">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search invoices</label>
                                            <input
                                                x-ref="search"
                                                type="text"
                                                class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-color focus:outline-none focus:ring-1 focus:ring-primary-color"
                                                placeholder="Invoice, customer, email, ticket..."
                                                x-model="query"
                                                x-on:keydown.escape.prevent="open = false"
                                            />
                                        </div>
                                        @if($canEditAllocations)
                                            <button
                                                type="button"
                                                class="shrink-0 rounded-md bg-primary-color px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-color-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                                                x-on:click.prevent="$dispatch('payment-add-all'); open = false"
                                                title="Automatically distribute the payment total across invoices from top to bottom"
                                            >Add all</button>
                                        @endif
                                    </div>
                                    @if($canEditAllocations)
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                class="text-xs font-semibold text-gray-600 hover:text-primary-color disabled:opacity-40"
                                                x-show="allocation.invoice_id"
                                                x-on:click.prevent="clearInvoice()"
                                            >Clear selection</button>
                                        </div>
                                    @endif
                                </div>
                                <div class="max-h-72 overflow-auto py-1">
                                    <template x-if="filteredInvoiceOptions().length === 0">
                                        <div class="px-4 py-6 text-sm text-gray-500">No invoices match this filter.</div>
                                    </template>
                                    <template x-for="option in filteredInvoiceOptions()" :key="option.id">
                                        <button
                                            type="button"
                                            class="w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50 last:border-b-0"
                                            x-on:mousedown.prevent="selectInvoice(option)"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="truncate text-sm font-medium text-gray-900" x-text="option.selection_label"></div>
                                                </div>
                                                <div class="flex shrink-0 flex-col items-end text-right text-xs text-gray-500">
                                                    <span class="font-semibold text-gray-900" x-text="'Remaining: ' + formatMoney(option.remaining_amount || 0)"></span>
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500" x-text="option.payment_state_label"></span>
                                                </div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-3 h-full flex flex-col">
                            <label class="block text-sm pl-1">Amount</label>
                            <div class="flex flex-1 items-center">
                                <input type="text" class="mt-1 disabled:bg-gray-100 bg-white block px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border border-gray-300" x-model="allocation.allocated_amount" x-on:input="serializeAllocations()" x-on:blur="normalizeAllocation(index)" {{ $canEditAllocations ? '' : 'disabled' }} />
                            </div>
                        </div>
                        <div class="col-span-1 h-full">
                            <div class="h-full flex items-center gap-3 pt-5">
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

            <x-ui.input type="textarea" label="Notes (Private)" name="notes" :value="old('notes', $customerPayment->notes ?? '')" />

            @isset($customerPayment)
                <h3 class="text-sm mb-1">Payment Split</h3>
                <div class="mb-4 rounded-lg border border-gray-300 bg-white p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                        <div><span class="font-semibold w-44 inline-block">Original Amount:</span> ${{ number_format((float) $customerPayment->total_amount, 2) }}</div>
                        <div><span class="font-semibold w-44 inline-block">Refunded:</span> ${{ number_format($recordedRefundedAmount, 2) }}</div>
                        <div><span class="font-semibold w-44 inline-block">Remaining Refundable:</span> ${{ number_format($displayRemainingRefundableAmount, 2) }}</div>
                        <div><span class="font-semibold w-44 inline-block">Allocated:</span> ${{ number_format($allocatedNetAmount, 2) }}</div>
                        <div><span class="font-semibold w-44 inline-block">Unallocated:</span> ${{ number_format($unallocatedAmount, 2) }}</div>
                    </div>
                </div>

                @if($isSquareManaged || ! $isCreditGrant)
                    <h3 class="text-sm mb-1">Transaction Details</h3>
                    <div class="mb-4 rounded-lg border border-gray-300 bg-white p-4">
                        @if($isSquareManaged)
                            <div>
                                <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Square Integration</div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm font-mono">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Gateway</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->gateway_provider ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Status</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->gateway_status ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Square Payment ID</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->square_payment_id ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Order ID</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->square_order_id ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Card</div>
                                        <div class="mt-1 break-all text-gray-900">
                                            @if($customerPayment->square_card_brand || $customerPayment->square_card_last4)
                                                {{ trim(($customerPayment->square_card_brand ?? '').' ****'.($customerPayment->square_card_last4 ?? '')) }}
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Paid / Refunded (cents)</div>
                                        <div class="mt-1 break-all text-gray-900">{{ (int) ($customerPayment->square_paid_money_amount ?? 0) }} / {{ (int) ($customerPayment->square_refunded_money_amount ?? 0) }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Square Created</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->square_gateway_created_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Square Updated</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->square_gateway_updated_at?->format('M j, Y g:i a') ?? '-' }}</div>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 md:col-span-2">
                                        <div class="text-[11px] uppercase tracking-wide text-gray-500">Last Webhook</div>
                                        <div class="mt-1 break-all text-gray-900">{{ $customerPayment->square_last_event_type ?? '-' }}{{ $customerPayment->square_last_event_at ? ' @ '.$customerPayment->square_last_event_at->format('M j, Y g:i a') : '' }}</div>
                                    </div>
                                </div>
                                @php
                                    $squareMeta = is_array($customerPayment->square_integration_meta ?? null)
                                        ? $customerPayment->square_integration_meta
                                        : [];
                                @endphp
                                @if($squareMeta !== [])
                                    <div class="mt-4 border-t border-gray-200 pt-4">
                                        <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Square Metadata</div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm font-mono">
                                            @foreach($squareMeta as $metaKey => $metaValue)
                                                @continue(is_array($metaValue) || is_object($metaValue))
                                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 md:col-span-1">
                                                    <div class="text-[11px] uppercase tracking-wide text-gray-500">{{ \Illuminate\Support\Str::headline((string) $metaKey) }}</div>
                                                    <div class="mt-1 break-all text-gray-900">{{ is_bool($metaValue) ? ($metaValue ? 'true' : 'false') : (string) $metaValue }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if(! $isCreditGrant)
                        <h3 class="text-sm mb-1">Receipt Links</h3>
                        <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4">
                            @if($receiptLinkItems->isEmpty())
                                <div class="text-sm text-gray-500">No receipt links available.</div>
                            @else
                                <ul class="space-y-2 list-disc pl-5 text-gray-900 marker:text-gray-900">
                                    @foreach($receiptLinkItems as $receiptLink)
                                        <li class="py-2 text-sm text-gray-900">
                                            <div class="flex items-center justify-between gap-3">
                                                <a href="{{ $receiptLink['view_url'] }}" target="_blank" class="text-primary-color hover:text-primary-color-dark hover:underline" title="View receipt">Receipt #{{ $receiptLink['number'] }}</a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <h3 class="text-sm mb-1">Refund Details</h3>
                        <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4">
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
                                                <tr id="refund-{{ $refund->id }}" class="border-b border-gray-100 {{ $highlightRefundId === (int) $refund->id ? 'highlight-row' : '' }}">
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
                                @if($highlightRefundId > 0)
                                    <script>
                                        window.addEventListener('DOMContentLoaded', function () {
                                            var target = document.getElementById('refund-{{ $highlightRefundId }}');
                                            if (target) {
                                                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            }
                                        });
                                    </script>
                                @endif
                            @endif
                        </div>
                    @endif
                @endif
            @endisset

        </form>

        @if(isset($customerPayment) && ! $isRefundRecord)
            <div class="mt-4">
                @if($isCreditGrant)
                    <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-600">
                        This payment method is Credit and is not refundable.
                    </div>
                @elseif($isSquareManaged)
                    @if($squareRemainingCents <= 0 || $displayRemainingRefundableAmount <= 0)
                        <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-600">
                            Square refund is unavailable because there are no unallocated funds available to refund.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('admin.payment.square.refund', $customerPayment) }}"
                              class="rounded-lg border border-gray-300 bg-white p-4"
                              x-data="{ isSubmitting: false }"
                              x-on:submit.prevent="if (isSubmitting) return; isSubmitting = true; $el.submit();">
                            @csrf
                            <h3 class="font-bold text-lg mb-3">Process Square Refund</h3>
                            <x-ui.input type="number" step="0.01" min="0.01" label="Refund Amount (optional)" name="amount" value="" info="Leave blank to refund remaining amount. This creates a refund payment record against unallocated credit only." :moneyFormat="true" />
                            <x-ui.input label="Reason (optional)" name="reason" value="" />
                            <x-ui.input label="Idempotency Key (optional)" name="idempotency_key" value="" info="Optional. Max 45 characters to match Square." />
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
                        <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-600">
                            Manual refund is unavailable because there are no unallocated funds available to refund.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('admin.payment.refund.manual', $customerPayment) }}"
                              class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
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
                            <x-ui.input type="datetime-local" label="Refund Date/Time" name="received_on" value="{{ old('received_on', now()->format('Y-m-d\TH:i')) }}" />
                            <x-ui.input label="Transfer / Cash Reference" name="reference" value="" info="Optional receipt number, transfer note, or cash reference." />
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

        @if(! $isRefundRecord)
            <div
                class="mt-8 flex justify-between items-center flex-col sm:flex-row gap-3"
                x-data="{
                    selectedPaymentMethod: '',
                    bankTransferMethod: @js(\App\Models\Payment::PAYMENT_METHOD_BANK_TRANSFER),
                    bankTransferCleared: false,
                    init() {
                        this.selectedPaymentMethod = document.getElementById('payment-method')?.value || '';
                        this.bankTransferCleared = document.getElementById('bank-transfer-cleared')?.checked || false;
                    }
                }"
                x-init="init()"
                x-on:payment-method-updated.window="selectedPaymentMethod = $event.detail.value"
                x-on:bank-transfer-cleared-updated.window="bankTransferCleared = $event.detail.checked"
            >
                <label
                    class="flex items-center gap-3"
                    x-bind:class="selectedPaymentMethod === bankTransferMethod && ! bankTransferCleared ? 'opacity-60' : ''"
                >
                    <input
                        type="checkbox"
                        name="email_receipt"
                        value="1"
                        form="payment-edit-form"
                        class="h-5 w-5 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                        x-bind:disabled="selectedPaymentMethod === bankTransferMethod && ! bankTransferCleared"
                        x-bind:title="selectedPaymentMethod === bankTransferMethod && ! bankTransferCleared ? 'Mark the bank transfer as cleared first' : null"
                        @checked(old('email_receipt'))
                    >
                    <span class="block text-sm font-semibold text-gray-900">Email receipt to customer</span>
                </label>

                <x-ui.button type="submit" form="payment-edit-form" class="w-full sm:w-auto">Save</x-ui.button>
            </div>
        @endif

    </x-container>
</x-layout>
