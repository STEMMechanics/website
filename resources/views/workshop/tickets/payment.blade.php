@php
    $accountCreditAvailable = round((float) ($accountCreditAvailable ?? 0), 2);
    $applyAccountCreditDefault = (bool) ($applyAccountCreditDefault ?? ($accountCreditAvailable > 0.0001));
@endphp

<x-layout>
    <x-mast>{{ $workshop->usesClassroomRegistration() ? 'Course Checkout' : 'Ticket Checkout' }}</x-mast>

    <x-container class="max-w-3xl mt-6 mx-auto">
        @php($isClassroomAccess = $workshop->usesClassroomRegistration())
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6 relative"
            x-data="ticketPaymentPage({
                        squareEnabled: @js($squareEnabled),
                        squareApplicationId: @js($squareApplicationId),
                        squareLocationId: @js($squareLocationId),
                    squareEnvironment: @js($squareEnvironment),
                    expiresAt: @js($session['expires_at']),
                    accountCreditAvailable: @js($accountCreditAvailable),
                    totalAmount: @js($totalAmount),
                    useAccountCredit: @js((bool) old('apply_account_credit', $applyAccountCreditDefault)),
                    isClassroomAccess: @js($isClassroomAccess),
                })"
            x-init="startHoldTimer()">
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-3">{{ $isClassroomAccess ? 'Course Payment' : 'Payment' }}</h2>

                @include('workshop.tickets.partials.summary', [
                'workshop' => $workshop,
                'rows' => [
                ['label' => $isClassroomAccess ? 'Course' : 'Tickets', 'value' => $holdCount.' @ '.($ticketPriceAmount > 0 ? '$'.number_format($ticketPriceAmount, 2).' per '.($isClassroomAccess ? 'access' : 'ticket') : 'Free')],
                ['label' => 'Total Cost', 'value' => $totalAmount > 0 ? '$'.number_format($totalAmount, 2) : 'Free'],
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
                                    labelClass="text-sm font-semibold text-emerald-950"
                                    x-model="useAccountCredit"
                                    x-on:change="onCreditToggle()"
                                    class="mb-0"
                                />
                                <div class="text-sm text-emerald-900 ml-10">
                                    <div>
                                        Available credit:
                                        <strong>${{ number_format($accountCreditAvailable, 2) }}</strong>
                                    </div>
                                    <div x-show="useAccountCredit && remainingAfterCredit() > 0.0001" x-cloak class="mt-1">
                                        Remaining after credit:
                                        <strong x-text="formatMoney(remainingAfterCredit())"></strong>.
                                    </div>
                                    <div x-show="useAccountCredit && remainingAfterCredit() <= 0.0001" x-cloak class="mt-1">
                                        This purchase will be covered in full by account credit.
                                    </div>
                                    <div x-show="!useAccountCredit" x-cloak class="mt-1">
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
                        {{ $isClassroomAccess ? 'Your course registration hold has now expired.' : 'Your ticket hold has now expired.' }}
                    </div>
                    <input
                        type="hidden"
                        name="payment_method"
                        value="credit"
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
                            <option value="pay_at_door">Pay at the door</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="credit_card" {{ ($squareEnabled && $squareApplicationId !== '' && $squareLocationId !== '') ? '' : 'disabled' }}>Pay by credit card</option>
                            @else
                            <option value="pay_at_door">No payment required</option>
                            @endif
                        </x-ui.select>
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
                                class="min-h-[88px] transition"
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
            paymentMethod: @js($totalAmount > 0 ? old('payment_method', 'pay_at_door') : 'pay_at_door'),
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
            totalAmount: Number(config.totalAmount || 0),
            useAccountCredit: Boolean(config.useAccountCredit),

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
                if (this.isFullyCoveredByCredit() || this.paymentMethod === 'credit') {
                    return this.isClassroomAccess ? 'Confirm Registration' : 'Complete Purchase';
                }
                if (this.paymentMethod === 'credit_card') {
                    return this.isClassroomAccess ? 'Confirm Registration' : 'Purchase Tickets';
                }
                return this.isClassroomAccess ? 'Enrol Now' : 'Reserve Tickets';
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
                    this.errorMessage = this.isClassroomAccess ? 'Your course registration hold has expired.' : 'Your ticket hold has expired.';
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
