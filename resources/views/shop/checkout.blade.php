@php
    $selectedShippingMethodCode = (string) ($summary['shipping_method_code'] ?? ($prefill['shipping_method_code'] ?? ''));
    $canOfferConsolidation = (bool) ($summary['contains_physical'] ?? false) && (bool) ($summary['has_delayed_items'] ?? false);
    $selectedShippingState = trim((string) ($prefill['shipping_state'] ?? ''));
    $consolidationSavingsAmount = (float) ($summary['shipping_quote']['consolidation_savings_amount'] ?? 0);
    $itemCount = (int) $lines->sum('quantity');
    $checkoutTotal = (float) ($summary['total'] ?? 0);
    $hasCheckoutTotal = $checkoutTotal > 0.0001;
    $accountCreditAvailable = round((float) ($accountCreditAvailable ?? 0), 2);
    $accountCreditApplied = round((float) ($accountCreditApplied ?? 0), 2);
    $amountDueAfterCredit = round((float) ($amountDueAfterCredit ?? $checkoutTotal), 2);
    $hasAmountDue = $amountDueAfterCredit > 0.0001;
    $requiresManualQuote = (bool) ($summary['shipping_quote']['requires_manual_quote'] ?? false);
    $submitLabel = $requiresManualQuote ? 'Request Quote' : ($hasAmountDue ? 'Place Order' : 'Complete Order');
    $continueLabel = $requiresManualQuote ? 'Request Quote' : ($hasAmountDue ? 'Enter Payment Details' : 'Complete Order');
    $showPaymentStep = ! $requiresManualQuote && (session('shop_checkout_step') === 'payment' || $errors->has('source_id') || $errors->has('cart'));
    $accountTermsDays = (int) ($accountTermsDays ?? 0);
    $canUseAccountTerms = (bool) ($canUseAccountTerms ?? ($accountTermsDays > 0));
    $accountTermsLabel = trim((string) ($accountTermsLabel ?? ($accountTermsDays > 0 ? $accountTermsDays.' days' : 'Current')));
@endphp

<x-layout title="Checkout" :canonical="route('shop.checkout')">
    <x-mast backRoute="shop.index" backTitle="Store">Checkout</x-mast>

    @include('shop.partials.processing-pause-notice', [
        'notice' => $summary['shipping_quote']['processing_pause_notice'] ?? null,
    ])

    <x-container class="py-8">
        <div
            class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start"
            x-data="shopCheckoutPage({
                checkoutStep: @js($showPaymentStep ? 'payment' : 'shipping'),
                cartState: @js($cartPayload),
                summaryCanCheckout: @js((bool) ($cartPayload['summary']['can_checkout'] ?? ($summary['can_checkout'] ?? false))),
                shippingMethodCode: @js($cartPayload['summary']['shipping_method_code'] ?? $selectedShippingMethodCode),
                shippingCountry: @js($cartPayload['shipping_country'] ?? $prefill['shipping_country']),
                consolidateShipments: @js((bool) ($cartPayload['summary']['consolidate_shipments'] ?? $prefill['consolidate_shipments'])),
                canOfferConsolidation: @js($canOfferConsolidation),
                requiresPayment: @js($amountDueAfterCredit > 0.0001),
                accountCreditAvailable: @js($accountCreditAvailable),
                accountCreditApplied: @js($accountCreditApplied),
                amountDueAfterCredit: @js($amountDueAfterCredit),
                accountTermsDays: @js($accountTermsDays),
                canUseAccountTerms: @js($canUseAccountTerms),
                paymentMethod: @js(old('payment_method', $canUseAccountTerms ? 'account_terms' : 'credit_card')),
                squareEnabled: @js($squareEnabled),
                squareApplicationId: @js($squareApplicationId),
                squareLocationId: @js($squareLocationId),
                squareEnvironment: @js($squareEnvironment),
                initialCouponDraft: @js((string) old('coupon_code', '')),
                routes: {
                    show: @js(route('shop.cart.show')),
                    update: @js(route('shop.cart.update')),
                    remove: @js(route('shop.cart.remove')),
                    preferences: @js(route('shop.cart.preferences')),
                    couponApply: @js(route('shop.cart.coupon.apply')),
                    couponRemove: @js(route('shop.cart.coupon.remove')),
                },
            })"
            x-init="init()"
        >
            <div class="lg:hidden">
                @include('shop.partials.checkout-order-summary', [
                    'summary' => $summary,
                    'itemCount' => $itemCount,
                    'submitLabel' => $submitLabel,
                    'showSubmitButton' => false,
                    'showLineItems' => true,
                    'couponCode' => $couponCode ?? null,
                    'shippingCountry' => $prefill['shipping_country'],
                    'shippingCountryBinding' => 'shippingCountry',
                    'returnTo' => route('shop.checkout'),
                ])
            </div>

            <form id="shop-checkout-form" method="POST" action="{{ route('shop.checkout.place-order') }}" class="min-w-0 space-y-6" x-ref="checkoutForm" x-on:submit.prevent="submitOrder($event)">
                @csrf
                <input type="hidden" name="source_id" x-model="sourceId" x-ref="sourceIdInput">

{{--                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">--}}
{{--                    <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Checkout</div>--}}
{{--                    <h2 class="mt-1 text-3xl font-bold text-gray-900">Complete your order</h2>--}}
{{--                    <p class="mt-3 max-w-2xl text-sm text-gray-600">Shipping details and Square payment now stay on the same page. Review your order, switch to payment, then place the order once your card details are entered.</p>--}}
{{--                </section>--}}

                <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm uppercase tracking-[0.18em] text-gray-500" x-text="requiresManualQuote() ? 'Step 1 of 1' : 'Step 1 of 2'">{{ $requiresManualQuote ? 'Step 1 of 1' : 'Step 1 of 2' }}</div>
                            <h3 class="mt-1 text-2xl font-bold text-gray-900">Order Details</h3>
                            <p class="mt-2 text-sm text-gray-600">Contact details, delivery settings, item review, and order notes.</p>
                        </div>
                        <button
                            x-show="checkoutStep === 'payment'"
                            x-cloak
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50"
                            x-on:click="editShipping()"
                        >
                            Edit
                        </button>
                    </div>

                    <div x-show="checkoutStep === 'shipping'" x-cloak class="mt-6 space-y-6">
                        <div x-show="!requiresManualQuote() && !canCheckout() && checkoutBlockedReason() !== ''" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                            <div class="font-semibold">Checkout needs review</div>
                            <div class="mt-1" x-text="checkoutBlockedReason()">{{ $summary['shipping_quote']['reason'] ?? '' }}</div>
                        </div>

                        @error('coupon_code')
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('shipping_country')
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('shipping_method_code')
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        <div x-show="hasInventoryChangeNotices()" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                            <div class="font-semibold">Stock availability changed</div>
                            <div class="mt-1">Review these updates before moving to payment.</div>
                            <div class="mt-3 space-y-2">
                                <template x-for="notice in cartState.inventory_change_notices" :key="`${notice.type}-${notice.key}`">
                                    <div class="rounded-xl border border-amber-200 bg-white/80 px-3 py-2" x-text="notice.message"></div>
                                </template>
                            </div>
                        </div>

{{--                        <div x-show="Boolean(cartState?.summary?.contains_preorder)" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">--}}
{{--                            <div class="font-semibold">Pre-order items in your cart</div>--}}
{{--                            <div class="mt-1">Your order will ship once these items become available.</div>--}}
{{--                        </div>--}}

{{--                        <div x-show="Boolean(cartState?.summary?.contains_backorder)" x-cloak class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">--}}
{{--                            <div class="font-semibold">Split shipment items in your cart</div>--}}
{{--                            <div class="mt-1">Any quantity above available stock will move into a later shipment unless you choose to consolidate it.</div>--}}
{{--                        </div>--}}

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <h4 class="text-lg font-bold text-gray-900">Contact Details</h4>
                            <p class="mt-1 text-sm text-gray-600">We use these details for order updates and payment confirmations.</p>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <x-ui.input
                                    name="billing_name"
                                    label="Full Name"
                                    :value="$prefill['billing_name']"
                                    required
                                    x-on:input="syncRecipientField('billing_name', 'shipping_name')"
                                    x-on:change="syncRecipientField('billing_name', 'shipping_name')"
                                />
                                <x-ui.input name="billing_company" label="Company" :value="$prefill['billing_company']" />
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <x-ui.input name="billing_email" label="Email" type="email" :value="$prefill['billing_email']" required />
                                <x-ui.input
                                    name="billing_phone"
                                    label="Phone"
                                    :value="$prefill['billing_phone']"
                                    required
                                    x-on:input="syncRecipientField('billing_phone', 'shipping_phone')"
                                    x-on:change="syncRecipientField('billing_phone', 'shipping_phone')"
                                />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900">Shipping Address</h4>
                                    <p class="mt-1 text-sm text-gray-600" x-text="hasPhysicalItems() ? 'Shipping is only available within Australia.' : 'This order only contains digital items, so no shipping address is required.'">Shipping is only available within Australia.</p>
                                </div>
                                <div x-show="hasPhysicalItems()" x-cloak class="text-sm font-medium text-gray-500" x-text="needsShippingAddress() ? 'Delivery selected' : 'Pickup selected'"></div>
                            </div>

                            <div x-show="!hasPhysicalItems()" x-cloak class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                                This order only contains digital items, so no shipping address is required.
                            </div>

                            <div x-show="hasPhysicalItems() && !needsShippingAddress()" x-cloak class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                                We will contact you when your order is available to collect.
                            </div>

                            <div x-show="needsShippingAddress()" x-cloak class="mt-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <x-ui.input
                                        name="shipping_name"
                                        label="Recipient Name"
                                        :value="$prefill['shipping_name']"
                                        x-bind:required="needsShippingAddress()"
                                        x-bind:disabled="!needsShippingAddress()"
                                        x-on:input="markRecipientFieldEdited('shipping_name', 'billing_name')"
                                        x-on:change="markRecipientFieldEdited('shipping_name', 'billing_name')"
                                    />
                                    <x-ui.input
                                        name="shipping_phone"
                                        label="Recipient Phone"
                                        :value="$prefill['shipping_phone']"
                                        x-bind:required="needsShippingAddress()"
                                        x-bind:disabled="!needsShippingAddress()"
                                        x-on:input="markRecipientFieldEdited('shipping_phone', 'billing_phone')"
                                        x-on:change="markRecipientFieldEdited('shipping_phone', 'billing_phone')"
                                    />
                                </div>
                                <x-ui.input name="shipping_address" label="Address Line 1" :value="$prefill['shipping_address']" x-bind:required="needsShippingAddress()" x-bind:disabled="!needsShippingAddress()" />
                                <x-ui.input name="shipping_address2" label="Address Line 2" :value="$prefill['shipping_address2']" x-bind:disabled="!needsShippingAddress()" />
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <x-ui.input name="shipping_city" label="City" :value="$prefill['shipping_city']" x-bind:required="needsShippingAddress()" x-bind:disabled="!needsShippingAddress()" />
                                    <x-ui.select name="shipping_state" label="State" x-bind:required="needsShippingAddress()" x-bind:disabled="!needsShippingAddress()">
                                        <option value="">Select state</option>
                                        @foreach($australianStates as $stateCode => $stateLabel)
                                            <option value="{{ $stateCode }}" @selected($selectedShippingState === $stateCode)>{{ $stateLabel }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    <x-ui.input
                                        name="shipping_postcode"
                                        label="Postcode"
                                        :value="$prefill['shipping_postcode']"
                                        x-bind:required="needsShippingAddress()"
                                        x-bind:disabled="!needsShippingAddress()"
                                        maxlength="4"
                                        inputmode="numeric"
                                        pattern="\d{4}"
                                        x-on:input="$el.value = $el.value.replace(/\D+/g, '').slice(0, 4)"
                                    />
                                    <div>
                                        <x-ui.input name="shipping_country" label="Country" :value="$prefill['shipping_country']" x-model="shippingCountry" readonly="true" x-bind:required="needsShippingAddress()" x-bind:disabled="!needsShippingAddress()" class="mb-0" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden rounded-2xl border border-gray-200 p-5 lg:block">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900">List of Items</h4>
                                </div>
                                <div class="text-sm font-medium text-gray-500" x-text="`${Number(cartState?.summary?.item_count || {{ $itemCount }})} item${Number(cartState?.summary?.item_count || {{ $itemCount }}) === 1 ? '' : 's'}`">{{ $itemCount }} items</div>
                            </div>

                            <div class="mt-5 space-y-4">
                                <template x-for="line in cartState.lines" :key="line.key">
                                    <div class="rounded-2xl border border-gray-200 p-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                                <img :src="line.product.image_url" :alt="line.display_title" class="h-14 w-14 rounded-xl bg-gray-100 object-cover" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3 lg:block">
                                                        <div class="min-w-0">
                                                            <a :href="line.product.url" class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-color" x-text="line.product.title"></a>
                                                            <div x-show="line.variant_name" class="mt-0.5 truncate text-xs font-medium text-gray-600" x-text="line.variant_name"></div>
                                                            <div x-show="lineFulfilmentLabel(line)" x-cloak class="mt-1 text-xs" :class="line.is_preorder ? 'text-amber-800' : (Number(line.delayed_quantity || 0) > 0 ? 'text-sky-800' : 'text-gray-500')" x-text="lineFulfilmentLabel(line)"></div>
                                                            <div x-show="lineTriggersManualQuote(line)" x-cloak class="mt-1 text-xs font-medium text-amber-800">Requires pickup or a manual shipping quote</div>
                                                        </div>
                                                        <div class="text-right lg:hidden">
                                                            <div class="text-sm font-bold text-gray-900" x-text="formatMoney(line.line_price)"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between gap-4 lg:justify-end">
                                                <div class="hidden text-xs text-gray-500 xl:block" x-text="`${formatMoney(line.unit_price)} each`"></div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-medium uppercase tracking-[0.14em] text-gray-500">Qty</span>
                                                    <div class="shop-catalog-stepper flex items-center gap-2 rounded border border-gray-300 bg-white">
                                                        <button
                                                            type="button"
                                                            class="shop-catalog-stepper-button inline-flex h-8 w-8 items-center justify-center border-r border-r-gray-300 p-1 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                            :disabled="busyLineKey === line.key || isSubmitting"
                                                            @click="changeCartQuantity(line.key, Number(line.quantity || 1) - 1, line.max_quantity)"
                                                        >-</button>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            :max="line.max_quantity || 99"
                                                            :value="line.quantity"
                                                            class="shop-catalog-stepper-input h-8 min-w-12 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                                            :disabled="busyLineKey === line.key || isSubmitting"
                                                            @change="changeCartQuantity(line.key, $event.target.value, line.max_quantity)"
                                                        />
                                                        <button
                                                            type="button"
                                                            class="shop-catalog-stepper-button inline-flex h-8 w-8 items-center justify-center border-l border-l-gray-300 p-1 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                            :disabled="busyLineKey === line.key || isSubmitting || Number(line.quantity || 0) >= Number(line.max_quantity || 99)"
                                                            @click="changeCartQuantity(line.key, Number(line.quantity || 0) + 1, line.max_quantity)"
                                                        >+</button>
                                                    </div>
                                                </div>
                                                <div class="min-w-[4.5rem] text-right">
                                                    <div class="hidden text-sm font-bold text-gray-900 lg:block" x-text="formatMoney(line.line_price)"></div>
                                                    <button
                                                        type="button"
                                                        class="mt-1 text-xs text-red-600 transition hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="busyLineKey === line.key || isSubmitting"
                                                        @click="removeCartLine(line.key)"
                                                    >Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div x-show="hasPhysicalItems()" x-cloak class="rounded-2xl border border-gray-200 p-5">
                            <h4 class="text-lg font-bold text-gray-900">Shipping Options</h4>

                            <div class="mt-5 space-y-3">
                                <template x-for="method in cartState.summary.shipping_methods" :key="method.code">
                                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border px-4 py-3 transition" :class="shippingMethodCode === method.code ? 'border-sky-500 bg-sky-50' : 'border-gray-200 bg-white'">
                                        <span class="flex min-w-0 items-center gap-4">
                                            <input
                                                type="radio"
                                                name="shipping_method_code"
                                                class="mt-1 h-5 w-5 border-gray-300 text-sky-600 focus:ring-sky-500"
                                                :value="method.code"
                                                x-model="shippingMethodCode"
                                                :disabled="deliveryUpdateBusy || isSubmitting || !hasPhysicalItems()"
                                                @change="scheduleDeliveryUpdate()"
                                            >
                                            <span class="block">
                                                <span class="block text-sm font-semibold text-gray-900" x-text="method.name"></span>
                                                <span x-show="method.description" class="mt-0.5 block text-xs text-gray-500" x-text="method.description"></span>
                                                <span x-show="method.delivery_estimate_label" class="mt-1 block text-xs font-medium text-gray-600" x-text="`ETA ${method.delivery_estimate_label}`"></span>
                                            </span>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-900" x-text="shippingMethodAmountLabel(method)"></span>
                                    </label>
                                </template>
                            </div>

                            <label x-show="currentCanOfferConsolidation() && shippingMethodCode !== 'pickup' && shippingMethodCode !== 'request_quote'" x-cloak class="mt-4 flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    name="consolidate_shipments"
                                    value="1"
                                    class="mt-0.5 h-5 w-5 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                    x-model="consolidateShipments"
                                    :disabled="deliveryUpdateBusy || isSubmitting || !currentCanOfferConsolidation()"
                                    @change="scheduleDeliveryUpdate()"
                            >
                                <span>
                                    Hold everything and consolidate into one shipment once all items are available.
                                    <span x-show="hasConsolidationSavings()" x-cloak class="mt-1 block text-xs text-gray-500" x-text="consolidationSavingsLabel()">@if($consolidationSavingsAmount > 0.0001){{ (bool) ($summary['shipping_quote']['consolidate_shipments'] ?? false) ? 'Saving' : 'Save' }} ${{ number_format($consolidationSavingsAmount, 2) }} by sending everything together.@endif</span>
                                </span>
                            </label>

                            <div class="mt-4 text-xs text-gray-500">
                                <span x-show="deliveryUpdateBusy || deliveryUpdateNotice === 'Updating delivery...'" x-cloak>Updating shipping...</span>
                                <span x-show="deliveryUpdateError" x-cloak class="font-medium text-red-600" x-text="deliveryUpdateError"></span>
                            </div>

                            <div x-show="!requiresManualQuote() && hasShipmentPlan()" x-cloak class="mt-4 space-y-3 border-t border-gray-200 pt-4">
                                <template x-for="shipment in cartState.summary.shipping_quote.shipments" :key="shipment.key">
                                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 font-semibold text-gray-900">
                                                    <i x-show="shipmentIconClass(shipment)" x-cloak :class="shipmentIconClass(shipment)" aria-hidden="true"></i>
                                                    <span x-text="shipment.title_primary || shipment.title"></span>
                                                </div>
                                                <div x-show="shipment.title_meta" x-cloak class="mt-1 max-w-sm text-xs text-gray-500" x-text="shipment.title_meta"></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-semibold text-gray-900" x-text="shipment.is_pickup ? 'Free' : formatMoney(shipment.amount)"></div>
                                            </div>
                                        </div>

                                        <template x-if="Array.isArray(shipment.items) && shipment.items.length > 0">
                                            <ul class="mt-3 list-disc space-y-2 border-t border-gray-200 pl-5 pt-3 text-xs text-gray-600">
                                                <template x-for="item in shipment.items" :key="`${shipment.key}-${item.display_title}-${item.quantity}`">
                                                    <li>
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
                        </div>

                        <div class="hidden" aria-hidden="true">
                            @foreach($lines as $line)
                                @if((int) $line->delayed_quantity > 0)
                                    @if((int) $line->available_now_quantity > 0)
                                        <div>{{ (int) $line->available_now_quantity }} ships now, {{ (int) $line->delayed_quantity }} ships later{{ $line->delayed_shipping_estimate ? ' from '.$line->delayed_shipping_estimate : '' }}</div>
                                    @else
                                        <div>Backorder · Expected shipping {{ $line->delayed_shipping_estimate ?: 'to be confirmed' }}</div>
                                    @endif
                                @endif
                            @endforeach
                            @foreach(($summary['shipping_quote']['shipments'] ?? []) as $shipment)
                                @if(trim((string) ($shipment['title'] ?? '')) !== '')
                                    <div>{{ $shipment['title'] }}</div>
                                @endif
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <h4 class="text-lg font-bold text-gray-900">Order Notes</h4>
                            <p class="mt-1 text-sm text-gray-600">Add any delivery or packing notes that should travel with this order.</p>
                            <div class="mt-5">
                                <x-ui.input type="textarea" name="notes" label="Anything we should know?" :value="$prefill['notes']" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-ui.button type="button" x-bind:disabled="shippingStepButtonDisabled()" x-on:click="requiresManualQuote() ? submitManualQuote() : goToPayment()">
                                <span x-text="requiresManualQuote() ? 'Request Quote' : @js($continueLabel)">{{ $continueLabel }}</span>
                            </x-ui.button>
                        </div>
                    </div>

{{--                    <div x-show="checkoutStep === 'payment'" x-cloak class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-700">--}}
{{--                        <div class="font-semibold text-gray-900">Shipping details locked in for review</div>--}}
{{--                        <div class="mt-1">Contact details, delivery settings, items, and notes are hidden while payment is open. Use Edit any time to make changes.</div>--}}
{{--                    </div>--}}
                </section>

                <section x-show="!requiresManualQuote()" x-cloak class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm" x-ref="paymentSection">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm uppercase tracking-[0.18em] text-gray-500">Step 2 of 2</div>
                            <h3 class="mt-1 text-2xl font-bold text-gray-900">Payment Details</h3>
                        </div>
                    </div>

                    <div x-show="checkoutStep !== 'payment'" x-cloak class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600">
                        Finish the shipping details first, then open payment to enter your card details.
                    </div>

                    <div x-show="checkoutStep === 'payment'" x-cloak class="mt-6 space-y-5">
                        @error('cart')
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('source_id')
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        <div x-show="Boolean(cartState?.summary?.contains_digital)" x-cloak class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            Digital downloads unlock automatically after payment.
                        </div>

{{--                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">--}}
{{--                            <div class="flex items-center justify-between gap-4">--}}
{{--                                <div>--}}
{{--                                    <h4 class="text-lg font-bold text-gray-900">Review</h4>--}}
{{--                                    <p class="mt-1 text-sm text-gray-600">You can still go back and edit shipping details before placing the order.</p>--}}
{{--                                </div>--}}
{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50"--}}
{{--                                    x-on:click="editShipping()"--}}
{{--                                >--}}
{{--                                    Edit Shipping Details--}}
{{--                                </button>--}}
{{--                            </div>--}}

{{--                            <div class="mt-5 grid gap-4 md:grid-cols-2">--}}
{{--                                <div class="rounded-2xl border border-gray-200 bg-white p-4">--}}
{{--                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Contact</div>--}}
{{--                                    <div class="mt-2 text-sm text-gray-700">Your contact details from Step 1 will be used for receipts, order updates, and delivery communication.</div>--}}
{{--                                </div>--}}
{{--                                <div class="rounded-2xl border border-gray-200 bg-white p-4">--}}
{{--                                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500" x-text="hasPhysicalItems() ? 'Shipping' : 'Delivery'">{{ $summary['contains_physical'] ? 'Shipping' : 'Delivery' }}</div>--}}
{{--                                    <div class="mt-2 text-sm text-gray-700" x-text="hasPhysicalItems() ? 'Your selected shipping option, address, and notes remain editable until you place the order.' : 'No shipping is required for this order.'">{{ $summary['contains_physical'] ? 'Your selected shipping option, address, and notes remain editable until you place the order.' : 'No shipping is required for this order.' }}</div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}

                        @if($accountCreditApplied > 0.0001)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                Account credit of ${{ number_format($accountCreditApplied, 2) }} will be applied automatically.
                                @if($amountDueAfterCredit > 0.0001)
                                    Remaining after credit: <strong>${{ number_format($amountDueAfterCredit, 2) }}</strong>.
                                @else
                                    This order will be covered in full by account credit.
                                @endif
                            </div>
                        @endif

                        @if(!$hasAmountDue)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                No payment is required for this checkout. Place Order is now available.
                            </div>
                        @elseif(!$squareEnabled && ! $canUseAccountTerms)
                            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                                Online card payments are currently unavailable.
                            </div>
                        @else
                            <div class="rounded-2xl border border-gray-200 p-5">
                                <x-ui.select
                                    label="Payment Method"
                                    name="payment_method"
                                    error=""
                                    x-model="paymentMethod"
                                    x-on:change="onPaymentMethodChange()"
                                    x-on:mousedown="if (isSubmitting || checkoutStep !== 'payment') { $event.preventDefault() }"
                                    x-on:keydown="if (isSubmitting || checkoutStep !== 'payment') { $event.preventDefault() }">
                                    @if($canUseAccountTerms)
                                        <option value="account_terms">Charge to account ({{ $accountTermsLabel }})</option>
                                    @endif
                                    <option value="credit_card" {{ ($squareEnabled && $squareApplicationId !== '' && $squareLocationId !== '') ? '' : 'disabled' }}>Pay by credit card</option>
                                </x-ui.select>

                                @if($canUseAccountTerms)
                                    <div
                                        x-show="paymentMethod === 'account_terms'"
                                        x-cloak
                                        class="mt-2 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                                        Your account has {{ $accountTermsDays }}-day terms. This order will be invoiced and payable within {{ $accountTermsDays }} days.
                                    </div>
                                @endif

                                <div class="mt-5" x-show="requiresPayment && paymentMethod === 'credit_card'" x-cloak x-init="initSquareCard()">
                                    <div class="flex items-center justify-between gap-4">
                                        <label class="block text-sm font-semibold text-gray-900">Card Details</label>
                                        <a href="https://squareup.com/au/en" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                                            Secure payment by Square
                                        </a>
                                    </div>
                                    <div x-ref="squareCardContainer" class="mt-4" x-bind:class="{ 'pointer-events-none opacity-60': isSubmitting || isCardLoading }"></div>
                                    <div x-show="isCardLoading" x-cloak class="absolute inset-0 flex items-center justify-center bg-white/80">
                                        <img src="{{ asset('loading.gif') }}" alt="Loading card form" width="56" height="56" />
                                    </div>
                                    <div x-show="errorMessage" x-cloak class="mt-2 text-xs text-red-600" x-text="errorMessage"></div>
                                </div>
                            </div>
                        @endif

                        <div class="lg:hidden" x-show="checkoutStep === 'payment'" x-cloak>
                            <button
                                type="submit"
                                class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white inline-flex w-full items-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none"
                                x-bind:disabled="placeOrderDisabled()"
                            >
                                <span x-show="!isSubmitting" x-cloak>{{ $submitLabel }}</span>
                                <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-2">
                                    <span class="altcha-inline-spinner" aria-hidden="true"></span>
                                    <span>{{ $requiresManualQuote ? 'Requesting Quote...' : 'Processing...' }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>

            <aside class="hidden lg:block lg:sticky lg:top-12">
                @include('shop.partials.checkout-order-summary', [
                    'summary' => $summary,
                    'itemCount' => $itemCount,
                    'submitLabel' => $submitLabel,
                    'formId' => 'shop-checkout-form',
                    'couponCode' => $couponCode ?? null,
                    'shippingCountry' => $prefill['shipping_country'],
                    'shippingCountryBinding' => 'shippingCountry',
                    'returnTo' => route('shop.checkout'),
                    'submitButtonAttributes' => [
                        'x-bind:disabled' => 'checkoutSubmitDisabled()',
                    ],
                ])
            </aside>
        </div>
    </x-container>
</x-layout>

@if($squareEnabled && $hasCheckoutTotal)
    <script src="{{ $squareEnvironment === 'production' ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js' }}" async></script>
@endif
<script>
    function shopCheckoutPage(config) {
        return {
            checkoutStep: config.checkoutStep || 'shipping',
            cartState: config.cartState || {},
            alpineReady: false,
            summaryCanCheckout: Boolean(config.summaryCanCheckout),
            shippingMethodCode: config.shippingMethodCode || '',
            shippingCountry: config.shippingCountry || 'Australia',
            consolidateShipments: Boolean(config.consolidateShipments),
            canOfferConsolidation: Boolean(config.canOfferConsolidation),
            requiresPayment: Boolean(config.requiresPayment),
            squareEnabled: Boolean(config.squareEnabled),
            squareApplicationId: config.squareApplicationId || '',
            squareLocationId: config.squareLocationId || '',
            paymentMethod: config.paymentMethod || 'credit_card',
            routes: config.routes || {},
            busyLineKey: null,
            couponDraft: config.initialCouponDraft || '',
            couponBusy: false,
            couponError: '',
            deliveryUpdateTimer: null,
            deliveryStatusTimer: null,
            deliveryUpdateBusy: false,
            deliveryUpdateError: '',
            deliveryUpdateNotice: '',
            quoteDirty: false,
            squareCard: null,
            squareCardInitPromise: null,
            squareListenersBound: false,
            sourceId: '',
            errorMessage: '',
            isSubmitting: false,
            isCardLoading: false,
            paymentDetailsEntered: false,
            accountTermsDays: Number(config.accountTermsDays || 0),
            canUseAccountTerms: Boolean(config.canUseAccountTerms),
            paymentFields: {
                cardNumber: { isEmpty: true },
                expirationDate: { isEmpty: true },
                cvv: { isEmpty: true },
            },

            formatMoney(value) {
                const amount = Number(value || 0);
                return `$${amount.toFixed(2)}`;
            },

            shippingMethodAmountLabel(method) {
                if (!method || typeof method !== 'object') {
                    return this.formatMoney(0);
                }

                if (Boolean(method.requires_manual_quote)) {
                    return '';
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

            accountCreditAppliedAmount() {
                const total = Number(this.cartState?.summary?.total || 0);
                return Math.min(this.accountCreditAvailable || 0, Math.max(0, total));
            },

            remainingDueAfterCredit() {
                const total = Number(this.cartState?.summary?.total || 0);
                return Math.max(0, total - this.accountCreditAppliedAmount());
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
                    return `This order is currently split into ${shipmentCount} shipments.`;
                }

                return 'This order is currently split into multiple shipments.';
            },

            hasInventoryChangeNotices() {
                return Array.isArray(this.cartState?.inventory_change_notices) && this.cartState.inventory_change_notices.length > 0;
            },

            hasPhysicalItems() {
                return Boolean(this.cartState?.summary?.contains_physical);
            },

            needsShippingAddress() {
                return this.hasPhysicalItems() && String(this.shippingMethodCode || '') !== 'pickup';
            },

            hasShipmentPlan() {
                return Array.isArray(this.cartState?.summary?.shipping_quote?.shipments) && this.cartState.summary.shipping_quote.shipments.length > 0;
            },

            requiresManualQuote() {
                return Boolean(this.cartState?.summary?.shipping_quote?.requires_manual_quote);
            },

            manualQuoteLineKeys() {
                const keys = [];
                const shippingQuoteKeys = this.cartState?.summary?.shipping_quote?.manual_quote_line_keys;
                if (Array.isArray(shippingQuoteKeys)) {
                    keys.push(...shippingQuoteKeys);
                }

                const shippingMethods = Array.isArray(this.cartState?.summary?.shipping_methods)
                    ? this.cartState.summary.shipping_methods
                    : [];

                for (const method of shippingMethods) {
                    if (!Boolean(method?.requires_manual_quote)) {
                        continue;
                    }

                    if (Array.isArray(method?.manual_quote_line_keys)) {
                        keys.push(...method.manual_quote_line_keys);
                    }
                }

                return [...new Set(keys.map((key) => String(key || '').trim()).filter((key) => key !== ''))];
            },

            lineTriggersManualQuote(line) {
                if (!line || typeof line !== 'object') {
                    return false;
                }

                return this.manualQuoteLineKeys().includes(String(line.key || ''));
            },

            checkoutField(name) {
                const form = this.$refs.checkoutForm;
                if (!(form instanceof HTMLFormElement) || typeof name !== 'string' || name.trim() === '') {
                    return null;
                }

                const field = form.querySelector(`[name="${name.trim()}"]`);
                return field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement
                    ? field
                    : null;
            },

            syncRecipientField(sourceName, targetName) {
                const source = this.checkoutField(sourceName);
                const target = this.checkoutField(targetName);

                if (!(source instanceof HTMLInputElement) || !(target instanceof HTMLInputElement)) {
                    return;
                }

                const sourceValue = String(source.value || '').trim();
                const targetValue = String(target.value || '').trim();
                const mirroredFrom = String(target.dataset.smCheckoutMirroredFrom || '');
                const isMirrored = mirroredFrom === sourceName;
                const isManual = target.dataset.smCheckoutManual === '1';

                if (sourceValue === '') {
                    if (isMirrored && targetValue !== '') {
                        target.value = '';
                    }

                    if (String(target.value || '').trim() === '') {
                        delete target.dataset.smCheckoutMirroredFrom;
                        delete target.dataset.smCheckoutManual;
                    }

                    return;
                }

                if (isManual && !isMirrored) {
                    return;
                }

                if (targetValue === '' || isMirrored || targetValue === sourceValue) {
                    target.value = source.value;
                    target.dataset.smCheckoutMirroredFrom = sourceName;
                    delete target.dataset.smCheckoutManual;
                }
            },

            markRecipientFieldEdited(targetName, sourceName) {
                const source = this.checkoutField(sourceName);
                const target = this.checkoutField(targetName);

                if (!(source instanceof HTMLInputElement) || !(target instanceof HTMLInputElement)) {
                    return;
                }

                const sourceValue = String(source.value || '').trim();
                const targetValue = String(target.value || '').trim();

                if (targetValue === '') {
                    delete target.dataset.smCheckoutMirroredFrom;
                    delete target.dataset.smCheckoutManual;
                    return;
                }

                if (targetValue === sourceValue) {
                    target.dataset.smCheckoutMirroredFrom = sourceName;
                    delete target.dataset.smCheckoutManual;
                    return;
                }

                target.dataset.smCheckoutManual = '1';
                delete target.dataset.smCheckoutMirroredFrom;
            },

            currentCanOfferConsolidation() {
                return Boolean(this.cartState?.summary?.shipping_quote?.offers_consolidation ?? this.canOfferConsolidation);
            },

            canCheckout() {
                return Boolean(this.cartState?.summary?.can_checkout ?? this.summaryCanCheckout);
            },

            hasRequiredFieldValue(name) {
                const field = this.checkoutField(name);
                if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
                    return false;
                }

                return String(field.value || '').trim() !== '';
            },

            hasRequiredContactDetails() {
                return this.hasRequiredFieldValue('billing_name')
                    && this.hasRequiredFieldValue('billing_email')
                    && this.hasRequiredFieldValue('billing_phone');
            },

            hasRequiredShippingDetails() {
                if (!this.needsShippingAddress()) {
                    return true;
                }

                return this.hasRequiredFieldValue('shipping_name')
                    && this.hasRequiredFieldValue('shipping_phone')
                    && this.hasRequiredFieldValue('shipping_address')
                    && this.hasRequiredFieldValue('shipping_city')
                    && this.hasRequiredFieldValue('shipping_state')
                    && this.hasRequiredFieldValue('shipping_postcode')
                    && this.hasRequiredFieldValue('shipping_country');
            },

            checkoutBlockedReason() {
                return String(this.cartState?.summary?.shipping_quote?.reason || '').trim();
            },

            checkoutShippingLabel() {
                const shippingLabel = String(this.cartState?.summary?.shipping_quote?.method || '').trim();
                if (shippingLabel !== '') {
                    return shippingLabel;
                }

                if (!this.hasPhysicalItems()) {
                    return 'Delivery';
                }

                return String(this.shippingMethodCode || '') === 'pickup' ? 'Pickup' : 'Shipping';
            },

            discountLabel() {
                const couponCode = String(this.cartState?.summary?.coupon_code || '').trim();
                return couponCode !== '' ? `Discount (${couponCode})` : 'Discount';
            },

            currentCouponCode() {
                return String(this.cartState?.summary?.coupon_code || '').trim();
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
                if (!this.hasPhysicalItems()) {
                    this.quoteDirty = false;
                    this.deliveryUpdateError = '';
                    return;
                }

                if (String(this.shippingMethodCode || '') === 'pickup') {
                    this.consolidateShipments = false;
                }

                this.quoteDirty = true;
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
                this.clearDeliveryUpdateTimers();

                if (!this.hasPhysicalItems()) {
                    this.quoteDirty = false;
                    this.deliveryUpdateError = '';
                    this.deliveryUpdateNotice = '';
                    return true;
                }

                if (this.deliveryUpdateBusy) {
                    return false;
                }

                if (!window.SM?.shopCart) {
                    this.quoteDirty = false;
                    return true;
                }

                if (String(this.shippingMethodCode || '') === 'pickup') {
                    this.consolidateShipments = false;
                }

                this.deliveryUpdateBusy = true;
                this.deliveryUpdateError = '';
                this.deliveryUpdateNotice = 'Updating delivery...';

                try {
                    await window.SM.shopCart.updatePreferences({
                        shippingMethodCode: this.shippingMethodCode,
                        consolidateShipments: this.consolidateShipments,
                        shippingCountry: this.shippingCountry,
                        showNotice: false,
                    });
                    this.quoteDirty = false;
                    this.deliveryUpdateNotice = '';
                    return true;
                } catch (error) {
                    this.deliveryUpdateNotice = '';
                    this.deliveryUpdateError = error?.message || 'Unable to update delivery options right now.';
                    this.quoteDirty = true;
                    return false;
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

                if (!availableCodes.includes(String(this.shippingMethodCode || ''))) {
                    this.shippingMethodCode = fallbackCode;
                }

                if (!Boolean(this.cartState?.summary?.shipping_quote?.offers_consolidation)) {
                    this.consolidateShipments = false;
                    return;
                }

                this.consolidateShipments = Boolean(this.cartState?.summary?.consolidate_shipments);
            },

            setCartState(cart) {
                if (!cart || typeof cart !== 'object') {
                    return;
                }

                this.cartState = cart;
                this.summaryCanCheckout = Boolean(cart.summary?.can_checkout ?? this.summaryCanCheckout);
                this.accountCreditApplied = this.accountCreditAppliedAmount();
                this.amountDueAfterCredit = this.remainingDueAfterCredit();
                this.requiresPayment = this.amountDueAfterCredit > 0.0001;
                this.canOfferConsolidation = Boolean(
                    cart.summary?.shipping_quote?.offers_consolidation
                    ?? (cart.summary?.contains_physical && cart.summary?.has_delayed_items)
                    ?? this.canOfferConsolidation
                );
                this.shippingCountry = String(cart.shipping_country || this.shippingCountry || 'Australia').trim() || 'Australia';
                this.deliveryUpdateError = '';
                this.quoteDirty = false;
                if (this.currentCouponCode() !== '') {
                    this.couponDraft = '';
                    this.couponError = '';
                }
                this.syncDeliverySelections();

                if (Boolean(cart.is_empty)) {
                    const redirectUrl = String(cart.cart_url || this.routes.show || '').trim();
                    if (redirectUrl !== '') {
                        window.location.assign(redirectUrl);
                    }
                    return;
                }

                if (this.checkoutStep === 'payment' && !this.canCheckout()) {
                    this.checkoutStep = 'shipping';
                }

                if (this.requiresManualQuote()) {
                    this.checkoutStep = 'shipping';
                }
            },

            async changeCartQuantity(lineKey, nextQuantity, maxQuantity = 99) {
                if (this.isSubmitting || this.busyLineKey || !window.SM?.shopCart) {
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
                if (this.isSubmitting || this.busyLineKey || !window.SM?.shopCart) {
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

            async applyCouponCode(event) {
                const form = event?.target instanceof HTMLFormElement
                    ? event.target
                    : event?.target?.closest?.('form');
                if (this.isSubmitting || this.couponBusy || !(form instanceof HTMLFormElement) || !window.SM?.shopCart) {
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
                if (this.isSubmitting || this.couponBusy || !(form instanceof HTMLFormElement) || !window.SM?.shopCart) {
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

            init() {
                if (window.SM?.shopCart) {
                    window.SM.shopCart.configure({
                        showUrl: this.routes.show,
                        updateUrl: this.routes.update,
                        removeUrl: this.routes.remove,
                        preferencesUrl: this.routes.preferences,
                        couponApplyUrl: this.routes.couponApply,
                        couponRemoveUrl: this.routes.couponRemove,
                        initialState: this.cartState,
                    });

                    window.SM.shopCart.subscribe((cart) => this.setCartState(cart));
                }

                this.setCartState(this.cartState);
                this.alpineReady = true;
                this.$nextTick(() => {
                    this.syncRecipientField('billing_name', 'shipping_name');
                    this.syncRecipientField('billing_phone', 'shipping_phone');
                });

                if (this.checkoutStep === 'payment') {
                    if (!this.canCheckout()) {
                        this.checkoutStep = 'shipping';
                        return;
                    }

                    if (this.paymentMethod === 'credit_card') {
                        this.$nextTick(() => this.initSquareCard());
                    }
                }
            },

            onPaymentMethodChange() {
                this.errorMessage = '';
                this.sourceId = '';
                if (this.$refs?.sourceIdInput) {
                    this.$refs.sourceIdInput.value = '';
                }

                if (this.paymentMethod === 'credit_card') {
                    this.$nextTick(() => this.initSquareCard());
                }
            },

            canUseCreditCard() {
                return this.paymentMethod === 'credit_card'
                    && this.requiresPayment
                    && this.squareEnabled
                    && this.squareApplicationId !== ''
                    && this.squareLocationId !== '';
            },

            async goToPayment() {
                const form = this.$refs.checkoutForm;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                this.errorMessage = '';
                if (!form.reportValidity()) {
                    return;
                }

                if (this.deliveryUpdateTimer !== null || this.quoteDirty) {
                    const updated = await this.applyDeliveryUpdate();
                    if (!updated) {
                        return;
                    }
                }

                if (!this.canCheckout()) {
                    return;
                }

                this.checkoutStep = 'payment';
                this.$nextTick(async () => {
                    await this.initSquareCard();
                    this.$refs.paymentSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            },

            editShipping() {
                this.checkoutStep = 'shipping';
                this.$nextTick(() => {
                    this.$refs.checkoutForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            },

            shippingStepButtonDisabled() {
                if (this.requiresManualQuote()) {
                    return this.isSubmitting || this.deliveryUpdateBusy;
                }

                return this.isSubmitting || this.deliveryUpdateBusy || this.quoteDirty || !this.canCheckout();
            },

            placeOrderDisabled() {
                if (!this.canCheckout() || this.isSubmitting || this.deliveryUpdateBusy || this.quoteDirty || this.deliveryUpdateTimer !== null) {
                    return true;
                }

                if (this.checkoutStep !== 'payment') {
                    return true;
                }

                if (!this.requiresPayment) {
                    return false;
                }

                if (this.paymentMethod === 'account_terms') {
                    return false;
                }

                return !this.squareEnabled || this.isCardLoading || !this.paymentDetailsEntered;
            },

            checkoutSubmitDisabled() {
                if (this.requiresManualQuote()) {
                    return this.isSubmitting || this.deliveryUpdateBusy;
                }

                return this.placeOrderDisabled();
            },

            submitManualQuote() {
                const form = this.$refs.checkoutForm;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                this.submitOrder({ target: form });
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
                        this.bindSquareCardListeners();
                        return true;
                    }

                    const payments = window.Square.payments(this.squareApplicationId, this.squareLocationId);
                    this.squareCard = await payments.card();
                    await this.squareCard.attach(this.$refs.squareCardContainer);
                    this.bindSquareCardListeners();
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

            bindSquareCardListeners() {
                if (this.squareListenersBound || !this.squareCard || typeof this.squareCard.addEventListener !== 'function') {
                    return;
                }

                const syncFromEvent = (event) => {
                    const detail = event && typeof event === 'object' && 'detail' in event ? event.detail || {} : {};
                    const field = typeof detail.field === 'string' ? detail.field : '';
                    const currentState = detail.currentState || {};

                    if (field !== '') {
                        this.paymentFields[field] = {
                            isEmpty: Boolean(currentState.isEmpty ?? false),
                        };
                    }

                    this.paymentDetailsEntered = Object.values(this.paymentFields).some((state) => state && state.isEmpty === false);
                };

                ['cardBrandChanged', 'errorClassAdded', 'errorClassRemoved', 'focusClassRemoved', 'postalCodeChanged'].forEach((eventName) => {
                    this.squareCard.addEventListener(eventName, syncFromEvent);
                });

                this.squareListenersBound = true;
            },

            async submitOrder(event) {
                if (this.isSubmitting) {
                    return;
                }

                const form = event.target instanceof HTMLFormElement ? event.target : event.target.closest('form');
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (this.requiresManualQuote()) {
                    if (!form.reportValidity()) {
                        return;
                    }

                    this.errorMessage = '';
                    this.isSubmitting = true;
                    if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                        window.SM.setFormProcessing(form, true, { submitLabel: 'Requesting Quote...' });
                    }

                    if (this.deliveryUpdateTimer !== null || this.quoteDirty) {
                        const updated = await this.applyDeliveryUpdate();
                        if (!updated || !this.requiresManualQuote()) {
                            this.errorMessage = this.deliveryUpdateError || 'Review the shipping details before requesting a quote.';
                            this.isSubmitting = false;
                            if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                                window.SM.setFormProcessing(form, false);
                            }
                            return;
                        }
                    }

                    form.submit();
                    return;
                }

                if (this.checkoutStep !== 'payment') {
                    await this.goToPayment();
                    return;
                }

                this.errorMessage = '';
                this.isSubmitting = true;
                if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                    window.SM.setFormProcessing(form, true, { submitLabel: 'Processing...' });
                }

                if (this.deliveryUpdateTimer !== null || this.quoteDirty) {
                    const updated = await this.applyDeliveryUpdate();
                    if (!updated || !this.canCheckout()) {
                        this.errorMessage = this.checkoutBlockedReason() || this.deliveryUpdateError || 'Review the shipping details before placing the order.';
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }
                }

                if (this.requiresPayment && this.paymentMethod === 'credit_card') {
                    if (!this.squareEnabled) {
                        this.errorMessage = 'Online card payments are currently unavailable.';
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }

                    if (!this.paymentDetailsEntered) {
                        this.errorMessage = 'Enter your card details before placing the order.';
                        this.isSubmitting = false;
                        if (window.SM && typeof window.SM.setFormProcessing === 'function') {
                            window.SM.setFormProcessing(form, false);
                        }
                        return;
                    }

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
                        if (this.$refs.sourceIdInput) {
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

                for (let attempt = 0; attempt < 50; attempt += 1) {
                    await new Promise((resolve) => window.setTimeout(resolve, 100));
                    if (window.Square && typeof window.Square.payments === 'function') {
                        return true;
                    }
                }

                return false;
            },
        };
    }
</script>
