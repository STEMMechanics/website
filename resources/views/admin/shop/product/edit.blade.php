@php
    $hasCalculatedShipping = \App\Support\ShopShippingSettings::hasCalculatedChannels();
    $editorProductType = (string) old('product_type', $product->product_type ?? \App\Models\Product::PRODUCT_TYPE_PHYSICAL);
    $editorIsPhysical = in_array($editorProductType, [\App\Models\Product::PRODUCT_TYPE_PHYSICAL, \App\Models\Product::PRODUCT_TYPE_KIT], true);
    $editorSharedInventory = (bool) old('shared_inventory', $product->shared_inventory ?? false);
    $formatCost = static fn ($value) => is_numeric($value) ? number_format((float) $value, 2, '.', '') : ($value ?? '');
    $costingService = app(\App\Services\ProductRecipeService::class);
    $materialsConfig = [
        'catalog' => $recipeCatalog ?? [],
        'recipe' => old('recipe', isset($product) ? $product->recipeComponents->map(fn ($row) => [...$row->only(['component_product_id', 'component_variant_id', 'cut_size_id', 'quantity', 'quantity_unit', 'length', 'length_unit', 'note']), 'item_name' => $row->component?->title ?? ''])->all() : []),
        'cuts' => old('cut_sizes', isset($product) ? $product->cutSizes->toArray() : []),
        'isKit' => old('product_type', $product->product_type ?? '') === \App\Models\Product::PRODUCT_TYPE_KIT,
        'useAsMaterial' => (bool) old('use_as_material', isset($product) && $product->hasMaterialMeasurements()),
        'materialKind' => old('material_kind', $product->material_kind ?? 'unit'),
        'purchaseUnit' => old('purchase_unit', $product->purchase_unit ?? 'piece'),
        'purchaseQuantity' => old('purchase_quantity', $product->purchase_quantity ?? 1),
        'purchasePackUnits' => old('purchase_pack_units', $product->purchase_pack_units ?? 1),
        'measureUnit' => old('material_measure_unit', $product->material_measure_unit ?? 'm'),
        'materialLength' => old('material_length_mm', $product->material_length_mm ?? ''),
        'materialWidth' => old('material_width_mm', $product->material_width_mm ?? ''),
        'unitCost' => old('unit_cost_ex_tax', $product->unit_cost_ex_tax ?? ''),
        'purchasePackCost' => old('purchase_pack_cost_ex_tax', isset($product) ? $costingService->fallbackPurchasePackCost($product) : ''),
        'purchaseCosts' => isset($product) ? $costingService->purchaseCostInputs($product) : [],
        'basePackUnits' => old('inventory_units', $product->inventory_units ?? 1),
        'operationalCost' => $formatCost(old('operational_cost_ex_tax', $product->operational_cost_ex_tax ?? '')),
        'timeCost' => $formatCost(old('time_cost_ex_tax', $product->time_cost_ex_tax ?? '')),
        'automaticPricing' => (bool) old('automatic_pricing', $product->automatic_pricing ?? false),
        'markup' => old('markup_percent', $product->markup_percent ?? ''),
        'bulkDiscount' => old('bulk_discount_percent', $product->bulk_discount_percent ?? ''),
        'priceEnding' => (float) config('product-costing.price_ending', 0.95),
        'defaults' => ['markup' => $costingService->defaultMarkup(), 'bulkDiscount' => $costingService->defaultBulkDiscount(), 'itemOperational' => \App\Models\SiteOption::value('store.costing.item-operational', '0.50'), 'kitOperational' => \App\Models\SiteOption::value('store.costing.kit-operational', '1.00'), 'itemTime' => \App\Models\SiteOption::value('store.costing.item-time', '3.50'), 'kitTime' => 2 * (float) \App\Models\SiteOption::value('store.costing.item-time', '3.50')],
    ];
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
    $variantDeletionReasons = isset($product)
        ? $product->variants->mapWithKeys(fn ($variant) => [$variant->id => $variant->deletionDisabledReason($product)])->all()
        : [];
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
    $productAllowsBackorder = (bool) old('allow_backorder', isset($product) ? ((bool) $product->allow_backorder || (bool) $product->is_preorder) : false);
    $productBackorderEstimate = old('backorder_shipping_estimate', isset($product)
        ? ($product->backorder_shipping_estimate?->format('Y-m-d') ?? $product->preorder_shipping_estimate?->format('Y-m-d') ?? '')
        : '');
    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = isset($product)
            ? ($product->variants->isNotEmpty()
                ? $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'original_option' => $variant->id === $product->legacy_base_variant_id,
                    'is_base' => $variant->id === $product->default_variant_id,
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
                    'unit_cost_ex_tax' => $variant->unit_cost_ex_tax,
                    'bulk_discount_percent' => $variant->bulk_discount_percent ?? '',
                    'automatic_pricing' => (bool) $variant->automatic_pricing,
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
                    'deletion_disabled_reason' => $variantDeletionReasons[$variant->id] ?? null,
                    'awaiting_fulfilment' => (int) data_get($variantInventoryContextMap, (string) $variant->id.'.awaiting', 0),
                    'reserved_quantity' => (int) data_get($variantInventoryContextMap, (string) $variant->id.'.reserved', 0),
                ])->values()->all()
                : [])
            : [];
        if (!isset($product) || !$product->usesVariantRecords()) {
            array_unshift($variantRows, [
                'id' => null, 'original_option' => true, 'is_base' => true, 'is_active' => true,
                'name' => isset($product) ? $product->baseOptionName() : 'Base',
                'description' => $product->base_variant_description ?? '', 'product_details' => [],
                'sku' => $product->sku ?? '', 'price' => $product->price ?? '0.00',
                'compare_at_price' => $product->compare_at_price ?? '',
                'inventory_units' => $product->inventory_units ?? 1,
                'inventory_quantity' => $editorSharedInventory ? '' : ($product->inventory_quantity ?? ''),
                'unit_cost_ex_tax' => '', 'bulk_discount_percent' => '',
                'automatic_pricing' => (bool) ($product->automatic_pricing ?? false),
                'weight_grams' => $product->weight_grams ?? '',
                'length_mm' => $product->length_mm ?? '', 'width_mm' => $product->width_mm ?? '', 'height_mm' => $product->height_mm ?? '',
                'low_stock_threshold' => $product->low_stock_threshold ?? '',
                'allow_backorder' => $productAllowsBackorder,
                'backorder_shipping_estimate_type' => $productBackorderEstimateType,
                'backorder_shipping_estimate' => $productBackorderEstimate,
                'backorder_shipping_offset_days' => $productBackorderOffsetDays,
                'sort_order' => 0, 'awaiting_fulfilment' => $baseInventoryContext['awaiting'], 'reserved_quantity' => $baseInventoryContext['reserved'],
            ]);
        }
    } else {
        $variantRows = collect($variantRows)
            ->map(function ($variant) use ($variantInventoryContextMap, $variantDeletionReasons) {
                $variantId = (string) data_get($variant, 'id', '');
                $context = $variantInventoryContextMap[$variantId] ?? ['awaiting' => 0, 'reserved' => 0];

                return array_merge($variant, [
                    'deletion_disabled_reason' => $variantDeletionReasons[(int) $variantId] ?? null,
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

        return array_merge($variant, [
            'product_details' => $otherRows->concat($skuRows)->values()->all(),
            'unit_cost_ex_tax' => $variant['unit_cost_ex_tax'] ?? '',
            'bulk_discount_percent' => $variant['bulk_discount_percent'] ?? '',
            'automatic_pricing' => filter_var($variant['automatic_pricing'] ?? false, FILTER_VALIDATE_BOOL),
        ]);
    })->values()->all();

@endphp
<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Products">{{ isset($product) ? 'Edit' : 'Create' }} Product
        @if(isset($product) && $product->isSellable())
            <x-slot:actions>
                <x-ui.button href="{{ route('shop.product.show', $product) }}" color="mast"><i class="fa-solid fa-arrow-up-right-from-square mr-2" aria-hidden="true"></i>View Product</x-ui.button>
            </x-slot:actions>
        @endif
    </x-mast>

    <x-container class="mt-4">
        <x-admin.ai-status-toast id="product-ai-toast" message="Preparing product copy…" detail="Your current product details are being used to draft the update." progress-label="Product content generation" />
        <form
            id="product-form"
            class="product-editor"
            x-on:sm-product-specifications-ai.window="mergeAiProductDetails($event.detail.details)"
            x-on:invalid.capture="let section = $event.target.closest('details'); while (section) { section.open = true; section = section.parentElement.closest('details'); }"
            method="POST"
            action="{{ route('admin.shop.product.'.(isset($product) ? 'update' : 'store'), $product ?? []) }}"
            x-data="{
                ...SM.productMaterials(@js($materialsConfig)),
                ...SM.productVariantEditor(),
                variantErrors: @js(array_keys($errors->getMessages())),
                sharedInventory: @js($editorSharedInventory),
                baseInventoryQuantity: @js(old('inventory_quantity', $product->inventory_quantity ?? '')),
                baseLowStockThreshold: @js(old('low_stock_threshold', isset($product) ? ($product->low_stock_threshold ?? '') : 5)),
                productType: @js($editorProductType),
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
                productTaxRate: @js((float) ($product->tax_rate ?? 0.1)),
                baseCompareAtPrice: @js(old('compare_at_price', isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : '')),
                baseShippingUnits: @js(old('shipping_units', isset($product) ? number_format((float) $product->shipping_units, 3, '.', '') : '0.000')),
                baseMinSatchelRank: @js((string) old('min_satchel_rank', $product->min_satchel_rank ?? $defaultSatchelRank)),
                baseVariantName: @js(old('base_variant_name', $product->base_variant_name ?? '')),
                productBackorderEstimateType: @js($productBackorderEstimateType),
                productBackorderOffsetDays: @js((string) $productBackorderOffsetDays),
                variants: @js($variantRows),
                productDetails: @js($productDetailRows),
                variantInputClasses: 'disabled:bg-gray-100 bg-white block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                variantTextareaClasses: 'disabled:bg-gray-100 bg-white block min-h-20 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                formatAud(value) {
                    const amount = Number(value);
                    if (!Number.isFinite(amount)) return '—';

                    return new Intl.NumberFormat('en-AU', {
                        style: 'currency',
                        currency: 'AUD',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(amount);
                },
                salePriceExTax(priceIncTax) {
                    const price = Number.parseFloat(String(priceIncTax ?? ''));
                    if (!Number.isFinite(price)) return null;
                    const taxRate = Number(this.productTaxRate);

                    return price / (1 + (Number.isFinite(taxRate) && taxRate >= 0 ? taxRate : 0));
                },
                variantSalePrice(variant) {
                    const variantPrice = String(variant?.price ?? '').trim();
                    const price = Number.parseFloat(variantPrice !== '' ? variantPrice : String(this.basePrice ?? ''));

                    return Number.isFinite(price) ? price : null;
                },
                defaultBaseOptionLabel() {
                    return this.productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Home' : 'Base';
                },
                baseOptionDisplayName() {
                    return 'Shared allocation defaults';
                },
                displayVariantName(variant, index) {
                    const explicitName = String(variant?.name || '').trim();

                    return explicitName !== '' ? explicitName : `Variant ${index + 1}`;
                },
                packStockSummary(quantity, unitsPerPack, shared = false) {
                    if (quantity === null || quantity === undefined || String(quantity).trim() === '') {
                        return shared ? '' : 'Stock level is not tracked.';
                    }

                    const count = Number(quantity);
                    const units = Number(unitsPerPack);
                    if (!Number.isInteger(count) || count < 0 || !Number.isInteger(units) || units < 1) return '';

                    const packs = shared ? Math.floor(count / units) : count;
                    const packLabel = packs === 1 ? 'pack' : 'packs';
                    const unitLabel = units === 1 ? 'unit' : 'units';

                    return shared
                        ? `${packs} ${packLabel} available (${units} ${unitLabel} per pack) from ${count} individual units.`
                        : `${packs} ${packLabel} × ${units} ${unitLabel} = ${packs * units} individual units.`;
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
                    if (!variant || String(variant.sku || '').trim() !== '') {
                        return;
                    }

                    variant.sku = this.ensureUniqueVariantSku(this.variantSkuSeed(variant, index), index);
                },
                addVariant() {
                    this.variants.push(this.prepareNewVariant({
                        id: null,
                        name: '',
                        description: '',
                        product_details: [],
                        sku: '',
                        price: '',
                        compare_at_price: '',
                        inventory_quantity: '',
                        unit_cost_ex_tax: '',
                        bulk_discount_percent: '',
                        automatic_pricing: false,
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
                    }));
                    this.normalizeVariantOrder();
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
                    this.initVariantEditor();
                    this.initMaterials();
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
            <input type="hidden" name="variant_editor" value="1">
            @isset($product)
                @method('PUT')
            @endisset

            <x-ui.collapsible-section title="Product" variant="product" :open="true">
                <x-slot:summary><span x-text="title || 'Product information'"></span></x-slot:summary>
                <div class="product-editor__fields">
                    <x-ui.select name="product_type" label="Product Type" x-model="productType">
                        @foreach(\App\Models\Product::PRODUCT_TYPES as $type)
                            <option value="{{ $type }}">{{ \App\Models\Product::productTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select name="status" label="Status" x-model="status">
                        @foreach(\App\Models\Product::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $product->status ?? \App\Models\Product::STATUS_DRAFT) === $status)>{{ \App\Models\Product::statusLabel($status) }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input name="title" label="Title" :value="$product->title ?? ''" x-model="title" x-on:blur="handleTitleInput()" />
                    <x-ui.input name="slug" label="Slug" :value="$product->slug ?? ''" x-model="slug" x-on:input="handleSlugInput()" />
                    <x-ui.input name="subtitle" label="Subtitle" :value="$product->subtitle ?? ''" class="md:col-span-2" />
                    <x-ui.input id="material-sku" name="sku" label="SKU" :value="$product->sku ?? ''" x-model="baseSku" x-on:input="handleBaseSkuInput()" x-show="productType === 'material'" x-cloak :disabled="$editorProductType !== \App\Models\Product::PRODUCT_TYPE_MATERIAL" x-bind:disabled="productType !== 'material'" required info="Required. Used to identify this material." />
                    <div class="flex flex-wrap items-start gap-6">
                        <x-ui.input name="sort_order" label="Sort Order" type="number" min="0" :value="$product->sort_order ?? 0" class="mb-0 w-24" />
                        <div class="pt-7" x-show="productType !== 'material'" x-cloak>
                            <x-ui.checkbox name="is_featured" label="Featured product" :checked="(bool) old('is_featured', $product->is_featured ?? false)" noWrapper x-model="isFeatured" x-bind:disabled="productType === 'material' || status !== '{{ \App\Models\Product::STATUS_ACTIVE }}'" />
                        </div>
                    </div>
                </div>
            </x-ui.collapsible-section>

            <x-ui.collapsible-section title="Categories" x-show="productType !== 'material'" x-cloak variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ $categories->whereIn('id', $selectedCategoryIds)->pluck('name')->implode(', ') ?: 'No categories selected' }}</x-slot:summary>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Assign any number of managed categories to this product.</p>
                        </div>
                        <x-ui.button href="{{ route('admin.shop.category.index') }}" color="outline" size="small" class="shrink-0">Manage Categories</x-ui.button>
                    </div>

                    @if(($categories ?? collect())->isEmpty())
                        <p class="mt-3 text-sm text-gray-600">Create product categories first, then come back and assign them here.</p>
                    @else
                        <div class="mt-4 product-editor__fields">
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
            <x-ui.collapsible-section title="Description" x-show="productType !== 'material'" x-cloak variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ \Illuminate\Support\Str::limit(strip_tags($product->short_description ?? $productDescription), 90) ?: 'No description added' }}</x-slot:summary>
                <x-ui.input name="short_description" label="Short Description" :value="$product->short_description ?? ''" />
                <x-ui.editor name="description" label="Description" :value="$productDescription">
                    <x-slot:toolbar>
                        <x-ui.button type="button" variant="plain" class="rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-copy" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Improving product copy…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" data-ai-fill-scope="#product-form" data-ai-fill-fields="title,short_description,description" data-ai-editor-field="description" data-ai-editor-format="product-description" :disabled="blank(config('services.openai.api_key'))" aria-label="Improve product copy with AI" title="Improve product copy with AI" size="icon-small">
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
                        <x-ui.button type="button" variant="plain" class="rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-warning" data-ai-kind="warning" data-ai-result-key="warning" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Checking product warning…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" :disabled="blank(config('services.openai.api_key'))" aria-label="Draft a short product warning" title="Draft a short warning if the product details support one" size="icon-small">
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
            <x-ui.collapsible-section title="Specifications" x-show="productType !== 'material'" x-cloak variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary><span x-text="productDetails.filter(detail => detail.key || detail.value).length + ' product details'"></span></x-slot:summary>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="mt-1 text-xs text-gray-500">Add specifications such as pack size, material, colour, and recommended age. Values may include <code>{sku}</code>, which follows the selected variant.</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui.button type="button" variant="plain" class="inline-flex size-9 items-center justify-center rounded-lg text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-action="product-specifications" data-ai-kind="specifications" data-ai-result-key="product_details" data-ai-widget-target="#product-ai-toast" data-ai-processing-message="Drafting product specifications…" data-ai-url="{{ route('admin.ai.products.draft') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#product-form" data-ai-fields="title,short_description,description,caution_message" data-ai-context="{}" x-bind:data-ai-context="JSON.stringify(productAiContext())" :disabled="blank(config('services.openai.api_key'))" aria-label="Create or update specifications with AI" title="Create or update supported specifications with AI">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            </x-ui.button>
                            <x-ui.button type="button" color="primary-outline" size="small" x-on:click="addProductDetail()">Add Detail</x-ui.button>
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
            @include('admin.shop.product.materials-and-recipe')

            <x-ui.collapsible-section title="Variants" x-show="productType !== 'material'" x-cloak titleExpression="productType === 'digital' ? 'Licence Tiers' : 'Variants'" variant="product" :open="!isset($product) || $errors->any()" data-product-price-inventory>
                <x-slot:summary><span x-text="variants.filter(variant => variant.is_active).length + ' active option' + (variants.filter(variant => variant.is_active).length === 1 ? '' : 's')"></span></x-slot:summary>
                <x-slot:actions>
                    <x-ui.button type="button" color="primary-outline" size="small" x-on:click.stop.prevent="addVariant(); $el.closest('details').open = true" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Add tier' : 'Add variant'">Add variant</x-ui.button>
                </x-slot:actions>
                <p x-show="isPhysicalProduct()" x-cloak class="text-sm text-gray-600">Choose an active base variant as the default option. Each pack size, colour or option can override the shared defaults.</p>
                <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="text-sm text-gray-600">Digital variants act as licence tiers. Choose one active tier as the default.</p>

                <div class="space-y-3" x-show="isPhysicalProduct()" x-cloak>
                    <x-ui.checkbox name="shared_inventory" label="Share stock across all packs and variants" x-model="sharedInventory" :checked="old('shared_inventory', $product->shared_inventory ?? false)" x-bind:disabled="!isPhysicalProduct()" noWrapper="true" />
                    <div x-show="sharedInventory" x-cloak class="space-y-3">
                        <p class="text-xs text-gray-600">Count individual units once for all variants. For example, 50 units can make five 10-packs or twenty-five 2-packs.</p>
                        <div class="product-editor__fields">
                            <x-ui.input id="shared-inventory-quantity" name="inventory_quantity" label="Individual units in stock" type="number" min="0" step="1" x-model="baseInventoryQuantity" :value="old('inventory_quantity', $product->inventory_quantity ?? '')" :disabled="!$editorSharedInventory || !$editorIsPhysical" x-bind:disabled="!sharedInventory || !isPhysicalProduct()" info="Shared by every pack size. Leave blank to stop tracking stock, or enter 0 when sold out." />
                            <x-ui.input id="shared-low-stock-threshold" name="low_stock_threshold" label="Low-stock alert threshold" labelInfo="(individual units)" type="number" min="1" step="1" x-model="baseLowStockThreshold" :value="old('low_stock_threshold', isset($product) ? ($product->low_stock_threshold ?? '') : 5)" :disabled="!$editorSharedInventory || !$editorIsPhysical" x-bind:disabled="!sharedInventory || !isPhysicalProduct()" info="Emails a warning when the shared stock reaches this count. Leave blank to disable." />
                        </div>
                    </div>
                    @if(isset($product) && $product->low_stock_alert_sent_at)
                        <p class="text-xs text-gray-500">Last low-stock alert: {{ $product->low_stock_alert_sent_at->format('M j, Y g:i a') }}</p>
                    @endif
                </div>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Shared defaults and cost basis</h3>
                    <p class="text-sm text-gray-600">Defaults apply when a variant has no override. The cost basis quantity describes the shared purchase cost or recipe; each selling pack gets one operations and time allowance.</p>
                    <div class="product-editor__fields">
                        <x-ui.input name="sku" label="Product code" x-model="baseSku" x-on:input="handleBaseSkuInput()" :value="$product->sku ?? ''" x-bind:disabled="productType === 'material'" info="Identifies the product group. Each selling variant also has its own SKU." />
                        <x-ui.input name="price" label="Fallback selling price" labelInfo="(inc GST)" x-bind:readonly="automaticPricing" moneyFormat="true" x-model="basePrice" />
                        <x-ui.input name="compare_at_price" label="Recommended price default" moneyFormat="true" x-model="baseCompareAtPrice" />
                        <x-ui.input name="inventory_units" label="Cost basis quantity" type="number" min="1" step="1" x-model="basePackUnits" x-bind:disabled="!isPhysicalProduct()" info="Units covered by the shared purchase cost or recipe. Each variant sets its own units per pack." />
                        <div x-show="!sharedInventory" x-cloak>
                            <x-ui.input name="low_stock_threshold" label="Default low-stock threshold" type="number" min="1" step="1" x-model="baseLowStockThreshold" x-bind:disabled="sharedInventory || !isPhysicalProduct()" info="Packs per variant. Individual variants can override this threshold." />
                        </div>
                    </div>
                    @if($hasCalculatedShipping)
                        <div class="space-y-3 border-t border-gray-200 pt-4" x-show="isPhysicalProduct()" x-cloak>
                            <h3 class="text-sm font-semibold text-gray-900">Packaging defaults</h3>
                            <div class="product-editor__compact-fields">
                                <x-ui.input name="length_mm" label="Packed Length" labelInfo="(mm)" type="number" min="1" max="10000" x-model="basePackedLength" class="w-40" />
                                <x-ui.input name="width_mm" label="Packed Width" labelInfo="(mm)" type="number" min="1" max="10000" x-model="basePackedWidth" class="w-40" />
                                <x-ui.input name="height_mm" label="Packed Height" labelInfo="(mm)" type="number" min="1" max="10000" x-model="basePackedHeight" class="w-40" />
                                <x-ui.input name="weight_grams" label="Packed Weight" labelInfo="(g)" type="number" min="0" x-model="basePackedWeight" class="w-40" />
                            </div>
                            <x-ui.checkbox name="box_only" label="Requires rigid parcel shipping" x-model="boxOnly" class="mb-0" />
                            <x-product-postage-preview />
                        </div>
                    @endif
                </div>

                @error('variants')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <p class="text-sm text-red-700" role="alert" x-show="status === 'active' && !variants.some(variant => variant.is_active)" x-cloak>Activate a variant or change this product to draft or archived.</p>

                <div class="space-y-4">
                    <template x-for="{ variant, index } in orderedVariantRows()" :key="variant.editor_key">
                        <x-ui.collapsible-section title="Variant" titleExpression="displayVariantName(variant, index)" variant="product" x-bind:open="variant.open" x-on:toggle="variant.open = $el.open" class="product-variant" x-bind:class="{ 'product-variant--inactive': !variant.is_active }">
                            <x-slot:summary>
                                <span x-text="formatAud(variantSalePrice(variant)) + (isPhysicalProduct() ? ' · ' + (variant.inventory_units || 1) + ' per pack' : '')"></span>
                                <span x-show="variant.is_base" class="ml-2 font-semibold text-sky-800">Base</span>
                                <span x-show="!variant.is_active" class="ml-2 text-gray-500">Inactive</span>
                            </x-slot:summary>
                            <x-slot:actions>
                                <span class="inline-flex" x-bind:data-tooltip="variantMoveDisabledReason(variant, -1) || 'Move up'" x-bind:tabindex="variantMoveDisabledReason(variant, -1) ? 0 : null" x-on:click.stop.prevent>
                                    <x-ui.button variant="text" color="secondary" size="icon-small" icon="arrow-up" label="Move variant up" x-bind:disabled="!!variantMoveDisabledReason(variant, -1)" x-on:click.stop.prevent="moveVariant(index, -1)" />
                                </span>
                                <span class="inline-flex" x-bind:data-tooltip="variantMoveDisabledReason(variant, 1) || 'Move down'" x-bind:tabindex="variantMoveDisabledReason(variant, 1) ? 0 : null" x-on:click.stop.prevent>
                                    <x-ui.button variant="text" color="secondary" size="icon-small" icon="arrow-down" label="Move variant down" x-bind:disabled="!!variantMoveDisabledReason(variant, 1)" x-on:click.stop.prevent="moveVariant(index, 1)" />
                                </span>
                                <span class="inline-flex" x-bind:data-tooltip="variantDeletionDisabledReason(variant) || 'Delete variant'" x-bind:tabindex="variantDeletionDisabledReason(variant) ? 0 : null" x-on:click.stop.prevent>
                                    <x-ui.button variant="text" color="danger" size="icon-small" icon="trash" label="Delete variant" x-bind:disabled="!!variantDeletionDisabledReason(variant)" x-on:click.stop.prevent="removeVariant(index)" />
                                </span>
                            </x-slot:actions>
                            @include('admin.shop.product.variant-fields')
                        </x-ui.collapsible-section>
                    </template>
                </div>
            </x-ui.collapsible-section>

            <div x-show="productType !== 'material'" x-cloak>@include('admin.shop.product.allocation-matrix')</div>

            <x-ui.collapsible-section title="Images and Downloads" x-show="productType !== 'material'" x-cloak variant="product" :open="!isset($product) || $errors->any()">
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

            <x-ui.collapsible-section title="Admin Notes" variant="product" :open="!isset($product) || $errors->any()">
                <x-slot:summary>{{ filled($product->private_notes ?? null) ? 'Private notes added' : 'No private notes' }}</x-slot:summary>
                <x-ui.input type="textarea" name="private_notes" label="Private Notes" :value="$product->private_notes ?? ''" info="Only visible in admin." class="mb-0" />
            </x-ui.collapsible-section>

            <x-ui.editor-actions>
                @isset($product)
                    <div data-editor-delete class="flex shrink-0 gap-3">
                        @if(! $product->store_order_items_exists)
                            <x-ui.button type="button" color="danger" class="" aria-label="Delete Product" title="Delete Product"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete product?', 'Permanently delete this unused product? This action cannot be undone.', '{{ route('admin.shop.product.destroy', $product) }}')" size="icon"><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
                        @endif
                        @if($product->status === \App\Models\Product::STATUS_ARCHIVED)
                            <x-ui.button type="submit" color="outline" form="restore-product-form" class="" aria-label="Restore as Draft" title="Restore as Draft" size="icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></x-ui.button>
                        @else
                            <x-ui.button type="submit" color="outline" form="archive-product-form" class="" aria-label="Archive Product" title="Archive Product" size="icon"><i class="fa-solid fa-box-archive" aria-hidden="true"></i></x-ui.button>
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
