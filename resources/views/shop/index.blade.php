@php
    $searchValue = trim((string) request()->query('search', ''));
    $mobileCategoriesOpen = $selectedCategory !== '';
    $totalResults = $products->total();
    $firstResult = $products->firstItem() ?? 0;
    $lastResult = $products->lastItem() ?? 0;
    $sortOptions = [
        'relevance' => 'Relevance',
        'price_low' => 'Price low to high',
        'price_high' => 'Price high to low',
        'title_asc' => 'Product name (A - Z)',
        'title_desc' => 'Product name (Z - A)',
    ];
    $mobileSelectedCategoryLabel = $selectedCategory !== '' ? ucfirst($selectedCategory) : 'All Products';
    $clearCategoryUrl = route('shop.index', request()->except('page', 'category', 'view'));
    $clearSearchUrl = route('shop.index', request()->except('page', 'search', 'view'));
@endphp

<x-layout
    title="Store"
    description="Browse STEMMechanics kits, downloads, and hands-on resources."
    :canonical="route('shop.index')"
>
    <x-mast>Store</x-mast>

    <style>
        @verbatim
        [data-shop-catalog] [data-shop-view-button] {
            background: #fff;
            color: #374151;
        }

        [data-shop-catalog] [data-shop-view-button]:hover {
            background: #f9fafb;
            color: #0284c7;
        }

        [data-shop-catalog][data-current-view="grid"] [data-shop-view-button="grid"],
        [data-shop-catalog][data-current-view="list"] [data-shop-view-button="list"] {
            background: #0284c7;
            color: #fff;
        }

        [data-shop-catalog][data-current-view="grid"] [data-shop-results] {
            display: grid;
            gap: 1.5rem;
        }

        @media (max-width: 767px) {
            [data-shop-catalog] [data-shop-results] {
                display: grid !important;
                gap: 1.5rem !important;
            }

            [data-shop-catalog] .shop-product-card {
                padding: 0 !important;
            }

            [data-shop-catalog] .shop-product-card-inner {
                display: flex !important;
                flex-direction: column !important;
                gap: 0 !important;
            }

            [data-shop-catalog] .shop-product-card-image-frame {
                grid-column: auto !important;
                grid-row: auto !important;
                height: auto !important;
                border-radius: 0 !important;
            }

            [data-shop-catalog] .shop-product-card-image {
                height: 16rem !important;
                min-height: 0 !important;
            }

            [data-shop-catalog] .shop-product-card-body {
                grid-column: auto !important;
                grid-row: auto !important;
                min-width: 0;
                padding: 1.25rem 1.25rem 0.5rem 1.25rem !important;
            }

            [data-shop-catalog] .shop-product-card-header {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.75rem !important;
                margin-bottom: 0.75rem !important;
            }

            [data-shop-catalog] .shop-product-card-title {
                margin-bottom: 0.5rem !important;
            }

            [data-shop-catalog] .shop-product-card-description {
                display: block !important;
                margin-top: 0 !important;
                margin-bottom: 1rem !important;
                max-width: none !important;
                overflow: visible !important;
                -webkit-line-clamp: unset !important;
            }

            [data-shop-catalog] .shop-product-card-stock {
                margin-top: 0 !important;
            }

            [data-shop-catalog] .shop-product-card-actions {
                grid-column: auto !important;
                grid-row: auto !important;
                min-width: 0 !important;
                justify-self: auto !important;
                /*align-self: auto !important;*/
                padding: 0 1.25rem 1rem !important;
            }

            [data-shop-catalog] .shop-product-card-action-link,
            [data-shop-catalog] .shop-catalog-add-button {
                width: 100% !important;
            }
        }

        @media (min-width: 768px) {
            [data-shop-catalog][data-current-view="grid"] [data-shop-results] {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            [data-shop-catalog][data-current-view="grid"] [data-shop-results] {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        [data-shop-catalog][data-current-view="list"] [data-shop-results] {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        [data-shop-catalog][data-current-view="grid"] .shop-product-card-body {
            padding: 1.25rem 1.25rem .5rem 1.25rem;
        }

        [data-shop-catalog][data-current-view="grid"] .shop-product-card-actions {
            padding: 0 1.25rem 1rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card {
            padding: 1rem;
        }

        @media (min-width: 640px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card {
                padding: 1.25rem;
            }
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-inner {
            display: grid;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-inner {
                grid-template-columns: 10rem minmax(0, 1fr);
                grid-template-rows: minmax(0, 1fr) auto;
                column-gap: 1.25rem;
                align-items: stretch;
            }
        }

        @media (min-width: 1024px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-inner {
                grid-template-columns: 11rem minmax(0, 1fr);
            }
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-image-frame {
            overflow: hidden;
            border-radius: 1rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-image {
            height: 9rem;
        }

        @media (min-width: 640px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-image-frame {
                grid-column: 1;
                grid-row: 1 / span 2;
                height: 100%;
            }

            [data-shop-catalog][data-current-view="list"] .shop-product-card-image {
                height: 100%;
                min-height: 100%;
            }
        }

        @media (min-width: 1024px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-image {
                min-height: 12rem;
            }
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-body {
            min-width: 0;
            grid-column: 2;
            grid-row: 1;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-header {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        @media (min-width: 640px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-header {
                align-items: flex-start;
                flex-direction: row;
                justify-content: space-between;
            }
        }

        [data-shop-catalog][data-current-view="grid"] .shop-product-card-title {
            margin-bottom: 0.5rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-description {
            display: -webkit-box;
            margin-top: 0.5rem;
            max-width: 44rem;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        [data-shop-catalog][data-current-view="grid"] .shop-product-card-description {
            margin-bottom: 1rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-stock {
            margin-top: 0.75rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-actions {
            width: 12rem;
            grid-column: 2;
            grid-row: 2;
            justify-self: start;
            align-self: end;
            text-align: right;
            margin-top: -2rem;
        }

        @media (min-width: 640px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-actions {
                justify-self: end;
            }
        }

        [data-shop-catalog][data-current-view="list"] .shop-product-card-action-link,
        [data-shop-catalog][data-current-view="list"] .shop-catalog-add-button {
            width: 100%;
        }

        @media (min-width: 1024px) {
            [data-shop-catalog][data-current-view="list"] .shop-product-card-action-link,
            [data-shop-catalog][data-current-view="list"] .shop-catalog-add-button {
                width: 100%;
            }
        }

        [data-shop-catalog][data-current-view="list"] .shop-catalog-cart-label {
            display: none;
        }

        [data-shop-catalog][data-current-view="list"] .shop-catalog-stepper {
            background: #fff;
            border-color: #e5e7eb;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        [data-shop-catalog][data-current-view="list"] .shop-catalog-stepper-button {
            height: 2.5rem;
            width: 2.5rem;
        }

        [data-shop-catalog][data-current-view="list"] .shop-catalog-stepper-input {
            font-size: 1rem;
            height: 2.5rem;
            width: 4rem;
        }

        [data-shop-mobile-categories] summary::-webkit-details-marker {
            display: none;
        }

        [data-shop-mobile-categories] [data-shop-mobile-categories-chevron] {
            transition: transform 180ms ease, color 180ms ease;
        }

        [data-shop-mobile-categories][open] [data-shop-mobile-categories-chevron] {
            color: #0284c7;
            transform: rotate(180deg);
        }
        @endverbatim
    </style>

    <x-container class="py-8">
        <div data-shop-catalog data-current-view="{{ $selectedView }}">
            <div class="flex items-start gap-3">
                @if(($categories ?? collect())->isNotEmpty())
                    <aside class="hidden lg:block">
                        <div class="sticky top-24 min-w-56">
                            <h2 class="mt-1 text-2xl font-bold text-gray-900">Categories</h2>
                            <div class="mt-3 space-y-1">
                                <a
                                    href="{{ $clearCategoryUrl }}"
                                    data-shop-view-link
                                    data-shop-view-base="{{ $clearCategoryUrl }}"
                                    class="flex items-center justify-between rounded px-3 py-2 text-sm font-medium transition {{ $selectedCategory === '' ? 'border-primary-color bg-primary-color text-white' : 'text-gray-700 hover:border-primary-color hover:text-primary-color' }}"
                                >
                                    All Products
                                </a>
                                @foreach($categories as $category)
                                    @php
                                        $categoryUrl = route('shop.index', array_merge(request()->except('page', 'category', 'view'), ['category' => $category]));
                                    @endphp
                                    <a
                                        href="{{ $categoryUrl }}"
                                        data-shop-view-link
                                        data-shop-view-base="{{ $categoryUrl }}"
                                        class="block rounded px-3 py-2 text-sm font-medium transition {{ $selectedCategory === $category ? 'border-primary-color bg-primary-color text-white' : 'text-gray-700 hover:border-primary-color hover:text-primary-color' }}"
                                    >
                                        {{ ucfirst($category) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </aside>
                @endif

                <div class="flex flex-col gap-6 w-full">
                    @if(($categories ?? collect())->isNotEmpty())
                        <section class="lg:hidden -mt-3 mb-2">
                            <details class="rounded-lg border border-gray-200 bg-white shadow-sm" data-shop-mobile-categories>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-gray-700">
                                    <span class="min-w-0 inline-flex items-center gap-2">
                                        <i class="fa-solid fa-layer-group"></i>
                                        <span class="min-w-0">
                                            <span class="block">Browse categories</span>
                                            <span class="block truncate text-xs font-normal text-gray-500">{{ $mobileSelectedCategoryLabel }}</span>
                                        </span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-xs text-gray-400" data-shop-mobile-categories-chevron></i>
                                </summary>
                                <div class="border-t border-gray-200 p-2">
                                    <div class="space-y-1">
                                        <a
                                            href="{{ $clearCategoryUrl }}"
                                            data-shop-view-link
                                            data-shop-view-base="{{ $clearCategoryUrl }}"
                                            class="block px-4 py-2 text-sm rounded transition hover:bg-sky-600 hover:text-white {{ $selectedCategory === '' ? 'border-primary-color bg-primary-color text-white' : 'border-gray-200 bg-gray-50 text-gray-700' }}"
                                        >
                                            <span>All Products</span>
                                        </a>
                                        @foreach($categories as $category)
                                            @php
                                                $categoryUrl = route('shop.index', array_merge(request()->except('page', 'category', 'view'), ['category' => $category]));
                                            @endphp
                                            <a
                                                href="{{ $categoryUrl }}"
                                                data-shop-view-link
                                                data-shop-view-base="{{ $categoryUrl }}"
                                                class="block px-4 py-2 text-sm rounded transition hover:bg-sky-600 hover:text-white {{ $selectedCategory === $category ? 'border-primary-color bg-primary-color text-white' : 'border-gray-200 bg-gray-50 text-gray-700' }}"
                                            >
                                                {{ ucfirst($category) }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        </section>
                    @endif

                    <section class="flex-1">
                        <div class="flex flex-col sm:flex-row items-center -mt-4 mb-4">
                            <form method="GET" action="{{ route('shop.index') }}" class="w-full sm:w-64">
                                @if($selectedCategory !== '')
                                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                                @endif
                                @if($searchValue !== '')
                                    <input type="hidden" name="search" value="{{ $searchValue }}">
                                @endif
                                <input type="hidden" name="view" value="{{ $selectedView }}" data-shop-view-input>

                                <x-ui.select
                                    id="shop-sort"
                                    name="sort"
                                    label="Sort By"
                                    :value="$selectedSort"
                                    class="mb-0 sm:w-64 w-full"
                                    inline-label
                                    onchange="this.form.submit()"
                                >
                                    @foreach($sortOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </form>

                            <div class="hidden sm:inline-block text-sm text-gray-600 flex-1 text-center">
                                Showing <span class="font-semibold text-gray-900">{{ $firstResult }}-{{ $lastResult }}</span> of <span class="font-semibold text-gray-900">{{ $totalResults }}</span> result{{ $totalResults === 1 ? '' : 's' }}
                            </div>

                            <div class="hidden sm:inline-flex overflow-hidden rounded border border-gray-300 bg-white shadow-sm self-start lg:self-auto">
                                <button
                                    type="button"
                                    data-shop-view-button="grid"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition"
                                    aria-pressed="{{ $selectedView === 'grid' ? 'true' : 'false' }}"
                                >
                                    <i class="fa-solid fa-grip"></i>
                                    <span>Grid</span>
                                </button>
                                <button
                                    type="button"
                                    data-shop-view-button="list"
                                    class="inline-flex items-center gap-2 border-l border-gray-300 px-4 py-2.5 text-sm font-medium transition"
                                    aria-pressed="{{ $selectedView === 'list' ? 'true' : 'false' }}"
                                >
                                    <i class="fa-solid fa-list"></i>
                                    <span>List</span>
                                </button>
                            </div>
                        </div>

                    @if($products->isEmpty())
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-gray-600">
                            No products are available right now.
                        </div>
                    @else
                        <div data-shop-results>
                            @foreach($products as $product)
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
                                    $preorderEstimate = $product->preorderShippingEstimateLabel();
                                    $backorderEstimate = $product->backorderShippingEstimateLabel();
                                    $backorderNowInventory = null;
                                    if (! $hasVariants) {
                                        $backorderNowInventory = $product->availableInventory();
                                    } elseif ($defaultVariant) {
                                        $backorderNowInventory = $product->availableInventory($defaultVariant);
                                    }
                                @endphp
                                <article class="shop-product-card flex flex-col group relative overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                                    <a href="{{ route('shop.product.show', $product) }}" class="absolute inset-0 z-10" aria-label="View {{ $product->title }}"></a>

                                    <div class="shop-product-card-inner flex flex-col flex-1">
                                        <div class="shop-product-card-image-frame pointer-events-none block bg-gray-100">
                                            <img
                                                src="{{ $product->primaryImageUrl() }}"
                                                alt="{{ $product->title }}"
                                                class="shop-product-card-image h-64 w-full object-cover"
                                            />
                                        </div>

                                        <div class="shop-product-card-body pointer-events-none flex-1 flex flex-col">
                                            <div class="shop-product-card-header mb-3 flex items-start justify-between gap-3">
                                                <div class="shop-product-card-title">
                                                    <h3 class="shop-product-card-title text-xl font-bold text-gray-900 transition group-hover:text-primary-color">{{ $product->title }}</h3>
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

                                            <div class="shop-product-card-stock text-xs text-gray-500">
                                                @if(!$inStock)
                                                    <span class="font-semibold text-red-600">Out of stock</span>
                                                @elseif($product->isDigital())
                                                    Instant download after checkout
                                                @elseif($hasVariants)
                                                    {{ $variantCount }} option{{ $variantCount === 1 ? '' : 's' }} available
                                                @elseif($product->isPreorder())
                                                    Pre-order available.<br>{{ $preorderEstimate ? 'Shipping expected ' . $preorderEstimate : 'Will ship when available' }}
                                                @elseif($product->allowsBackorder())
                                                    @if($hasVariants && ! $defaultVariant)
                                                        {{ $backorderEstimate ? 'Available now. More expected '.$backorderEstimate : 'Available now. More coming soon' }}
                                                    @elseif($backorderNowInventory === null)
                                                        {{ $backorderEstimate ? 'Available now. More expected '.$backorderEstimate : 'Available now. More coming soon' }}
                                                    @elseif($backorderNowInventory > 0)
                                                        {{ $backorderNowInventory }} available now. {{ $backorderEstimate ? 'More expected '.$backorderEstimate : 'More coming soon' }}
                                                    @else
                                                        {{ $backorderEstimate ? 'Available to order. More expected '.$backorderEstimate : 'Available to order. More coming soon' }}
                                                    @endif
                                                @else
                                                    {{ $product->availableInventoryForPurchase() !== null ? $product->availableInventoryForPurchase().' in stock' : 'In stock' }}
                                                @endif
                                            </div>
                                        </div>

                                        <div class="shop-product-card-actions pointer-events-auto relative z-20">
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
                        </div>

                        <div class="mt-8" data-shop-pagination>
                            {{ $products->appends(request()->query())->links() }}
                        </div>
                    @endif
                    </section>
                </div>
            </div>
        </div>
    </x-container>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.querySelector('[data-shop-catalog]');
            if (!root) {
                return;
            }

            if (window.SM && window.SM.shopCart) {
                window.SM.shopCart.configure({
                    showUrl: @js(route('shop.cart.show')),
                    updateUrl: @js(route('shop.cart.update')),
                    removeUrl: @js(route('shop.cart.remove')),
                    initialState: @js($cartPayload),
                });
            }

            var currentView = root.getAttribute('data-current-view') === 'list' ? 'list' : 'grid';
            var viewInputs = root.querySelectorAll('[data-shop-view-input]');
            var viewLinks = root.querySelectorAll('[data-shop-view-link]');
            var viewButtons = root.querySelectorAll('[data-shop-view-button]');
            var pagination = root.querySelector('[data-shop-pagination]');

            function hrefWithView(baseHref) {
                if (!baseHref) {
                    return '';
                }

                var url = new URL(baseHref, window.location.origin);
                url.searchParams.set('view', currentView);

                return url.pathname + url.search + url.hash;
            }

            function syncView(pushHistory) {
                root.setAttribute('data-current-view', currentView);

                viewInputs.forEach(function (input) {
                    input.value = currentView;
                });

                viewLinks.forEach(function (link) {
                    var baseHref = link.getAttribute('data-shop-view-base') || link.getAttribute('href');
                    if (!baseHref) {
                        return;
                    }

                    link.setAttribute('data-shop-view-base', baseHref);
                    link.setAttribute('href', hrefWithView(baseHref));
                });

                viewButtons.forEach(function (button) {
                    var isActive = button.getAttribute('data-shop-view-button') === currentView;
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                if (pagination) {
                    pagination.querySelectorAll('a').forEach(function (link) {
                        var baseHref = link.getAttribute('data-shop-view-base') || link.getAttribute('href');
                        if (!baseHref) {
                            return;
                        }

                        link.setAttribute('data-shop-view-base', baseHref);
                        link.setAttribute('href', hrefWithView(baseHref));
                    });
                }

                if (pushHistory && window.history && typeof window.history.replaceState === 'function') {
                    var url = new URL(window.location.href);
                    url.searchParams.set('view', currentView);
                    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
                }
            }

            viewButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var nextView = button.getAttribute('data-shop-view-button') === 'list' ? 'list' : 'grid';
                    if (nextView === currentView) {
                        return;
                    }

                    currentView = nextView;
                    syncView(true);
                });
            });

            syncView(false);
        });
    </script>
</x-layout>
