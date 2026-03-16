@php
    $variants = $product->purchasableVariants();
    $hasOptionChoices = $product->hasOptionChoices();
    $oldInput = session()->getOldInput();
    $hasOldVariantInput = is_array($oldInput) && array_key_exists('product_variant_id', $oldInput);
    $oldVariantId = trim((string) old('product_variant_id', ''));
    $defaultOptionInput = $hasOldVariantInput
        ? $oldVariantId
        : null;
    $defaultAddQuantity = max(1, (int) old('quantity', 1));
    $preorderEstimate = $product->preorderShippingEstimateLabel('F jS, Y');
    $chooserHeading = $product->isDigital() ? 'Choose a licence' : 'Choose a variant';
    $chooserErrorMessage = $product->isDigital()
        ? 'Choose a licence before adding this item to your cart.'
        : 'Choose a variant before adding this item to your cart.';
    $optionCount = $product->optionChoiceCount();
    $variantCountLabel = $optionCount.' '.($product->isDigital()
        ? 'tier'.($optionCount === 1 ? '' : 's')
        : 'option'.($optionCount === 1 ? '' : 's'));
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
    $multiOptionStockLabel = $hasOptionChoices
        ? ($product->isDigital()
            ? $variantCountLabel.' available. Choose a licence when adding to cart.'
            : $variantCountLabel.' available. Choose an option when adding to cart.')
        : $availabilityLabel();
    $optionPayload = collect();
    if ($hasOptionChoices) {
        $optionPayload->push([
            'key' => 'base',
            'input_value' => '',
            'variant_id' => null,
            'name' => $product->baseOptionName(),
            'description' => $product->baseOptionDescription(),
            'price_label' => $product->priceLabel(),
            'compare_at_price_label' => $product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase(),
            'actual_inventory_quantity' => $product->availableInventory(),
            'is_in_stock' => $product->isSelectionPurchasable(),
            'is_preorder' => $product->isPreorder(),
            'allows_backorder' => $product->allowsBackorder(),
            'preorder_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS, Y'),
            'availability_label' => $availabilityLabel(),
        ]);
    }

    $optionPayload = $optionPayload
        ->concat($variants->map(fn ($variant) => [
            'key' => 'variant:'.$variant->id,
            'input_value' => (string) $variant->id,
            'variant_id' => $variant->id,
            'name' => $product->variantDisplayName($variant),
            'description' => trim((string) ($variant->description ?? '')) ?: null,
            'price_label' => $product->priceLabel($variant),
            'compare_at_price_label' => $variant->effectiveCompareAtPrice() !== null ? '$'.number_format((float) $variant->effectiveCompareAtPrice(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase($variant),
            'actual_inventory_quantity' => $product->availableInventory($variant),
            'is_in_stock' => $product->isSelectionPurchasable($variant),
            'is_preorder' => $product->isPreorder($variant),
            'allows_backorder' => $product->allowsBackorder($variant),
            'preorder_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS, Y', $variant),
            'availability_label' => $availabilityLabel($variant),
        ]))
        ->values()
        ->all();
    $rawDescription = trim((string) $product->description);
    $productDescriptionHtml = '';
    if ($rawDescription !== '') {
        $productDescriptionHtml = $rawDescription !== strip_tags($rawDescription)
            ? $rawDescription
            : nl2br(e($rawDescription));
    }
@endphp
<x-layout
        :title="$product->title"
        :description="$product->short_description ?: strip_tags((string) $product->description)"
        :canonical="route('shop.product.show', $product)"
>
    <x-mast backRoute="shop.index" backTitle="Store">{{ $product->title }}</x-mast>

    <x-container class="py-8">
        <div
                class="space-y-8"
                x-data="{
                options: @js($optionPayload),
                selectedVariantId: @js($defaultOptionInput),
                addQuantity: {{ $defaultAddQuantity }},
                optionDraftQuantities: @js($hasOptionChoices
                    ? collect($optionPayload)->mapWithKeys(fn ($option) => [($option['key'] ?? 'base') => $defaultAddQuantity])->all()
                    : []),
                cartState: @js($cartPayload),
                busyCartLineKey: null,
                formError: @js($errors->first('product_variant_id')),
                baseIsPreorder: @js($product->isPreorder()),
                productIsDigital: @js($product->isDigital()),
                productTitle: @js($product->title),
                basePreorderShippingEstimate: @js($preorderEstimate),
                priceRangeLabel: @js($product->priceRangeLabel()),
                basePriceLabel: @js($product->priceLabel()),
                baseCompareAtPriceLabel: @js($product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null),
                baseInventoryQuantity: @js($product->availableInventoryForPurchase()),
                baseIsPurchasable: @js($product->isPurchasable()),
                baseStockLabel: @js($availabilityLabel()),
                multiOptionStockLabel: @js($multiOptionStockLabel),
                chooserErrorMessage: @js($chooserErrorMessage),
                setCartState(cart) {
                    if (!cart || typeof cart !== 'object') {
                        return;
                    }

                    this.cartState = cart;
                    this.syncAddQuantity();
                    this.syncOptionDraftQuantities();
                },
                get selectedOption() {
                    return this.options.find((option) => String(option.input_value ?? '') === String(this.selectedVariantId ?? '')) || null;
                },
                normalizeQuantity(value, min = 0, max = 99) {
                    const cleaned = String(value ?? '').replace(/[^0-9]/g, '');
                    const parsed = Number.parseInt(cleaned, 10);
                    if (!Number.isFinite(parsed)) {
                        return min;
                    }

                    return Math.max(min, Math.min(max, parsed));
                },
                currentPriceLabel() {
                    return this.options.length > 0 ? this.priceRangeLabel : this.basePriceLabel;
                },
                currentCompareAtPriceLabel() {
                    return this.options.length > 0 ? null : this.baseCompareAtPriceLabel;
                },
                currentStockLabel() {
                    return this.options.length > 0 ? this.multiOptionStockLabel : this.baseStockLabel;
                },
                currentSelectionDescription() {
                    if (this.selectedOption) {
                        return this.selectedOption.description || '';
                    }

                    return '';
                },
                selectionIsPreorder(option = null) {
                    if (this.options.length === 0) {
                        return this.baseIsPreorder;
                    }

                    return Boolean((option || this.selectedOption)?.is_preorder);
                },
                selectionPreorderEstimate(option = null) {
                    if (this.options.length === 0) {
                        return this.basePreorderShippingEstimate;
                    }

                    return (option || this.selectedOption)?.preorder_shipping_estimate || '';
                },
                preorderItemTitle(option = null) {
                    const selection = option || this.selectedOption;

                    if (!selection || this.options.length === 0) {
                        return this.productTitle;
                    }

                    return selection.name ? `${this.productTitle} - ${selection.name}` : this.productTitle;
                },
                maxQuantity() {
                    if (this.selectedOption && this.selectedOption.inventory_quantity !== null) {
                        return Math.max(1, Number(this.selectedOption.inventory_quantity));
                    }

                    if (@js($product->availableInventoryForPurchase()) !== null) {
                        return Math.max(1, Number(@js($product->availableInventoryForPurchase())));
                    }

                    return 99;
                },
                activeLineKey() {
                    return `${@js($product->id)}:${this.selectedOption?.variant_id ?? 0}`;
                },
                cartLine() {
                    return (this.cartState?.lines || []).find((line) => String(line.key) === String(this.activeLineKey())) || null;
                },
                cartQuantity() {
                    return Number(this.cartLine()?.quantity || 0);
                },
                cartMaxQuantity() {
                    return Number(this.cartLine()?.max_quantity || this.maxQuantity());
                },
                maxAddQuantity() {
                    if (this.options.length === 0 && this.baseInventoryQuantity !== null) {
                        return Math.max(0, Number(this.baseInventoryQuantity) - this.cartQuantity());
                    }

                    if (this.options.length === 0) {
                        return this.baseIsPurchasable ? Math.max(0, 99 - this.cartQuantity()) : 0;
                    }

                    if (this.selectedOption && this.selectedOption.inventory_quantity !== null) {
                        return Math.max(0, Number(this.selectedOption.inventory_quantity) - this.cartQuantity());
                    }

                    return Math.max(0, 99 - this.cartQuantity());
                },
                canAddSelection() {
                    if (this.options.length === 0) {
                        return this.baseIsPurchasable && this.maxAddQuantity() >= 1;
                    }

                    if (this.options.length > 0 && !this.selectedOption) {
                        return false;
                    }

                    if (this.selectedOption && !this.selectedOption.is_in_stock) {
                        return false;
                    }

                    return this.maxAddQuantity() >= 1;
                },
                selectionLimitMessage() {
                    if (this.canAddSelection()) {
                        return '';
                    }

                    if (this.cartQuantity() > 0 && this.maxAddQuantity() <= 0) {
                        return 'The maximum available quantity for this selection is already in your cart.';
                    }

                    return '';
                },
                optionByInputValue(value) {
                    return this.options.find((option) => String(option.input_value ?? '') === String(value ?? '')) || null;
                },
                lineKeyForOption(option) {
                    return `${@js($product->id)}:${option?.variant_id ?? 0}`;
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
                    if (!option) {
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

                    if (this.cartQuantityForOption(option) > 0 && this.maxAddQuantityForOption(option) <= 0) {
                        return 'The maximum available quantity for this option is already in your cart.';
                    }

                    return option?.is_in_stock ? '' : 'This option is currently unavailable.';
                },
                optionDraftQuantity(option) {
                    const key = String(option?.key || 'base');
                    const max = Math.max(1, this.maxAddQuantityForOption(option));

                    return this.normalizeQuantity(this.optionDraftQuantities[key] ?? 1, 1, max);
                },
                setOptionDraftQuantity(option, value) {
                    if (!option) {
                        return;
                    }

                    const key = String(option.key || 'base');
                    const max = Math.max(1, this.maxAddQuantityForOption(option));
                    this.optionDraftQuantities[key] = this.normalizeQuantity(value, 1, max);
                },
                syncOptionDraftQuantities() {
                    if (!Array.isArray(this.options) || this.options.length === 0) {
                        return;
                    }

                    this.options.forEach((option) => {
                        this.setOptionDraftQuantity(option, this.optionDraftQuantity(option));
                    });
                },
                resolvedAddQuantity() {
                    return this.normalizeQuantity(this.addQuantity, 1, Math.max(1, this.maxAddQuantity()));
                },
                syncAddQuantity() {
                    const maxAddQuantity = this.maxAddQuantity();
                    if (maxAddQuantity <= 0) {
                        this.addQuantity = 1;
                        return;
                    }

                    this.addQuantity = this.normalizeQuantity(this.addQuantity, 1, maxAddQuantity);
                },
                async handleAddToCart(form) {
                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    if (this.options.length > 0 && !this.selectedOption) {
                        this.formError = this.chooserErrorMessage;
                        return;
                    }

                    if (!this.canAddSelection()) {
                        this.formError = this.selectionLimitMessage() || 'That selection is currently unavailable.';
                        return;
                    }

                    try {
                        this.formError = '';

                        if (this.selectionIsPreorder() && window.SM?.shopCart?.confirmPreorder) {
                            const confirmed = await window.SM.shopCart.confirmPreorder({
                                itemTitle: this.preorderItemTitle(),
                                shippingEstimate: this.selectionPreorderEstimate(),
                                confirmText: 'Add to cart',
                            });

                            if (!confirmed) {
                                return;
                            }

                            window.SM.shopCart.setFormInput(form, 'preorder_acknowledged', '1');
                        }

                        await this.submitCartForm(form, this.activeLineKey());
                    } catch (_error) {
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

                        this.setOptionDraftQuantity(option, 1);
                    } catch (_error) {
                    }
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
                            showAddSheet: true,
                            addedLineKey: lineKey,
                        });
                    } finally {
                        this.busyCartLineKey = null;
                    }
                },
                async removeOptionFromCart(option) {
                    if (!option || this.busyCartLineKey || !window.SM?.shopCart) {
                        return;
                    }

                    const lineKey = this.lineKeyForOption(option);
                    const removedTitle = this.cartLineForOption(option)?.display_title || @js($product->title);
                    this.busyCartLineKey = lineKey;

                    try {
                        await window.SM.shopCart.removeLine(lineKey, {
                            shippingCountry: this.cartState?.shipping_country || 'Australia',
                            showNotice: false,
                        });
                        if (typeof window.SM.alert === 'function') {
                            window.SM.alert('Removed from cart', `${removedTitle} has been removed from your cart.`, 'danger');
                        }
                    } finally {
                        this.busyCartLineKey = null;
                    }
                },
                async changeOptionCartQuantity(option, nextQuantity) {
                    if (!option || this.busyCartLineKey || !window.SM?.shopCart) {
                        return;
                    }

                    const lineKey = this.lineKeyForOption(option);
                    const maxQuantity = this.optionMaxQuantity(option);
                    const resolvedQuantity = this.normalizeQuantity(nextQuantity, 0, maxQuantity);
                    const removedTitle = this.cartLineForOption(option)?.display_title || @js($product->title);
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
                async changeCartQuantity(nextQuantity) {
                    if (this.busyCartLineKey || !window.SM?.shopCart) {
                        return;
                    }

                    const lineKey = this.activeLineKey();
                    const maxQuantity = this.cartMaxQuantity();
                    const resolvedQuantity = this.normalizeQuantity(nextQuantity, 0, maxQuantity);
                    const removedTitle = this.cartLine()?.display_title || @js($product->title);
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
                    this.syncAddQuantity();
                    this.syncOptionDraftQuantities();

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
            <div class="flex xgrid md:xgrid-cols-[minmax(0,1.08fr)_minmax(20rem,0.92fr)] lg:items-start rounded-3xl border border-gray-200 bg-white">
                <div class="space-y-4 w-full">
                    <div class="flex flex-col sm:flex-row-reverse flex-1">
                        <div class="flex-1">
                            <div class="overflow-hidden rounded-t-3xl sm:rounded-tr-3xl sm:rounded-bl-3xl sm:rounded-tl-none">
                                <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" class="max-h-[42rem] w-full object-cover" />
                            </div>
                        </div>
                        {{--                    @if($product->galleryMedia->isNotEmpty())--}}
                        {{--                        <div class="grid grid-cols-3 gap-3 md:grid-cols-4">--}}
                        {{--                            @foreach($product->galleryMedia as $media)--}}
                        {{--                                <a href="{{ $media->url }}" target="_blank" rel="noopener noreferrer" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-sky-300">--}}
                        {{--                                    <img src="{{ $media->thumbnail }}" alt="{{ $media->title }}" class="h-24 w-full object-cover" />--}}
                        {{--                                </a>--}}
                        {{--                            @endforeach--}}
                        {{--                        </div>--}}
                        {{--                    @endif--}}

                        <div class="p-6 flex-1">
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $product->title }}</h1>

                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                @if(!$hasOptionChoices && $product->isPreorder())
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold tracking-[0.16em] text-amber-800">Pre-order</span>
                                @endif
                                @if($product->isDigital())
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold tracking-[0.16em] text-emerald-800">
                                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower(\App\Models\Product::productTypeLabel((string) $product->product_type))) }}
                                    </span>
                                @endif
                                @if(trim((string) $product->category) !== '')
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ \Illuminate\Support\Str::ucfirst((string) $product->category) }}</span>
                                @endif
                                @if(trim((string) $product->sku) !== '')
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">SKU {{ $product->sku }}</span>
                                @endif
                                @if(!$product->isPurchasable())
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Out of stock</span>
                                @endif
                            </div>

                            <div class="mt-5">
                                <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
                                    <div class="text-3xl font-bold tracking-tight text-gray-900" x-text="currentPriceLabel()">{{ $product->priceRangeLabel() }}</div>
                                    <div
                                            x-show="currentCompareAtPriceLabel()"
                                            x-cloak
                                            class="pb-1 text-base text-gray-400 line-through"
                                            x-text="currentCompareAtPriceLabel()"
                                    >{{ $product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : '' }}</div>
                                </div>
                                <div class="mt-2 text-sm font-medium text-gray-600" x-text="currentStockLabel()">{{ $multiOptionStockLabel }}</div>
                            </div>

                            @if(!$hasOptionChoices && $product->isPreorder())
                                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                                    <div class="font-semibold text-amber-900">Pre-order item</div>
                                    @if($preorderEstimate)
                                        <div class="mt-1">Estimated shipping date: {{ $preorderEstimate }}</div>
                                    @endif
                                    <div class="mt-1">Orders containing this item will ship once it becomes available.</div>
                                </div>
                            @endif

                            @if($hasOptionChoices)
                                <div class="mt-6 space-y-3">
                                    <div class="mb-1 pl-1 text-sm font-medium text-gray-900">{{ $chooserHeading }}</div>

                                    @error('preorder_acknowledged')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    @error('product_variant_id')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    <div class="text-sm text-red-600" x-show="formError" x-cloak x-text="formError"></div>

                                    @foreach($optionPayload as $option)
                                        @php
                                            $optionInputValue = (string) ($option['input_value'] ?? '');
                                            $optionKey = (string) ($option['key'] ?? ($optionInputValue !== '' ? 'variant:'.$optionInputValue : 'base'));
                                        @endphp
                                        <div
                                                class="rounded-2xl border border-sky-100 bg-sky-50/40 px-4 py-4"
                                                x-data="{ option: @js($option) }"
                                                x-bind:class="cartQuantityForOption(option) > 0 ? 'border-sky-300 bg-sky-50 shadow-sm' : (!option.is_in_stock ? 'opacity-70' : '')"
                                        >
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0">
                                                    <div class="text-base font-semibold text-gray-900">{{ $option['name'] }}</div>
                                                    @if(!empty($option['description']))
                                                        <div class="mt-1 text-sm leading-6 text-gray-600">{{ $option['description'] }}</div>
                                                    @endif
                                                </div>
                                                <div class="shrink-0 text-left sm:text-right">
                                                    <div class="text-base font-semibold text-gray-900">{{ $option['price_label'] }}</div>
                                                    @if(!empty($option['compare_at_price_label']))
                                                        <div class="text-xs text-gray-400 line-through">{{ $option['compare_at_price_label'] }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex justify-between items-center mt-4">
                                                <div class="text-xs font-medium text-sky-700">
                                                    {{ $option['availability_label'] }}
                                                </div>

                                                <form
                                                        method="POST"
                                                        action="{{ route('shop.cart.add', $product) }}"
                                                        class="m-0 w-38"
                                                        @submit.prevent="handleOptionAddToCart($event.target, option)"
                                                        x-show="cartQuantityForOption(option) <= 0"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                                    <input type="hidden" name="product_variant_id" value="{{ $optionInputValue }}">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <x-ui.button type="submit" :color="$product->isPreorder() ? 'accent' : 'primary'" class="w-full" x-bind:disabled="!optionCanAdd(option) || busyCartLineKey === lineKeyForOption(option)">
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
                                                                id="product-quantity-{{ $optionKey }}"
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
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-6 space-y-3">
                                    <form
                                            method="POST"
                                            action="{{ route('shop.cart.add', $product) }}"
                                            class="m-0"
                                            x-on:submit.prevent="handleAddToCart($event.target)"
                                            x-show="cartQuantity() <= 0"
                                    >
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <x-ui.button type="submit" :color="$product->isPreorder() ? 'accent' : 'primary'" class="w-full sm:w-auto" x-bind:disabled="!canAddSelection() || busyCartLineKey === activeLineKey()">
                                            <span x-show="canAddSelection() && busyCartLineKey !== activeLineKey()" x-text="selectionIsPreorder() ? 'Pre-order Now' : 'Add to Cart'">{{ $product->isPreorder() ? 'Pre-order Now' : 'Add to Cart' }}</span>
                                            <span x-show="canAddSelection() && busyCartLineKey === activeLineKey()" x-cloak>Adding...</span>
                                            <span x-show="!canAddSelection()" x-cloak>Sold out</span>
                                        </x-ui.button>
                                    </form>

                                    <div x-show="cartQuantity() > 0" x-cloak>
                                        <div class="shop-catalog-stepper inline-flex items-center gap-2 rounded border border-gray-300 bg-white">
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

                                    @error('preorder_acknowledged')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    @error('product_variant_id')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    <div class="text-sm text-red-600" x-show="formError" x-cloak x-text="formError"></div>
                                    <div class="text-xs text-gray-500" x-show="selectionLimitMessage()" x-cloak x-text="selectionLimitMessage()"></div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <section class="px-6 pb-6">
                        @if($productDescriptionHtml !== '')
                            <article class="mt-4 content text-gray-700">
                                {!! $productDescriptionHtml !!}
                            </article>
                        @elseif(trim((string) $product->short_description) !== '')
                            <p class="mt-4 text-base leading-7 text-gray-700">{{ $product->short_description }}</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
