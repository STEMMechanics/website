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
    $defaultProductDetails = [
        ['key' => 'Pack size', 'value' => ''],
        ['key' => 'Material', 'value' => ''],
        ['key' => 'Colour', 'value' => ''],
        ['key' => 'Recommended age', 'value' => ''],
    ];
    $productDetailRows = collect(old('product_details', isset($product) ? ($product->product_details ?? []) : $defaultProductDetails))
        ->map(fn ($detail) => [
            'key' => (string) data_get($detail, 'key', ''),
            'value' => (string) data_get($detail, 'value', ''),
        ])
        ->values();
    [$skuProductDetailRows, $otherProductDetailRows] = $productDetailRows->partition(fn (array $detail): bool => mb_strtolower(trim($detail['key'])) === 'sku');
    $skuProductDetail = $skuProductDetailRows->first();
    $productDetailRows = $otherProductDetailRows->push([
        'key' => 'SKU',
        'value' => trim((string) data_get($skuProductDetail, 'value', '')) ?: '{sku}',
    ])->values()->all();
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
                    'product_details' => collect($variant->product_details ?? [])->map(fn ($detail) => [
                        'key' => (string) data_get($detail, 'key', ''),
                        'value' => (string) data_get($detail, 'value', ''),
                    ])->values()->all(),
                    'sku' => $variant->sku,
                    'price' => $variant->price !== null ? number_format((float) $variant->price, 2, '.', '') : '',
                    'compare_at_price' => $variant->compare_at_price !== null ? number_format((float) $variant->compare_at_price, 2, '.', '') : '',
                    'inventory_quantity' => $variant->inventory_quantity,
                    'inventory_units' => $variant->inventory_units ?? 1,
                    'weight_grams' => $variant->weight_grams,
                    'length_mm' => $variant->length_mm,
                    'width_mm' => $variant->width_mm,
                    'height_mm' => $variant->height_mm,
                    'low_stock_threshold' => $variant->low_stock_threshold,
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
                    'product_details' => collect(data_get($variant, 'product_details', []))->map(fn ($detail) => [
                        'key' => (string) data_get($detail, 'key', ''),
                        'value' => (string) data_get($detail, 'value', ''),
                    ])->values()->all(),
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
    $variantRows = collect($variantRows)->map(function ($variant): array {
        $variant = is_array($variant) ? $variant : [];
        $details = collect($variant['product_details'] ?? []);
        [$skuRows, $otherRows] = $details->partition(fn ($detail): bool => mb_strtolower(trim((string) data_get($detail, 'key', ''))) === 'sku');

        return array_merge($variant, ['product_details' => $otherRows->concat($skuRows)->values()->all()]);
    })->values()->all();
    $productAllowsBackorder = (bool) old('allow_backorder', isset($product) ? ((bool) $product->allow_backorder || (bool) $product->is_preorder) : false);
    $productBackorderEstimate = old('backorder_shipping_estimate', isset($product)
        ? ($product->backorder_shipping_estimate?->format('Y-m-d') ?? $product->preorder_shipping_estimate?->format('Y-m-d') ?? '')
        : '');
@endphp
<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">{{ isset($product) ? 'Edit' : 'Create' }} Product
        @isset($product)
            <x-slot:actions>
                <x-ui.button href="{{ route('shop.product.show', $product) }}" color="mast"><i class="fa-solid fa-arrow-up-right-from-square mr-2" aria-hidden="true"></i>View Product</x-ui.button>
            </x-slot:actions>
        @endisset
    </x-mast>

    <x-container class="mt-4">
        <x-admin.ai-status-toast id="product-ai-toast" message="Preparing product copy…" detail="Your current product details are being used to draft the update." progress-label="Product content generation" />
        <form
            id="product-form"
            x-on:sm-product-specifications-ai.window="mergeAiProductDetails($event.detail.details)"
            x-on:invalid.capture="let section = $event.target.closest('details'); while (section) { section.open = true; section = section.parentElement.closest('details'); }"
            method="POST"
            action="{{ route('admin.shop.product.'.(isset($product) ? 'update' : 'store'), $product ?? []) }}"
            x-data="{
                sharedInventory: @js((bool) old('shared_inventory', $product->shared_inventory ?? false)),
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
                basePackedLength: @js(old('length_mm', $product->length_mm ?? '')),
                basePackedWidth: @js(old('width_mm', $product->width_mm ?? '')),
                basePackedHeight: @js(old('height_mm', $product->height_mm ?? '')),
                basePackedWeight: @js(old('weight_grams', $product->weight_grams ?? '')),
                basePrice: @js(old('price', isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00')),
                baseCompareAtPrice: @js(old('compare_at_price', isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : '')),
                baseShippingUnits: @js(old('shipping_units', isset($product) ? number_format((float) $product->shipping_units, 3, '.', '') : '0.000')),
                baseMinSatchelRank: @js((string) old('min_satchel_rank', $product->min_satchel_rank ?? $defaultSatchelRank)),
                baseVariantName: @js(old('base_variant_name', $product->base_variant_name ?? '')),
                productBackorderEstimateType: @js($productBackorderEstimateType),
                productBackorderOffsetDays: @js((string) $productBackorderOffsetDays),
                variants: @js($variantRows),
                productDetails: @js($productDetailRows),
                variantInputClasses: 'disabled:bg-gray-100 bg-white block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                variantTextareaClasses: 'disabled:bg-gray-100 bg-white block min-h-28 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
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
                        product_details: [],
                        sku: '',
                        price: '',
                        compare_at_price: '',
                        inventory_quantity: '',
                        inventory_units: 1,
                        weight_grams: '',
                        length_mm: '',
                        width_mm: '',
                        height_mm: '',
                        low_stock_threshold: '',
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
                addVariantProductDetail(index) {
                    this.variants[index].product_details ??= [];
                    const details = this.variants[index].product_details;
                    const skuIndex = details.findIndex((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku');
                    details.splice(skuIndex < 0 ? details.length : skuIndex, 0, { key: '', value: '' });
                },
                addVariantProductDetailAfterTab(event, variantIndex, detailIndex) {
                    const details = this.variants[variantIndex]?.product_details || [];
                    const lastEditableIndex = details.filter((detail) => String(detail?.key || '').trim().toLowerCase() !== 'sku').length - 1;
                    if (event.shiftKey || detailIndex !== lastEditableIndex) {
                        return;
                    }

                    event.preventDefault();
                    this.addVariantProductDetail(variantIndex);
                    this.$nextTick(() => {
                        const inputs = Array.from(this.$root.querySelectorAll('[data-variant-detail-key]'));
                        inputs.find((input) => input.dataset.variantDetailKey === `${variantIndex}-${detailIndex + 1}`)?.focus();
                    });
                },
                removeVariantProductDetail(variantIndex, detailIndex) {
                    this.variants[variantIndex].product_details.splice(detailIndex, 1);
                },
                addProductDetail() {
                    const skuIndex = this.productDetails.findIndex((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku');
                    this.productDetails.splice(skuIndex < 0 ? this.productDetails.length : skuIndex, 0, { key: '', value: '' });
                },
                addProductDetailAfterTab(event, index) {
                    const lastEditableIndex = this.productDetails.filter((detail) => String(detail?.key || '').trim().toLowerCase() !== 'sku').length - 1;
                    if (event.shiftKey || index !== lastEditableIndex) {
                        return;
                    }

                    event.preventDefault();
                    this.addProductDetail();
                    this.$nextTick(() => {
                        const inputs = this.$root.querySelectorAll('[data-product-detail-key]');
                        inputs[index + 1]?.focus();
                    });
                },
                removeProductDetail(index) {
                    if (String(this.productDetails[index]?.key || '').trim().toLowerCase() === 'sku') return;
                    this.productDetails.splice(index, 1);
                },
                skuLastDetails(details) {
                    const rows = Array.isArray(details) ? details : [];
                    const isSku = (detail) => String(detail?.key || '').trim().toLowerCase() === 'sku';
                    return [...rows.filter((detail) => !isSku(detail)), ...rows.filter(isSku)];
                },
                ensureBaseSkuDetail(details) {
                    const rows = Array.isArray(details) ? details : [];
                    const isSku = (detail) => String(detail?.key || '').trim().toLowerCase() === 'sku';
                    const existingSku = rows.find(isSku);
                    const skuRow = existingSku
                        ? { ...existingSku, key: 'SKU', value: String(existingSku.value || '').trim() || '{sku}' }
                        : { key: 'SKU', value: '{sku}' };

                    return [...rows.filter((detail) => !isSku(detail)), skuRow];
                },
                productDetailKeyIdentity(key) {
                    const normalized = String(key || '').trim().toLowerCase().normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, ' ')
                        .trim();
                    const comparable = normalized
                        .replace(/\bbatteries\b/g, 'battery')
                        .replace(/\b(?:include|includes|included)\b/g, 'included');

                    return comparable === 'battery included' ? comparable : normalized;
                },
                productDetailKeyLabel(key) {
                    return this.productDetailKeyIdentity(key) === 'battery included'
                        ? 'Batteries included'
                        : String(key || '').trim();
                },
                normalizeBaseProductDetails(details) {
                    const rows = Array.isArray(details) ? details : [];
                    const nonSkuRows = rows.filter((detail) => String(detail?.key || '').trim().toLowerCase() !== 'sku');
                    const normalizedRows = [];
                    const positions = new Map();

                    nonSkuRows.forEach((detail) => {
                        const key = String(detail?.key || '').trim();
                        if (!key) {
                            normalizedRows.push(detail);
                            return;
                        }

                        const identity = this.productDetailKeyIdentity(key);
                        if (positions.has(identity)) {
                            const existing = normalizedRows[positions.get(identity)];
                            if (!String(existing?.value || '').trim() && String(detail?.value || '').trim()) {
                                existing.value = detail.value;
                            }
                            return;
                        }

                        positions.set(identity, normalizedRows.length);
                        normalizedRows.push({ ...detail, key: this.productDetailKeyLabel(key) });
                    });

                    return this.ensureBaseSkuDetail([...normalizedRows, ...rows.filter((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku')]);
                },
                productAiContext() {
                    const form = document.getElementById('product-form');
                    if (!form) return {};
                    const read = (name) => form.elements.namedItem(name)?.value ?? '';
                    const detailContext = (details, limit, valueLimit) => (Array.isArray(details) ? details : [])
                        .filter((detail) => String(detail?.key || '').trim() !== '' && String(detail?.value || '').trim() !== '')
                        .slice(0, limit)
                        .map((detail) => ({ key: String(detail.key).trim().slice(0, 100), value: String(detail.value).trim().slice(0, valueLimit) }));
                    const categories = Array.from(form.querySelectorAll('input:checked'))
                        .filter((input) => input.name === 'category_ids[]')
                        .map((input) => input.closest('div')?.querySelector('span.block.font-medium')?.textContent?.trim() || '')
                        .filter(Boolean)
                        .slice(0, 10);

                    return {
                        product_type: this.productType,
                        sku: String(this.baseSku || '').slice(0, 120),
                        base_option: {
                            name: String(this.baseVariantName || '').slice(0, 100),
                            description: String(read('base_variant_description')).slice(0, 500),
                        },
                        search_terms: String(read('search_terms')).slice(0, 1500),
                        categories,
                        product_details: detailContext(this.productDetails, 20, 200),
                        variants: this.variants.slice(0, 10).map((variant) => ({
                            name: String(variant.name || '').slice(0, 100),
                            sku: String(variant.sku || '').slice(0, 120),
                            description: String(variant.description || '').slice(0, 250),
                            product_details: detailContext(variant.product_details, 5, 200),
                        })),
                    };
                },
                isVariantSpecificProductDetailValue(key, proposedValue, currentValue) {
                    const identity = this.productDetailKeyIdentity(key);
                    const proposed = String(proposedValue || '').trim().toLowerCase();
                    const current = String(currentValue || '').trim().toLowerCase();

                    return this.variants.some((variant) => {
                        const override = (Array.isArray(variant.product_details) ? variant.product_details : [])
                            .find((detail) => this.productDetailKeyIdentity(detail?.key) === identity);
                        if (override) {
                            const overrideValue = String(override.value || '').trim().toLowerCase();
                            if (overrideValue === proposed && proposed !== current) return true;
                        }

                        if (identity !== 'pack size') return false;
                        const proposedCount = proposed.match(/\d+(?:[.,]\d+)?/)?.[0];
                        if (!proposedCount) return false;
                        const variantName = String(variant.name || '').toLowerCase();
                        const variantCounts = variantName.match(/\d+(?:[.,]\d+)?/g) || [];

                        return variantCounts.includes(proposedCount)
                            && /\b(?:pack|packs|holder|holders|unit|units|pieces?|count)\b/.test(variantName);
                    });
                },
                mergeAiProductDetails(details) {
                    const currentSku = this.productDetails.find((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku');
                    const incomingSku = (Array.isArray(details) ? details : []).find((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku');
                    const skuRows = [currentSku || incomingSku || { key: 'SKU', value: '{sku}' }];
                    const merged = this.productDetails.filter((detail) => String(detail?.key || '').trim().toLowerCase() !== 'sku');
                    const nonSkuLimit = Math.max(0, 30 - skuRows.length);
                    const seenIncoming = new Set();

                    (Array.isArray(details) ? details : []).forEach((detail) => {
                        const key = String(detail?.key || '').trim();
                        const value = String(detail?.value || '').trim();
                        if (!key || !value || key.toLowerCase() === 'sku') return;
                        const identity = this.productDetailKeyIdentity(key);
                        if (seenIncoming.has(identity)) return;
                        seenIncoming.add(identity);

                        const current = merged.find((row) => this.productDetailKeyIdentity(row?.key) === identity);
                        if (current) {
                            current.key = this.productDetailKeyLabel(current.key);
                            if (this.isVariantSpecificProductDetailValue(key, value, current.value)) return;
                            current.value = value;
                        } else if (merged.length < nonSkuLimit) {
                            merged.push({ key: this.productDetailKeyLabel(key), value });
                        }
                    });

                    this.productDetails = this.normalizeBaseProductDetails([...merged, ...skuRows]);
                },
                moveProductDetail(index, direction) {
                    const skuRows = this.productDetails.filter((detail) => String(detail?.key || '').trim().toLowerCase() === 'sku');
                    const movableRows = this.productDetails.filter((detail) => String(detail?.key || '').trim().toLowerCase() !== 'sku');
                    const targetIndex = index + direction;

                    if (index >= movableRows.length || targetIndex < 0 || targetIndex >= movableRows.length) {
                        return;
                    }

                    const [detail] = movableRows.splice(index, 1);
                    movableRows.splice(targetIndex, 0, detail);
                    this.productDetails = [...movableRows, ...skuRows];
                },
                init() {
                    this.productDetails = this.normalizeBaseProductDetails(this.productDetails);
                    this.variants = this.variants.map((variant) => ({
                        ...variant,
                        product_details: this.skuLastDetails(variant.product_details || []),
                    }));
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

            <x-ui.collapsible-section title="Product" variant="product" :open="true">
                <x-slot:summary><span x-text="title || 'Product information'"></span></x-slot:summary>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$product->title ?? ''" x-model="title" x-on:blur="handleTitleInput()" />
                    <x-ui.input name="slug" label="Slug" :value="$product->slug ?? ''" x-model="slug" x-on:input="handleSlugInput()" />
                </div>
                <x-ui.input
                    name="subtitle"
                    label="Subtitle"
                    :value="$product->subtitle ?? ''"
                />

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
            </x-ui.collapsible-section>

            <x-ui.collapsible-section title="Categories" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ $categories->whereIn('id', $selectedCategoryIds)->pluck('name')->implode(', ') ?: 'No categories selected' }}</x-slot:summary>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
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
                                <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm transition hover:border-primary-color hover:text-primary-color">
                                    <x-ui.checkbox
 id="category-{{ $category->id }}"
 name="category_ids[]"
 value="{{ $category->id }}"
 :checked="$isSelected"
 noWrapper
 inputClass="mt-0.5"
 />
                                    <label for="category-{{ $category->id }}" class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                                            <i class="{{ $category->iconClass() }}"></i>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-gray-900">{{ $category->name }}</span>
                                            <span class="block text-xs text-gray-500">{{ $category->slug }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
            </x-ui.collapsible-section>
            <x-ui.collapsible-section title="Description" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ \Illuminate\Support\Str::limit(strip_tags($product->short_description ?? $productDescription), 90) ?: 'No description added' }}</x-slot:summary>
                <x-ui.input name="short_description" label="Short Description" :value="$product->short_description ?? ''" />
                <x-ui.editor name="description" label="Description" :value="$productDescription">
                    <x-slot:toolbar>
                        <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-copy" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Improving product copy…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" data-ai-fill-scope="#product-form" data-ai-fill-fields="title,short_description,description" data-ai-editor-field="description" data-ai-editor-format="product-description" :disabled="blank(config('services.openai.api_key'))" aria-label="Improve product copy with AI" title="Improve product copy with AI">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </x-ui.button>
                    </x-slot:toolbar>
                </x-ui.editor>
                <x-ui.input
                    type="textarea"
                    name="search_terms"
                    label="Search Terms"
                    :value="$product->search_terms ?? ''"
                    rows="3"
                    info="Alternative names and related words, separated by spaces or commas. Used by site search and product-page metadata; not shown in the product description."
                />

                <div class="mb-4">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label for="caution_message" class="flex items-center text-sm pl-1">Product Warning</label>
                        <x-ui.button type="button" variant="plain" class="inline-flex size-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-warning" data-ai-kind="warning" data-ai-result-key="warning" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Checking product warning…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" :disabled="blank(config('services.openai.api_key'))" aria-label="Draft a short product warning" title="Draft a short warning if the product details support one">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </x-ui.button>
                    </div>
                    <x-ui.input
                        name="caution_message"
                        type="textarea"
                        rows="3"
                        noLabel="true"
                        :value="$product->caution_message ?? ''"
                        info="Optional. Displayed to customers with a caution icon."
                        placeholder="Not suitable for children under 3 years."
                        class="mb-0"
                    />
                </div>
            </x-ui.collapsible-section>
            <x-ui.collapsible-section title="Specifications" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary><span x-text="productDetails.filter(detail => detail.key || detail.value).length + ' product details'"></span></x-slot:summary>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="mt-1 text-xs text-gray-500">Add specifications such as pack size, material, colour, and recommended age. Values may include <code>{sku}</code>, which follows the selected variant.</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui.button type="button" variant="plain" class="inline-flex size-9 items-center justify-center rounded-lg text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-specifications" data-ai-kind="specifications" data-ai-result-key="product_details" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Drafting product specifications…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" :disabled="blank(config('services.openai.api_key'))" aria-label="Create or update specifications with AI" title="Create or update supported specifications with AI">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            </x-ui.button>
                            <x-ui.button type="button" color="outline" x-on:click="addProductDetail()">Add Detail</x-ui.button>
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white" x-show="productDetails.length > 0" x-cloak>
                        <x-ui.table variant="plain" table-class="w-full table-fixed border-collapse">
                            <thead class="bg-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="w-2/5 px-3 py-2">Detail</th>
                                    <th class="px-3 py-2">Value</th>
                                    <th class="w-28 px-2 py-2"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(detail, index) in productDetails" :key="index">
                                    <tr>
                                        <td class="border-r border-gray-200 p-0">
                                            <x-ui.input-control type="text" x-bind:name="`product_details[${index}][key]`" x-model="detail.key" x-on:blur="productDetails = normalizeBaseProductDetails(productDetails)" x-bind:readonly="String(detail.key || '').trim().toLowerCase() === 'sku'" data-product-detail-key class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300 readonly:bg-gray-50 readonly:text-gray-500" placeholder="Detail name" />
                                        </td>
                                        <td class="border-r border-gray-200 p-0">
                                            <x-ui.input-control type="text" x-bind:name="`product_details[${index}][value]`" x-model="detail.value" x-bind:readonly="String(detail.key || '').trim().toLowerCase() === 'sku'" class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300 readonly:bg-gray-50 readonly:text-gray-500" placeholder="Value" x-on:keydown.tab="addProductDetailAfterTab($event, index)" />
                                        </td>
                                        <td class="p-0 text-center">
                                            <x-ui.button variant="plain" type="button" class="inline-flex h-9 w-7 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-25" x-on:click="moveProductDetail(index, -1)" x-bind:disabled="index === 0 || String(detail.key || '').trim().toLowerCase() === 'sku'" title="Move detail up" aria-label="Move detail up">
                                                <i class="fa-solid fa-arrow-up text-xs" aria-hidden="true"></i>
                                            </x-ui.button>
                                            <x-ui.button variant="plain" type="button" class="inline-flex h-9 w-7 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-25" x-on:click="moveProductDetail(index, 1)" x-bind:disabled="index >= productDetails.filter(row => String(row.key || '').trim().toLowerCase() !== 'sku').length - 1 || String(detail.key || '').trim().toLowerCase() === 'sku'" title="Move detail down" aria-label="Move detail down">
                                                <i class="fa-solid fa-arrow-down text-xs" aria-hidden="true"></i>
                                            </x-ui.button>
                                            <x-ui.button variant="plain" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-25" x-on:click="removeProductDetail(index)" x-bind:disabled="String(detail.key || '').trim().toLowerCase() === 'sku'" x-bind:title="String(detail.key || '').trim().toLowerCase() === 'sku' ? 'SKU is always kept last' : 'Remove detail'" title="Remove detail" aria-label="Remove detail">
                                                <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </x-ui.table>
                    </div>
                    <p class="mt-4 text-sm text-gray-500" x-show="productDetails.length === 0">No structured details added.</p>
            </x-ui.collapsible-section>
            <x-ui.collapsible-section title="Item Price and Inventory" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary><span x-text="'$' + Number(basePrice || 0).toFixed(2) + ' · ' + (allowBackorder ? 'Back orders allowed' : 'No back orders')"></span></x-slot:summary>

                <div class="grid gap-4 md:grid-cols-2" data-product-price-inventory>
                    <x-ui.input name="price" label="Base Price" labelInfo="(inc GST)" moneyFormat="true" :value="isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00'" x-model="basePrice" class="mb-0" />
                    <x-ui.input name="compare_at_price" label="Recommended Price" labelInfo="(inc GST, optional)" moneyFormat="true" :value="isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : ''" x-model="baseCompareAtPrice" class="mb-0" />
                    <div class="md:col-span-2" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                        <x-ui.checkbox name="shared_inventory" label="Share stock across all packs and variants" x-model="sharedInventory" :checked="old('shared_inventory', $product->shared_inventory ?? false)" />
                        <p class="mb-3 text-xs text-gray-500" x-show="sharedInventory" x-cloak>Enter the total number of individual units below. Set the units in each pack in the Variants panel below.</p>
                        <div class="grid items-start gap-4 md:grid-cols-2">
                            <x-ui.input name="inventory_quantity" label="Inventory Quantity" type="number" min="0" :value="$product->inventory_quantity ?? ''" info="Leave blank for unlimited." class="mb-0" />
                        </div>
                    </div>
                        <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
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
                            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
                                <div><span class="font-semibold">Awaiting fulfilment:</span> {{ $baseInventoryContext['awaiting'] }}</div>
                                <div class="mt-1"><span class="font-semibold">Reserved now:</span> {{ $baseInventoryContext['reserved'] }}</div>
                            </div>
                        @endif
                </div>
            </x-ui.collapsible-section>

            <x-ui.collapsible-section title="Packaging" variant="product" :open="!isset($product) || $errors->any()" x-show="productType === 'physical'" x-cloak>
                <x-slot:summary><span x-text="boxOnly ? 'Rigid parcel shipping' : 'Standard packaging'"></span></x-slot:summary>

                <input type="hidden" name="shipping_units" value="0" step="0.001">
                <input type="hidden" name="min_satchel_rank" value="1">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input x-model="basePackedLength" name="length_mm" label="Packed Length" labelInfo="(mm)" type="number" step="1" min="1" :value="old('length_mm', $product->length_mm ?? '')" info="Measure the product as it will be placed in the shipping box." />
                    <x-ui.input x-model="basePackedWidth" name="width_mm" label="Packed Width" labelInfo="(mm)" type="number" step="1" min="1" :value="old('width_mm', $product->width_mm ?? '')" />
                    <x-ui.input x-model="basePackedHeight" name="height_mm" label="Packed Height" labelInfo="(mm)" type="number" step="1" min="1" :value="old('height_mm', $product->height_mm ?? '')" />
                    <x-ui.input
                        name="weight_grams"
                        x-model="basePackedWeight"
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
                <x-product-postage-preview />
            </x-ui.collapsible-section>


            <x-ui.collapsible-section title="Variants" titleExpression="productType === 'digital' ? 'Licence Tiers' : 'Variants'" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary><span x-text="variants.length + ' additional option' + (variants.length === 1 ? '' : 's')"></span></x-slot:summary>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak class="text-sm text-gray-600">Use variants for pack sizes, colours, or other options. Leave price, weight, or dimensions blank to inherit the base product values.</p>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="text-sm text-gray-600">Digital variants act as licence tiers. Add only the extra tiers you want to offer.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addVariant()" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Add Custom Tier' : 'Add Variant'">Add Variant</x-ui.button>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4" x-show="variants.length > 0 || (sharedInventory && productType === 'physical')" x-cloak>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-900" x-text="baseOptionDisplayName()"></div>
                            <div class="mt-1 text-sm text-gray-600">This option uses the base SKU, price, stock, packaging, and weight set above.</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block pl-1 text-sm">Base Option Name</label>
                            <x-ui.input-control type="text" x-bind:class="variantInputClasses" name="base_variant_name" x-model="baseVariantName" />
                            <div class="mt-1 pl-1 text-xs text-gray-500" x-text="'Leave blank to show ' + defaultBaseOptionLabel() + '.'"></div>
                            @error('base_variant_name')
                                <div class="mt-1 pl-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        <div x-show="sharedInventory && productType === 'physical'" x-cloak data-base-pack-units>
                            <label class="mb-1 block pl-1 text-sm" for="inventory_units">Units in the base pack</label>
                            <x-ui.input-control id="inventory_units" name="inventory_units" type="number" min="1" x-bind:class="variantInputClasses" :value="old('inventory_units', $product->inventory_units ?? 1)" />
                            <div class="mt-1 pl-1 text-xs text-gray-500">Deducted from the shared stock for each base pack sold.</div>
                            @error('inventory_units')
                                <div class="mt-1 pl-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="md:col-span-2 xl:col-span-4">
                            <label class="mb-1 block pl-1 text-sm" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Base Licence Details' : 'Base Option Details'"></label>
                            <x-ui.textarea-control x-bind:class="variantTextareaClasses" name="base_variant_description" rows="3">{{ old('base_variant_description', $product->base_variant_description ?? '') }}</x-ui.textarea-control>
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
                                <x-ui.button variant="plain" type="button" class="text-sm text-red-600 hover:underline" x-on:click="removeVariant(index)">Remove</x-ui.button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Variant Name</label>
                                    <x-ui.input-control type="text" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][name]`" x-model="variant.name" x-on:blur="syncVariantSku(index)" />
                                    <div class="mt-1 pl-1 text-xs text-gray-500">Each added option needs its own name.</div>
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">SKU</label>
                                    <x-ui.input-control type="text" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][sku]`" x-model="variant.sku" />
                                </div>
                                <x-ui.checkbox
 label="Is Active"
 x-model="variant.is_active"
 class="mt-7"
 />
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Sort Order</label>
                                    <x-ui.input-control type="number" min="0" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][sort_order]`" x-model="variant.sort_order" />
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Price (inc GST)</label>
                                    <x-ui.input-control type="number" step="0.01" min="0" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][price]`" x-model="variant.price" placeholder="Inherit base price" />
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Recommended Price (inc GST)</label>
                                    <x-ui.input-control type="number" step="0.01" min="0" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price" placeholder="No recommended price" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block pl-1 text-sm" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Licence Details' : 'Variant Details'"></label>
                                <x-ui.textarea-control x-bind:class="variantTextareaClasses" x-bind:name="`variants[${index}][description]`" x-model="variant.description" x-bind:placeholder="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Describe who this licence tier covers and where it may be used.' : 'Optional extra notes for this option.'"></x-ui.textarea-control>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-100 px-3 py-2">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Product detail overrides</h3>
                                        <p class="text-xs text-gray-500">Matching names replace the base value; new names are appended.</p>
                                    </div>
                                    <x-ui.button type="button" color="primary-outline-sm" x-on:click="addVariantProductDetail(index)">Add detail</x-ui.button>
                                </div>
                                <x-ui.table variant="plain" table-class="w-full table-fixed border-collapse" x-show="(variant.product_details || []).length > 0" x-cloak>
                                    <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="w-2/5 px-3 py-2">Detail</th>
                                            <th class="px-3 py-2">Override value</th>
                                            <th class="w-12 px-2 py-2"><span class="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <template x-for="(detail, detailIndex) in (variant.product_details || [])" :key="detailIndex">
                                            <tr>
                                                <td class="border-r border-gray-200 p-0">
                                                    <x-ui.input-control type="text" x-bind:name="`variants[${index}][product_details][${detailIndex}][key]`" x-model="detail.key" x-on:blur="variant.product_details = skuLastDetails(variant.product_details || [])" x-bind:data-variant-detail-key="`${index}-${detailIndex}`" class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300" placeholder="e.g. Pack size" />
                                                </td>
                                                <td class="border-r border-gray-200 p-0">
                                                    <x-ui.input-control type="text" x-bind:name="`variants[${index}][product_details][${detailIndex}][value]`" x-model="detail.value" class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-300" placeholder="Variant value" x-on:keydown.tab="addVariantProductDetailAfterTab($event, index, detailIndex)" />
                                                </td>
                                                <td class="p-0 text-center">
                                                    <x-ui.button variant="plain" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600" x-on:click="removeVariantProductDetail(index, detailIndex)" title="Remove detail" aria-label="Remove detail">
                                                        <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                                    </x-ui.button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </x-ui.table>
                                <p class="px-3 py-3 text-sm text-gray-500" x-show="(variant.product_details || []).length === 0">No detail overrides.</p>
                            </div>

                            <div class="grid items-start gap-4 md:grid-cols-2" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak data-variant-inventory>
                                <div x-show="sharedInventory" x-cloak data-variant-pack-units>
                                    <x-ui.input label="Units in this pack" type="number" min="1" x-bind:name="`variants[${index}][inventory_units]`" x-model="variant.inventory_units" info="Deducted from the shared stock for each pack sold." class="mb-0" />
                                </div>
                                <div x-show="!sharedInventory" x-cloak>
                                    <x-ui.input label="Inventory Quantity" type="number" min="0" class="mb-0" x-bind:name="`variants[${index}][inventory_quantity]`" x-model="variant.inventory_quantity" info="Leave blank for unlimited stock. Enter 0 when this variant is sold out." />
                                </div>
                                <div x-show="!sharedInventory" x-cloak>
                                    <x-ui.input label="Low-stock alert threshold" type="number" min="1" class="mb-0" x-bind:name="`variants[${index}][low_stock_threshold]`" x-model="variant.low_stock_threshold" placeholder="Inherit base threshold" info="Leave blank to use the base product threshold." />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 bg-white p-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                <div class="mb-3">
                                    <h3 class="text-sm font-semibold text-gray-900">Packaging overrides</h3>
                                    <p class="mt-1 text-xs text-gray-500">Only fill these in when this pack has different packed measurements from the base option.</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <label class="mb-1 block pl-1 text-sm">Packed Length <span class="text-xs text-gray-500">(mm)</span></label>
                                        <x-ui.input-control type="number" min="1" max="10000" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][length_mm]`" x-model="variant.length_mm" placeholder="Inherit base length" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block pl-1 text-sm">Packed Width <span class="text-xs text-gray-500">(mm)</span></label>
                                        <x-ui.input-control type="number" min="1" max="10000" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][width_mm]`" x-model="variant.width_mm" placeholder="Inherit base width" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block pl-1 text-sm">Packed Height <span class="text-xs text-gray-500">(mm)</span></label>
                                        <x-ui.input-control type="number" min="1" max="10000" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][height_mm]`" x-model="variant.height_mm" placeholder="Inherit base height" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block pl-1 text-sm">Packed Weight <span class="text-xs text-gray-500">(grams)</span></label>
                                        <x-ui.input-control type="number" min="0" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][weight_grams]`" x-model="variant.weight_grams" placeholder="Inherit base weight" />
                                    </div>
                                </div>
                                <x-product-postage-preview :variant="true" />
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
                                                <x-ui.select-control x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][backorder_shipping_estimate_type]`" x-model="variant.backorder_shipping_estimate_type">
                                                    <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_STATIC }}">Specific date</option>
                                                    <option value="{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}">Days from today</option>
                                                </x-ui.select-control>
                                            </div>

                                            <div x-show="variant.backorder_shipping_estimate_type === '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                                <label class="mb-1 block pl-1 text-sm">Days from today</label>
                                                <x-ui.input-control type="number" min="1" step="1" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][backorder_shipping_offset_days]`" x-model="variant.backorder_shipping_offset_days" />
                                            </div>

                                            <div x-show="variant.backorder_shipping_estimate_type !== '{{ \App\Models\Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC }}'" x-cloak>
                                                <label class="mb-1 block pl-1 text-sm">Shipping Date</label>
                                                <x-ui.input-control type="date" x-bind:class="variantInputClasses" x-bind:name="`variants[${index}][backorder_shipping_estimate]`" x-model="variant.backorder_shipping_estimate" />
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
            </x-ui.collapsible-section>

            @include('admin.shop.product.allocation-matrix')

            <x-ui.collapsible-section title="Images and Downloads" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ isset($product) ? (($product->hero_media_name ? 'Hero image · ' : '').$product->galleryMedia->count().' gallery images') : 'Product images and digital files' }}</x-slot:summary>
                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="text-sm text-slate-600">
                    Digital products ignore parcel packing and shipping fields, unlock their download files after payment, and can use licence tiers when you want to sell different usage rights.
                </div>
                <x-ui.media label="Hero Image" name="hero_media_name" value="{{ $product->hero_media_name ?? '' }}" allow_uploads="true" public_usable_only="true" />
                <x-ui.gallery name="gallery_files" label="Gallery" value="{{ $galleryFilesValue }}" editor="true" />
                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak>
                    <x-ui.filelist name="download_files" label="Digital Download Files" :value="$downloadFilesValue" editor="true" />
                </div>
            </x-ui.collapsible-section>

            <x-ui.collapsible-section title="Admin Notes & Alerts" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ filled($product->private_notes ?? null) ? 'Private notes added' : 'No private notes' }} · Low-stock alerts</x-slot:summary>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
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
            </x-ui.collapsible-section>

            <x-ui.editor-actions>
                @isset($product)
                    <div data-editor-delete class="flex shrink-0 gap-3">
                        @if(! $product->store_order_items_exists)
                            <x-ui.button type="button" color="danger" class="size-11 shrink-0 p-0!" aria-label="Delete Product" title="Delete Product"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete product?', 'Permanently delete this unused product? This action cannot be undone.', '{{ route('admin.shop.product.destroy', $product) }}')"
                            ><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
                        @endif
                        @if($product->status === \App\Models\Product::STATUS_ARCHIVED)
                            <x-ui.button type="submit" color="outline" form="restore-product-form" class="size-11 shrink-0 p-0!" aria-label="Restore as Draft" title="Restore as Draft"><i class="fa-solid fa-box-open" aria-hidden="true"></i></x-ui.button>
                        @else
                            <x-ui.button type="submit" color="outline" form="archive-product-form" class="size-11 shrink-0 p-0!" aria-label="Archive Product" title="Archive Product"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></x-ui.button>
                        @endif
                    </div>
                @endisset
                <x-ui.button type="submit" class="ml-auto min-h-11 px-4">Save Product</x-ui.button>
            </x-ui.editor-actions>
        </form>
        @isset($product)
            @if($product->status === \App\Models\Product::STATUS_ARCHIVED)
                <form id="restore-product-form" method="POST" action="{{ route('admin.shop.product.restore', $product) }}">
                    @csrf
                    @method('PATCH')
                </form>
            @else
                <form id="archive-product-form" method="POST" action="{{ route('admin.shop.product.archive', $product) }}" x-data x-on:submit.prevent="SM.confirm('Archive product?', 'This removes the product from the store while preserving its order history.', 'Archive Product', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                    @csrf
                    @method('PATCH')
                </form>
            @endif
        @endisset
    </x-container>
</x-layout>
