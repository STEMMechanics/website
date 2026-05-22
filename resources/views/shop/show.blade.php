@php
    $variants = $product->purchasableVariants();
    $hasOptionChoices = $product->hasOptionChoices();
    $oldInput = session()->getOldInput();
    $hasOldVariantInput = is_array($oldInput) && array_key_exists('product_variant_id', $oldInput);
    $oldVariantId = trim((string) old('product_variant_id', ''));
    $defaultOptionInput = $hasOldVariantInput
        ? $oldVariantId
        : ($hasOptionChoices ? '' : null);
    $defaultAddQuantity = max(1, (int) old('quantity', 1));
    $chooserHeading = $product->isDigital() ? 'Choose a licence' : 'Choose a variant';
    $chooserErrorMessage = $product->isDigital()
        ? 'Choose a licence before adding this item to your cart.'
        : 'Choose a variant before adding this item to your cart.';
    $optionCount = $product->optionChoiceCount();
    $variantCountLabel = $optionCount.' '.($product->isDigital()
        ? 'tier'.($optionCount === 1 ? '' : 's')
        : 'option'.($optionCount === 1 ? '' : 's'));
    $availabilityLabel = fn ($variant = null): string => $product->availabilityLabel('F jS', $variant);
    $availabilityTone = fn ($variant = null): string => $product->availabilityTone($variant);
    $baseOptionSku = trim((string) ($product->sku ?? ''));
    if ($baseOptionSku === '') {
        $baseOptionSku = strtoupper(\Illuminate\Support\Str::slug((string) ($product->slug ?: $product->title), '-'));
    }
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
            'sku' => $baseOptionSku !== '' ? $baseOptionSku : null,
            'description' => $product->baseOptionDescription(),
            'price_label' => $product->priceLabel(),
            'compare_at_price_label' => $product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null,
            'inventory_quantity' => $product->availableInventoryForPurchase(),
            'actual_inventory_quantity' => $product->availableInventory(),
            'is_in_stock' => $product->isSelectionPurchasable(),
            'allows_backorder' => $product->allowsBackorder(),
            'availability_tone' => $availabilityTone(),
            'availability_label' => $availabilityLabel(),
        ]);
    }

    $optionPayload = $optionPayload
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
            'actual_inventory_quantity' => $product->availableInventory($variant),
            'is_in_stock' => $product->isSelectionPurchasable($variant),
            'allows_backorder' => $product->allowsBackorder($variant),
            'availability_tone' => $availabilityTone($variant),
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

    @include('shop.partials.processing-pause-notice', [
        'notice' => $cartPayload['summary']['shipping_quote']['processing_pause_notice'] ?? null,
    ])

    <x-container class="py-8">
        <div
                class="space-y-8"
                x-data="{
                options: @js($optionPayload),
                selectedVariantId: @js($defaultOptionInput),
                variantMenuOpen: false,
                addQuantity: {{ $defaultAddQuantity }},
                optionDraftQuantities: @js($hasOptionChoices
                    ? collect($optionPayload)->mapWithKeys(fn ($option) => [($option['key'] ?? 'base') => $defaultAddQuantity])->all()
                    : []),
                cartState: @js($cartPayload),
                busyCartLineKey: null,
                formError: @js($errors->first('product_variant_id')),
                productIsDigital: @js($product->isDigital()),
                productTitle: @js($product->title),
                priceRangeLabel: @js($product->priceRangeLabel()),
                basePriceLabel: @js($product->priceLabel()),
                baseCompareAtPriceLabel: @js($product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null),
                baseInventoryQuantity: @js($product->availableInventoryForPurchase()),
                baseIsPurchasable: @js($product->isPurchasable()),
                baseStockTone: @js($availabilityTone()),
                baseStockLabel: @js($availabilityLabel()),
                multiOptionStockTone: @js($hasOptionChoices ? ($product->isDigital() ? 'success' : $availabilityTone()) : $availabilityTone()),
                multiOptionStockLabel: @js($multiOptionStockLabel),
                chooserHeading: @js($chooserHeading),
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
                    if (this.options.length > 0) {
                        return this.selectedOption?.price_label || this.priceRangeLabel;
                    }

                    return this.basePriceLabel;
                },
                currentCompareAtPriceLabel() {
                    if (this.options.length > 0) {
                        return this.selectedOption?.compare_at_price_label || null;
                    }

                    return this.baseCompareAtPriceLabel;
                },
                currentStockLabel() {
                    if (this.options.length > 0) {
                        return this.selectedOption?.availability_label || this.multiOptionStockLabel;
                    }

                    return this.baseStockLabel;
                },
                currentStockTone() {
                    if (this.options.length > 0) {
                        return this.selectedOption?.availability_tone || this.multiOptionStockTone;
                    }

                    return this.baseStockTone;
                },
                currentStockToneClass() {
                    const tone = this.currentStockTone();

                    if (tone === 'danger') {
                        return 'text-red-700';
                    }

                    if (tone === 'warning') {
                        return 'text-amber-700';
                    }

                    if (tone === 'success') {
                        return 'text-emerald-700';
                    }

                    return 'text-gray-600';
                },
                currentSelectionDescription() {
                    if (this.selectedOption) {
                        return this.selectedOption.description || '';
                    }

                    return '';
                },
                selectionButtonLabel() {
                    if (this.selectedOption) {
                        return this.selectedOption.name || this.chooserHeading;
                    }

                    return this.chooserHeading;
                },
                selectionButtonMeta() {
                    if (this.selectedOption) {
                        return this.selectedOption.availability_label || '';
                    }

                    return this.multiOptionStockLabel;
                },
                selectionButtonTone() {
                    if (this.selectedOption) {
                        return this.selectedOption.availability_tone || 'neutral';
                    }

                    return this.multiOptionStockTone;
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
                    this.syncAddQuantity();
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
                    if (this.options.length > 0 && !this.selectedOption) {
                        return `${@js($product->id)}:unselected`;
                    }

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
                <div class="w-full p-6 flow-root">
                    <div class="-ml-6 -mr-6 -mt-6 mb-6 overflow-hidden rounded-t-3xl sm:rounded-tl-none sm:rounded-bl-3xl sm:ml-6 sm:float-right sm:w-[42%] lg:w-[38%]">
                        <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" class="max-h-96 w-full object-cover" />
                    </div>

                    <div class="min-w-0">
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $product->title }}</h1>
                            @if(trim((string) $product->subtitle) !== '')
                                <div class="mt-2 text-base font-medium text-gray-500">{{ $product->subtitle }}</div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                @if($product->isDigital())
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold tracking-[0.16em] text-emerald-800">
                                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower(\App\Models\Product::productTypeLabel((string) $product->product_type))) }}
                                    </span>
                                @endif
                                @foreach($product->displayCategories() as $category)
                                    <x-product-category-badge
                                        :label="$category->name"
                                        :icon-class="$category->iconClass()"
                                        :href="route('shop.index', ['category' => $category->slug])"
                                    />
                                @endforeach
                                @if(trim((string) $product->sku) !== '')
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">SKU {{ $product->sku }}</span>
                                @endif
                                @if(!$product->isPurchasable())
                                    <x-stock-indicator tone="danger" :label="'Out of stock'" class="rounded-full bg-red-100 px-3 py-1" />
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
                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-sm font-medium" :class="currentStockToneClass()">
                                    <i x-show="currentStockTone() === 'danger'" class="fa-solid fa-circle-xmark text-xs" x-cloak></i>
                                    <i x-show="currentStockTone() === 'warning'" class="fa-solid fa-triangle-exclamation text-xs" x-cloak></i>
                                    <span x-text="currentStockLabel()">{{ $multiOptionStockLabel }}</span>
                                </div>
                            </div>

                            @if($hasOptionChoices)
                                <div class="mt-6 space-y-3">
                                    <div class="mb-1 pl-1 text-sm font-medium text-gray-900">{{ $chooserHeading }}</div>

                                    @error('product_variant_id')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    <div class="text-sm text-red-600" x-show="formError" x-cloak x-text="formError"></div>

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
                                                            <span x-text="selectionButtonMeta()">{{ $multiOptionStockLabel }}</span>
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
                                                                <span class="flex items-baseline gap-2 text-sm">
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
                                                x-on:submit.prevent="handleAddToCart($event.target)"
                                                x-show="cartQuantity() <= 0"
                                        >
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="product_variant_id" :value="selectedVariantId ?? ''">
                                            <input type="hidden" name="quantity" value="1">
                                            <x-ui.button type="submit" color="primary" class="w-full sm:h-full" x-bind:disabled="!canAddSelection() || busyCartLineKey === activeLineKey()">
                                                <span x-show="canAddSelection() && busyCartLineKey !== activeLineKey()">Add to Cart</span>
                                                <span x-show="canAddSelection() && busyCartLineKey === activeLineKey()" x-cloak>Adding...</span>
                                                <span x-show="!canAddSelection() && selectedOption" x-cloak>Sold out</span>
                                                <span x-show="!canAddSelection() && !selectedOption" x-cloak>Select option</span>
                                            </x-ui.button>
                                        </form>

                                        <div x-show="cartQuantity() > 0" x-cloak class="sm:min-w-40">
                                            <div class="shop-catalog-stepper flex h-full items-center gap-2 rounded border border-gray-300 bg-white">
                                                <button
                                                        type="button"
                                                        class="shop-catalog-stepper-button inline-flex h-11 w-11 p-1 items-center justify-center border-r border-r-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="busyCartLineKey === activeLineKey()"
                                                        @click="changeCartQuantity(cartQuantity() - 1)"
                                                >-</button>
                                                <input
                                                        type="number"
                                                        min="0"
                                                        :max="cartMaxQuantity()"
                                                        :value="cartQuantity()"
                                                        class="shop-catalog-stepper-input h-11 min-w-16 flex-1 border-0 bg-transparent px-0 text-center text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                                        :disabled="busyCartLineKey === activeLineKey()"
                                                        @change="changeCartQuantity($event.target.value)"
                                                />
                                                <button
                                                        type="button"
                                                        class="shop-catalog-stepper-button inline-flex h-11 w-11 items-center justify-center p-1 border-l border-l-gray-300 text-gray-700 transition hover:bg-white hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="busyCartLineKey === activeLineKey() || cartQuantity() >= cartMaxQuantity()"
                                                        @click="changeCartQuantity(cartQuantity() + 1)"
                                                >+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-xs text-gray-500" x-show="selectionLimitMessage()" x-cloak x-text="selectionLimitMessage()"></div>
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
                                        <x-ui.button type="submit" color="primary" class="w-full sm:w-auto" x-bind:disabled="!canAddSelection() || busyCartLineKey === activeLineKey()">
                                            <span x-show="canAddSelection() && busyCartLineKey !== activeLineKey()">Add to Cart</span>
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

                                    @error('product_variant_id')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                    <div class="text-sm text-red-600" x-show="formError" x-cloak x-text="formError"></div>
                                    <div class="text-xs text-gray-500" x-show="selectionLimitMessage()" x-cloak x-text="selectionLimitMessage()"></div>
                                </div>
                            @endif

                            @if($productDescriptionHtml !== '')
                                <article class="mt-8 content text-gray-700">
                                    {!! \App\Support\HtmlContentTransformer::collapseSectionsForDisplay((string) $productDescriptionHtml) !!}
                                </article>
                            @elseif(trim((string) $product->short_description) !== '')
                                <p class="mt-8 text-base leading-7 text-gray-700">{{ $product->short_description }}</p>
                            @endif

                            @if($product->galleryMedia->isNotEmpty())
                                <x-ui.gallery
                                    class="mt-8"
                                    name="product_gallery_{{ $product->id }}"
                                    :value="$product->galleryMedia->pluck('name')->join(',')"
                                />
                            @endif
                    </div>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>
