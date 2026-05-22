@php
    $consolidationSavingsAmount = (float) ($summary['shipping_quote']['consolidation_savings_amount'] ?? 0);
@endphp

<x-layout title="Cart" :canonical="route('shop.cart.show')">
    <x-mast backRoute="shop.index" backTitle="Store">Cart</x-mast>

    @include('shop.partials.processing-pause-notice', [
        'notice' => $summary['shipping_quote']['processing_pause_notice'] ?? null,
    ])

    <x-container
        class="py-8"
        x-data="shopCartPage(window.shopCartPageConfig || {})"
    >
        <div x-show="cartState.is_empty" x-cloak class="rounded-3xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Browse the store and add a few items to get started.</p>
            <x-ui.button type="link" href="{{ route('shop.index') }}">Browse Store</x-ui.button>
        </div>

        <div x-show="!cartState.is_empty" x-cloak class="grid gap-6 xl:grid-cols-[1.35fr,0.65fr]">
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                <div x-show="hasInventoryChangeNotices()" x-cloak class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <div class="font-semibold text-amber-900">Stock availability changed</div>
                    <p class="mt-1">Review these updates before continuing. Your cart has already been adjusted to the latest available stock.</p>
                    <div class="mt-3 space-y-2">
                        <template x-for="notice in cartState.inventory_change_notices" :key="`${notice.type}-${notice.key}`">
                            <div class="rounded-xl border border-amber-200 bg-white/80 px-3 py-2" x-text="notice.message"></div>
                        </template>
                    </div>
                </div>

                <div x-show="cartUpdateNotice !== ''" x-cloak class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900" role="status" x-text="cartUpdateNotice"></div>
                <div x-show="cartUpdateError !== ''" x-cloak class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert" x-text="cartUpdateError"></div>

                <div class="space-y-4">
                    <template x-for="line in cartState.lines" :key="line.key">
                        <div class="grid gap-4 border-b border-gray-100 pb-4 last:border-b-0 last:pb-0 md:grid-cols-[7rem,1fr,11rem,8rem] md:items-center">
                            <img :src="line.product.image_url" alt="Product image" :alt="line.display_title" class="h-28 w-28 rounded-2xl object-cover bg-gray-100" />
                            <div>
                                <a :href="line.product.url" class="text-lg font-bold text-gray-900 hover:text-primary-color" x-text="line.product.title"></a>
                                <div x-show="line.variant_name" class="mt-1 text-sm font-medium text-gray-700" x-text="line.variant_name"></div>
                                <div class="mt-1 text-sm text-gray-600" x-text="line.product.product_type_label"></div>
                                <div x-show="lineFulfilmentLabel(line)" x-cloak class="mt-1 text-sm" :class="line.is_preorder ? 'text-amber-800' : (Number(line.delayed_quantity || 0) > 0 ? 'text-sky-800' : 'text-gray-500')" x-text="lineFulfilmentLabel(line)"></div>
                                <div class="text-sm text-gray-500" x-text="`${formatMoney(line.unit_price)} each`"></div>
                            </div>
                            <div>
                                <div class="mb-1 text-sm font-medium text-gray-700">Qty</div>
                                <div class="shop-catalog-stepper flex items-center gap-2 rounded border border-gray-300 bg-white">
                                    <button
                                        type="button"
                                        class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-r border-r-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyLineKey === line.key"
                                        @click="changeCartQuantity(line.key, Number(line.quantity || 1) - 1, line.max_quantity, null, line.quantity)"
                                    >-</button>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        autocomplete="off"
                                        :value="line.quantity"
                                        class="shop-catalog-stepper-input h-9 min-w-14 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                        :disabled="busyLineKey === line.key"
                                        @input="sanitizeCartQuantityInput($event.target)"
                                        @change="changeCartQuantity(line.key, $event.target.value, line.max_quantity, $event.target, line.quantity)"
                                    />
                                    <button
                                        type="button"
                                        class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-l border-l-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyLineKey === line.key || Number(line.quantity || 0) >= Number(line.max_quantity || 99)"
                                        @click="changeCartQuantity(line.key, Number(line.quantity || 0) + 1, line.max_quantity, null, line.quantity)"
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
                    <x-ui.button href="{{ route('shop.index') }}" color="outline">Continue Browsing</x-ui.button>
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

                <div x-show="Array.isArray(cartState.summary.shipping_methods) && cartState.summary.shipping_methods.length > 0" x-cloak class="space-y-4">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 mb-2">Delivery</div>
                        <p class="text-sm text-gray-600">Choose how this order should be delivered. Pickup is free. Split shipments add a second shipping charge unless you hold everything and consolidate it.</p>
                    </div>

                    <form method="POST" action="{{ route('shop.cart.preferences') }}" class="space-y-3" @submit.prevent="applyDeliveryUpdate()">
                        @csrf
                        <input type="hidden" name="shipping_country" x-bind:value="shippingCountry">

                        <template x-for="method in cartState.summary.shipping_methods" :key="method.code">
                            <label class="flex cursor-pointer items-start justify-between gap-4 rounded-2xl border px-4 py-4 transition" :class="selectedShippingMethodCode === method.code ? 'border-sky-500 bg-sky-50' : 'border-gray-200 bg-white'">
                                <span class="flex items-start gap-3">
                                    <input
                                        type="radio"
                                        name="shipping_method_code"
                                        class="mt-1 h-5 w-5 border-gray-300 text-sky-600 focus:ring-sky-500"
                                        :value="method.code"
                                        x-model="selectedShippingMethodCode"
                                        @change="scheduleDeliveryUpdate()"
                                    >
                                    <span class="block">
                                        <span class="block text-sm font-semibold text-gray-900" x-text="method.name"></span>
                                        <span x-show="method.description" class="mt-1 block text-sm text-gray-600" x-text="method.description"></span>
                                        <span x-show="method.delivery_estimate_label" class="mt-2 block text-xs uppercase tracking-[0.14em] text-gray-500" x-text="`Delivery ETA ${method.delivery_estimate_label}`"></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold text-gray-900" x-text="shippingMethodAmountLabel(method)"></span>
                            </label>
                        </template>

                        <label x-show="cartState.summary.shipping_quote.offers_consolidation && selectedShippingMethodCode !== 'pickup'" x-cloak class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="consolidate_shipments"
                                value="1"
                                class="mt-0.5 h-5 w-5 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                x-model="consolidateShipments"
                                @change="scheduleDeliveryUpdate()"
                            >
                            <span>
                                Consolidate into one shipment when everything is available.
                                <span x-show="hasConsolidationSavings()" x-cloak class="mt-1 block text-xs text-gray-500" x-text="consolidationSavingsLabel()">@if($consolidationSavingsAmount > 0.0001)Save ${{ number_format($consolidationSavingsAmount, 2) }} by sending everything together.@endif</span>
                            </span>
                        </label>

                        <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-950" x-show="splitShipmentLabel()" x-cloak>
                            <div class="font-semibold text-sky-900" x-text="splitShipmentLabel()"></div>
                            <div class="mt-1" x-show="!cartState.summary.shipping_quote.consolidate_shipments && selectedShippingMethodCode !== 'pickup'">
                                The later shipment currently adds
                                <span class="font-semibold text-sky-950" x-text="formatMoney(cartState.summary.shipping_quote.second_shipment_charge_amount || 0)"></span>
                                in extra shipping.
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-xs">
                                <span x-show="deliveryUpdateBusy || deliveryUpdateNotice === 'Updating delivery...'" x-cloak class="text-gray-500">Updating delivery...</span>
                                <span x-show="deliveryUpdateError" x-cloak class="font-medium text-red-600" x-text="deliveryUpdateError"></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" class="inline-flex items-center justify-center rounded-md border border-sky-200 bg-white px-4 py-2 text-sm font-semibold text-sky-700 shadow-sm transition hover:border-sky-300 hover:text-sky-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="deliveryUpdateBusy" @click="applyDeliveryUpdate()">Refresh quote</button>
                                <noscript>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Update delivery</button>
                                </noscript>
                            </div>
                        </div>
                    </form>
                </div>

                <div x-show="Array.isArray(cartState.summary.shipping_quote.shipments) && cartState.summary.shipping_quote.shipments.length > 0" x-cloak class="space-y-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Expected delivery</div>
                        <p x-show="cartState.summary.shipping_quote.split_shipments && !cartState.summary.shipping_quote.consolidate_shipments" class="mt-1 text-sm text-gray-600">
                            This cart will arrive in more than one dispatch. The expected timing for each stage is listed below.
                        </p>
                        <p x-show="cartState.summary.shipping_quote.consolidate_shipments" class="mt-1 text-sm text-gray-600">This cart is set to wait until everything is available so it can arrive together.</p>
                    </div>

                    <template x-for="(shipment, shipmentIndex) in cartState.summary.shipping_quote.shipments" :key="shipment.key">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-700">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500" x-text="cartState.summary.shipping_quote.shipments.length > 1 ? `Delivery ${shipmentIndex + 1}` : 'Delivery'"></div>
                                <div class="mt-1 text-base font-semibold text-gray-900" x-text="shipment.delivery_estimate_label || shipment.title_meta || shipment.dispatch_label || 'Timing to be confirmed'"></div>
                                <div x-show="shipment.delivery_estimate_label && shipment.title_meta" x-cloak class="mt-1 max-w-sm text-xs text-gray-500" x-text="shipment.title_meta"></div>
                                <div x-show="!shipment.delivery_estimate_label && shipment.title_primary" x-cloak class="mt-1 max-w-sm text-xs text-gray-500" x-text="shipment.title_primary"></div>
                            </div>

                            <template x-if="Array.isArray(shipment.items) && shipment.items.length > 0">
                                <ul class="mt-3 list-disc space-y-2 border-t border-gray-200 pl-5 pt-3">
                                    <template x-for="item in shipment.items" :key="`${shipment.key}-${item.display_title}-${item.quantity}`">
                                        <li class="text-xs text-gray-600">
                                            <div>
                                                <div class="font-medium text-gray-800" x-text="`${item.display_title} x ${item.quantity}`"></div>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>
                    </template>
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-2">Voucher</div>
                    @error('coupon_code')
                        <div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</div>
                    @enderror
                    <div x-show="couponError !== ''" style="display:none;" class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="couponError"></div>

                    <form method="POST" action="{{ route('shop.cart.coupon.remove') }}" class="mb-3" x-show="currentCouponCode() !== ''" style="{{ $couponCode ? '' : 'display:none;' }}" @submit.prevent="removeCouponCode($event)">
                        @csrf
                        <input type="hidden" name="shipping_country" x-bind:value="shippingCountry">
                        <input type="hidden" name="return_to" value="{{ route('shop.cart.show') }}">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            <div>
                                <span x-text="currentCouponCode() !== '' ? 'Applied ' + 'voucher:' : ''">{{ $couponCode ? 'Applied voucher:' : '' }}</span>
                                <strong x-text="currentCouponCode()">{{ $couponCode }}</strong>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-900 disabled:cursor-not-allowed disabled:opacity-50"
                                aria-label="Remove voucher"
                                :disabled="couponBusy"
                            >
                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('shop.cart.coupon.apply') }}" class="space-y-3" x-show="currentCouponCode() === ''" style="{{ $couponCode ? 'display:none;' : '' }}" @submit.prevent="applyCouponCode($event)">
                        @csrf
                        <input type="hidden" name="shipping_country" x-bind:value="shippingCountry">
                        <input type="hidden" name="return_to" value="{{ route('shop.cart.show') }}">
                        <x-ui.input name="coupon_code" label="Add voucher" :value="old('coupon_code', '')" x-model="couponDraft" x-bind:disabled="couponBusy" />
                        <x-ui.button type="submit" color="outline" class="w-full" x-bind:disabled="couponBusy || couponDraft.trim() === ''">Apply Voucher</x-ui.button>
                    </form>
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
                    <template x-if="cartState.summary.can_checkout && !checkoutLocked()">
                        <x-ui.button href="{{ route('shop.checkout') }}" class="w-full">Checkout</x-ui.button>
                    </template>
                    <template x-if="cartState.summary.can_checkout && checkoutLocked()">
                        <button type="button" disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-8 py-1.5 text-sm font-semibold leading-6 text-gray-600 shadow-sm">Updating cart...</button>
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

<script>
    window.shopCartPageConfig = {
        cartState: @js($cartPayload),
        shippingCountry: @js($shippingCountry),
        selectedShippingMethodCode: @js($summary['shipping_method_code'] ?? ''),
        consolidateShipments: @js((bool) ($summary['consolidate_shipments'] ?? false)),
        initialCouponDraft: @js((string) old('coupon_code', '')),
        routes: {
            show: @js(route('shop.cart.show')),
            update: @js(route('shop.cart.update')),
            remove: @js(route('shop.cart.remove')),
            preferences: @js(route('shop.cart.preferences')),
            couponApply: @js(route('shop.cart.coupon.apply')),
            couponRemove: @js(route('shop.cart.coupon.remove')),
        },
    };

    function shopCartPage(config) {
        return {
            cartState: config.cartState || {},
            shippingCountry: config.shippingCountry || 'Australia',
            busyLineKey: null,
            pendingCartUpdate: null,
            cartUpdateError: '',
            cartUpdateErrorTimer: null,
            cartUpdateNotice: '',
            cartUpdateDebounceTimer: null,
            couponDraft: config.initialCouponDraft || '',
            couponBusy: false,
            couponError: '',
            selectedShippingMethodCode: config.selectedShippingMethodCode || '',
            consolidateShipments: Boolean(config.consolidateShipments),
            deliveryUpdateTimer: null,
            deliveryStatusTimer: null,
            deliveryUpdateBusy: false,
            deliveryUpdateError: '',
            deliveryUpdateNotice: '',

            formatMoney(value) {
                const amount = Number(value || 0);
                return `$${amount.toFixed(2)}`;
            },

            clearCartUpdateErrorTimer() {
                if (this.cartUpdateErrorTimer !== null) {
                    window.clearTimeout(this.cartUpdateErrorTimer);
                    this.cartUpdateErrorTimer = null;
                }
            },

            clearCartUpdateDebounceTimer() {
                if (this.cartUpdateDebounceTimer !== null) {
                    window.clearTimeout(this.cartUpdateDebounceTimer);
                    this.cartUpdateDebounceTimer = null;
                }
            },

            setCartUpdateError(message, durationMs = 12000) {
                this.cartUpdateError = String(message || '').trim();
                this.clearCartUpdateErrorTimer();

                if (this.cartUpdateError === '') {
                    return;
                }

                this.cartUpdateErrorTimer = window.setTimeout(() => {
                    this.cartUpdateError = '';
                    this.cartUpdateErrorTimer = null;
                }, durationMs);
            },

            setCartUpdateNotice(message) {
                this.cartUpdateNotice = String(message || '').trim();
            },

            checkoutLocked() {
                return Boolean(
                    this.busyLineKey
                    || this.pendingCartUpdate
                    || this.cartUpdateNotice !== ''
                    || this.couponBusy
                    || this.deliveryUpdateBusy
                    || this.deliveryUpdateNotice !== ''
                );
            },

            sanitizeCartQuantityInput(input) {
                if (!window.SM?.shopCart) {
                    return;
                }

                window.SM.shopCart.stripNonNumericQuantityInput(input);
            },

            async recoverCartAfterFailedUpdate(message) {
                this.setCartUpdateNotice('Checking cart...');

                await new Promise((resolve) => {
                    window.setTimeout(resolve, 1200);
                });

                try {
                    await window.SM.shopCart.refresh(this.shippingCountry, {
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                    return true;
                } catch (_error) {
                    this.setCartUpdateNotice('');
                    this.setCartUpdateError(message);
                    return false;
                }
            },

            async submitPendingCartQuantityUpdate() {
                if (!this.pendingCartUpdate || this.busyLineKey || !window.SM?.shopCart) {
                    return;
                }

                const pending = this.pendingCartUpdate;
                this.pendingCartUpdate = null;
                this.clearCartUpdateDebounceTimer();
                this.busyLineKey = pending.lineKey;

                try {
                    await window.SM.shopCart.updateQuantity(pending.lineKey, pending.quantity, {
                        max: pending.maxQuantity,
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                } catch (error) {
                    await this.recoverCartAfterFailedUpdate(error?.message || 'Unable to update the cart right now.');
                } finally {
                    this.busyLineKey = null;
                    if (!this.pendingCartUpdate) {
                        this.clearCartUpdateDebounceTimer();
                    }
                }
            },

            shippingMethodAmountLabel(method) {
                if (!method || typeof method !== 'object') {
                    return this.formatMoney(0);
                }

                return method.is_pickup ? 'Free' : this.formatMoney(method.estimated_amount || 0);
            },

            consolidationSavingsLabel() {
                const savings = Number(this.cartState?.summary?.shipping_quote?.consolidation_savings_amount || 0);
                const isConsolidated = Boolean(this.cartState?.summary?.shipping_quote?.consolidate_shipments);

                return `${isConsolidated ? 'Saving' : 'Save'} ${this.formatMoney(savings)} by sending everything together.`;
            },

            hasConsolidationSavings() {
                return Number(this.cartState?.summary?.shipping_quote?.consolidation_savings_amount || 0) > 0.0001;
            },

            lineFulfilmentLabel(line) {
                if (!line || typeof line !== 'object') {
                    return '';
                }

                if (line.is_preorder) {
                    return line.preorder_shipping_estimate
                        ? `Pre-order · Estimated shipping ${line.preorder_shipping_estimate}`
                        : 'Pre-order item';
                }

                if (Number(line.delayed_quantity || 0) > 0) {
                    const delayedEta = line.delayed_shipping_estimate ? ` from ${line.delayed_shipping_estimate}` : '';
                    if (Number(line.available_now_quantity || 0) > 0) {
                        return `${line.available_now_quantity} ships now, ${line.delayed_quantity} ships later${delayedEta}`;
                    }

                    return `Backorder · Expected shipping${delayedEta}`;
                }

                return '';
            },

            splitShipmentLabel() {
                const shipmentCount = Number(this.cartState?.summary?.shipping_quote?.shipment_count || 0);
                if (!Boolean(this.cartState?.summary?.shipping_quote?.split_shipments)) {
                    return '';
                }

                if (shipmentCount > 1) {
                    return `This cart is currently split into ${shipmentCount} shipments.`;
                }

                return 'This cart is currently split into multiple shipments.';
            },

            shipmentIconClass(shipment) {
                const type = String(shipment?.type || '').trim();

                if (type === 'immediate') {
                    return 'fa-solid fa-paper-plane text-sky-600';
                }

                if (type === 'delayed' || type === 'consolidated') {
                    return 'fa-solid fa-clock text-amber-600';
                }

                return '';
            },

            currentCouponCode() {
                return String(this.cartState?.summary?.coupon_code || '').trim();
            },

            hasInventoryChangeNotices() {
                return Array.isArray(this.cartState?.inventory_change_notices) && this.cartState.inventory_change_notices.length > 0;
            },

            clearDeliveryUpdateTimers() {
                if (this.deliveryUpdateTimer !== null) {
                    window.clearTimeout(this.deliveryUpdateTimer);
                    this.deliveryUpdateTimer = null;
                }
                if (this.deliveryStatusTimer !== null) {
                    window.clearTimeout(this.deliveryStatusTimer);
                    this.deliveryStatusTimer = null;
                }
            },

            setDeliveryNotice(message) {
                this.deliveryUpdateNotice = message;
                if (!message) {
                    return;
                }

                if (this.deliveryStatusTimer !== null) {
                    window.clearTimeout(this.deliveryStatusTimer);
                }

                this.deliveryStatusTimer = window.setTimeout(() => {
                    this.deliveryUpdateNotice = '';
                    this.deliveryStatusTimer = null;
                }, 1800);
            },

            scheduleDeliveryUpdate() {
                if (!window.SM?.shopCart) {
                    return;
                }

                if (String(this.selectedShippingMethodCode || '') === 'pickup') {
                    this.consolidateShipments = false;
                }

                this.deliveryUpdateError = '';
                this.deliveryUpdateNotice = 'Updating delivery...';

                if (this.deliveryUpdateTimer !== null) {
                    window.clearTimeout(this.deliveryUpdateTimer);
                }

                this.deliveryUpdateTimer = window.setTimeout(() => {
                    this.deliveryUpdateTimer = null;
                    this.applyDeliveryUpdate();
                }, 180);
            },

            async applyDeliveryUpdate() {
                if (this.deliveryUpdateBusy || !window.SM?.shopCart) {
                    return;
                }

                if (String(this.selectedShippingMethodCode || '') === 'pickup') {
                    this.consolidateShipments = false;
                }

                this.deliveryUpdateBusy = true;
                this.deliveryUpdateError = '';

                try {
                    await window.SM.shopCart.updatePreferences({
                        shippingMethodCode: this.selectedShippingMethodCode,
                        consolidateShipments: this.consolidateShipments,
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                        showError: false,
                    });
                    this.deliveryUpdateNotice = '';
                } catch (error) {
                    this.deliveryUpdateNotice = '';
                    this.deliveryUpdateError = error?.message || 'Unable to update delivery options right now.';
                } finally {
                    this.deliveryUpdateBusy = false;
                }
            },

            syncDeliverySelections() {
                const methods = Array.isArray(this.cartState?.summary?.shipping_methods)
                    ? this.cartState.summary.shipping_methods
                    : [];
                const availableCodes = methods.map((method) => String(method.code || ''));
                const fallbackCode = String(this.cartState?.summary?.shipping_method_code || methods[0]?.code || '');

                if (!availableCodes.includes(String(this.selectedShippingMethodCode || ''))) {
                    this.selectedShippingMethodCode = fallbackCode;
                }

                if (!Boolean(this.cartState?.summary?.shipping_quote?.offers_consolidation)) {
                    this.consolidateShipments = false;
                } else {
                    this.consolidateShipments = Boolean(this.cartState?.summary?.consolidate_shipments);
                }
            },

            setCartState(cart) {
                if (!cart || typeof cart !== 'object') {
                    return;
                }

                this.cartState = cart;
                this.pendingCartUpdate = null;
                this.cartUpdateError = '';
                this.cartUpdateNotice = '';
                this.clearCartUpdateErrorTimer();
                this.clearCartUpdateDebounceTimer();
                this.shippingCountry = cart.shipping_country || this.shippingCountry;
                this.deliveryUpdateError = '';
                if (this.currentCouponCode() !== '') {
                    this.couponDraft = '';
                    this.couponError = '';
                }
                this.syncDeliverySelections();
            },

            async applyCouponCode(event) {
                const form = event?.target instanceof HTMLFormElement
                    ? event.target
                    : event?.target?.closest?.('form');
                if (this.couponBusy || !(form instanceof HTMLFormElement) || !window.SM?.shopCart) {
                    return;
                }

                this.couponBusy = true;
                this.couponError = '';

                try {
                    await window.SM.shopCart.applyCoupon({
                        couponCode: this.couponDraft,
                        shippingCountry: this.shippingCountry,
                        returnTo: form.querySelector('input[name=\"return_to\"]')?.value || window.location.href,
                        showError: false,
                    });
                    if (this.currentCouponCode() !== '') {
                        this.couponDraft = '';
                    }
                } catch (error) {
                    this.couponError = error?.message || 'Unable to update the voucher right now.';
                } finally {
                    this.couponBusy = false;
                }
            },

            async removeCouponCode(event) {
                const form = event?.target instanceof HTMLFormElement
                    ? event.target
                    : event?.target?.closest?.('form');
                if (this.couponBusy || !(form instanceof HTMLFormElement) || !window.SM?.shopCart) {
                    return;
                }

                this.couponBusy = true;
                this.couponError = '';

                try {
                    await window.SM.shopCart.removeCoupon({
                        shippingCountry: this.shippingCountry,
                        returnTo: form.querySelector('input[name=\"return_to\"]')?.value || window.location.href,
                        showError: false,
                    });
                    this.couponDraft = '';
                } catch (error) {
                    this.couponError = error?.message || 'Unable to remove the voucher right now.';
                } finally {
                    this.couponBusy = false;
                }
            },

            async changeCartQuantity(lineKey, nextQuantity, maxQuantity = 99, input = null, currentQuantity = 1) {
                if (this.busyLineKey || !window.SM?.shopCart) {
                    return;
                }

                const update = window.SM.shopCart.prepareQuantityUpdate(nextQuantity, {
                    input,
                    max: maxQuantity,
                    fallbackQuantity: currentQuantity,
                });
                if (!update.shouldSubmit) {
                    if (this.pendingCartUpdate && this.pendingCartUpdate.lineKey === lineKey) {
                        this.pendingCartUpdate = null;
                        this.clearCartUpdateDebounceTimer();
                        this.setCartUpdateNotice('');
                    }
                    return;
                }

                if (this.pendingCartUpdate && this.pendingCartUpdate.lineKey !== lineKey) {
                    return;
                }

                this.cartUpdateError = '';
                this.clearCartUpdateErrorTimer();
                this.pendingCartUpdate = {
                    lineKey,
                    quantity: update.quantity,
                    maxQuantity,
                };
                this.clearCartUpdateDebounceTimer();
                this.cartUpdateDebounceTimer = window.setTimeout(() => {
                    this.submitPendingCartQuantityUpdate();
                }, 500);
            },

            async removeCartLine(lineKey) {
                if (this.busyLineKey || !window.SM?.shopCart) {
                    return;
                }

                this.pendingCartUpdate = null;
                this.clearCartUpdateDebounceTimer();
                this.busyLineKey = lineKey;
                this.cartUpdateError = '';
                this.clearCartUpdateErrorTimer();

                try {
                    await window.SM.shopCart.removeLine(lineKey, {
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                        showError: false,
                    });
                    this.setCartUpdateNotice('');
                } catch (error) {
                    await this.recoverCartAfterFailedUpdate(error?.message || 'Unable to update the cart right now.');
                } finally {
                    this.busyLineKey = null;
                }
            },

            init() {
                if (!window.SM?.shopCart) {
                    return;
                }

                window.SM.shopCart.configure({
                    showUrl: config.routes.show,
                    updateUrl: config.routes.update,
                    removeUrl: config.routes.remove,
                    preferencesUrl: config.routes.preferences,
                    couponApplyUrl: config.routes.couponApply,
                    couponRemoveUrl: config.routes.couponRemove,
                    initialState: config.cartState,
                });

                this.syncDeliverySelections();
                window.SM.shopCart.subscribe((cart) => this.setCartState(cart));
            },
        };
    }
</script>
