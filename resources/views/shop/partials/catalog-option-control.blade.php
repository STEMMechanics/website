@php
    $variants = $product->purchasableVariants();
    $chooserHeading = $product->isDigital() ? 'Choose a licence' : 'Choose a variant';
    $chooserIntro = $product->isDigital()
        ? 'Pick the licence that matches how the files will be used.'
        : 'Choose the variant you want and add it straight to your cart.';
    $availabilityLabel = function ($variant = null) use ($product): string {
        if ($product->isDigital()) {
            return 'Instant download after checkout';
        }

        if ($product->isPreorder($variant)) {
            $selectionPreorderEstimate = $product->preorderShippingEstimateLabel('F jS, Y', $variant);

            return $selectionPreorderEstimate
                ? 'Pre-order. Estimated shipping '.$selectionPreorderEstimate
                : 'Pre-order available';
        }

        if ($product->allowsBackorder($variant)) {
            $selectionBackorderEstimate = $product->backorderShippingEstimateLabel('F jS, Y', $variant);
            $delayedLabel = $selectionBackorderEstimate ? 'More expected '.$selectionBackorderEstimate : 'More coming soon';
            $actualInventory = $product->availableInventory($variant);

            if ($actualInventory === null) {
                return 'Available now. '.$delayedLabel;
            }

            if ($actualInventory > 0) {
                return $actualInventory.' available now. '.$delayedLabel;
            }

            return 'Available to order. '.$delayedLabel;
        }

        $inventory = $product->availableInventoryForPurchase($variant);
        if ($inventory === null) {
            return $product->isSelectionPurchasable($variant) ? 'In stock' : 'Out of stock';
        }

        return $inventory > 0 ? $inventory.' in stock' : 'Out of stock';
    };
    $optionPayload = collect([
        [
            'key' => 'base',
            'variant_id' => null,
            'name' => $product->baseOptionName(),
            'description' => $product->baseOptionDescription(),
            'price_label' => $product->priceLabel(),
            'compare_at_price_label' => $product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase(),
            'is_in_stock' => $product->isSelectionPurchasable(),
            'is_preorder' => $product->isPreorder(),
            'preorder_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS, Y'),
            'availability_label' => $availabilityLabel(),
        ],
    ])
        ->concat($variants->map(fn ($variant) => [
            'key' => 'variant:'.$variant->id,
            'variant_id' => $variant->id,
            'name' => $product->variantDisplayName($variant),
            'description' => trim((string) ($variant->description ?? '')) ?: null,
            'price_label' => $product->priceLabel($variant),
            'compare_at_price_label' => $variant->effectiveCompareAtPrice() !== null ? '$'.number_format((float) $variant->effectiveCompareAtPrice(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase($variant),
            'is_in_stock' => $product->isSelectionPurchasable($variant),
            'is_preorder' => $product->isPreorder($variant),
            'preorder_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS, Y', $variant),
            'availability_label' => $availabilityLabel($variant),
        ]))
        ->values()
        ->all();
@endphp

<div
    class="shop-catalog-option-control"
    x-data="{
        dialogOpen: false,
        options: @js($optionPayload),
        busyCartLineKey: null,
        formError: '',
        productId: {{ $product->id }},
        productTitle: @js($product->title),
        cartState: @js($cartPayload),
        lineKeyForOption(option) {
            return `${this.productId}:${option?.variant_id ?? 0}`;
        },
        cartLineForOption(option) {
            if (!option) {
                return null;
            }

            return (this.cartState?.lines || []).find((line) => String(line.key) === String(this.lineKeyForOption(option))) || null;
        },
        cartQuantityForOption(option) {
            return Number(this.cartLineForOption(option)?.quantity || 0);
        },
        maxAddQuantityForOption(option) {
            if (!option || !option.is_in_stock) {
                return 0;
            }

            if (option.inventory_quantity !== null) {
                return Math.max(0, Number(option.inventory_quantity) - this.cartQuantityForOption(option));
            }

            return Math.max(0, 99 - this.cartQuantityForOption(option));
        },
        optionMaxQuantity(option) {
            if (!option) {
                return 1;
            }

            const lineMax = Number(this.cartLineForOption(option)?.max_quantity || 0);
            if (lineMax > 0) {
                return lineMax;
            }

            if (option.inventory_quantity !== null) {
                return Math.max(1, Number(option.inventory_quantity));
            }

            return 99;
        },
        optionCanAdd(option) {
            return !!option && !!option.is_in_stock && this.maxAddQuantityForOption(option) >= 1;
        },
        optionLimitMessage(option) {
            if (this.optionCanAdd(option)) {
                return '';
            }

            if (!option?.is_in_stock) {
                return 'This option is currently unavailable.';
            }

            if (this.cartQuantityForOption(option) > 0 && this.maxAddQuantityForOption(option) <= 0) {
                return 'The maximum available quantity for this option is already in your cart.';
            }

            return '';
        },
        selectionIsPreorder(option) {
            return Boolean(option?.is_preorder);
        },
        selectionPreorderEstimate(option) {
            return String(option?.preorder_shipping_estimate || '');
        },
        preorderItemTitle(option) {
            return option?.name
                ? `${this.productTitle} - ${option.name}`
                : this.productTitle;
        },
        openDialog() {
            if (this.busyCartLineKey) {
                return;
            }

            this.formError = '';
            this.dialogOpen = true;
        },
        closeDialog() {
            this.formError = '';
            this.dialogOpen = false;
        },
        async submitCartForm(form, lineKey) {
            if (this.busyCartLineKey || !(form instanceof HTMLFormElement)) {
                return;
            }

            if (!window.SM?.shopCart) {
                form.submit();
                return;
            }

            this.busyCartLineKey = lineKey;

            try {
                await window.SM.shopCart.submitAddForm(form, {
                    addedLineKey: lineKey,
                });
            } finally {
                this.busyCartLineKey = null;
            }
        },
        async handleOptionAddToCart(form, option) {
            if (!(form instanceof HTMLFormElement) || !option) {
                return;
            }

            if (!this.optionCanAdd(option)) {
                this.formError = this.optionLimitMessage(option) || 'That option is currently unavailable.';
                return;
            }

            try {
                this.formError = '';

                if (this.selectionIsPreorder(option) && window.SM?.shopCart?.confirmPreorder) {
                    const confirmed = await window.SM.shopCart.confirmPreorder({
                        itemTitle: this.preorderItemTitle(option),
                        shippingEstimate: this.selectionPreorderEstimate(option),
                        confirmText: 'Add to cart',
                    });

                    if (!confirmed) {
                        return;
                    }

                    window.SM.shopCart.setFormInput(form, 'preorder_acknowledged', '1');
                }

                await this.submitCartForm(form, this.lineKeyForOption(option));
            } catch (_error) {
            }
        },
        async changeOptionCartQuantity(option, nextQuantity) {
            if (!option || this.busyCartLineKey || !window.SM?.shopCart) {
                return;
            }

            const lineKey = this.lineKeyForOption(option);
            const maxQuantity = this.optionMaxQuantity(option);
            const resolvedQuantity = window.SM.toBoundedInt(nextQuantity, {
                min: 0,
                max: maxQuantity,
                allowNull: false,
            });
            const removedTitle = this.cartLineForOption(option)?.display_title || this.preorderItemTitle(option);
            this.busyCartLineKey = lineKey;

            try {
                if (resolvedQuantity <= 0) {
                    await window.SM.shopCart.removeLine(lineKey, {
                        shippingCountry: this.cartState?.shipping_country || 'Australia',
                        showNotice: false,
                    });
                    if (typeof window.SM.alert === 'function') {
                        window.SM.alert('Removed from cart', `${removedTitle} has been removed from your cart.`, 'danger');
                    }
                    return;
                }

                await window.SM.shopCart.updateQuantity(lineKey, resolvedQuantity, {
                    max: maxQuantity,
                    shippingCountry: this.cartState?.shipping_country || 'Australia',
                    showNotice: false,
                });
            } finally {
                this.busyCartLineKey = null;
            }
        },
        init() {
            if (!window.SM?.shopCart) {
                return;
            }

            const currentState = typeof window.SM.shopCart.getState === 'function'
                ? window.SM.shopCart.getState()
                : null;

            if (currentState && typeof currentState === 'object') {
                this.cartState = currentState;
            }

            if (typeof window.SM.shopCart.subscribe === 'function') {
                window.SM.shopCart.subscribe((cart) => {
                    this.cartState = cart;
                });
            }
        },
    }"
>
    <x-ui.button
        type="button"
        color="primary"
        class="shop-product-card-action-link w-full !px-5"
        x-bind:disabled="Boolean(busyCartLineKey)"
        @click="openDialog()"
    >
        Add to Cart
    </x-ui.button>

    <noscript>
        <x-ui.button type="link" href="{{ route('shop.product.show', $product) }}" class="shop-product-card-action-link w-full !px-5">
            View Product
        </x-ui.button>
    </noscript>

    <template x-teleport="body">
        <div
            x-show="dialogOpen"
            x-cloak
            class="fixed inset-0 z-[280] flex items-end justify-center bg-slate-950/55 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="shop-catalog-option-dialog-title-{{ $product->id }}"
            @click.self="closeDialog()"
            @keydown.escape.window="if (dialogOpen) closeDialog()"
        >
            <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <div class="border-b border-gray-200 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="shop-catalog-option-dialog-title-{{ $product->id }}" class="text-xl font-bold text-gray-900">{{ $chooserHeading }}</h2>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $product->title }}</p>
                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $chooserIntro }}</p>
                        </div>
                        <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeDialog()" aria-label="Close chooser">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="space-y-4 overflow-y-auto px-6 py-5">
                        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-show="formError" x-cloak x-text="formError"></div>

                        <div class="space-y-3">
                            <template x-for="option in options" :key="option.key">
                                <div
                                    class="rounded-2xl border border-sky-100 bg-sky-50/40 px-4 py-4"
                                    :class="cartQuantityForOption(option) > 0 ? 'border-sky-300 bg-sky-50 shadow-sm' : (!option.is_in_stock ? 'opacity-70' : '')"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="text-base font-semibold text-gray-900" x-text="option.name"></div>
                                            <div class="mt-1 text-sm leading-6 text-gray-600" x-show="option.description" x-cloak x-text="option.description"></div>
                                        </div>
                                        <div class="shrink-0 text-left sm:text-right">
                                            <div class="text-base font-semibold text-gray-900" x-text="option.price_label"></div>
                                            <div class="text-xs text-gray-400 line-through" x-show="option.compare_at_price_label" x-cloak x-text="option.compare_at_price_label"></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-4">
                                        <div class="text-xs font-medium text-sky-700" x-text="option.availability_label"></div>

                                        <form
                                            method="POST"
                                            action="{{ route('shop.cart.add', $product) }}"
                                            class="m-0 w-38"
                                            @submit.prevent="handleOptionAddToCart($event.target, option)"
                                            x-show="cartQuantityForOption(option) <= 0"
                                        >
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="product_variant_id" :value="option.variant_id ?? ''">
                                            <input type="hidden" name="quantity" value="1">
                                            <x-ui.button type="submit" color="primary" class="w-full" x-bind:disabled="!optionCanAdd(option) || busyCartLineKey === lineKeyForOption(option)">
                                                <span x-show="option.is_in_stock && busyCartLineKey !== lineKeyForOption(option)" x-text="option.is_preorder ? 'Pre-order Now' : 'Add to Cart'">Add to Cart</span>
                                                <span x-show="option.is_in_stock && busyCartLineKey === lineKeyForOption(option)" x-cloak>Adding...</span>
                                                <span x-show="!option.is_in_stock" x-cloak>Sold out</span>
                                            </x-ui.button>
                                        </form>

                                        <div x-show="cartQuantityForOption(option) > 0" x-cloak>
                                            <div class="w-38 shop-catalog-stepper flex items-center gap-2 rounded border border-gray-300 bg-white">
                                                <button
                                                    type="button"
                                                    class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-r-gray-300 border-r text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                    :disabled="busyCartLineKey === lineKeyForOption(option)"
                                                    @click="changeOptionCartQuantity(option, cartQuantityForOption(option) - 1)"
                                                >-</button>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    :max="optionMaxQuantity(option)"
                                                    :value="cartQuantityForOption(option)"
                                                    class="shop-catalog-stepper-input h-9 min-w-14 p-1 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                                    :disabled="busyCartLineKey === lineKeyForOption(option)"
                                                    @change="changeOptionCartQuantity(option, $event.target.value)"
                                                />
                                                <button
                                                    type="button"
                                                    class="shop-catalog-stepper-button inline-flex h-9 w-9 items-center justify-center p-1 border-l-gray-300 border-l text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                    :disabled="busyCartLineKey === lineKeyForOption(option) || cartQuantityForOption(option) >= optionMaxQuantity(option)"
                                                    @click="changeOptionCartQuantity(option, cartQuantityForOption(option) + 1)"
                                                >+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-xs text-gray-500" x-show="optionLimitMessage(option)" x-cloak x-text="optionLimitMessage(option)"></div>
                                </div>
                            </template>
                        </div>

                        @error('product_variant_id')
                            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('preorder_acknowledged')
                            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border-t border-gray-200 px-6 py-4">
                        <div class="flex justify-end">
                            <x-ui.button type="button" color="outline" @click="closeDialog()">Close</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
