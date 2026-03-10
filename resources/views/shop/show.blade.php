@php
    $variants = $product->purchasableVariants();
    $defaultVariant = $product->variantById((int) old('product_variant_id')) ?? $product->defaultPurchasableVariant();
    $variantPayload = $variants->map(fn ($variant) => [
        'id' => $variant->id,
        'name' => $variant->name,
        'price_label' => '$'.number_format($variant->effectivePrice(), 2),
        'compare_at_price_label' => $variant->effectiveCompareAtPrice() !== null ? '$'.number_format((float) $variant->effectiveCompareAtPrice(), 2) : null,
        'inventory_quantity' => $variant->availableInventory(),
        'is_in_stock' => $variant->isInStock(),
    ])->values()->all();
@endphp
<x-layout
    :title="$product->title"
    :description="$product->short_description ?: strip_tags((string) $product->description)"
    :canonical="route('shop.product.show', $product)"
>
    <x-mast backRoute="shop.index" backTitle="Store">{{ $product->title }}</x-mast>

    <x-container class="py-8">
        <div
            class="grid gap-8 lg:grid-cols-[1.1fr,0.9fr] lg:items-start"
            x-data="{
                variants: @js($variantPayload),
                selectedVariantId: @js($defaultVariant?->id),
                cartState: @js($cartPayload),
                busyCartLineKey: null,
                get selectedVariant() {
                    return this.variants.find(variant => String(variant.id) === String(this.selectedVariantId)) || null;
                },
                setCartState(cart) {
                    if (!cart || typeof cart !== 'object') {
                        return;
                    }

                    this.cartState = cart;
                },
                normalizeQuantity(value, min = 0, max = 99) {
                    const parsed = Number.parseInt(String(value ?? ''), 10);
                    if (!Number.isFinite(parsed)) {
                        return min;
                    }

                    return Math.max(min, Math.min(max, parsed));
                },
                currentPriceLabel() {
                    return this.selectedVariant ? this.selectedVariant.price_label : @js($product->priceLabel());
                },
                currentCompareAtPriceLabel() {
                    return this.selectedVariant ? this.selectedVariant.compare_at_price_label : @js($product->compareAtPriceForVariant() !== null ? '$'.number_format((float) $product->compareAtPriceForVariant(), 2) : null);
                },
                currentStockLabel() {
                    if (this.variants.length === 0) {
                        return @js($product->availableInventory() !== null ? $product->availableInventory().' in stock' : ($product->isInStock() ? 'In stock' : 'Out of stock'));
                    }

                    if (!this.selectedVariant) {
                        return 'Choose an option to continue';
                    }

                    if (!this.selectedVariant.is_in_stock) {
                        return 'Out of stock';
                    }

                    if (this.selectedVariant.inventory_quantity === null) {
                        return 'In stock';
                    }

                    return `${this.selectedVariant.inventory_quantity} in stock`;
                },
                canSubmit() {
                    if (this.variants.length > 0 && !this.selectedVariant) {
                        return false;
                    }

                    return this.selectedVariant ? this.selectedVariant.is_in_stock : @js($product->isInStock());
                },
                maxQuantity() {
                    if (this.selectedVariant && this.selectedVariant.inventory_quantity !== null) {
                        return Math.max(1, Number(this.selectedVariant.inventory_quantity));
                    }

                    if (@js($product->availableInventory()) !== null) {
                        return Math.max(1, Number(@js($product->availableInventory())));
                    }

                    return 99;
                },
                activeLineKey() {
                    return `${@js($product->id)}:${this.selectedVariant ? this.selectedVariant.id : 0}`;
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
                async submitCartForm(form) {
                    if (this.busyCartLineKey || !(form instanceof HTMLFormElement)) {
                        return;
                    }

                    if (!window.SM?.shopCart) {
                        form.submit();
                        return;
                    }

                    const lineKey = this.activeLineKey();
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
                            if (typeof window.SM.notice === 'function') {
                                window.SM.notice('Removed from cart', `${removedTitle} has been removed from your cart.`, 'success', { toast: true });
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
            <div class="space-y-4">
                <div class="overflow-hidden rounded-3xl bg-gray-100 border border-gray-200">
                    <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->title }}" class="w-full object-cover max-h-[34rem]" />
                </div>
                @if($product->galleryMedia->isNotEmpty())
                    <div class="grid grid-cols-3 gap-3 md:grid-cols-4">
                        @foreach($product->galleryMedia as $media)
                            <a href="{{ $media->url }}" target="_blank" rel="noopener noreferrer" class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                <img src="{{ $media->thumbnail }}" alt="{{ $media->title }}" class="h-24 w-full object-cover" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
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
                        @if(!$product->isInStock())
                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Out of stock</span>
                        @endif
                    </div>

                    @if(trim((string) $product->short_description) !== '')
                        <p class="text-base text-gray-700 mb-5">{{ $product->short_description }}</p>
                    @endif

                    @if(trim((string) $product->description) !== '')
                        <div class="@if($variants->isNotEmpty()) border-t border-gray-200 pt-6 @endif lg:col-start-1 prose prose-sm max-w-none text-gray-700">
                            {!! nl2br(e((string) $product->description)) !!}
                        </div>
                    @endif
                </div>

                <form
                    method="POST"
                    action="{{ route('shop.cart.add', $product) }}"
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-8 lg:items-stretch"
                    x-on:submit.prevent="submitCartForm($event.target)"
                >
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="quantity" value="1">

                    @if($variants->isNotEmpty())
                        <div class="lg:col-start-1">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-sm font-semibold text-gray-900">Choose an option</label>
                                <div class="text-xs text-gray-500">{{ $variants->count() }} option{{ $variants->count() === 1 ? '' : 's' }}</div>
                            </div>
                            <div class="grid gap-3">
                                @foreach($variants as $variant)
                                    <label class="flex cursor-pointer items-start justify-between gap-4 rounded-2xl border border-gray-200 px-4 py-3 transition hover:border-sky-300" :class="String(selectedVariantId) === '{{ $variant->id }}' ? 'border-sky-500 bg-sky-50' : 'bg-white'">
                                        <div>
                                            <input type="radio" name="product_variant_id" value="{{ $variant->id }}" class="sr-only" x-model="selectedVariantId">
                                            <div class="font-semibold text-gray-900">{{ $variant->name }}</div>
                                            <div class="text-sm text-gray-500">
                                                @if($variant->availableInventory() !== null)
                                                    {{ $variant->availableInventory() }} in stock
                                                @else
                                                    In stock
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold text-gray-900">${{ number_format($variant->effectivePrice(), 2) }}</div>
                                            @if($variant->effectiveCompareAtPrice() !== null && $variant->effectiveCompareAtPrice() > $variant->effectivePrice())
                                                <div class="text-xs text-gray-400 line-through">${{ number_format((float) $variant->effectiveCompareAtPrice(), 2) }}</div>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            @error('product_variant_id')
                                <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="space-y-5 md:mr-auto md:max-w-sm lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:max-w-none lg:self-stretch">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="text-4xl font-bold text-gray-900" x-text="currentPriceLabel()">{{ $product->priceRangeLabel() }}</div>
                                <div class="pb-1 text-lg text-gray-400 line-through" x-show="currentCompareAtPriceLabel()" x-cloak x-text="currentCompareAtPriceLabel()"></div>
                            </div>
                            <div class="text-sm font-medium text-gray-500">(inc GST)</div>
                            <div class="text-sm font-medium text-gray-600" x-text="currentStockLabel()">
                                {{ $product->availableInventory() !== null ? $product->availableInventory().' in stock' : 'In stock' }}
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template x-if="cartQuantity() <= 0">
                                <div>
                                    <x-ui.button type="submit" class="w-full" x-bind:disabled="!canSubmit() || busyCartLineKey === activeLineKey()">
                                        <span x-show="busyCartLineKey !== activeLineKey()">Add to Cart</span>
                                        <span x-show="busyCartLineKey === activeLineKey()" x-cloak>Adding...</span>
                                    </x-ui.button>
                                </div>
                            </template>

                            <template x-if="cartQuantity() > 0">
                                <div class="flex items-center gap-2 rounded border border-gray-300 bg-white p-1">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded text-gray-700 transition hover:bg-gray-100 hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyCartLineKey === activeLineKey()"
                                        @click="changeCartQuantity(cartQuantity() - 1)"
                                    >-</button>
                                    <input
                                        type="number"
                                        min="0"
                                        :max="cartMaxQuantity()"
                                        :value="cartQuantity()"
                                        class="h-10 min-w-0 flex-1 border-0 bg-transparent px-0 text-center text-base font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                        :disabled="busyCartLineKey === activeLineKey()"
                                        @change="changeCartQuantity($event.target.value)"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded text-gray-700 transition hover:bg-gray-100 hover:text-primary-color disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="busyCartLineKey === activeLineKey() || cartQuantity() >= cartMaxQuantity()"
                                        @click="changeCartQuantity(cartQuantity() + 1)"
                                    >+</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </x-container>
</x-layout>
