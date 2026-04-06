<x-container class="mt-4" inner-class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 w-full">
    @foreach ($products as $product)
        @php
            $variants = $product->purchasableVariants();
            $variantCount = $product->optionChoiceCount();
            $hasVariants = $product->hasOptionChoices();
            $defaultVariant = $variantCount === 1 ? $variants->first() : null;
            $inStock = $product->isPurchasable();
            $lineKey = $product->id.':'.($defaultVariant?->id ?? 0);
            $fallbackMaxQuantity = $product->availableInventoryForPurchase($defaultVariant);
            if ($fallbackMaxQuantity === null) {
                $fallbackMaxQuantity = $product->availableInventoryForPurchase();
            }
            $fallbackMaxQuantity = $fallbackMaxQuantity !== null ? max(1, (int) $fallbackMaxQuantity) : 99;
            $removeMessage = $product->title.' has been removed from your cart.';
            $shortDescription = trim((string) $product->short_description);
            $priceRangeLabel = $product->priceRangeLabel();
            $priceIsFromRange = \Illuminate\Support\Str::startsWith($priceRangeLabel, 'From ');
            $priceRangeAmountLabel = $priceIsFromRange
                ? \Illuminate\Support\Str::after($priceRangeLabel, 'From ')
                : $priceRangeLabel;
        @endphp
        <article class="shop-product-card flex flex-col group relative overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
            <a href="{{ route('shop.product.show', $product) }}" class="absolute inset-0 z-10" aria-label="View {{ $product->title }}"></a>

            <div class="shop-product-card-inner flex flex-col flex-1">
                <div class="shop-product-card-image-frame pointer-events-none relative block bg-gray-100">
                    @if(in_array((int) $product->id, $bestSellerProductIds ?? [], true))
                        <x-best-seller-badge class="absolute left-3 top-3 z-20" />
                    @endif
                    <img
                        src="{{ $product->hero?->url ? $product->hero->url.'?md' : $product->primaryImageUrl() }}"
                        alt="{{ $product->title }}"
                        class="shop-product-card-image h-64 w-full object-cover"
                    />
                </div>

                <div class="shop-product-card-body pointer-events-none flex-1 flex flex-col p-6 pb-4">
                    <div class="shop-product-card-header flex items-start justify-between gap-3">
                        <div class="shop-product-card-title flex flex-col justify-between">
                            <div class="flex items-start gap-2">
                                <h3 class="shop-product-card-title text-xl font-bold text-gray-900 transition group-hover:text-primary-color">{{ $product->title }}</h3>
                                @if($isAdmin)
                                    <a
                                        href="{{ route('admin.shop.product.edit', $product) }}"
                                        class="pointer-events-auto relative z-20 mt-1 inline-flex shrink-0 items-center text-gray-400 transition hover:text-primary-color"
                                        title="Edit product"
                                        aria-label="Edit {{ $product->title }}"
                                    >
                                        <i class="fa-solid fa-pen text-sm"></i>
                                    </a>
                                @endif
                            </div>
                            @if(trim((string) $product->subtitle) !== '')
                                <div class="text-sm font-medium text-gray-500">{{ $product->subtitle }}</div>
                            @endif
                            <div class="flex flex-wrap items-center gap-2 -ml-1">
                                @if($product->isDigital())
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower(\App\Models\Product::productTypeLabel((string) $product->product_type))) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="flex items-baseline gap-1 text-xl font-bold text-gray-900 sm:justify-end">
                                @if($priceIsFromRange)
                                    <span class="text-sm font-medium text-gray-500">From</span>
                                @endif
                                <span>{{ $priceRangeAmountLabel }}</span>
                            </div>
                            <div class="text-xs text-gray-500">(inc GST)</div>
                        </div>
                    </div>

                    @if($shortDescription !== '')
                        <p class="shop-product-card-description text-sm text-gray-600 flex-1 min-h-18">{{ $shortDescription }}</p>
                    @endif

                    <div class="shop-product-card-stock">
                        @if(!$inStock)
                            <x-stock-indicator tone="danger" :label="'Out of stock'" />
                        @elseif($product->isDigital())
                            <x-stock-indicator tone="success" :label="'Instant download after checkout'" />
                        @elseif($hasVariants)
                            <span class="text-xs font-medium text-gray-500">{{ $variantCount }} option{{ $variantCount === 1 ? '' : 's' }} available</span>
                        @else
                            <x-stock-indicator :tone="$product->availabilityTone()" :label="$product->availabilityLabel()" />
                        @endif
                    </div>
                </div>

                <div class="shop-product-card-actions pointer-events-auto relative z-20 px-6 pb-6">
                    @if($inStock && $variantCount <= 1)
                        @include('shop.partials.catalog-cart-control', [
                            'product' => $product,
                            'defaultVariant' => $defaultVariant,
                            'lineKey' => $lineKey,
                            'fallbackMaxQuantity' => $fallbackMaxQuantity,
                            'removeMessage' => $removeMessage,
                        ])
                    @elseif($inStock && $variantCount > 1)
                        @include('shop.partials.catalog-option-control', [
                            'product' => $product,
                            'cartPayload' => $cartPayload,
                        ])
                    @else
                        <x-ui.button type="link" href="{{ route('shop.product.show', $product) }}" class="shop-product-card-action-link px-5!">{{ $variantCount > 1 ? 'View Product' : 'View' }}</x-ui.button>
                    @endif
                </div>
            </div>
        </article>
    @endforeach
</x-container>

<x-container>
    {{ $products->appends(request()->except('product'))->links('', ['pageName' => 'product']) }}
</x-container>
