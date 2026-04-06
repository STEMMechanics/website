@php
    $inventoryContexts = is_array($inventoryContexts ?? null)
        ? $inventoryContexts
        : ['base' => ['awaiting' => 0, 'reserved' => 0], 'variants' => []];
    $baseInventoryContext = [
        'awaiting' => (int) data_get($inventoryContexts, 'base.awaiting', 0),
        'reserved' => (int) data_get($inventoryContexts, 'base.reserved', 0),
    ];
    $variantInventoryContextMap = collect(data_get($inventoryContexts, 'variants', []))
        ->mapWithKeys(fn ($context, $variantId) => [
            (string) $variantId => [
                'awaiting' => (int) data_get($context, 'awaiting', 0),
                'reserved' => (int) data_get($context, 'reserved', 0),
            ],
        ])
        ->all();
    $galleryFilesValue = isset($product)
        ? implode(',', $product->galleryMedia->pluck('name')->all())
        : '';
    $downloadFilesValue = isset($product)
        ? $product->downloadMedia()->orderBy('name')->get()
        : collect();
    $selectedCategoryIds = collect(old('category_ids', isset($product) ? $product->categories->pluck('id')->map(fn ($categoryId) => (string) $categoryId)->all() : []))
        ->map(fn ($categoryId) => (string) $categoryId)
        ->values()
        ->all();
    if ($selectedCategoryIds === [] && isset($product) && trim((string) $product->category) !== '' && ($categories ?? collect())->isNotEmpty()) {
        $legacyCategoryName = trim((string) $product->category);
        $legacyCategory = collect($categories)->first(function ($category) use ($legacyCategoryName): bool {
            return mb_strtolower((string) $category->name) === mb_strtolower($legacyCategoryName)
                || mb_strtolower((string) $category->slug) === mb_strtolower(\Illuminate\Support\Str::slug($legacyCategoryName));
        });

        if ($legacyCategory) {
            $selectedCategoryIds = [(string) $legacyCategory->id];
        }
    }
    $productDescription = old('description', $product->description ?? '');
    $satchelOptions = \App\Models\Product::satchelOptions();
    $defaultSatchelRank = (int) ($satchelOptions->first()['rank'] ?? 1);
    $productBackorderEstimateType = old('backorder_shipping_estimate_type', isset($product)
        ? ($product->backorder_shipping_estimate_type ?? ($product->backorder_shipping_offset_days !== null ? \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC : \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC))
        : \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC);
    $productBackorderOffsetDays = old('backorder_shipping_offset_days', isset($product)
        ? ($product->backorder_shipping_offset_days !== null ? (string) $product->backorder_shipping_offset_days : '')
        : '');
    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = isset($product)
            ? ($product->variants->isNotEmpty()
                ? $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'description' => $variant->description,
                    'sku' => $variant->sku,
                    'price' => $variant->price !== null ? number_format((float) $variant->price, 2, '.', '') : '',
                    'compare_at_price' => $variant->compare_at_price !== null ? number_format((float) $variant->compare_at_price, 2, '.', '') : '',
                    'inventory_quantity' => $variant->inventory_quantity,
                    'allow_backorder' => (bool) ($variant->allow_backorder || $variant->is_preorder),
                    'backorder_shipping_estimate_type' => $variant->backorder_shipping_estimate_type ?? ($variant->backorder_shipping_offset_days !== null ? \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC : \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC),
                    'backorder_shipping_estimate' => $variant->backorder_shipping_estimate?->format('Y-m-d')
                        ?? $variant->preorder_shipping_estimate?->format('Y-m-d')
                        ?? '',
                    'backorder_shipping_offset_days' => $variant->backorder_shipping_offset_days !== null ? (string) $variant->backorder_shipping_offset_days : '',
                    'sort_order' => $variant->sort_order,
                    'is_active' => (bool) $variant->is_active,
                    'awaiting_fulfilment' => (int) data_get($variantInventoryContextMap, (string) $variant->id.'.awaiting', 0),
                    'reserved_quantity' => (int) data_get($variantInventoryContextMap, (string) $variant->id.'.reserved', 0),
                ])->values()->all()
                : [])
            : [];
    } else {
        $variantRows = collect($variantRows)
            ->map(function ($variant) use ($variantInventoryContextMap) {
                $variantId = (string) data_get($variant, 'id', '');
                $context = $variantInventoryContextMap[$variantId] ?? ['awaiting' => 0, 'reserved' => 0];

                return array_merge($variant, [
                    'description' => data_get($variant, 'description', ''),
                    'allow_backorder' => (bool) data_get($variant, 'allow_backorder', data_get($variant, 'is_preorder', false)),
                    'backorder_shipping_estimate_type' => data_get($variant, 'backorder_shipping_estimate_type', data_get($variant, 'backorder_shipping_offset_days', '') !== '' ? \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC : \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC),
                    'backorder_shipping_estimate' => data_get($variant, 'backorder_shipping_estimate', data_get($variant, 'preorder_shipping_estimate', '')),
                    'backorder_shipping_offset_days' => data_get($variant, 'backorder_shipping_offset_days', ''),
                    'awaiting_fulfilment' => (int) data_get($variant, 'awaiting_fulfilment', $context['awaiting']),
                    'reserved_quantity' => (int) data_get($variant, 'reserved_quantity', $context['reserved']),
                ]);
            })
            ->values()
            ->all();
    }
    $productAllowsBackorder = (bool) old('allow_backorder', isset($product) ? ((bool) $product->allow_backorder || (bool) $product->is_preorder) : false);
    $productBackorderEstimate = old('backorder_shipping_estimate', isset($product)
        ? ($product->backorder_shipping_estimate?->format('Y-m-d') ?? $product->preorder_shipping_estimate?->format('Y-m-d') ?? '')
        : '');
@endphp
<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">{{ isset($product) ? 'Edit' : 'Create' }} Product</x-mast>

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.shop.product.'.(isset($product) ? 'update' : 'store'), $product ?? []) }}"
            x-data="{
                productType: @js(old('product_type', $product->product_type ?? \App\Models\Product::PRODUCT_TYPE_PHYSICAL)),
                status: @js(old('status', $product->status ?? \App\Models\Product::STATUS_DRAFT)),
                title: @js(old('title', $product->title ?? '')),
                slug: @js(old('slug', $product->slug ?? '')),
                baseSku: @js(old('sku', $product->sku ?? '')),
                baseSkuTouched: @js(trim((string) old('sku', $product->sku ?? '')) !== ''),
                slugTouched: @js(trim((string) old('slug', $product->slug ?? '')) !== ''),
                allowBackorder: @js($productAllowsBackorder),
                isFeatured: @js((bool) old('is_featured', $product->is_featured ?? false)),
                boxOnly: @js((bool) old('box_only', $product->box_only ?? false)),
                basePrice: @js(old('price', isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00')),
                baseShippingUnits: @js(old('shipping_units', isset($product) ? number_format((float) $product->shipping_units, 3, '.', '') : '0.000')),
                baseMinSatchelRank: @js((string) old('min_satchel_rank', $product->min_satchel_rank ?? $defaultSatchelRank)),
                baseVariantName: @js(old('base_variant_name', $product->base_variant_name ?? '')),
                productBackorderEstimateType: @js($productBackorderEstimateType),
                productBackorderOffsetDays: @js((string) $productBackorderOffsetDays),
                variants: @js($variantRows),
                variantInputClasses: 'disabled:bg-gray-100 bg-white block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                variantTextareaClasses: 'disabled:bg-gray-100 bg-white block min-h-[7rem] w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                defaultBaseOptionLabel() {
                    return this.productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Home' : 'Base';
                },
                baseOptionDisplayName() {
                    const explicitName = String(this.baseVariantName || '').trim();

                    return explicitName !== '' ? explicitName : this.defaultBaseOptionLabel() + ' Variant';
                },
                displayVariantName(variant, index) {
                    const explicitName = String(variant?.name || '').trim();

                    return explicitName !== '' ? explicitName : `Variant ${index + 1}`;
                },
                slugify(value) {
                    return String(value || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '')
                        .replace(/-{2,}/g, '-');
                },
                syncSlugFromTitle() {
                    if (this.slugTouched || String(this.slug || '').trim() !== '') {
                        return;
                    }

                    this.slug = this.slugify(this.title);
                },
                handleTitleInput() {
                    this.syncSlugFromTitle();
                    this.syncBaseSkuFromSlug();
                },
                handleSlugInput() {
                    this.slugTouched = String(this.slug || '').trim() !== '';
                    this.syncBaseSkuFromSlug();
                },
                handleBaseSkuInput() {
                    this.baseSkuTouched = String(this.baseSku || '').trim() !== '';
                },
                normalizeSkuPart(value) {
                    return String(value || '')
                        .toUpperCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^A-Z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '')
                        .replace(/-{2,}/g, '-');
                },
                variantSkuSeed(variant, index) {
                    const base = this.normalizeSkuPart(this.baseSku || this.slug || this.title);
                    const name = this.normalizeSkuPart(variant?.name || '');
                    const fallback = `VARIANT-${index + 1}`;

                    if (base !== '' && name !== '') {
                        return `${base}-${name}`;
                    }
                    if (name !== '') {
                        return name;
                    }
                    if (base !== '') {
                        return `${base}-${fallback}`;
                    }

                    return fallback;
                },
                ensureUniqueVariantSku(candidate, currentIndex) {
                    const seed = this.normalizeSkuPart(candidate);
                    if (seed === '') {
                        return '';
                    }

                    const reserved = new Set();
                    const baseProductSku = this.normalizeSkuPart(this.baseSku);
                    if (baseProductSku !== '') {
                        reserved.add(baseProductSku);
                    }

                    this.variants.forEach((variant, index) => {
                        if (index === currentIndex) {
                            return;
                        }

                        const existing = this.normalizeSkuPart(variant?.sku || '');
                        if (existing !== '') {
                            reserved.add(existing);
                        }
                    });

                    let uniqueSku = seed;
                    let suffix = 2;
                    while (reserved.has(uniqueSku)) {
                        uniqueSku = `${seed}-${suffix}`;
                        suffix += 1;
                    }

                    return uniqueSku;
                },
                ensureUniqueBaseSku(candidate) {
                    const seed = this.slugify(candidate);
                    if (seed === '') {
                        return '';
                    }

                    const reserved = new Set();
                    const currentBaseSku = this.slugify(this.baseSku || '');
                    if (currentBaseSku !== '') {
                        reserved.add(currentBaseSku);
                    }

                    this.variants.forEach((variant) => {
                        const existing = this.slugify(variant?.sku || '');
                        if (existing !== '') {
                            reserved.add(existing);
                        }
                    });

                    let uniqueSku = seed;
                    let suffix = 2;
                    while (reserved.has(uniqueSku)) {
                        uniqueSku = `${seed}-${suffix}`;
                        suffix += 1;
                    }

                    return uniqueSku;
                },
                syncBaseSkuFromSlug() {
                    if (this.baseSkuTouched) {
                        return;
                    }

                    const candidate = String(this.slug || this.title || '').trim();
                    if (candidate === '') {
                        return;
                    }

                    this.baseSku = this.ensureUniqueBaseSku(candidate);
                },
                syncVariantSku(index) {
                    const variant = this.variants[index];
                    if (!variant) {
                        return;
                    }

                    variant.sku = this.ensureUniqueVariantSku(this.variantSkuSeed(variant, index), index);
                },
                addVariant() {
                    this.variants.push({
                        id: null,
                        name: '',
                        description: '',
                        sku: '',
                        price: '',
                        compare_at_price: '',
                        inventory_quantity: '',
                        awaiting_fulfilment: 0,
                        reserved_quantity: 0,
                        allow_backorder: false,
                        backorder_shipping_estimate_type: '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC }}',
                        backorder_shipping_estimate: '',
                        backorder_shipping_offset_days: '',
                        sort_order: this.variants.length,
                        is_active: true,
                    });
                },
                removeVariant(index) {
                    this.variants.splice(index, 1);
                    this.variants = this.variants.map((variant, currentIndex) => ({
                        ...variant,
                        sort_order: variant.sort_order === '' || variant.sort_order === null ? currentIndex : variant.sort_order,
                    }));
                },
                init() {
                    this.syncSlugFromTitle();
                    this.syncBaseSkuFromSlug();
                    this.$watch('productType', (value) => {
                        if (value === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}') {
                            this.allowBackorder = false;
                        }
                    });
                    this.$watch('status', (value) => {
                        if (value !== '{{ \App\Models\Product::STATUS_ACTIVE }}') {
                            this.isFeatured = false;
                        }
                    });
                },
            }"
        >
            @csrf
            @isset($product)
                @method('PUT')
            @endisset

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$product->title ?? ''" x-model="title" x-on:blur="handleTitleInput()" />
                    <x-ui.input name="slug" label="Slug" :value="$product->slug ?? ''" x-model="slug" x-on:input="handleSlugInput()" />
                </div>
                <x-ui.input
                    name="subtitle"
                    label="Subtitle"
                    :value="$product->subtitle ?? ''"
                />
                <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Categories</h3>
                            <p class="text-xs text-gray-500">Assign any number of managed categories to this product.</p>
                        </div>
                        <x-ui.button href="{{ route('admin.shop.category.index') }}" color="outline" class="shrink-0">Manage Categories</x-ui.button>
                    </div>

                    @if(($categories ?? collect())->isEmpty())
                        <p class="mt-3 text-sm text-gray-600">Create product categories first, then come back and assign them here.</p>
                    @else
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($categories as $category)
                                @php
                                    $categoryId = (string) $category->id;
                                    $isSelected = in_array($categoryId, $selectedCategoryIds, true);
                                @endphp
                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm transition hover:border-primary-color hover:text-primary-color">
                                    <input
                                        type="checkbox"
                                        name="category_ids[]"
                                        value="{{ $category->id }}"
                                        @checked($isSelected)
                                        class="mt-1 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                    >
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                                        <i class="{{ $category->iconClass() }}"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-gray-900">{{ $category->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $category->slug }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <x-ui.input name="sku" label="Base SKU" :value="$product->sku ?? ''" x-model="baseSku" x-on:input="handleBaseSkuInput()" required info="Required. Used on orders and inventory records." />
                    <x-ui.select
                        name="status"
                        label="Status"
                        x-model="status"
                    >
                        @foreach(\App\Models\Product::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $product->status ?? \App\Models\Product::STATUS_DRAFT) === $status)>{{ \App\Models\Product::statusLabel($status) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.checkbox
                        name="is_featured"
                        label="Featured product"
                        :checked="(bool) old('is_featured', $product->is_featured ?? false)"
                        class="mt-7"
                        x-model="isFeatured"
                        x-bind:disabled="status !== '{{ \App\Models\Product::STATUS_ACTIVE }}'"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.select name="product_type" label="Product Type" x-model="productType">
                        @foreach(\App\Models\Product::PRODUCT_TYPES as $type)
                            <option value="{{ $type }}">{{ \App\Models\Product::productTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="sort_order" label="Sort Order" type="number" min="0" :value="$product->sort_order ?? 0" />
                </div>
                <x-ui.input name="short_description" label="Short Description" :value="$product->short_description ?? ''" />
                <x-ui.editor name="description" label="Description" :value="$productDescription" />
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Item Price and Inventory</h2>
                    </div>
                </div>

                <div class="grid md:gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <x-ui.input name="price" label="Base Price" labelInfo="(inc GST)" moneyFormat="true" :value="isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00'" x-model="basePrice" />
                        <x-ui.input name="compare_at_price" label="Recommended Price" labelInfo="(Optional)" moneyFormat="true" :value="isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : ''" x-model="baseCompareAtPrice" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                            <x-ui.input name="inventory_quantity" label="Base Inventory Quantity" type="number" min="0" :value="$product->inventory_quantity ?? ''" info="Leave blank for unlimited." class="mb-0!" />
                        </div>
                        <div class="my-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <x-ui.checkbox
                                    name="allow_backorder"
                                    label="Allow back ordering"
                                    :checked="$productAllowsBackorder"
                                    x-model="allowBackorder"
                                    noWrapper
                            />
                            <div class="mt-4" x-show="allowBackorder" x-cloak>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <x-ui.select
                                        name="backorder_shipping_estimate_type"
                                        label="Backorder Estimate Type"
                                        x-model="productBackorderEstimateType"
                                    >
                                        <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC }}">Specific date</option>
                                        <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}">Days from today</option>
                                    </x-ui.select>

                                    <div x-show="productBackorderEstimateType === '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                        <x-ui.input
                                            name="backorder_shipping_offset_days"
                                            type="number"
                                            min="1"
                                            step="1"
                                            label="Days from today"
                                            :value="$productBackorderOffsetDays"
                                            x-model="productBackorderOffsetDays"
                                        />
                                    </div>

                                    <div x-show="productBackorderEstimateType !== '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                        <x-ui.input
                                            name="backorder_shipping_estimate"
                                            type="date"
                                            label="Shipping Date"
                                            :value="$productBackorderEstimate"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if(isset($product) && (string) ($product->product_type ?? '') === \App\Models\Product::PRODUCT_TYPE_PHYSICAL)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
                                <div><span class="font-semibold">Awaiting fulfilment:</span> {{ $baseInventoryContext['awaiting'] }}</div>
                                <div class="mt-1"><span class="font-semibold">Reserved now:</span> {{ $baseInventoryContext['reserved'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Packaging</h2>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div x-bind:class="boxOnly ? 'opacity-60' : ''">
                        <x-ui.input
                            name="shipping_units"
                            label="Package Units"
                            type="number"
                            step="0.001"
                            min="0"
                            :value="isset($product) ? number_format((float) $product->shipping_units, 3, '.', '') : '0.000'"
                            info="The size of this item compared to capacity of the smallest package."
                            x-model="baseShippingUnits"
                            x-bind:disabled="boxOnly"
                        />
                        <template x-if="boxOnly">
                            <input type="hidden" name="shipping_units" x-bind:value="baseShippingUnits">
                        </template>
                    </div>
                    <div x-bind:class="boxOnly ? 'opacity-60' : ''">
                        <x-ui.select
                            name="min_satchel_rank"
                            label="Minimum Package Size"
                            info="The smallest package size this product can fit into."
                            x-model="baseMinSatchelRank"
                            x-bind:disabled="boxOnly"
                        >
                            @foreach($satchelOptions as $satchel)
                                <option value="{{ $satchel['rank'] }}" @selected((int) old('min_satchel_rank', $product->min_satchel_rank ?? $defaultSatchelRank) === (int) $satchel['rank'])>
                                    {{ $satchel['label'] }} (capacity {{ number_format((float) $satchel['capacity'], 2) }})
                                </option>
                            @endforeach
                        </x-ui.select>
                        <template x-if="boxOnly">
                            <input type="hidden" name="min_satchel_rank" x-bind:value="baseMinSatchelRank">
                        </template>
                    </div>
                    <x-ui.input
                        name="weight_grams"
                        label="Packed Weight"
                        labelInfo="(grams, optional)"
                        type="number"
                        min="0"
                        :value="$product->weight_grams ?? ''"
                    />
                    <div class="pt-5">
                        <x-ui.checkbox
                            name="box_only"
                            label="Requires rigid parcel shipping"
                            :checked="(bool) old('box_only', $product->box_only ?? false)"
                            class="mt-2"
                            x-model="boxOnly"
                        />
                    </div>
                </div>
            </div>

            <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                Digital products ignore parcel packing and shipping fields, unlock their download files after payment, and can use licence tiers when you want to sell different usage rights.
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Licence Tiers' : 'Variants'"></h2>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak class="text-sm text-gray-600">Use variants for option-level differences like colour or size. Physical variants share the base product price and packaging, and only change their own name, SKU, details, stock, and fulfilment status.</p>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="text-sm text-gray-600">Digital variants act as licence tiers. Add only the extra tiers you want to offer.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addVariant()" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Add Custom Tier' : 'Add Variant'">Add Variant</x-ui.button>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4" x-show="variants.length > 0" x-cloak>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-900" x-text="baseOptionDisplayName()"></div>
                            <div class="mt-1 text-sm text-gray-600">This option uses the base SKU, price, stock, packaging, and weight set above.</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block pl-1 text-sm">Base Option Name</label>
                            <input type="text" x-bind:class="variantInputClasses" name="base_variant_name" x-model="baseVariantName">
                            <div class="mt-1 pl-1 text-xs text-gray-500" x-text="'Leave blank to show ' + defaultBaseOptionLabel() + '.'"></div>
                            @error('base_variant_name')
                                <div class="mt-1 pl-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="mb-1 block pl-1 text-sm" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Base Licence Details' : 'Base Option Details'"></label>
                            <textarea x-bind:class="variantTextareaClasses" name="base_variant_description" rows="3">{{ old('base_variant_description', $product->base_variant_description ?? '') }}</textarea>
                            @error('base_variant_description')
                                <div class="mt-1 pl-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <template x-for="(variant, index) in variants" :key="index">
                        <div class="rounded-2xl border border-gray-200 p-4 space-y-4 bg-gray-50">
                            <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">
                            <input type="hidden" :name="`variants[${index}][is_active]`" :value="variant.is_active ? 1 : 0">

                            <div class="flex items-center justify-between gap-4">
                                <div class="text-lg font-semibold text-gray-900" x-text="displayVariantName(variant, index)"></div>
                                <button type="button" class="text-sm text-red-600 hover:underline" x-on:click="removeVariant(index)">Remove</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Variant Name</label>
                                    <input type="text" x-bind:class="variantInputClasses" :name="`variants[${index}][name]`" x-model="variant.name" x-on:blur="syncVariantSku(index)">
                                    <div class="mt-1 pl-1 text-xs text-gray-500">Each added option needs its own name.</div>
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">SKU</label>
                                    <input type="text" x-bind:class="variantInputClasses" :name="`variants[${index}][sku]`" x-model="variant.sku">
                                </div>
                                <x-ui.checkbox
                                        label="Is Active"
                                        x-model="variant.is_active"
                                        class="mt-7"
                                />
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Sort Order</label>
                                    <input type="number" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][sort_order]`" x-model="variant.sort_order">
                                </div>
                                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak>
                                    <label class="mb-1 block pl-1 text-sm">Price</label>
                                    <input type="number" step="0.01" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][price]`" x-model="variant.price">
                                </div>
                                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak>
                                    <label class="mb-1 block pl-1 text-sm">Compare-at Price</label>
                                    <input type="number" step="0.01" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block pl-1 text-sm" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Licence Details' : 'Variant Details'"></label>
                                <textarea x-bind:class="variantTextareaClasses" :name="`variants[${index}][description]`" x-model="variant.description" :placeholder="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Describe who this licence tier covers and where it may be used.' : 'Optional extra notes for this option.'"></textarea>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Inventory Quantity</label>
                                    <x-ui.input
                                        type="number"
                                        min="0"
                                        noLabel="true"
                                        class="mb-0"
                                        fieldClasses="mt-0"
                                        x-bind:name="`variants[${index}][inventory_quantity]`"
                                        x-model="variant.inventory_quantity"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4">
                                    <x-ui.checkbox
                                        label="Allow back ordering"
                                        noWrapper="true"
                                        inline="true"
                                        x-bind:name="`variants[${index}][allow_backorder]`"
                                        x-model="variant.allow_backorder"
                                    />
                                    <div class="mt-4" x-show="variant.allow_backorder" x-cloak>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block pl-1 text-sm">Backorder Estimate Type</label>
                                                <select x-bind:class="variantInputClasses" :name="`variants[${index}][backorder_shipping_estimate_type]`" x-model="variant.backorder_shipping_estimate_type">
                                                    <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC }}">Specific date</option>
                                                    <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}">Days from today</option>
                                                </select>
                                            </div>

                                            <div x-show="variant.backorder_shipping_estimate_type === '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                                <label class="mb-1 block pl-1 text-sm">Days from today</label>
                                                <input type="number" min="1" step="1" x-bind:class="variantInputClasses" :name="`variants[${index}][backorder_shipping_offset_days]`" x-model="variant.backorder_shipping_offset_days">
                                            </div>

                                            <div x-show="variant.backorder_shipping_estimate_type !== '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                                <label class="mb-1 block pl-1 text-sm">Shipping Date</label>
                                                <input type="date" x-bind:class="variantInputClasses" :name="`variants[${index}][backorder_shipping_estimate]`" x-model="variant.backorder_shipping_estimate">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950" x-show="variant.id" x-cloak>
                                    <div><span class="font-semibold">Awaiting fulfilment:</span> <span x-text="Number(variant.awaiting_fulfilment || 0)"></span></div>
                                    <div class="mt-1"><span class="font-semibold">Reserved now:</span> <span x-text="Number(variant.reserved_quantity || 0)"></span></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <x-ui.media label="Hero Image" name="hero_media_name" value="{{ $product->hero_media_name ?? '' }}" allow_uploads="true" />
                <x-ui.gallery name="gallery_files" label="Gallery" value="{{ $galleryFilesValue }}" editor="true" />
                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak>
                    <x-ui.filelist name="download_files" label="Digital Download Files" :value="$downloadFilesValue" editor="true" />
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Admin Notes & Alerts</h2>
                        <p class="text-sm text-gray-600">Private notes stay in admin only. Low-stock alerts help surface products that need ordering attention.</p>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr),minmax(0,0.9fr)]">
                    <x-ui.input
                            type="textarea"
                            name="private_notes"
                            label="Private Notes"
                            :value="$product->private_notes ?? ''"
                    />

                    <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                        <x-ui.input
                                name="low_stock_threshold"
                                label="Low-stock alert threshold"
                                type="number"
                                min="1"
                                :value="old('low_stock_threshold', $product->low_stock_threshold ?? 5)"
                                info="Leave blank to disable low-stock warning emails for this product."
                        />
                        @if(isset($product) && $product->low_stock_alert_sent_at)
                            <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
                                <span class="font-semibold">Last low-stock alert:</span>
                                {{ $product->low_stock_alert_sent_at->format('M j, Y g:i a') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3 flex-col-reverse sm:flex-row-reverse justify-between">
                <div class="flex flex-col sm:flex-row gap-3">
                    @isset($product)
                        <x-ui.button href="{{ route('shop.product.show', $product) }}" color="outline">View Product</x-ui.button>
                    @endisset
                    <x-ui.button type="submit">Save Product</x-ui.button>
                </div>
                @isset($product)
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-danger-color px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition hover:bg-danger-color-dark"
                        x-data
                        x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete product?', 'Are you sure you want to delete this product? This action cannot be undone.', '{{ route('admin.shop.product.destroy', $product) }}')"
                    >Delete Product</button>
                @endisset
            </div>
        </form>
    </x-container>
</x-layout>
