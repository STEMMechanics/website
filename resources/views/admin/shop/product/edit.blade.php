@php
    $galleryFilesValue = isset($product)
        ? implode(',', $product->galleryMedia->pluck('name')->all())
        : '';
    $downloadFilesValue = isset($product)
        ? json_encode($product->downloadMedia->pluck('name')->values()->all())
        : json_encode([]);
    $satchelOptions = \App\Models\Product::satchelOptions();
    $defaultSatchelRank = (int) ($satchelOptions->first()['rank'] ?? 1);
    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = isset($product)
            ? $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price !== null ? number_format((float) $variant->price, 2, '.', '') : '',
                'compare_at_price' => $variant->compare_at_price !== null ? number_format((float) $variant->compare_at_price, 2, '.', '') : '',
                'inventory_quantity' => $variant->inventory_quantity,
                'weight_grams' => $variant->weight_grams,
                'sort_order' => $variant->sort_order,
                'is_active' => (bool) $variant->is_active,
            ])->values()->all()
            : [];
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
                variants: @js($variantRows),
                addVariant() {
                    this.variants.push({
                        id: null,
                        name: '',
                        sku: '',
                        price: '',
                        compare_at_price: '',
                        inventory_quantity: '',
                        weight_grams: '',
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
            }"
        >
            @csrf
            @isset($product)
                @method('PUT')
            @endisset

            <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$product->title ?? ''" />
                    <x-ui.input name="slug" label="Slug" :value="$product->slug ?? ''" />
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
                    <x-ui.input name="sku" label="Base SKU" :value="$product->sku ?? ''" />
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
                <x-ui.input type="textarea" name="description" label="Description" :value="$product->description ?? ''" />
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input name="price" label="Base Price (inc GST)" moneyFormat="true" :value="isset($product) ? number_format((float) $product->price, 2, '.', '') : '0.00'" info="Used when there are no variants, or as a fallback when a variant price is blank." />
                    <x-ui.input name="compare_at_price" label="Base Compare-at Price" moneyFormat="true" :value="isset($product) && $product->compare_at_price !== null ? number_format((float) $product->compare_at_price, 2, '.', '') : ''" />
                    <x-ui.input name="inventory_quantity" label="Base Inventory Quantity" type="number" min="0" :value="$product->inventory_quantity ?? ''" info="Leave blank for unlimited. Ignored when a chosen variant has its own stock quantity." />
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

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Satchel Shipping</h2>
                        <p class="text-sm text-gray-600">These fields control the internal Australia Post satchel-style packing logic.</p>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        Use the smallest satchel the item fits into. Weight is optional but helps split heavy parcels.
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <x-ui.input
                        name="shipping_units"
                        label="Shipping Units"
                        type="number"
                        step="0.01"
                        min="0"
                        :value="isset($product) ? number_format((float) $product->shipping_units, 2, '.', '') : '0.00'"
                        info="Internal packing space for one unit. Example: 0.50 for a small item, 1.00 for a standard kit."
                    />
                    <x-ui.select name="min_satchel_rank" label="Minimum Satchel" info="Use the smallest satchel size this product can fit into.">
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
                        info="Optional. This only affects packing when the total known parcel weight would exceed the store's satchel weight limit."
                    />
                    <x-ui.checkbox
                        name="box_only"
                        label="This item is box-only and cannot ship in satchels"
                        :checked="(bool) old('box_only', $product->box_only ?? false)"
                        class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3"
                        inputClass="!h-5 !w-5 !min-w-5 !rounded-md"
                    />
                </div>
            </div>

            <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_DIGITAL }}'" x-cloak class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                Digital products ignore satchel packing and shipping fields, unlock their download files after payment, and do not use variants.
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Variants</h2>
                        <p class="text-sm text-gray-600">Use variants for options like size or colour. Variants can override price, stock, and packed weight only.</p>
                    </div>
                    <x-ui.button type="button" color="outline" x-on:click="addVariant()">Add Variant</x-ui.button>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    Satchel size, shipping units, and box-only behavior stay on the base product so packing stays predictable.
                </div>

                <div x-show="variants.length === 0" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-600">
                    No variants yet. Leave it this way for a single-SKU product.
                </div>

                <div class="space-y-4">
                    <template x-for="(variant, index) in variants" :key="index">
                        <div class="rounded-2xl border border-gray-200 p-4 space-y-4 bg-gray-50">
                            <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id">
                            <input type="hidden" :name="`variants[${index}][is_active]`" :value="variant.is_active ? 1 : 0">

                            <div class="flex items-center justify-between gap-4">
                                <div class="text-lg font-semibold text-gray-900" x-text="variant.name || `Variant ${index + 1}`"></div>
                                <button type="button" class="text-sm text-red-600 hover:underline" x-on:click="removeVariant(index)">Remove</button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Variant Name</label>
                                    <input type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][name]`" x-model="variant.name">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Variant SKU</label>
                                    <input type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][sku]`" x-model="variant.sku">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Price</label>
                                    <input type="number" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][price]`" x-model="variant.price">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Compare-at Price</label>
                                    <input type="number" step="0.01" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][compare_at_price]`" x-model="variant.compare_at_price">
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Inventory Quantity</label>
                                    <input type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][inventory_quantity]`" x-model="variant.inventory_quantity">
                                </div>
                                <div x-show="productType === '{{ \App\Models\Product::PRODUCT_TYPE_PHYSICAL }}'" x-cloak>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Packed Weight (grams)</label>
                                    <input type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][weight_grams]`" x-model="variant.weight_grams">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Sort Order</label>
                                    <input type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500" :name="`variants[${index}][sort_order]`" x-model="variant.sort_order">
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
                    <x-ui.filelist name="download_files" label="Digital Download Files" value="{{ $downloadFilesValue }}" editor="true" />
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
