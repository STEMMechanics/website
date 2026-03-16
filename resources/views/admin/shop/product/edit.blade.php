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
    $productDescription = old('description', $product->description ?? '');
    $satchelOptions = \App\Models\Product::satchelOptions();
    $defaultSatchelRank = (int) ($satchelOptions->first()['rank'] ?? 1);
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
                    'shipping_units' => $variant->shipping_units !== null ? number_format((float) $variant->shipping_units, 2, '.', '') : '',
                    'inventory_quantity' => $variant->inventory_quantity,
                    'weight_grams' => $variant->weight_grams,
                    'is_preorder' => (bool) $variant->is_preorder,
                    'preorder_shipping_estimate' => $variant->preorder_shipping_estimate?->format('Y-m-d') ?? '',
                    'allow_backorder' => (bool) $variant->allow_backorder,
                    'backorder_shipping_estimate' => $variant->backorder_shipping_estimate?->format('Y-m-d') ?? '',
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
                    'shipping_units' => data_get($variant, 'shipping_units', ''),
                    'is_preorder' => (bool) data_get($variant, 'is_preorder', false),
                    'preorder_shipping_estimate' => data_get($variant, 'preorder_shipping_estimate', ''),
                    'allow_backorder' => (bool) data_get($variant, 'allow_backorder', false),
                    'backorder_shipping_estimate' => data_get($variant, 'backorder_shipping_estimate', ''),
                    'awaiting_fulfilment' => (int) data_get($variant, 'awaiting_fulfilment', $context['awaiting']),
                    'reserved_quantity' => (int) data_get($variant, 'reserved_quantity', $context['reserved']),
                ]);
            })
            ->values()
            ->all();
    }
@endphp
<x-layout>
    <x-mast backRoute="admin.shop.product.index" backTitle="Store Products">{{ isset($product) ? 'Edit' : 'Create' }} Product</x-mast>

    <x-container class="mt-4">
        <form
            method="POST"
            action="{{ route('admin.shop.product.'.(isset($product) ? 'update' : 'store'), $product ?? []) }}"
            x-data="{
                productType: @js(old('product_type', $product->product_type ?? \App\Models\Product::PRODUCT_TYPE_PHYSICAL)),
                title: @js(old('title', $product->title ?? '')),
                slug: @js(old('slug', $product->slug ?? '')),
                slugTouched: @js(trim((string) old('slug', $product->slug ?? '')) !== ''),
                isPreorder: @js((bool) old('is_preorder', $product->is_preorder ?? false)),
                allowBackorder: @js((bool) old('allow_backorder', $product->allow_backorder ?? false)),
                basePrice: @js(old('price', isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00')),
                baseVariantName: @js(old('base_variant_name', $product->base_variant_name ?? '')),
                variants: @js($variantRows),
                variantInputClasses: 'disabled:bg-gray-100 bg-white block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                variantTextareaClasses: 'disabled:bg-gray-100 bg-white block min-h-[7rem] w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0',
                defaultBaseOptionLabel() {
                    return this.productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Home' : 'Base';
                },
                baseOptionDisplayName() {
                    const explicitName = String(this.baseVariantName || '').trim();

                    return explicitName !== '' ? explicitName : this.defaultBaseOptionLabel();
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
                },
                handleSlugInput() {
                    this.slugTouched = String(this.slug || '').trim() !== '';
                },
                addVariant() {
                    this.variants.push({
                        id: null,
                        name: '',
                        description: '',
                        sku: '',
                        price: '',
                        compare_at_price: '',
                        shipping_units: '',
                        inventory_quantity: '',
                        awaiting_fulfilment: 0,
                        reserved_quantity: 0,
                        weight_grams: '',
                        is_preorder: false,
                        preorder_shipping_estimate: '',
                        allow_backorder: false,
                        backorder_shipping_estimate: '',
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
                    this.$watch('productType', (value) => {
                        if (value === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}') {
                            this.isPreorder = false;
                            this.allowBackorder = false;
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
                    <x-ui.input name="title" label="Title" :value="$product->title ?? ''" x-model="title" x-on:input="handleTitleInput()" />
                    <x-ui.input name="slug" label="Slug" :value="$product->slug ?? ''" x-model="slug" x-on:input="handleSlugInput()" info="Auto-generated from the title until you manually edit it." />
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <x-ui.input
                        name="category"
                        label="Category"
                        :value="$product->category ?? ''"
                        :suggestions="$existingCategories ?? []"
                        info="Optional. Used to group products on the storefront."
                        showSuggestionsOnFocus="true"
                    />
                    <x-ui.input name="sku" label="Base SKU" :value="$product->sku ?? ''" x-model="baseSku" />
                    <x-ui.select
                        name="status"
                        label="Status"
                        info="Active products are live in the public store. Draft and archived products stay hidden without changing their pricing, stock, or variants."
                    >
                        @foreach(\App\Models\Product::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $product->status ?? \App\Models\Product::STATUS_DRAFT) === $status)>{{ \App\Models\Product::statusLabel($status) }}</option>
                        @endforeach
                    </x-ui.select>
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
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input name="price" label="Base Price (inc GST)" moneyFormat="true" :value="isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00'" info="Used when there are no variants, or as a fallback when a variant price is blank." x-model="basePrice" />
                    <x-ui.input name="compare_at_price" label="Base Compare-at Price" moneyFormat="true" :value="isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : ''" x-model="baseCompareAtPrice" />
                    <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                        <x-ui.input name="inventory_quantity" label="Base Inventory Quantity" type="number" min="0" :value="$product->inventory_quantity ?? ''" info="Leave blank for unlimited. Ignored when a chosen variant has its own stock quantity." class="!mb-0" />
                        @if(isset($product) && (string) ($product->product_type ?? '') === \App\Models\Product::PRODUCT_TYPE_PHYSICAL)
                            <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950">
                                <div><span class="font-semibold">Awaiting fulfilment:</span> {{ $baseInventoryContext['awaiting'] }}</div>
                                <div class="mt-1"><span class="font-semibold">Reserved now:</span> {{ $baseInventoryContext['reserved'] }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        <div class="font-semibold text-gray-900">GST</div>
                        <div class="mt-1">Store products always use 10% GST and prices entered here are GST-inclusive.</div>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="md:col-span-2 xl:col-span-3">
                        <x-ui.checkbox name="is_featured" label="Feature this product near the top of the store" :checked="(bool) old('is_featured', $product->is_featured ?? false)" />
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Admin Notes & Alerts</h2>
                        <p class="text-sm text-gray-600">Private notes stay in admin only. Low-stock alerts help surface products that need ordering attention.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                        When tracked stock drops to or below the threshold, admins receive a low-stock warning email.
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr),minmax(0,0.9fr)]">
                    <x-ui.input
                        type="textarea"
                        name="private_notes"
                        label="Private Notes"
                        :value="$product->private_notes ?? ''"
                        info="For supplier notes, purchase history, or internal reminders. Never shown publicly."
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

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Delayed Fulfilment</h2>
                        <p class="text-sm text-gray-600">Choose whether customers are buying future stock as a pre-order, or whether they can order above current stock and receive the remainder later.</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Pre-orders require customer acknowledgement. Backorders allow over-stock ordering and split the delayed quantity into a later shipment.
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                        <x-ui.checkbox
                            name="is_preorder"
                            label="Allow customers to purchase this item as a pre-order"
                            :checked="(bool) old('is_preorder', $product->is_preorder ?? false)"
                            x-model="isPreorder"
                            x-on:change="if (isPreorder) allowBackorder = false"
                            noWrapper
                        />
                        <div class="mt-2 text-sm text-gray-600">Use this when no quantity is available now and the whole line should ship later.</div>

                        <div class="mt-4" x-show="isPreorder" x-cloak>
                            <x-ui.input
                                name="preorder_shipping_estimate"
                                type="date"
                                label="Estimated Shipping Date"
                                :value="old('preorder_shipping_estimate', isset($product) && $product->preorder_shipping_estimate ? $product->preorder_shipping_estimate->format('Y-m-d') : '')"
                                info="Shown publicly on the storefront and required when pre-order is enabled."
                            />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4">
                        <x-ui.checkbox
                            name="allow_backorder"
                            label="Allow orders above available stock"
                            :checked="(bool) old('allow_backorder', $product->allow_backorder ?? false)"
                            x-model="allowBackorder"
                            x-on:change="if (allowBackorder) isPreorder = false"
                            noWrapper
                        />
                        <div class="mt-2 text-sm text-gray-600">Available stock ships first. Any extra quantity becomes a second shipment once replenished.</div>

                        <div class="mt-4" x-show="allowBackorder" x-cloak>
                            <x-ui.input
                                name="backorder_shipping_estimate"
                                type="date"
                                label="Estimated Backorder Shipping Date"
                                :value="old('backorder_shipping_estimate', isset($product) && $product->backorder_shipping_estimate ? $product->backorder_shipping_estimate->format('Y-m-d') : '')"
                                info="Shown publicly when a cart includes delayed backorder quantity."
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Packaging</h2>
                        <p class="text-sm text-gray-600">These fields control the internal parcel packing logic used across your delivery channels.</p>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        Use the smallest package size the item fits into. Weight is optional but helps split heavy parcels.
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input
                        name="shipping_units"
                        label="Package Units"
                        type="number"
                        step="0.01"
                        min="0"
                        :value="isset($product) ? number_format((float) $product->shipping_units, 2, '.', '') : '0.00'"
                        info="Internal packing space for one unit. Example: 0.50 for a small item, 1.00 for a standard kit."
                    />
                    <x-ui.select name="min_satchel_rank" label="Minimum Package Size" info="Use the smallest package size this product can fit into.">
                        @foreach($satchelOptions as $satchel)
                            <option value="{{ $satchel['rank'] }}" @selected((int) old('min_satchel_rank', $product->min_satchel_rank ?? $defaultSatchelRank) === (int) $satchel['rank'])>
                                {{ $satchel['label'] }} (capacity {{ number_format((float) $satchel['capacity'], 2) }})
                            </option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input
                        name="weight_grams"
                        label="Packed Weight (grams)"
                        type="number"
                        min="0"
                        :value="$product->weight_grams ?? ''"
                        info="Optional. This only affects packing when the total known parcel weight would exceed the store's package weight limit."
                    />
                    <x-ui.checkbox
                        name="box_only"
                        label="This item requires rigid parcel shipping"
                        :checked="(bool) old('box_only', $product->box_only ?? false)"
                        class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3"
                        inputClass="!h-5 !w-5 !min-w-5 !rounded-md"
                    />
                </div>
            </div>

            <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                Digital products ignore parcel packing and shipping fields, unlock their download files after payment, and can use licence tiers when you want to sell different usage rights.
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Licence Tiers' : 'Variants'"></h2>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak class="text-sm text-gray-600">Use variants for options like size, bundle, or finish. Variants can override price, stock, package units, packed weight, and their own preorder or backorder dates.</p>
                        <p x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="text-sm text-gray-600">Digital variants act as licence tiers. Add only the extra tiers you want to offer.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" color="outline" x-on:click="addVariant()" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Add Custom Tier' : 'Add Variant'">Add Variant</x-ui.button>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                    When you add variants, the base product becomes the first purchasable option. Give that base option its own public name here, then add any extra options below.
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak>
                    The base product becomes the first licence option and defaults to <span class="font-semibold">Home</span>. Add extra tiers only if this download needs separate licence options.
                </div>

                <div x-show="variants.length === 0" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'No extra licence tiers yet. Leave it this way for a home-only download, or add a tier below.' : 'No variants yet. Leave it this way for a single-SKU product.'">
                    No variants yet. Leave it this way for a single-SKU product.
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-900" x-text="baseOptionDisplayName()"></div>
                            <div class="mt-1 text-sm text-gray-600">This option uses the base SKU, price, stock, packaging, and weight set above.</div>
                        </div>
                        <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-700">Base Option</div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block pl-1 text-sm">Base Option Name</label>
                            <input type="text" x-bind:class="variantInputClasses" name="base_variant_name" x-model="baseVariantName">
                            <div class="mt-1 pl-1 text-xs text-gray-500" x-text="`Leave blank to show \\\"${defaultBaseOptionLabel()}\\\".`"></div>
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
                                    <input type="text" x-bind:class="variantInputClasses" :name="`variants[${index}][name]`" x-model="variant.name">
                                    <div class="mt-1 pl-1 text-xs text-gray-500">Each added option needs its own name.</div>
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">SKU</label>
                                    <input type="text" x-bind:class="variantInputClasses" :name="`variants[${index}][sku]`" x-model="variant.sku">
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Price</label>
                                    <input type="number" step="0.01" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][price]`" x-model="variant.price">
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Compare-at Price</label>
                                    <input type="number" step="0.01" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block pl-1 text-sm" x-text="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Licence Details' : 'Variant Details'"></label>
                                <textarea x-bind:class="variantTextareaClasses" :name="`variants[${index}][description]`" x-model="variant.description" :placeholder="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}' ? 'Describe who this licence tier covers and where it may be used.' : 'Optional extra notes for this option.'"></textarea>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Package Units</label>
                                    <input type="number" step="0.01" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][shipping_units]`" x-model="variant.shipping_units">
                                    <div class="mt-1 pl-1 text-xs text-gray-500">Leave blank to use the base product package units.</div>
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Inventory Quantity</label>
                                    <input type="number" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][inventory_quantity]`" x-model="variant.inventory_quantity">
                                    <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950" x-show="variant.id" x-cloak>
                                        <div><span class="font-semibold">Awaiting fulfilment:</span> <span x-text="Number(variant.awaiting_fulfilment || 0)"></span></div>
                                        <div class="mt-1"><span class="font-semibold">Reserved now:</span> <span x-text="Number(variant.reserved_quantity || 0)"></span></div>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Packed Weight (grams)</label>
                                    <input type="number" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][weight_grams]`" x-model="variant.weight_grams">
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4">
                                    <label class="flex items-center gap-3 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                            :name="`variants[${index}][is_preorder]`"
                                            x-model="variant.is_preorder"
                                            x-on:change="if (variant.is_preorder) variant.allow_backorder = false"
                                        >
                                        Variant is a pre-order
                                    </label>
                                    <div class="mt-2 text-sm text-gray-600">Use this when this variant is not available yet and should ship later as a full pre-order.</div>

                                    <div class="mt-4" x-show="variant.is_preorder" x-cloak>
                                        <label class="mb-1 block pl-1 text-sm">Estimated Shipping Date</label>
                                        <input type="date" x-bind:class="variantInputClasses" :name="`variants[${index}][preorder_shipping_estimate]`" x-model="variant.preorder_shipping_estimate">
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4">
                                    <label class="flex items-center gap-3 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                            :name="`variants[${index}][allow_backorder]`"
                                            x-model="variant.allow_backorder"
                                            x-on:change="if (variant.allow_backorder) variant.is_preorder = false"
                                        >
                                        Allow backorders for this variant
                                    </label>
                                    <div class="mt-2 text-sm text-gray-600">Use this when available stock can ship now and any extra quantity should ship once this variant is replenished.</div>

                                    <div class="mt-4" x-show="variant.allow_backorder" x-cloak>
                                        <label class="mb-1 block pl-1 text-sm">Estimated Backorder Shipping Date</label>
                                        <input type="date" x-bind:class="variantInputClasses" :name="`variants[${index}][backorder_shipping_estimate]`" x-model="variant.backorder_shipping_estimate">
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,0.55fr),minmax(0,0.85fr)]">
                                <div>
                                    <label class="mb-1 block pl-1 text-sm">Sort Order</label>
                                    <input type="number" min="0" x-bind:class="variantInputClasses" :name="`variants[${index}][sort_order]`" x-model="variant.sort_order">
                                </div>
                                <label class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                    <input type="checkbox" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" x-model="variant.is_active">
                                    Active and purchasable
                                </label>
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

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit">Save Product</x-ui.button>
                @isset($product)
                    <x-ui.button type="link" href="{{ route('shop.product.show', $product) }}" color="outline">View Product</x-ui.button>
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
