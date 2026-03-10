<x-layout title="Cart" :canonical="route('shop.cart.show')">
    <x-mast backRoute="shop.index" backTitle="Store">Cart</x-mast>

    <x-container
        class="py-8"
        x-data="{
            cartState: @js($cartPayload),
            shippingCountry: @js($shippingCountry),
            busyLineKey: null,
            formatMoney(value) {
                const amount = Number(value || 0);
                return `$${amount.toFixed(2)}`;
            },
            setCartState(cart) {
                if (!cart || typeof cart !== 'object') {
                    return;
                }

                this.cartState = cart;
                this.shippingCountry = cart.shipping_country || this.shippingCountry;
            },
            async changeCartQuantity(lineKey, nextQuantity, maxQuantity = 99) {
                if (this.busyLineKey) {
                    return;
                }

                this.busyLineKey = lineKey;

                try {
                    await window.SM.shopCart.updateQuantity(lineKey, nextQuantity, {
                        max: maxQuantity,
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                    });
                } finally {
                    this.busyLineKey = null;
                }
            },
            async removeCartLine(lineKey) {
                if (this.busyLineKey) {
                    return;
                }

                this.busyLineKey = lineKey;

                try {
                    await window.SM.shopCart.removeLine(lineKey, {
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                    });
                } finally {
                    this.busyLineKey = null;
                }
            },
            init() {
                if (!window.SM?.shopCart) {
                    return;
                }

                window.SM.shopCart.configure({
                    showUrl: @js(route('shop.cart.show')),
                    updateUrl: @js(route('shop.cart.update')),
                    removeUrl: @js(route('shop.cart.remove')),
                    initialState: @js($cartPayload),
                });

                window.SM.shopCart.subscribe((cart) => this.setCartState(cart));
            },
        }"
    >
        <div x-show="cartState.is_empty" x-cloak class="rounded-3xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Browse the store and add a few items to get started.</p>
            <x-ui.button type="link" href="{{ route('shop.index') }}">Browse Store</x-ui.button>
        </div>

        <div x-show="!cartState.is_empty" x-cloak class="grid gap-6 xl:grid-cols-[1.35fr,0.65fr]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="space-y-4">
                    <template x-for="line in cartState.lines" :key="line.key">
                        <div class="grid gap-4 border-b border-gray-100 pb-4 last:border-b-0 last:pb-0 md:grid-cols-[7rem,1fr,11rem,8rem] md:items-center">
                            <img :src="line.product.image_url" :alt="line.display_title" class="h-28 w-28 rounded-2xl object-cover bg-gray-100" />
                            <div>
                                <a :href="line.product.url" class="text-lg font-bold text-gray-900 hover:text-primary-color" x-text="line.product.title"></a>
                                <div x-show="line.variant_name" class="mt-1 text-sm font-medium text-gray-700" x-text="line.variant_name"></div>
                                <div class="mt-1 text-sm text-gray-600" x-text="line.product.product_type_label"></div>
                                <div class="text-sm text-gray-500" x-text="`${formatMoney(line.unit_price)} each`"></div>
                            </div>
                            <div>
                                <div class="mb-1 text-sm font-medium text-gray-700">Qty</div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-primary-color hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyLineKey === line.key || Number(line.quantity || 1) <= 1"
                                        @click="changeCartQuantity(line.key, Math.max(1, Number(line.quantity || 1) - 1), line.max_quantity)"
                                    >-</button>
                                    <input
                                        type="number"
                                        min="1"
                                        :max="line.max_quantity || 99"
                                        :value="line.quantity"
                                        class="block h-10 w-20 rounded-lg border border-gray-300 px-2 text-center text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                                        :disabled="busyLineKey === line.key"
                                        @change="changeCartQuantity(line.key, $event.target.value, line.max_quantity)"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-primary-color hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyLineKey === line.key || Number(line.quantity || 0) >= Number(line.max_quantity || 99)"
                                        @click="changeCartQuantity(line.key, Number(line.quantity || 0) + 1, line.max_quantity)"
                                    >+</button>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Items</div>
                                <div class="text-lg font-bold text-gray-900" x-text="formatMoney(line.line_price)"></div>
                                <button
                                    type="button"
                                    class="mt-2 text-sm text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="busyLineKey === line.key"
                                    @click="removeCartLine(line.key)"
                                >Remove</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button type="link" href="{{ route('shop.index') }}" color="outline">Continue Browsing</x-ui.button>
                </div>
            </section>

            <aside class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm h-fit space-y-5">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Summary</h2>
                    <p class="text-sm text-gray-600">Review your items, shipping fee, and total before checkout.</p>
                </div>

                <div x-show="cartState.summary.shipping_quote.boxed_shipping_required" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <div class="font-semibold text-amber-950" x-text="cartState.summary.shipping_quote.method"></div>
                    <div class="mt-1" x-text="cartState.summary.shipping_quote.reason"></div>
                    <div x-show="!cartState.summary.can_checkout" class="mt-2">Checkout is blocked until boxed shipping is priced manually.</div>
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-2">Coupon</div>
                    @if($couponCode)
                        <div class="mb-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Applied code: <strong>{{ $couponCode }}</strong>
                        </div>
                        <form method="POST" action="{{ route('shop.cart.coupon.remove') }}">
                            @csrf
                            <input type="hidden" name="shipping_country" x-bind:value="shippingCountry">
                            <x-ui.button type="submit" color="outline" class="w-full">Remove Coupon</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('shop.cart.coupon.apply') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="shipping_country" x-bind:value="shippingCountry">
                            <x-ui.input name="coupon_code" label="Coupon Code" :value="old('coupon_code', '')" />
                            <x-ui.button type="submit" color="outline" class="w-full">Apply Coupon</x-ui.button>
                        </form>
                    @endif
                </div>

                <div class="space-y-3 text-sm text-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <span x-text="`Items (${Number(cartState.summary.item_count || 0)})`"></span>
                        <span x-text="formatMoney(cartState.summary.subtotal)"></span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span>Shipping</span>
                        <span x-text="cartState.summary.can_checkout ? formatMoney(cartState.summary.shipping) : 'Manual quote'"></span>
                    </div>
                    <div x-show="Number(cartState.summary.discount || 0) > 0" class="flex items-center justify-between gap-4 text-emerald-700">
                        <span x-text="cartState.summary.coupon_code ? `Discount (${cartState.summary.coupon_code})` : 'Discount'"></span>
                        <span x-text="`- ${formatMoney(cartState.summary.discount)}`"></span>
                    </div>
                    <div class="flex items-center justify-between gap-4 text-gray-500">
                        <span>GST included</span>
                        <span x-text="formatMoney(cartState.summary.gst)"></span>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-4 flex items-center justify-between gap-4">
                    <span class="text-lg font-bold text-gray-900">Total</span>
                    <span class="text-right text-2xl font-bold text-gray-900" x-text="cartState.summary.total !== null ? formatMoney(cartState.summary.total) : 'Checkout unavailable'"></span>
                </div>
                <div class="space-y-3">
                    <template x-if="cartState.summary.can_checkout">
                        <x-ui.button type="link" href="{{ route('shop.checkout') }}" class="w-full">Checkout</x-ui.button>
                    </template>
                    <template x-if="!cartState.summary.can_checkout">
                        <button type="button" disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Checkout unavailable</button>
                    </template>
                    <p x-show="!cartState.summary.can_checkout && cartState.summary.shipping_quote.reason" class="text-sm text-gray-600" x-text="cartState.summary.shipping_quote.reason"></p>
                </div>
            </aside>
        </div>
    </x-container>
</x-layout>
