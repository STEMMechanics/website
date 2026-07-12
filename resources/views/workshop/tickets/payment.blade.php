@php
    $accountCreditAvailable = round((float) ($accountCreditAvailable ?? 0), 2);
    $applyAccountCreditDefault = (bool) ($applyAccountCreditDefault ?? ($accountCreditAvailable > 0.0001));
    $accountTermsDays = (int) ($accountTermsDays ?? 0);
    $canUseAccountTerms = (bool) ($canUseAccountTerms ?? ($accountTermsDays > 0));
    $accountTermsLabel = trim((string) ($accountTermsLabel ?? ($accountTermsDays > 0 ? $accountTermsDays.' days' : 'Current')));
    $voucherCode = trim((string) ($voucherCode ?? ''));
    $voucherDiscountAmount = round((float) ($voucherDiscountAmount ?? 0), 2);
    $voucherButtonLabel = trim((string) ($voucherButtonLabel ?? ($voucherCode !== '' ? 'Change voucher' : 'Add voucher')));
    $ticketPricing = is_array($ticketPricing ?? null) ? $ticketPricing : [];
    $pricingItems = is_array($ticketPricing['items'] ?? null) ? $ticketPricing['items'] : [];
    $ticketSubtotal = round((float) ($ticketPricing['subtotal_amount'] ?? ((float) $ticketPriceAmount * (int) ($holdCount ?? 0))), 2);
    $ticketTotal = round(max(0, $ticketSubtotal - $voucherDiscountAmount), 2);
    $hasAmountDue = $ticketTotal > 0.0001;
    $earlyBirdSummary = $workshop->earlyBirdSummaryLabel();
    $summaryRows = [];
    if ($pricingItems !== []) {
        foreach ($pricingItems as $item) {
            $count = (int) ($item['count'] ?? 0);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $label = trim((string) ($item['label'] ?? 'Tickets'));
            $value = $count.' @ '.($unitPrice > 0 ? '$'.number_format($unitPrice, 2).' per ticket' : 'Free');

            if (! empty($item['is_early_bird'])) {
                $value .= ' (Early bird)';
            }

            $summaryRows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }
    } else {
        $summaryRows[] = ['label' => 'Tickets', 'value' => $holdCount.' @ '.($ticketPriceAmount > 0 ? '$'.number_format($ticketPriceAmount, 2).' per ticket' : 'Free')];
    }
    if ($earlyBirdSummary && count($pricingItems) <= 1) {
        $summaryRows[] = ['label' => 'Early Bird', 'value' => $earlyBirdSummary];
    }
    if ($voucherDiscountAmount > 0.0001) {
        $summaryRows[] = ['type' => 'spacer'];
        $summaryRows[] = [
            'label' => 'Discount',
            'value' => '$-'.number_format($voucherDiscountAmount, 2).($voucherCode !== '' ? ' ('.$voucherCode.')' : ''),
        ];
    } else {
        $summaryRows[] = ['type' => 'spacer'];
    }
    $summaryRows[] = [
        'label' => 'Total Cost',
        'value' => $ticketTotal > 0 ? '$'.number_format($ticketTotal, 2) : 'Free',
    ];
@endphp

<x-layout>
    <x-mast>Ticket Checkout</x-mast>

    <x-container class="max-w-3xl mt-6 mx-auto">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6 relative"
            x-data="ticketPaymentPage({
                        squareEnabled: @js($squareEnabled),
                        squareApplicationId: @js($squareApplicationId),
                        squareLocationId: @js($squareLocationId),
                    squareEnvironment: @js($squareEnvironment),
                    expiresAt: @js($session['expires_at']),
                    accountCreditAvailable: @js($accountCreditAvailable),
                    accountTermsDays: @js($accountTermsDays),
                    canUseAccountTerms: @js($canUseAccountTerms),
                    totalAmount: @js($ticketTotal),
                    useAccountCredit: @js((bool) old('apply_account_credit', $applyAccountCreditDefault)),
                    paymentMethod: @js($ticketTotal > 0 ? old('payment_method', $canUseAccountTerms ? 'account_terms' : 'pay_at_door') : 'pay_at_door'),
                    voucherCode: @js($voucherCode),
                    voucherDraft: @js(old('voucher_code', $voucherCode)),
                    voucherError: @js($errors->first('voucher_code') ?: ''),
                    voucherDialogOpen: @js($errors->has('voucher_code')),
                })"
            x-init="startHoldTimer(); if (voucherDialogOpen) { $nextTick(() => { $refs.voucherInput?.focus() }) }">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">Payment</h2>

                @include('workshop.tickets.partials.summary', [
                    'workshop' => $workshop,
                    'rows' => $summaryRows,
                    'totalActionLabel' => $voucherButtonLabel,
                    'totalActionAttributes' => [
                        'x-on:click' => 'openVoucherDialog()',
                    ],
                ])

                <form id="ticket-payment-form" method="POST" action="{{ route('workshop.ticket.flow.payment.process', $workshop) }}"
                    x-on:submit.prevent="submitForm($event)">
                    @csrf
                    @if($accountCreditAvailable > 0.0001)
                        <div class="my-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                            <div class="flex flex-col">
                                <input type="hidden" name="apply_account_credit" value="0">
                                <x-ui.checkbox
                                    name="apply_account_credit"
                                    label="Apply account credit first"
                                    :checked="$applyAccountCreditDefault"
                                    :noWrapper="true"
                                    inline="true"
                                    inputClass="border-emerald-300"
                                    labelClass="font-semibold text-emerald-950"
                                    x-model="useAccountCredit"
                                    x-on:change="onCreditToggle()"
                                    class="mb-0"
                                />
                                <div class="text-sm text-emerald-900 ml-10">
                                    <div>
                                        Available credit:
                                        <strong>${{ number_format($accountCreditAvailable, 2) }}</strong>
                                    </div>
                                    <div x-show="useAccountCredit && totalAmount > 0.0001 && remainingAfterCredit() > 0.0001" x-cloak class="mt-1">
                                        Remaining after credit:
                                        <strong x-text="formatMoney(remainingAfterCredit())"></strong>.
                                    </div>
                                    <div x-show="useAccountCredit && totalAmount > 0.0001 && remainingAfterCredit() <= 0.0001" x-cloak class="mt-1">
                                        This purchase will be covered in full by account credit.
                                    </div>
                                    <div x-show="useAccountCredit && totalAmount <= 0.0001" x-cloak class="mt-1">
                                        This purchase does not require payment.
                                    </div>
                                    <div x-show="!useAccountCredit && totalAmount > 0.0001" x-cloak class="mt-1">
                                        Account credit will not be applied to this purchase.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif($accountCreditLoginHint)
                        <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                            An account on this email has credit available. Log in to use it toward this purchase.
                        </div>
                    @endif

                    <div class="my-12 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-center" x-show="expired" x-cloak>
                        Your ticket hold has now expired.
                    </div>
                    <input
                        type="hidden"
                        name="payment_method"
                        value="{{ $canUseAccountTerms ? 'account_terms' : 'credit' }}"
                        x-bind:name="isFullyCoveredByCredit() ? 'payment_method' : null"
                    >
                    <div x-show="!expired && !isFullyCoveredByCredit()" x-cloak>
                        <x-ui.select
                            label="Payment Method"
                            name="payment_method"
                            error=""
                            x-model="paymentMethod"
                            x-on:change="onPaymentMethodChange()"
                            x-on:mousedown="if (isSubmitting || expired) { $event.preventDefault() }"
                            x-on:keydown="if (isSubmitting || expired) { $event.preventDefault() }">
                            @if($totalAmount > 0)
                            @if($canUseAccountTerms)
                            <option value="account_terms">Charge to account ({{ $accountTermsLabel }})</option>
                            @endif
                            <option value="pay_at_door">Pay at the door</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="credit_card" {{ ($squareEnabled && $squareApplicationId !== '' && $squareLocationId !== '') ? '' : 'disabled' }}>Pay by credit card</option>
                            @else
                            <option value="pay_at_door">No payment required</option>
                            @endif
                        </x-ui.select>
                        @if($canUseAccountTerms)
                            <div x-show="paymentMethod === 'account_terms'" x-cloak class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                                Your account has {{ $accountTermsDays }}-day terms. This order will be invoiced and payable within {{ $accountTermsDays }} days.
                            </div>
                        @endif
                    </div>

                    <div x-show="!expired && !isFullyCoveredByCredit() && paymentMethod === 'credit_card' && remainingAfterCredit() > 0.0001" x-cloak x-init="initSquareCard()">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm">Card Details</label>
                            <a href="https://squareup.com/au/en" target="_blank" class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs text-blue-700" rel="noopener noreferrer">
                                Secure payment by Square
                            </a>
                        </div>
                        <div class="relative">
                            <div x-ref="squareCardContainer"
                                class="min-h-22 transition"
                                x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                            <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                                <img src="{{ asset('loading.gif') }}" alt="Loading card form" width="56" height="56" />
                            </div>
                        </div>
                        <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">
                        <div x-show="errorMessage" class="text-xs text-red-600 mt-2" x-text="errorMessage"></div>
                        @error('source_id')
                        <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                    @if($bankTransferNotice)
                        <div
                            x-show="!expired && paymentMethod === 'bank_transfer'"
                            x-cloak
                            class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                            {!! nl2br(e($bankTransferNotice)) !!}
                        </div>
                    @endif
                    @if($payAtDoorNotice)
                        <div
                            x-show="!expired && paymentMethod === 'pay_at_door'"
                            x-cloak
                            class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                            {!! nl2br(e($payAtDoorNotice)) !!}
                        </div>
                    @endif
                    @error('payment_method')
                    <div class="text-xs text-red-600 mb-3">{{ $message }}</div>
                    @enderror

                    <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between" x-show="!expired" x-cloak>
                        <x-ui.button type="button" color="danger-outline" x-bind:disabled="isSubmitting || expired" onclick="document.getElementById('ticket-cancel-form').submit();">Cancel</x-ui.button>
                        <x-ui.button type="submit" x-bind:disabled="expired || isSubmitting || (paymentMethod === 'credit_card' && isCardLoading)">
                            <span x-show="!isSubmitting" x-text="submitButtonLabel()"></span>
                            <span x-show="isSubmitting" x-cloak>Processing...</span>
                        </x-ui.button>
                    </div>
                    <div class="flex justify-end mt-6" x-show="expired" x-cloak>
                        <x-ui.button color="outline" class="w-full" href="{{ route('workshop.show', $workshop) }}">Back</x-ui.button>
                    </div>
                </form>

                <template x-teleport="body">
                    <div
                        x-show="voucherDialogOpen"
                        x-cloak
                        class="fixed inset-0 z-280 flex items-end justify-center bg-slate-950/55 p-4 sm:items-start sm:pt-[12vh]"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="ticket-voucher-dialog-title"
                        @click.self="closeVoucherDialog()"
                        @keydown.escape.window="if (voucherDialogOpen) closeVoucherDialog()"
                    >
                        <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-xl flex-col overflow-visible rounded-3xl bg-white shadow-2xl">
                            <div class="px-6 py-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 id="ticket-voucher-dialog-title" class="text-xl font-bold text-gray-900">Enter a voucher code</h3>
                                    </div>
                                    <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeVoucherDialog()" aria-label="Close voucher dialog">
                                        <i class="fa-solid fa-xmark text-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4 px-6 pb-5">
                                <form method="POST" action="{{ route('workshop.ticket.flow.voucher', $workshop) }}" @submit.prevent="applyVoucherCode($event)">
                                    @csrf
                                    <x-ui.input
                                        name="voucher_code"
                                        no-label="true"
                                        class="mb-0"
                                        x-ref="voucherInput"
                                        x-model="voucherDraft"
                                        x-bind:disabled="voucherBusy"
                                        x-bind:class="voucherError ? 'border-red-600! ring-red-600! focus:border-red-600! focus:ring-red-600!' : ''"
                                    />

                                    <div x-show="voucherError" x-cloak class="mt-2 ml-2 text-xs text-red-600" x-text="voucherError"></div>

                                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <x-ui.button type="button" color="outline" x-bind:disabled="voucherBusy" x-on:click="closeVoucherDialog()">Cancel</x-ui.button>
                                        <x-ui.button type="submit" x-bind:disabled="voucherBusy || (voucherDraft.trim() === '' && voucherCode === '')">Apply Voucher</x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center text-right" style="background-image:url('{{ $workshop->hero?->url }}')">
            </div>
            <div class="absolute top-0 right-0 m-4 rounded-lg border border-amber-300 bg-amber-50 p-2 text-sm w-38 flex shadow-md" x-show="!expired" x-cloak>
                <span>Remaining:</span>
                <span class="font-bold text-center flex-1" x-text="timeRemainingText()"></span>
            </div>

            <form id="ticket-cancel-form" method="POST" action="{{ route('workshop.ticket.flow.cancel', $workshop) }}" class="hidden">
                @csrf
            </form>
        </div>
    </x-container>
</x-layout>

@if($squareEnabled)
<script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function ticketPaymentPage(config) {
        return {
            paymentMethod: @js($totalAmount > 0 ? old('payment_method', $canUseAccountTerms ? 'account_terms' : 'pay_at_door') : 'pay_at_door'),
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            squareCard: null,
            sourceId: '',
            errorMessage: '',
            expiresAt: config.expiresAt || null,
            remainingSeconds: 0,
            expired: false,
            isSubmitting: false,
            isCardLoading: false,
            accountCreditAvailable: Number(config.accountCreditAvailable || 0),
            accountTermsDays: Number(config.accountTermsDays || 0),
            canUseAccountTerms: Boolean(config.canUseAccountTerms),
            totalAmount: Number(config.totalAmount || 0),
            useAccountCredit: Boolean(config.useAccountCredit),
            voucherCode: String(config.voucherCode || '').trim(),
            voucherDraft: String(config.voucherDraft || '').trim(),
            voucherError: String(config.voucherError || ''),
            voucherDialogOpen: Boolean(config.voucherDialogOpen),
            voucherBusy: false,

            openVoucherDialog() {
                this.voucherError = '';
                this.voucherDraft = this.voucherCode !== '' ? this.voucherCode : this.voucherDraft;
                this.voucherDialogOpen = true;
                this.$nextTick(() => {
                    this.$refs?.voucherInput?.focus?.();
                });
            },

            closeVoucherDialog() {
                if (this.voucherBusy) {
                    return;
                }

                this.voucherError = '';
                this.voucherDialogOpen = false;
            },

            async applyVoucherCode(event) {
                if (this.voucherBusy) {
                    return;
                }

                const form = event?.target instanceof HTMLFormElement
                    ? event.target
                    : event?.target?.closest?.('form');
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                this.voucherBusy = true;
                this.voucherError = '';

                const data = new FormData(form);
                data.set('voucher_code', this.voucherDraft.trim());

                try {
                    const response = await window.axios.post(form.action, data);
                    if (response?.data?.redirect_url) {
                        window.location.assign(response.data.redirect_url);
                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    const redirectUrl = error?.response?.data?.redirect_url;
                    if (redirectUrl) {
                        window.location.assign(redirectUrl);
                        return;
                    }

                    this.voucherError = error?.response?.data?.message
                        || error?.response?.data?.errors?.voucher_code?.[0]
                        || 'Unable to update the voucher right now.';
                } finally {
                    this.voucherBusy = false;
                }
            },

            canUseCreditCard() {
                return this.remainingAfterCredit() > 0.0001 && this.squareEnabled && this.squareApplicationId !== '' && this.squareLocationId !== '';
            },

            formatMoney(value) {
                return new Intl.NumberFormat('en-AU', {
                    style: 'currency',
                    currency: 'AUD',
                }).format(Number(value || 0));
            },

            creditAppliedAmount() {
                return this.useAccountCredit ? Math.min(this.accountCreditAvailable, this.totalAmount) : 0;
            },

            remainingAfterCredit() {
                return Math.max(0, Math.round((this.totalAmount - this.creditAppliedAmount()) * 100) / 100);
            },

            isFullyCoveredByCredit() {
                return this.useAccountCredit && this.creditAppliedAmount() > 0.0001 && this.remainingAfterCredit() <= 0.0001;
            },

            submitButtonLabel() {
                if (this.totalAmount <= 0.0001) {
                    return 'Complete Order';
                }

                if (this.paymentMethod === 'account_terms') {
                    return 'Place on Account';
                }

                if (this.isFullyCoveredByCredit() || this.paymentMethod === 'credit') {
                    return 'Complete Purchase';
                }
                if (this.paymentMethod === 'credit_card') {
                    return 'Purchase Tickets';
                }
                return 'Reserve Tickets';
            },

            onPaymentMethodChange() {
                this.errorMessage = '';
                this.sourceId = '';
                if (this.$refs?.sourceIdInput) {
                    this.$refs.sourceIdInput.value = '';
                }
                if (this.paymentMethod === 'credit_card') {
                    this.initSquareCard();
                }
            },

            onCreditToggle() {
                this.errorMessage = '';
                if (this.$refs?.sourceIdInput) {
                    this.$refs.sourceIdInput.value = '';
                }

                if (this.isFullyCoveredByCredit()) {
                    this.sourceId = '';
                }

                if (this.paymentMethod === 'credit_card' && this.remainingAfterCredit() > 0.0001) {
                    this.initSquareCard();
                }
            },

            startHoldTimer() {
                if (!this.expiresAt) {
                    return;
                }
                const update = () => {
                    const seconds = Math.max(0, Math.floor((new Date(this.expiresAt).getTime() - Date.now()) / 1000));
                    this.remainingSeconds = seconds;
                    this.expired = seconds <= 0;
                };

                update();
                const timer = setInterval(() => {
                    update();
                    if (this.expired) {
                        clearInterval(timer);
                    }
                }, 1000);
            },

            timeRemainingText() {
                const sec = Math.max(0, this.remainingSeconds);
                const minPart = String(Math.floor(sec / 60)).padStart(2, '0');
                const secPart = String(sec % 60).padStart(2, '0');
                return `${minPart}:${secPart}`;
            },

            async initSquareCard() {
                if (this.paymentMethod !== 'credit_card' || this.remainingAfterCredit() <= 0.0001) {
                    return false;
                }

                if (!this.canUseCreditCard()) {
                    this.errorMessage = 'Credit card payments are not available right now.';
                    return false;
                }

                this.isCardLoading = true;
                const ready = await this.waitForSquareSdk();
                if (!ready) {
                    this.errorMessage = 'Square SDK did not load.';
                    this.isCardLoading = false;
                    return false;
                }

                if (this.squareCard) {
                    this.isCardLoading = false;
                    return true;
                }

                try {
                    const payments = window.Square.payments(this.squareApplicationId, this.squareLocationId);
                    this.squareCard = await payments.card();
                    await this.squareCard.attach(this.$refs.squareCardContainer);
                    this.isCardLoading = false;
                    return true;
                } catch (e) {
                    this.errorMessage = e?.message || 'Unable to load card form.';
                    this.isCardLoading = false;
                    return false;
                }
            },

            async submitForm(event) {
                if (this.isSubmitting) {
                    return;
                }
                this.errorMessage = '';
                if (this.expired) {
                    this.errorMessage = 'Your ticket hold has expired.';
                    return;
                }

                const form = event.target.tagName === 'FORM' ? event.target : event.target.closest('form');
                if (!form) {
                    this.errorMessage = 'Unable to submit payment form.';
                    return;
                }

                this.isSubmitting = true;
                if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                    window.SM.setFormProcessing(form, true, { submitLabel: 'Processing...' });
                }

                if (this.paymentMethod === 'credit_card' && this.remainingAfterCredit() > 0.0001) {
                    const ready = await this.initSquareCard();
                    if (!ready) {
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }

                    try {
                        const result = await this.squareCard.tokenize();
                        if (result.status !== 'OK') {
                            const errs = Array.isArray(result.errors) ? result.errors.map((err) => err.message).filter(Boolean) : [];
                            this.errorMessage = errs.join(' | ') || 'Card validation failed.';
                            this.isSubmitting = false;
                            if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                                window.SM.setFormProcessing(form, false);
                            }
                            return;
                        }
                        this.sourceId = result.token;
                        if (this.$refs.sourceIdInput) {
                            this.$refs.sourceIdInput.value = this.sourceId;
                        }
                    } catch (e) {
                        this.errorMessage = e?.message || 'Unable to tokenize card.';
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }
                }

                const sourceIdInput = form.querySelector('input[name=\"source_id\"]');
                if (sourceIdInput) {
                    sourceIdInput.value = this.sourceId || '';
                }
                form.submit();
            },

            async waitForSquareSdk(maxWaitMs = 8000) {
                const started = Date.now();
                while (Date.now() - started < maxWaitMs) {
                    if (window.Square) {
                        return true;
                    }
                    await new Promise((resolve) => setTimeout(resolve, 150));
                }
                return false;
            }
        };
    }
</script>
