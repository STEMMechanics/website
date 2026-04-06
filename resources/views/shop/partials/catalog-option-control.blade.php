@php
    $variants = $product->purchasableVariants();
    $chooserHeading = $product->isDigital() ? 'Choose a licence' : 'Choose a variant';
    $availabilityLabel = fn ($variant = null): string => $product->availabilityLabel('F jS', $variant);
    $baseOptionSku = trim((string) ($product->sku ?? ''));
    if ($baseOptionSku === '') {
        $baseOptionSku = strtoupper(\Illuminate\Support\Str::slug((string) ($product->slug ?: $product->title), '-'));
    }
    $optionPayload = collect([
        [
            'key' => 'base',
            'input_value' => '',
            'variant_id' => null,
            'name' => $product->baseOptionName(),
            'sku' => $baseOptionSku !== '' ? $baseOptionSku : null,
            'description' => $product->baseOptionDescription(),
            'price_label' => $product->priceLabel(),
            'compare_at_price_label' => $product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase(),
            'is_in_stock' => $product->isSelectionPurchasable(),
            'availability_tone' => $product->availabilityTone(),
            'availability_label' => $availabilityLabel(),
        ],
    ])
        ->concat($variants->map(fn ($variant) => [
            'key' => 'variant:'.$variant->id,
            'input_value' => (string) $variant->id,
            'variant_id' => $variant->id,
            'name' => $product->variantDisplayName($variant),
            'sku' => trim((string) ($variant->sku ?? '')) ?: null,
            'description' => trim((string) ($variant->description ?? '')) ?: null,
            'price_label' => $product->priceLabel($variant),
            'compare_at_price_label' => $variant->effectiveCompareAtPrice() !== null ? '$'.number_format((float) $variant->effectiveCompareAtPrice(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase($variant),
            'is_in_stock' => $product->isSelectionPurchasable($variant),
            'availability_tone' => $product->availabilityTone($variant),
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
        selectedVariantId: @js((string) data_get($optionPayload, '0.input_value', '')),
        variantMenuOpen: false,
        busyCartLineKey: null,
        formError: '',
        productId: {{ $product->id }},
        productTitle: @js($product->title),
        cartState: @js($cartPayload),
        get selectedOption() {
            return this.options.find((option) => String(option.input_value ?? '') === String(this.selectedVariantId ?? '')) || null;
        },
        lineKeyForOption(option) {
            return `${this.productId}:${option?.variant_id ?? 0}`;
        },
        activeLineKey() {
            return this.lineKeyForOption(this.selectedOption);
        },
        cartLine() {
            return this.cartLineForOption(this.selectedOption);
        },
        cartQuantity() {
            return Number(this.cartLine()?.quantity || 0);
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
        cartMaxQuantity() {
            return this.optionMaxQuantity(this.selectedOption);
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
        canAddSelection() {
            return this.optionCanAdd(this.selectedOption);
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
        selectionLimitMessage() {
            return this.optionLimitMessage(this.selectedOption);
        },
        selectionButtonLabel() {
            return this.selectedOption?.name || @js($chooserHeading);
        },
        selectionButtonMeta() {
            return this.selectedOption?.availability_label || '';
        },
        selectionButtonTone() {
            return this.selectedOption?.availability_tone || 'neutral';
        },
        selectionButtonSku() {
            return this.selectedOption?.sku || '';
        },
        selectionButtonSKU() {
            return this.selectionButtonSku();
        },
        toggleVariantMenu() {
            this.variantMenuOpen = !this.variantMenuOpen;
        },
        closeVariantMenu() {
            this.variantMenuOpen = false;
        },
        chooseOption(value) {
            this.selectedVariantId = value;
            this.formError = '';
            this.variantMenuOpen = false;
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
            this.variantMenuOpen = false;
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
        async handleAddToCart(form) {
            if (!(form instanceof HTMLFormElement) || !this.selectedOption) {
                return;
            }

            if (!this.canAddSelection()) {
                this.formError = this.selectionLimitMessage() || 'That option is currently unavailable.';
                return;
            }

            try {
                this.formError = '';

                await this.submitCartForm(form, this.activeLineKey());
            } catch (_error) {
            }
        },
        async changeCartQuantity(nextQuantity) {
            if (!this.selectedOption || this.busyCartLineKey || !window.SM?.shopCart) {
                return;
            }

            const lineKey = this.activeLineKey();
            const maxQuantity = this.cartMaxQuantity();
            const resolvedQuantity = window.SM.toBoundedInt(nextQuantity, {
                min: 0,
                max: maxQuantity,
                allowNull: false,
            });
            const removedTitle = this.cartLine()?.display_title || (this.selectedOption?.name ? `${this.productTitle} - ${this.selectedOption.name}` : this.productTitle);
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
        class="shop-product-card-action-link w-full px-5!"
        x-bind:disabled="Boolean(busyCartLineKey)"
        @click="openDialog()"
    >
        Add to Cart
    </x-ui.button>

    <noscript>
        <x-ui.button type="link" href="{{ route('shop.product.show', $product) }}" class="shop-product-card-action-link w-full px-5!">
            View Product
        </x-ui.button>
    </noscript>

    <template x-teleport="body">
        <div
            x-show="dialogOpen"
            x-cloak
            class="fixed inset-0 z-[280] flex items-end justify-center bg-slate-950/55 p-4 sm:items-start sm:pt-[12vh]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="shop-catalog-option-dialog-title-{{ $product->id }}"
            @click.self="closeDialog()"
            @keydown.escape.window="if (dialogOpen) closeDialog()"
        >
            <div class="flex max-h-[calc(100dvh-2rem)] w-full max-w-2xl flex-col overflow-visible rounded bg-white shadow-2xl">
                <div class="border-b border-gray-200 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="shop-catalog-option-dialog-title-{{ $product->id }}" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xl font-bold text-gray-900">
                                <span>{{ $product->title }}</span>
                                <span class="text-gray-500 text-lg font-normal"> - {{ $chooserHeading }}</span>
                            </h2>
{{--                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $chooserIntro }}</p>--}}
                        </div>
                        <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeDialog()" aria-label="Close chooser">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="space-y-4 overflow-visible px-6 py-5">
                        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-show="formError" x-cloak x-text="formError"></div>

                        <div class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-stretch">
                                <div class="relative" @click.outside="closeVariantMenu()">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-4 rounded border border-gray-300 bg-gray-50/40 px-4 py-3 text-left transition hover:border-sky-300 hover:bg-sky-50"
                                        @click="toggleVariantMenu()"
                                        :aria-expanded="variantMenuOpen ? 'true' : 'false'"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2 text-sm">
                                                <span class="truncate font-semibold text-gray-900" x-text="selectionButtonLabel()">{{ $chooserHeading }}</span>
                                                <span class="text-gray-400">-</span>
                                                <span class="inline-flex items-center gap-1.5 truncate text-xs font-medium" :class="{
                                                    'text-red-700': selectionButtonTone() === 'danger',
                                                    'text-amber-700': selectionButtonTone() === 'warning',
                                                    'text-emerald-700': selectionButtonTone() === 'success',
                                                    'text-sky-700': selectionButtonTone() === 'neutral',
                                                }">
                                                    <i x-show="selectionButtonTone() === 'danger'" class="fa-solid fa-circle-xmark text-[0.75em]" x-cloak></i>
                                                    <i x-show="selectionButtonTone() === 'warning'" class="fa-solid fa-triangle-exclamation text-[0.75em]" x-cloak></i>
                                                    <span x-text="selectionButtonMeta()"></span>
                                                </span>
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-xs font-medium text-gray-400" x-show="selectionButtonSku()" x-cloak x-text="selectionButtonSku()"></span>
                                        <i class="fa-solid fa-chevron-down shrink-0 text-xs text-gray-500 transition" :class="variantMenuOpen ? 'rotate-180' : ''"></i>
                                    </button>

                                    <div
                                        x-show="variantMenuOpen"
                                        x-cloak
                                        x-transition.opacity
                                        class="absolute left-0 right-0 top-[calc(75%)] z-30 overflow-hidden rounded border border-gray-300 bg-white shadow-2xl"
                                    >
                                        <div class="max-h-80 overflow-y-auto py-2">
                                            @foreach($optionPayload as $option)
                                                <button
                                                    type="button"
                                                    x-data="{ option: @js($option) }"
                                                    class="flex w-full items-start justify-between gap-4 px-4 py-3 text-left transition hover:bg-sky-50"
                                                    :class="String(selectedVariantId ?? '') === String(option.input_value ?? '') ? 'bg-sky-50' : ''"
                                                    @click="chooseOption(String(@js((string) ($option['input_value'] ?? ''))))"
                                                >
                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex items-center gap-2 text-sm">
                                                            <span class="truncate font-semibold text-gray-900" x-text="option.name">{{ $option['name'] }}</span>
                                                            <span class="text-gray-400">-</span>
                                                            <span class="inline-flex items-center gap-1.5 truncate text-xs font-medium" :class="{
                                                                'text-red-700': option.availability_tone === 'danger',
                                                                'text-amber-700': option.availability_tone === 'warning',
                                                                'text-emerald-700': option.availability_tone === 'success',
                                                                'text-sky-700': option.availability_tone === 'neutral',
                                                            }">
                                                                <i x-show="option.availability_tone === 'danger'" class="fa-solid fa-circle-xmark text-[0.75em]" x-cloak></i>
                                                                <i x-show="option.availability_tone === 'warning'" class="fa-solid fa-triangle-exclamation text-[0.75em]" x-cloak></i>
                                                                <span x-text="option.availability_label">{{ $option['availability_label'] }}</span>
                                                            </span>
                                                        </span>
                                                    @if(!empty($option['description']))
                                                        <span class="mt-1 block text-xs leading-5 text-gray-500" x-show="option.description" x-cloak x-text="option.description">{{ $option['description'] }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="shrink-0 text-right">
                                                        @if(!empty($option['sku']))
                                                            <span class="block text-xs font-medium text-gray-400" x-text="option.sku">{{ $option['sku'] }}</span>
                                                        @endif
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('shop.cart.add', $product) }}"
                                    class="m-0 sm:min-w-40"
                                    @submit.prevent="handleAddToCart($event.target)"
                                    x-show="cartQuantity() <= 0"
                                >
                                    @csrf
                                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                    <input type="hidden" name="product_variant_id" :value="selectedOption?.variant_id ?? ''">
                                    <input type="hidden" name="quantity" value="1">
                                    <x-ui.button type="submit" color="primary" class="w-full sm:h-full" x-bind:disabled="!canAddSelection() || busyCartLineKey === activeLineKey()">
                                        <span x-show="canAddSelection() && busyCartLineKey !== activeLineKey()">Add to Cart</span>
                                        <span x-show="canAddSelection() && busyCartLineKey === activeLineKey()" x-cloak>Adding...</span>
                                        <span x-show="!canAddSelection()" x-cloak>Sold out</span>
                                    </x-ui.button>
                                </form>

                                <div x-show="cartQuantity() > 0" x-cloak class="sm:min-w-40 flex items-center">
                                    <div>
                                        <div class="shop-catalog-stepper flex h-full items-center gap-2 rounded border border-gray-300 bg-white">
                                            <button
                                                type="button"
                                                class="shop-catalog-stepper-button inline-flex h-9 w-9 p-1 items-center justify-center border-r-gray-300 border-r text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === activeLineKey()"
                                                @click="changeCartQuantity(cartQuantity() - 1)"
                                            >-</button>
                                            <input
                                                type="number"
                                                min="0"
                                                :max="cartMaxQuantity()"
                                                :value="cartQuantity()"
                                                class="shop-catalog-stepper-input h-9 min-w-14 p-1 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                                :disabled="busyCartLineKey === activeLineKey()"
                                                @change="changeCartQuantity($event.target.value)"
                                            />
                                            <button
                                                type="button"
                                                class="shop-catalog-stepper-button inline-flex h-9 w-9 items-center justify-center p-1 border-l-gray-300 border-l text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="busyCartLineKey === activeLineKey() || cartQuantity() >= cartMaxQuantity()"
                                                @click="changeCartQuantity(cartQuantity() + 1)"
                                            >+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @error('product_variant_id')
                            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border-t border-gray-200 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold" x-text="selectedOption?.price_label"></span>
                            <x-ui.button type="button" color="outline" @click="closeDialog()">Close</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
