<x-layout title="Order {{ $order->order_number }}">
    <x-mast :backRoute="$isAccountView ? 'account.order.index' : 'shop.index'" :backTitle="$isAccountView ? 'My Orders' : 'Store'">
        Order {{ $order->order_number }}
    </x-mast>

    <x-container class="max-w-5xl py-8 mx-auto">
        @include('shop.partials.checkout-steps', ['current' => 'payment'])

        @if(!$isAccountView)
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Keep this page URL if you checked out as a guest. It contains access to your order summary and downloads after payment.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr,0.9fr]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                        <div>
                            <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Order</div>
                            <h2 class="text-3xl font-bold text-gray-900">{{ $order->order_number }}</h2>
                        </div>
                        <span class="rounded-full {{ $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }} px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em]">
                            {{ $isPaid ? 'Paid' : 'Awaiting payment' }}
                        </span>
                    </div>

                    <div class="grid gap-3 text-sm text-gray-700 md:grid-cols-2">
                        <div class="flex items-center justify-between gap-4 md:block">
                            <span class="font-semibold">Placed</span>
                            <span>{{ $order->created_at?->format('M j, Y g:i a') ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 md:block">
                            <span class="font-semibold">Status</span>
                            <span>{{ $order->statusLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 md:block">
                            <span class="font-semibold">Invoice</span>
                            <span>{{ $order->invoice?->invoice_number ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 md:block">
                            <span class="font-semibold">Outstanding</span>
                            <span>${{ number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Items</h2>
                    <div class="space-y-4">
                        @foreach($order->items as $item)
                            <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                                <div>
                                    <div class="font-semibold text-gray-900">{{ $item->displayTitle() }}</div>
                                    <div class="text-sm text-gray-500">Qty {{ $item->quantity }} · {{ \App\Models\Product::productTypeLabel((string) $item->product_type) }}</div>
                                    @if($item->variant_sku || $item->product_sku)
                                        <div class="text-sm text-gray-500">SKU {{ $item->variant_sku ?: $item->product_sku }}</div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">${{ number_format((float) $item->line_total_amount, 2) }}</div>
                                    <div class="text-xs text-gray-500">GST incl. ${{ number_format((float) $item->line_gst_amount, 2) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if($downloadableItems->isNotEmpty() && $isPaid)
                    <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Downloads</h2>
                        <div class="space-y-4">
                            @foreach($downloadableItems as $item)
                                <div class="rounded-2xl border border-gray-200 p-4">
                                    <div class="font-semibold text-gray-900 mb-3">{{ $item->displayTitle() }}</div>
                                    <div class="space-y-3">
                                        @foreach($item->downloads as $download)
                                            <div class="flex items-center justify-between gap-4 rounded-2xl bg-gray-50 px-4 py-3">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $download->title ?: ($download->media?->title ?: $download->media_name) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $download->media?->file_type ?? 'Download file' }}</div>
                                                </div>
                                                <x-ui.button
                                                    type="link"
                                                    href="{{ $isAccountView ? route('account.order.download', ['storeOrder' => $order, 'storeOrderItemDownload' => $download]) : route('shop.order.download', ['storeOrder' => $order, 'accessToken' => $accessToken, 'storeOrderItemDownload' => $download]) }}"
                                                    class="!px-5"
                                                >
                                                    Download
                                                </x-ui.button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @elseif($downloadableItems->isNotEmpty())
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm text-sm text-gray-600">
                        Digital downloads unlock automatically after payment is completed.
                    </section>
                @endif

                @if($order->contains_physical)
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping</h2>
                        <div class="space-y-1 text-sm text-gray-700">
                            @foreach($order->shippingAddressLines() as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                            @if(empty($order->shippingAddressLines()))
                                <div>No shipping address recorded.</div>
                            @endif
                        </div>
                        <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            <div><strong>{{ $order->shipping_method ?: 'Shipping' }}</strong></div>
                            @if($order->shipping_package_summary)
                                <div class="mt-1">{{ $order->shipping_package_summary }}</div>
                            @endif
                            @if(($order->shipping_chargeable_weight_grams ?? 0) > 0)
                                <div class="mt-1 text-xs text-gray-500">Known packed weight {{ number_format((float) (($order->shipping_chargeable_weight_grams ?? 0) / 1000), 2) }} kg</div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Totals</h2>
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="flex items-center justify-between gap-4">
                            <span>Items</span>
                            <span>${{ number_format((float) $order->subtotal_amount, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span>Shipping</span>
                            <span>${{ number_format((float) $order->shipping_amount, 2) }}</span>
                        </div>
                        @if((float) $order->discount_amount > 0)
                            <div class="flex items-center justify-between gap-4 text-emerald-700">
                                <span>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</span>
                                <span>- ${{ number_format((float) $order->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-4 text-gray-500">
                            <span>GST included</span>
                            <span>${{ number_format((float) $order->gst_amount, 2) }}</span>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-200 pt-4 flex items-center justify-between gap-4">
                        <span class="text-lg font-bold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-gray-900">${{ number_format((float) $order->total_amount, 2) }}</span>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Step 2 of 2</div>
                        <h2 class="text-xl font-bold text-gray-900">Payment</h2>
                    </div>

                    @if($isPaid)
                        <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
                            Payment received. Your order is ready in this portal.
                        </div>
                    @elseif((string) $order->status === \App\Models\StoreOrder::STATUS_CANCELLED)
                        <div class="rounded-2xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                            This order has been cancelled.
                        </div>
                    @elseif(!$squareEnabled)
                        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            Card payments are currently unavailable.
                        </div>
                    @else
                        <form method="POST" action="{{ $payActionUrl }}" x-data="shopOrderPayment({
                                squareEnabled: @js($squareEnabled),
                                squareApplicationId: @js($squareApplicationId),
                                squareLocationId: @js($squareLocationId),
                                squareEnvironment: @js($squareEnvironment),
                              })" x-on:submit.prevent="submitForm($event)">
                            @csrf

                            <div x-init="initSquareCard()">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm">Card Details</label>
                                    <a href="https://squareup.com/au/en" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs text-blue-700">
                                        Secure payment by Square
                                    </a>
                                </div>
                                <div class="relative">
                                    <div x-ref="squareCardContainer" class="min-h-[88px] bg-white transition" x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                                    <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                                        <img src="/loading.gif" alt="Loading card form" width="56" height="56" />
                                    </div>
                                </div>
                                <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">
                                <div x-show="errorMessage" class="mt-2 text-xs text-red-600" x-text="errorMessage"></div>
                                @error('source_id')
                                    <div class="mt-2 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-5">
                                <x-ui.button type="submit" x-bind:disabled="isSubmitting || isCardLoading" class="w-full">
                                    <span x-show="!isSubmitting">Pay ${{ number_format((float) ($order->invoice?->outstandingAmount() ?? 0), 2) }}</span>
                                    <span x-show="isSubmitting" x-cloak>Processing...</span>
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                </section>
            </aside>
        </div>
    </x-container>
</x-layout>

@if($squareEnabled)
<script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function shopOrderPayment(config) {
        return {
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            squareCard: null,
            sourceId: '',
            errorMessage: '',
            isSubmitting: false,
            isCardLoading: false,

            canUseCreditCard() {
                return this.squareEnabled && this.squareApplicationId !== '' && this.squareLocationId !== '';
            },

            async initSquareCard() {
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
                    this.errorMessage = e?.message || 'Unable to load card payment form.';
                    this.isCardLoading = false;
                    return false;
                }
            },

            async submitForm(event) {
                if (this.isSubmitting) {
                    return;
                }
                this.errorMessage = '';
                this.isSubmitting = true;
                const ready = await this.initSquareCard();
                if (!ready) {
                    this.isSubmitting = false;
                    return;
                }

                try {
                    const result = await this.squareCard.tokenize();
                    if (result.status !== 'OK') {
                        throw new Error(result.errors?.[0]?.message || 'Unable to tokenize card.');
                    }
                    this.sourceId = result.token;
                    event.target.submit();
                } catch (e) {
                    this.errorMessage = e?.message || 'Unable to process card details.';
                    this.isSubmitting = false;
                }
            },

            async waitForSquareSdk() {
                if (window.Square?.payments) {
                    return true;
                }

                for (let attempt = 0; attempt < 50; attempt++) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                    if (window.Square?.payments) {
                        return true;
                    }
                }

                return false;
            },
        }
    }
</script>
