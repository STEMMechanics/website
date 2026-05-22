@php
    $hasAmountDue = (float) ($summary['total'] ?? 0) > 0.0001;
    $selectedShippingLabel = (string) ($summary['shipping_quote']['method'] ?? 'Shipping');
    $usesPickup = (bool) ($summary['shipping_quote']['is_pickup'] ?? false);
    $preorderItems = $lines->filter(fn ($line) => (bool) ($line->is_preorder ?? false))->values();
    $hasBackorderItems = (bool) ($summary['contains_backorder'] ?? false);
    $shipmentQuote = $summary['shipping_quote'] ?? [];
    $summaryRows = [
        ['label' => 'Items', 'value' => $lines->sum('quantity').' item'.($lines->sum('quantity') === 1 ? '' : 's')],
        ['label' => 'Subtotal', 'value' => '$'.number_format((float) $summary['subtotal'], 2)],
        ['label' => $selectedShippingLabel, 'value' => '$'.number_format((float) $summary['shipping'], 2)],
    ];

    if ((float) $summary['discount'] > 0) {
        $summaryRows[] = [
            'label' => 'Discount',
            'value' => '- $'.number_format((float) $summary['discount'], 2).($summary['coupon_code'] ? ' ('.$summary['coupon_code'].')' : ''),
            'value_class' => 'text-emerald-700',
        ];
    }

    $summaryRows[] = [
        'label' => 'Total',
        'value' => '$'.number_format((float) ($summary['total'] ?? 0), 2),
    ];
@endphp

<x-layout title="Payment" :canonical="route('shop.checkout.payment')">
    <x-mast backRoute="shop.checkout" backTitle="Checkout">Payment</x-mast>

    @include('shop.partials.processing-pause-notice', [
        'notice' => $summary['shipping_quote']['processing_pause_notice'] ?? null,
    ])

    <x-container class="max-w-4xl mt-6 mx-auto">
        <div
            class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 flex gap-6"
            x-data="shopCheckoutPaymentPage({
                squareEnabled: @js($squareEnabled),
                squareApplicationId: @js($squareApplicationId),
                squareLocationId: @js($squareLocationId),
                squareEnvironment: @js($squareEnvironment),
                requiresPayment: @js($hasAmountDue),
            })"
        >
            <div class="flex-1">
                <div class="text-sm uppercase tracking-[0.18em] text-gray-500 mb-1">Step 2 of 2</div>
                <h2 class="text-2xl font-bold mb-3">Payment</h2>
                <p class="text-sm text-gray-600 mb-4">Your order is created only after this payment step succeeds.</p>

                @include('shop.partials.flow-summary', [
                    'heading' => 'Order Summary',
                    'rows' => $summaryRows,
                ])

                @error('cart')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                @enderror
                @error('coupon_code')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                @enderror
                @error('shipping_country')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                @enderror

                @if(!empty($inventoryChangeNotices))
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <div class="font-semibold mb-1">Stock availability changed</div>
                        <div>Review these updates before completing payment.</div>
                        <div class="mt-3 space-y-2">
                            @foreach($inventoryChangeNotices as $notice)
                                <div class="rounded-xl border border-amber-200 bg-white/80 px-3 py-2">{{ $notice['message'] ?? 'An item in your cart changed.' }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mb-6">
                    <div class="text-sm font-semibold text-gray-900 mb-3">Items</div>
                    <div class="space-y-3">
                        @foreach($lines as $line)
                            <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3">
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $line->display_title }}</div>
                                    <div class="text-sm text-gray-500">Qty {{ $line->quantity }}</div>
                                    @if((bool) ($line->is_preorder ?? false))
                                        <div class="mt-1 text-sm text-amber-800">Pre-order · Estimated shipping {{ $line->preorder_shipping_estimate ?: 'to be confirmed' }}</div>
                                    @elseif((int) $line->delayed_quantity > 0)
                                        <div class="mt-1 text-sm text-sky-800">
                                            @if((int) $line->available_now_quantity > 0)
                                                {{ (int) $line->available_now_quantity }} ships now, {{ (int) $line->delayed_quantity }} ships later{{ $line->delayed_shipping_estimate ? ' from '.$line->delayed_shipping_estimate : '' }}
                                            @else
                                                Backorder · Expected shipping {{ $line->delayed_shipping_estimate ?: 'to be confirmed' }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="text-sm font-semibold text-gray-900">${{ number_format((float) $line->line_price, 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($preorderItems->isNotEmpty())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        This order includes pre-order items and will ship once those items become available.
                    </div>
                @endif

                @if($hasBackorderItems)
                    <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                        This order also includes delayed backorder quantities. The shipment breakdown below reflects the extra later shipment unless you chose consolidation.
                    </div>
                @endif

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact</h3>
                    <div class="space-y-1 text-sm text-gray-700">
                        <div>{{ $customer['billing_name'] ?? '-' }}</div>
                        <div>{{ $customer['billing_email'] ?? '-' }}</div>
                        <div>{{ $customer['billing_phone'] ?? '-' }}</div>
                        @if(trim((string) ($customer['billing_company'] ?? '')) !== '')
                            <div>{{ $customer['billing_company'] }}</div>
                        @endif
                    </div>
                </div>

                @if($summary['contains_physical'])
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Delivery</h3>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">{{ $selectedShippingLabel }}</div>
                            @if(trim((string) ($summary['shipping_quote']['note'] ?? '')) !== '')
                                <div class="mt-1">{{ $summary['shipping_quote']['note'] }}</div>
                            @endif
                        </div>
                        @if(!empty($shipmentQuote['shipments']))
                            <div class="mt-4">
                                @include('shop.partials.shipping-breakdown', [
                                    'shipments' => $shipmentQuote['shipments'],
                                ])
                            </div>
                        @endif
                        @if($usesPickup)
                            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                                We will contact you when your order is available to collect.
                            </div>
                        @else
                            <div class="mt-4 space-y-1 text-sm text-gray-700">
                                <div>{{ $customer['shipping_name'] ?? '-' }}</div>
                                <div>{{ $customer['shipping_address'] ?? '-' }}</div>
                                @if(trim((string) ($customer['shipping_address2'] ?? '')) !== '')
                                    <div>{{ $customer['shipping_address2'] }}</div>
                                @endif
                                <div>{{ collect([$customer['shipping_city'] ?? null, $customer['shipping_state'] ?? null, $customer['shipping_postcode'] ?? null])->filter()->implode(', ') }}</div>
                                <div>{{ $customer['shipping_country'] ?? '-' }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($summary['contains_digital'])
                    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        Digital downloads unlock automatically after payment.
                    </div>
                @endif

                <form id="shop-checkout-payment-form" method="POST" action="{{ route('shop.checkout.payment.process') }}" class="border-t border-gray-200 pt-6 mt-6" x-on:submit.prevent="submitForm($event)">
                    @csrf

                    @if(!$hasAmountDue)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            No payment is required for this checkout. Completing the order will unlock your items immediately.
                        </div>
                    @elseif(!$squareEnabled)
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            Card payments are currently unavailable.
                        </div>
                    @else
                        <div x-init="initSquareCard()">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm">Card Details</label>
                                <a href="https://squareup.com/au/en" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                    Secure payment by Square
                                </a>
                            </div>
                            <div class="relative rounded-lg border border-gray-200 bg-white p-4">
                                <div x-ref="squareCardContainer" class="min-h-[88px] transition" x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                                <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                                    <img src="{{ asset('loading.gif') }}" alt="Loading card form" width="56" height="56" />
                                </div>
                            </div>
                            <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">
                            <div x-show="errorMessage" x-cloak class="mt-2 text-xs text-red-600" x-text="errorMessage"></div>
                            @error('source_id')
                                <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 mt-6 sm:flex-row sm:justify-between">
                        <x-ui.button color="outline" href="{{ route('shop.checkout') }}">Back to Details</x-ui.button>
                        @if($hasAmountDue && !$squareEnabled)
                            <button type="button" disabled class="inline-flex cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Payment unavailable</button>
                        @else
                            <x-ui.button type="submit" x-bind:disabled="isSubmitting || isCardLoading">
                                <span x-show="!isSubmitting" x-cloak>{{ $hasAmountDue ? 'Pay $'.number_format((float) ($summary['total'] ?? 0), 2) : 'Complete Order' }}</span>
                                <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-2">
                                    <span class="altcha-inline-spinner" aria-hidden="true"></span>
                                    <span>Processing...</span>
                                </span>
                            </x-ui.button>
                        @endif
                    </div>
                </form>
            </div>

            @if($heroLine)
                <div class="hidden md:block w-64 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-cover bg-center" style="background-image:url('{{ $heroLine->product->primaryImageUrl() }}')"></div>
            @endif
        </div>
    </x-container>
</x-layout>

@if($squareEnabled)
<script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function shopCheckoutPaymentPage(config) {
        return {
            requiresPayment: Boolean(config.requiresPayment),
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            squareCard: null,
            squareCardInitPromise: null,
            sourceId: '',
            errorMessage: '',
            isSubmitting: false,
            isCardLoading: false,

            canUseCreditCard() {
                return this.requiresPayment && this.squareEnabled && this.squareApplicationId !== '' && this.squareLocationId !== '';
            },

            async initSquareCard() {
                if (!this.requiresPayment || !this.canUseCreditCard()) {
                    return false;
                }

                if (this.squareCardInitPromise) {
                    return this.squareCardInitPromise;
                }

                this.squareCardInitPromise = (async () => {
                    this.isCardLoading = true;
                    const ready = await this.waitForSquareSdk();
                    if (!ready) {
                        this.errorMessage = 'Square SDK did not load.';
                        return false;
                    }

                    if (this.squareCard) {
                        return true;
                    }

                    const payments = window.Square.payments(this.squareApplicationId, this.squareLocationId);
                    this.squareCard = await payments.card();
                    await this.squareCard.attach(this.$refs.squareCardContainer);
                    return true;
                })().catch((error) => {
                    this.errorMessage = error?.message || 'Unable to load card payment form.';
                    return false;
                }).finally(() => {
                    this.isCardLoading = false;
                    this.squareCardInitPromise = null;
                });

                return this.squareCardInitPromise;
            },

            async submitForm(event) {
                if (this.isSubmitting) {
                    return;
                }

                const form = event.target.tagName === 'FORM' ? event.target : event.target.closest('form');
                if (!form) {
                    return;
                }

                this.errorMessage = '';
                this.isSubmitting = true;
                if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                    window.SM.setFormProcessing(form, true, { submitLabel: 'Processing...' });
                }

                if (this.requiresPayment) {
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
                            throw new Error(result.errors?.[0]?.message || 'Unable to tokenize card.');
                        }
                        this.sourceId = result.token;
                        if (this.$refs?.sourceIdInput) {
                            this.$refs.sourceIdInput.value = this.sourceId;
                        }
                    } catch (error) {
                        this.errorMessage = error?.message || 'Unable to process card details.';
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }
                }

                form.submit();
            },

            async waitForSquareSdk() {
                if (window.Square && typeof window.Square.payments === 'function') {
                    return true;
                }

                const timeoutAt = Date.now() + 10000;
                while (Date.now() < timeoutAt) {
                    await new Promise((resolve) => setTimeout(resolve, 100));
                    if (window.Square && typeof window.Square.payments === 'function') {
                        return true;
                    }
                }

                return false;
            },
        };
    }
</script>
