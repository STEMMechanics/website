@props([
    'summary',
    'itemCount' => 0,
    'submitLabel' => 'Continue',
    'showSubmitButton' => true,
    'showLineItems' => false,
    'formId' => null,
    'couponCode' => null,
    'shippingCountry' => 'Australia',
    'shippingCountryBinding' => null,
    'returnTo' => null,
    'submitButtonAttributes' => [],
    'submitButtonColor' => 'primary',
])

@php
    $resolvedSummary = is_array($summary ?? null) ? $summary : [];
    $resolvedItemCount = max(0, (int) $itemCount);
    $resolvedFormId = trim((string) ($formId ?? ''));
    $resolvedSubmitLabel = trim((string) $submitLabel) ?: 'Continue';
    $resolvedShowSubmitButton = (bool) ($showSubmitButton ?? true);
    $resolvedShowLineItems = (bool) ($showLineItems ?? false);
    $resolvedCouponCode = trim((string) ($couponCode ?? ($resolvedSummary['coupon_code'] ?? '')));
    $resolvedShippingCountry = trim((string) ($shippingCountry ?? 'Australia')) ?: 'Australia';
    $resolvedShippingCountryBinding = trim((string) ($shippingCountryBinding ?? ''));
    $resolvedReturnTo = trim((string) ($returnTo ?? ''));
    $resolvedSubmitButtonAttributes = new \Illuminate\View\ComponentAttributeBag(is_array($submitButtonAttributes ?? null) ? $submitButtonAttributes : []);
    $resolvedSubmitButtonColor = trim((string) ($submitButtonColor ?? 'primary')) ?: 'primary';
    $canCheckout = (bool) ($resolvedSummary['can_checkout'] ?? false);
    $requiresManualQuote = (bool) ($resolvedSummary['shipping_quote']['requires_manual_quote'] ?? false);
    if ($requiresManualQuote) {
        $resolvedSubmitLabel = 'Request Quote';
    }
    $shippingLabel = trim((string) (($resolvedSummary['shipping_quote']['method'] ?? ''))) ?: 'Shipping';
    $blockedReason = trim((string) ($resolvedSummary['shipping_quote']['reason'] ?? ''));
    $submitButtonColorMap = [
        'primary' => 'hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color text-white',
        'accent' => 'hover:bg-orange-600 focus-visible:outline-orange-500 bg-orange-500 text-white',
    ];
    $submitButtonClasses = ($submitButtonColorMap[$resolvedSubmitButtonColor] ?? $submitButtonColorMap['primary']).' inline-flex w-full items-center justify-center rounded-md px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none';
@endphp

<div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-xl font-bold text-gray-900">Order Summary</h2>

    @if($resolvedShowLineItems)
        <div class="mt-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Items in your order</h3>
                    <p class="mt-1 text-xs text-gray-500">Review and adjust quantities before payment.</p>
                </div>
                <div class="text-xs font-medium text-gray-500" x-text="`${Number(cartState?.summary?.item_count || {{ $resolvedItemCount }})} item${Number(cartState?.summary?.item_count || {{ $resolvedItemCount }}) === 1 ? '' : 's'}`">{{ $resolvedItemCount }} items</div>
            </div>

            <div class="mt-4 space-y-3">
                <template x-for="line in cartState.lines" :key="`summary-${line.key}`">
                    <div class="rounded-2xl border border-gray-200 p-3">
                        <div class="flex gap-3">
                            <img :src="line.product.image_url" alt="Product image" :alt="line.display_title" class="h-14 w-14 rounded-xl bg-gray-100 object-cover" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a :href="line.product.url" class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-color" x-text="line.product.title"></a>
                                        <div x-show="line.variant_name" class="mt-0.5 truncate text-xs font-medium text-gray-600" x-text="line.variant_name"></div>
                                        <div x-show="lineFulfilmentLabel(line)" x-cloak class="mt-1 text-xs" :class="line.is_preorder ? 'text-amber-800' : (Number(line.delayed_quantity || 0) > 0 ? 'text-sky-800' : 'text-gray-500')" x-text="lineFulfilmentLabel(line)"></div>
                                        <div x-show="lineTriggersManualQuote(line)" x-cloak class="mt-1 text-xs font-medium text-amber-800">Requires pickup or a manual shipping quote</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-gray-900" x-text="formatMoney(line.line_price)"></div>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div class="text-xs text-gray-500" x-text="`${formatMoney(line.unit_price)} each`"></div>
                                    <div class="flex items-center gap-2">
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
                                        <button
                                            type="button"
                                            class="text-xs text-red-600 transition hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="busyLineKey === line.key || isSubmitting"
                                            @click="removeCartLine(line.key)"
                                        >Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endif

    <div class="{{ $resolvedShowLineItems ? 'mt-5 border-t border-gray-200 pt-5' : 'mt-4' }} space-y-3 text-sm text-gray-700">
        <div class="flex items-center justify-between gap-4">
            <span x-text="`Sub Total (${Number(cartState?.summary?.item_count || {{ $resolvedItemCount }})} item${Number(cartState?.summary?.item_count || {{ $resolvedItemCount }}) === 1 ? '' : 's'})`">Sub Total ({{ $resolvedItemCount }} item{{ $resolvedItemCount === 1 ? '' : 's' }})</span>
            <span class="font-semibold text-gray-900" x-text="formatMoney(cartState?.summary?.subtotal || 0)">${{ number_format((float) ($resolvedSummary['subtotal'] ?? 0), 2) }}</span>
        </div>

        <div class="flex items-center justify-between gap-4">
            <span x-text="checkoutShippingLabel()">{{ $shippingLabel }}</span>
            <span class="font-semibold text-gray-900" x-text="requiresManualQuote() ? '--' : (canCheckout() ? formatMoney(cartState?.summary?.shipping || 0) : 'Manual quote')">
                {{ $requiresManualQuote ? '--' : ($canCheckout ? '$'.number_format((float) ($resolvedSummary['shipping'] ?? 0), 2) : 'Manual quote') }}
            </span>
        </div>

        <div x-show="Number(cartState?.summary?.discount ?? {{ (float) ($resolvedSummary['discount'] ?? 0) }}) > 0" x-cloak class="flex items-center justify-between gap-4 text-emerald-700">
            <span x-text="discountLabel()">{{ $resolvedCouponCode !== '' ? 'Discount ('.$resolvedCouponCode.')' : 'Discount' }}</span>
            <span class="font-semibold" x-text="`- ${formatMoney(cartState?.summary?.discount ?? 0)}`">- ${{ number_format((float) ($resolvedSummary['discount'] ?? 0), 2) }}</span>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <div class="flex items-center justify-between gap-4">
                <span class="text-lg font-bold text-gray-900">Total</span>
                <span class="text-right text-2xl font-bold text-gray-900" x-text="requiresManualQuote() ? '--' : (cartState?.summary?.total !== null && cartState?.summary?.total !== undefined ? formatMoney(cartState.summary.total) : 'Unavailable')">
                    {{ $requiresManualQuote ? '--' : ($resolvedSummary['total'] !== null ? '$'.number_format((float) $resolvedSummary['total'], 2) : 'Unavailable') }}
                </span>
            </div>
            <div class="mt-2 flex items-center justify-between gap-4 text-sm text-gray-500">
                <span>GST Included</span>
                <span x-text="formatMoney(cartState?.summary?.gst || 0)">${{ number_format((float) ($resolvedSummary['gst'] ?? 0), 2) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-3">
        <form method="POST" action="{{ route('shop.cart.coupon.remove') }}" class="mt-3" x-show="currentCouponCode() !== ''" style="{{ $resolvedCouponCode !== '' ? '' : 'display:none;' }}" @submit.prevent="removeCouponCode($event)">
            @csrf
            <input type="hidden" name="shipping_country" value="{{ $resolvedShippingCountry }}" @if($resolvedShippingCountryBinding !== '') x-bind:value="{{ $resolvedShippingCountryBinding }}" @endif>
            <input type="hidden" name="return_to" value="{{ $resolvedReturnTo }}">
            <div class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <div>
                    <span x-text="currentCouponCode() !== '' ? 'Applied ' + 'voucher:' : ''">{{ $resolvedCouponCode !== '' ? 'Applied voucher:' : '' }}</span>
                    <strong x-text="currentCouponCode()">{{ $resolvedCouponCode }}</strong>
                </div>
                <button
                    type="submit"
                    class="border-0 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-emerald-100 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label="Remove voucher"
                    :disabled="couponBusy || isSubmitting"
                >
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('shop.cart.coupon.apply') }}" class="mt-3 space-y-3" x-show="currentCouponCode() === ''" style="{{ $resolvedCouponCode === '' ? '' : 'display:none;' }}" @submit.prevent="applyCouponCode($event)">
            @csrf
            <input type="hidden" name="shipping_country" value="{{ $resolvedShippingCountry }}" @if($resolvedShippingCountryBinding !== '') x-bind:value="{{ $resolvedShippingCountryBinding }}" @endif>
            <input type="hidden" name="return_to" value="{{ $resolvedReturnTo }}">
            <div class="text-xs text-slate-500">Add voucher</div>
            <div class="flex gap-2 items-center">
                <x-ui.input name="coupon_code" no-label="true" :value="old('coupon_code', '')" class="mb-0" x-model="couponDraft" x-bind:disabled="couponBusy || isSubmitting" />
                <x-ui.button type="submit" color="outline" class="text-xs px-2" x-bind:disabled="couponBusy || isSubmitting || couponDraft.trim() === ''">Apply</x-ui.button>
            </div>
        </form>

        @error('coupon_code')
        <div class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</div>
        @enderror
    <div x-show="couponError !== ''" style="display:none;" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="couponError"></div>
    </div>

    <div x-show="requiresManualQuote()" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <div>Due to the weight or size of the items in your cart, a manual quote for shipping is required.</div>
    </div>

    <div x-show="!requiresManualQuote() && checkoutBlockedReason() !== ''" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" x-text="checkoutBlockedReason()">
            {{ $blockedReason }}
    </div>

    @if($resolvedShowSubmitButton)
        <button
            type="submit"
            disabled
            class="{{ $submitButtonClasses }} mt-4"
            @if($resolvedFormId !== '')
                form="{{ $resolvedFormId }}"
            @endif
            {{ $resolvedSubmitButtonAttributes }}
        >
            <span x-show="!isSubmitting" x-cloak x-text="requiresManualQuote() ? 'Request Quote' : @js($resolvedSubmitLabel)">{{ $resolvedSubmitLabel }}</span>
            <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-2">
                <span class="altcha-inline-spinner" aria-hidden="true"></span>
                <span x-text="requiresManualQuote() ? 'Requesting Quote...' : 'Processing...'">Processing...</span>
            </span>
        </button>
    @endif
</div>
