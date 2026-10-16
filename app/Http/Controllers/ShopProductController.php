<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StoreOrderItem;
use App\Services\Finance\ProductAllocationEditor;
use App\Services\ProductAttention;
use App\Services\ProductPurchaseService;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use App\Services\SiteListControls;
use App\Services\StoreInventoryAllocatorService;
use App\Support\ListPageSize;
use App\Support\ShopShippingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['hero', 'variants' => fn ($relation) => $relation->chaperone(), 'categories'])->withExists('storeOrderItems');
        $legacyFilter = $this->normalizeIndexFilter($request->query('filter'));
        $scope = $request->query('status_scope', $legacyFilter === 'archived' ? 'archived' : 'current') ?: 'all';
        $inventory = $request->query('inventory', $legacyFilter === 'low_stock' ? 'low_stock' : '');
        $request->validate(['status_scope' => ['nullable', Rule::in(['all', 'current', 'draft', 'archived'])], 'inventory' => ['nullable', Rule::in(['low_stock', 'actionable'])], 'allocation_state' => ['nullable', Rule::in(['needs_review', 'allocated'])]]);
        if ($inventory === 'actionable') {
            $inventory = 'low_stock';
        }
        $request->query->set('status_scope', $scope);
        if ($inventory) {
            $request->query->set('inventory', $inventory);
        }
        $request->query->remove('filter');
        $allocationAttentionIds = app(ProductAllocationEditor::class)->attentionIds();
        $allocationAttentionCount = Product::where('status', Product::STATUS_ACTIVE)->whereIn('id', $allocationAttentionIds)->count();
        if ($request->query('allocation_state') === 'needs_review') {
            $query->whereIn('id', $allocationAttentionIds);
        } elseif ($request->query('allocation_state') === 'allocated') {
            $query->whereNotIn('id', $allocationAttentionIds);
        }
        $productAttention = app(ProductAttention::class)->counts();
        $lowStockCount = $productAttention['inventory'];
        $request->attributes->set('collection_preset_counts', [
            'Current products' => Product::query()->where('status', Product::STATUS_ACTIVE)->count(),
            'Materials' => Product::query()->active()->where('product_type', Product::PRODUCT_TYPE_MATERIAL)->count(),
            'Kits' => Product::query()->active()->where('product_type', Product::PRODUCT_TYPE_KIT)->count(),
            'Drafts' => Product::query()->where('status', Product::STATUS_DRAFT)->count(),
            'Needs allocation' => $allocationAttentionCount,
            'Archived' => Product::query()->where('status', Product::STATUS_ARCHIVED)->count(),
            'Low Stock' => $lowStockCount,
        ]);
        $request->attributes->set('collection_preset_attention', [
            'Needs allocation' => $allocationAttentionCount > 0,
            'Low Stock' => $lowStockCount > 0,
        ]);
        if ($scope === 'archived') {
            $query->where('status', Product::STATUS_ARCHIVED);
        } elseif ($scope === 'draft') {
            $query->where('status', Product::STATUS_DRAFT);
        } elseif ($scope === 'current') {
            $query->where('status', Product::STATUS_ACTIVE);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('search_terms', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhereHas('categories', fn ($categoryQuery) => $categoryQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%'))
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title');

        if ($inventory === 'low_stock') {
            $matchingProducts = $query->tap(fn ($listingQuery) => app(SiteListControls::class)->apply($listingQuery))->get();
            $inventorySummaries = app(ProductAttention::class)->inventorySummaries($matchingProducts);
            $products = $this->paginateProducts(
                $matchingProducts
                    ->filter(fn (Product $product): bool => (bool) ($inventorySummaries[(int) $product->id]['low_stock'] ?? false))
                    ->values(),
                $request
            );
        } else {
            $products = $query->tap(fn ($listingQuery) => app(SiteListControls::class)->apply($listingQuery))->paginate(ListPageSize::resolve(20))->onEachSide(1);
            $inventorySummaries = app(ProductAttention::class)->inventorySummaries($products->getCollection());
        }

        return view('admin.shop.product.index', [
            'products' => $products,
            'inventorySummaries' => $inventorySummaries,
            'allocationAttentionIds' => $allocationAttentionIds,
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.product.edit', [
            'categories' => $this->availableCategories(),
            'recipeCatalog' => app(ProductRecipeService::class)->catalog(),
        ]);
    }

    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'sku' => 'nullable|string|max:120', 'purchase_unit' => 'required|string|max:40']);
        $product = new Product;
        $sku = trim((string) ($data['sku'] ?? '')) ?: $this->uniqueProductSkuSeed($request, $product);
        if ($this->productSkuIsTaken($sku, $product)) {
            throw ValidationException::withMessages(['sku' => 'That SKU is already in use.']);
        }
        $product->fill(['title' => $data['title'], 'sku' => $sku, 'product_type' => Product::PRODUCT_TYPE_MATERIAL,
            'status' => Product::STATUS_ACTIVE, 'material_kind' => 'unit', 'purchase_unit' => $data['purchase_unit'],
            'purchase_quantity' => 1, 'price' => 0, 'tax_rate' => 0.10, 'shipping_rate' => 0]);
        $product->save();
        $item = collect(app(ProductPurchaseService::class)->catalog())->firstWhere('id', (int) $product->id);

        return response()->json(['item' => $item], 201);
    }

    public function store(Request $request, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        $product = new Product;
        DB::transaction(fn () => $this->saveProduct($request, $product, $allocator));

        session()->flash('message', 'Product has been created.');
        session()->flash('message-title', 'Product created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.index');
    }

    public function edit(Product $product): View
    {
        $product = $product->load(['hero', 'galleryMedia', 'downloadMedia', 'variants', 'categories', 'cutSizes', 'recipeComponents.component', 'recipeComponents.cutSize', 'recipeComponents.variant', 'purchases.expense', 'purchases.variant'])->loadExists('storeOrderItems');

        return view('admin.shop.product.edit', [
            'product' => $product,
            'categories' => $this->availableCategories(),
            'inventoryContexts' => $this->inventoryContexts($product),
            'recipeCatalog' => app(ProductRecipeService::class)->catalog($product),
        ]);
    }

    public function update(Request $request, Product $product, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        DB::transaction(fn () => $this->saveProduct($request, $product, $allocator));

        session()->flash('message', 'Product has been updated.');
        session()->flash('message-title', 'Product updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.edit', $product);
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $copy = DB::transaction(function () use ($product): Product {
            $product->loadMissing('variants');

            $copy = $product->replicate();
            $copy->slug = $this->uniqueCopySlug((string) $product->slug);
            $copy->sku = $this->uniqueCopySku((string) $product->sku);
            $copy->status = Product::STATUS_DRAFT;
            $copy->is_featured = false;
            $copy->low_stock_alert_sent_at = null;
            $copy->legacy_base_variant_id = null;
            $copy->default_variant_id = null;
            $copy->save();

            $categoryAssignments = DB::table('product_category_product')
                ->where('product_id', $product->id)
                ->pluck('sort_order', 'product_category_id')
                ->map(fn ($sortOrder): array => ['sort_order' => (int) $sortOrder])
                ->all();
            $copy->categories()->sync($categoryAssignments);
            $copy->updateFiles($product->galleryMedia()->pluck('media.name')->all(), 'gallery');
            $copy->updateFiles($product->downloadMedia()->pluck('media.name')->all(), 'downloads');

            $variantCopies = [];
            foreach ($product->variants as $variant) {
                $variantCopy = $variant->replicate();
                $variantCopy->product_id = $copy->id;
                $variantCopy->sku = trim((string) $variant->sku) !== ''
                    ? $this->uniqueCopySku((string) $variant->sku)
                    : null;
                $variantCopy->low_stock_alert_sent_at = null;
                $variantCopy->save();
                $variantCopies[(int) $variant->id] = $variantCopy;
            }
            if ($product->usesVariantRecords()) {
                $copy->legacy_base_variant_id = $variantCopies[$product->legacy_base_variant_id]->id;
                $copy->default_variant_id = isset($variantCopies[$product->default_variant_id])
                    ? $variantCopies[$product->default_variant_id]->id : null;
                $copy->save();
            }

            foreach ($product->cutSizes as $cut) {
                $copy->cutSizes()->create($cut->only(['length_mm', 'width_mm', 'yield', 'sort_order']));
            }
            foreach ($product->recipeComponents as $row) {
                $copy->recipeComponents()->create($row->only(['component_product_id', 'component_variant_id', 'cut_size_id', 'quantity', 'quantity_unit', 'length', 'length_unit', 'note', 'sort_order']));
            }

            return $copy;
        });

        session()->flash('message', 'Product has been duplicated as a draft.');
        session()->flash('message-title', 'Product duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.edit', $copy);
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        if ($product->usedInRecipes()->exists() || $product->purchases()->exists() || $product->hasPickListReferences()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This item is used in a recipe, pick list or purchase. Archive it instead.'], 409);
            }

            return redirect()->route('admin.shop.product.edit', $product)->with(['message' => 'This item is used in a recipe, pick list or purchase. Archive it instead.', 'message-title' => 'Delete blocked', 'message-type' => 'danger']);
        }
        if ($product->storeOrderItems()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This product has already been ordered and cannot be deleted. Archive it instead.'], 409);
            }

            session()->flash('message', 'This product has already been ordered and cannot be deleted. Archive it instead.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.shop.product.edit', $product);
        }

        $product->variants()->each(function (ProductVariant $variant): void {
            $variant->delete();
        });
        $product->galleryMedia()->detach();
        $product->downloadMedia()->detach();
        $product->delete();

        session()->flash('message', 'Product deleted.');
        session()->flash('message-title', 'Product deleted');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('admin.shop.product.index')]);
        }

        return redirect()->route('admin.shop.product.index');
    }

    public function archive(Product $product): RedirectResponse
    {
        $product->update([
            'status' => Product::STATUS_ARCHIVED,
            'is_featured' => false,
        ]);

        session()->flash('message', 'Product archived. It is no longer available in the store.');
        session()->flash('message-title', 'Product archived');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.index');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->update(['status' => Product::STATUS_DRAFT]);

        session()->flash('message', 'Product restored as a draft. Review it before making it active.');
        session()->flash('message-title', 'Product restored');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.edit', $product);
    }

    private function saveProduct(Request $request, Product $product, StoreInventoryAllocatorService $allocator): void
    {
        if ($product->usesVariantRecords() && ! $request->boolean('variant_editor')) {
            throw ValidationException::withMessages(['variants' => 'Refresh the product editor before saving these variants.']);
        }
        $unified = $request->boolean('variant_editor');
        $previousDefaultVariant = $product->usesVariantRecords()
            ? $product->variants()->find($product->default_variant_id) : null;
        $isMaterial = $request->input('product_type') === Product::PRODUCT_TYPE_MATERIAL;
        $isPhysical = in_array($request->input('product_type'), Product::PHYSICAL_TYPES, true);
        if ($unified && ! ShopShippingSettings::hasCalculatedChannels()) {
            $request->merge($product->only(['length_mm', 'width_mm', 'height_mm', 'weight_grams', 'box_only', 'shipping_units', 'min_satchel_rank']));
            $rows = $request->input('variants', []);
            if (is_array($rows)) {
                foreach ($rows as &$row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $savedId = ($row['id'] ?? null) ?: (! empty($row['original_option']) ? $product->legacy_base_variant_id : null);
                    $saved = $product->exists ? $product->variants()->find($savedId) : null;
                    foreach (['length_mm', 'width_mm', 'height_mm', 'weight_grams'] as $field) {
                        $row[$field] = $saved?->$field;
                    }
                }
                unset($row);
                $request->merge(['variants' => $rows]);
            }
        }
        if ($isMaterial) {
            // Hidden selling controls must not prevent a material from saving.
            $request->merge(['price' => 0, 'variants' => [], 'category_ids' => [], 'allow_backorder' => false, 'is_featured' => false, 'inventory_quantity' => null, 'product_details' => [], 'length_mm' => null, 'width_mm' => null, 'height_mm' => null]);
        }
        $recipes = app(ProductRecipeService::class);
        $recipeData = $recipes->validate($request, $product);
        $productAllocations = $isMaterial ? null : app(ProductAllocationEditor::class)->validate($request);
        $previousProductInventory = $product->exists ? $product->inventory_quantity : null;
        $previousVariantInventory = $product->exists
            ? $product->variants()->pluck('inventory_quantity', 'id')->map(fn ($quantity) => $quantity !== null ? (int) $quantity : null)->all()
            : [];
        $previousAutomaticPricing = [
            'base' => (bool) $product->automatic_pricing,
            'variants' => $product->exists
                ? $product->variants()->where('automatic_pricing', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [],
        ];
        $satchelRanks = Product::satchelOptions()->pluck('rank')->map(fn ($rank) => (int) $rank)->values()->all();
        if ($satchelRanks === []) {
            $satchelRanks = [1];
        }

        $baseSku = trim((string) $request->input('sku', ''));
        if ($baseSku === '') {
            $request->merge([
                'sku' => $this->uniqueProductSkuSeed($request, $product),
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'category' => ['nullable', 'string', 'max:120'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:product_categories,id'],
            'sku' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'product_type' => ['required', Rule::in(Product::PRODUCT_TYPES)],
            'allow_backorder' => ['nullable', 'boolean'],
            'backorder_shipping_estimate_type' => ['nullable', Rule::in([
                Product::BACKORDER_SHIPPING_ESTIMATE_STATIC,
                Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC,
            ])],
            'backorder_shipping_estimate' => ['nullable', 'date'],
            'backorder_shipping_offset_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'search_terms' => ['nullable', 'string', 'max:2000'],
            'product_details' => ['nullable', 'array', 'max:30'],
            'product_details.*.key' => ['nullable', 'string', 'max:120'],
            'product_details.*.value' => ['nullable', 'string', 'max:500'],
            'caution_message' => ['nullable', 'string', 'max:1000'],
            'base_variant_name' => ['nullable', 'string', 'max:120'],
            'base_variant_description' => ['nullable', 'string', 'max:2000'],
            'private_notes' => ['nullable', 'string'],
            'hero_media_name' => ['nullable', 'exists:media,name'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'shared_inventory' => ['sometimes', 'boolean'],
            'inventory_units' => ['nullable', 'integer', 'min:1'],
            'inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'shipping_units' => ['nullable', 'numeric', 'min:0'],
            'min_satchel_rank' => ['nullable', 'integer', Rule::in($satchelRanks)],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'width_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'box_only' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:1'],
            'gallery_files' => ['nullable', 'string'],
            'download_files' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.description' => ['nullable', 'string', 'max:2000'],
            'variants.*.product_details' => ['nullable', 'array', 'max:30'],
            'variants.*.product_details.*.key' => ['nullable', 'string', 'max:120'],
            'variants.*.product_details.*.value' => ['nullable', 'string', 'max:500'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.inventory_units' => ['nullable', 'integer', 'min:1'],
            'variants.*.inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.unit_cost_ex_tax' => ['nullable', 'numeric', 'min:0'],
            'variants.*.bulk_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'variants.*.automatic_pricing' => ['nullable', 'boolean'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.length_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'variants.*.width_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'variants.*.height_mm' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:1'],
            'variants.*.allow_backorder' => ['nullable', 'boolean'],
            'variants.*.backorder_shipping_estimate_type' => ['nullable', Rule::in([
                Product::BACKORDER_SHIPPING_ESTIMATE_STATIC,
                Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC,
            ])],
            'variants.*.backorder_shipping_estimate' => ['nullable', 'date'],
            'variants.*.backorder_shipping_offset_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.is_base' => ['nullable', 'boolean'],
            'variants.*.original_option' => ['nullable', 'boolean'],
        ], [
            'sku.required' => 'The Base SKU field is required.',
        ]);

        $isDigital = (string) $validated['product_type'] === Product::PRODUCT_TYPE_DIGITAL;
        $allowsBackorder = $isPhysical && $request->boolean('allow_backorder');
        $backorderShippingEstimateType = $this->backorderShippingEstimateType(
            $validated['backorder_shipping_estimate_type'] ?? null,
            $validated['backorder_shipping_estimate'] ?? null,
            $validated['backorder_shipping_offset_days'] ?? null,
        );
        $backorderShippingEstimate = $validated['backorder_shipping_estimate'] ?? null;
        $backorderShippingOffsetDays = $validated['backorder_shipping_offset_days'] ?? null;

        if ($allowsBackorder) {
            if ($backorderShippingEstimateType === Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC) {
                if ($backorderShippingOffsetDays === null) {
                    throw ValidationException::withMessages([
                        'backorder_shipping_offset_days' => 'A day offset is required when using a dynamic backorder estimate.',
                    ]);
                }
            } elseif ($backorderShippingEstimate === null) {
                throw ValidationException::withMessages([
                    'backorder_shipping_estimate' => 'An estimated shipping date is required when backorders are allowed.',
                ]);
            }
        }

        $productSku = trim((string) ($validated['sku'] ?? ''));
        if ($productSku === '') {
            throw ValidationException::withMessages([
                'sku' => 'The Base SKU field is required.',
            ]);
        }

        $productSkuKey = mb_strtolower($productSku);
        $productSkuExists = Product::query()
            ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))
            ->whereRaw('LOWER(sku) = ?', [$productSkuKey])
            ->exists();
        if ($productSkuExists) {
            throw ValidationException::withMessages([
                'sku' => 'That SKU is already in use by another product.',
            ]);
        }

        $variantSkuExists = ProductVariant::query()
            ->when($product->legacy_base_variant_id, fn ($query) => $query->where('id', '!=', $product->legacy_base_variant_id))
            ->whereRaw('LOWER(sku) = ?', [$productSkuKey])
            ->exists();
        if ($variantSkuExists) {
            throw ValidationException::withMessages([
                'sku' => 'That SKU is already in use by a product variant.',
            ]);
        }

        $variantRows = $validated['variants'] ?? [];
        foreach ($variantRows as &$row) {
            if ($unified && $product->usesVariantRecords() && ! empty($row['original_option']) && empty($row['id'])) {
                $row['id'] = $product->legacy_base_variant_id;
            }
            if ($unified && ! $product->usesVariantRecords() && ! empty($row['original_option']) && empty($row['sku'])) {
                $row['sku'] = $productSku;
            }
        }
        unset($row);
        $normalizedVariants = $this->normalizeVariants($variantRows, $product);
        if ($unified && ! $isMaterial) {
            if ($normalizedVariants->where('original_option', true)->count() > 1
                || $normalizedVariants->pluck('id')->filter()->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['variants' => 'Each variant can only appear once.']);
            }
            if ($normalizedVariants->where('is_base', true)->count() > 1) {
                throw ValidationException::withMessages(['variants' => 'Choose only one base variant.']);
            }
            foreach ($normalizedVariants as $row) {
                if ($row['is_base'] && ! $row['is_active']) {
                    throw ValidationException::withMessages(['variants.'.$row['row_index'].'.is_base' => 'The base variant must be active.']);
                }
            }
            if ($validated['status'] === Product::STATUS_ACTIVE && ! $normalizedVariants->contains('is_active', true)) {
                throw ValidationException::withMessages(['variants' => 'An active product needs at least one active variant. Activate another variant or change the product to draft or archived.']);
            }
        }
        $selectedCategories = $this->resolveSelectedCategories($validated, (string) ($validated['category'] ?? ''));
        $isFeatured = (string) $validated['status'] === Product::STATUS_ACTIVE && $request->boolean('is_featured');
        $product->fill([
            'title' => trim((string) $validated['title']),
            'subtitle' => trim((string) ($validated['subtitle'] ?? '')) ?: null,
            'slug' => trim((string) ($validated['slug'] ?? '')) ?: null,
            'category' => $selectedCategories->first()?->name,
            'sku' => trim((string) ($validated['sku'] ?? '')) ?: null,
            'status' => (string) $validated['status'],
            'product_type' => (string) $validated['product_type'],
            'is_preorder' => false,
            'preorder_shipping_estimate' => null,
            'allow_backorder' => $allowsBackorder,
            'backorder_shipping_estimate_type' => $allowsBackorder ? $backorderShippingEstimateType : null,
            'backorder_shipping_estimate' => $allowsBackorder && $backorderShippingEstimateType === Product::BACKORDER_SHIPPING_ESTIMATE_STATIC
                ? $backorderShippingEstimate
                : null,
            'backorder_shipping_offset_days' => $allowsBackorder && $backorderShippingEstimateType === Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC
                ? (int) $backorderShippingOffsetDays
                : null,
            'short_description' => trim((string) ($validated['short_description'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'search_terms' => trim((string) ($validated['search_terms'] ?? '')) ?: null,
            'product_details' => $this->ensureBaseSkuProductDetails(collect($validated['product_details'] ?? [])
                ->map(fn (array $detail): array => [
                    'key' => trim((string) ($detail['key'] ?? '')),
                    'value' => trim((string) ($detail['value'] ?? '')),
                ])
                ->filter(fn (array $detail): bool => $detail['key'] !== '')
                ->values()
                ->all()) ?: null,
            'caution_message' => trim((string) ($validated['caution_message'] ?? '')) ?: null,
            'base_variant_name' => trim((string) ($validated['base_variant_name'] ?? '')) ?: null,
            'base_variant_description' => trim((string) ($validated['base_variant_description'] ?? '')) ?: null,
            'private_notes' => trim((string) ($validated['private_notes'] ?? '')) ?: null,
            'hero_media_name' => trim((string) ($validated['hero_media_name'] ?? '')) ?: null,
            'price' => $isPhysical && $request->boolean('automatic_pricing') && $product->exists
                ? (float) $product->price
                : round((float) $validated['price'], 2),
            'compare_at_price' => ($validated['compare_at_price'] ?? null) !== null ? round((float) $validated['compare_at_price'], 2) : null,
            'shipping_rate' => 0,
            'tax_rate' => 0.10,
            'shared_inventory' => $isPhysical && $request->boolean('shared_inventory'),
            'inventory_units' => (int) ($validated['inventory_units'] ?? 1),
            'inventory_quantity' => $isPhysical ? ($validated['inventory_quantity'] ?? null) : null,
            'stock_item_id' => null,
            'stock_quantity_per_sale' => null,
            'shipping_units' => ! $isPhysical ? 0 : round((float) ($validated['shipping_units'] ?? 0), 3),
            'min_satchel_rank' => ! $isPhysical ? 1 : (int) ($validated['min_satchel_rank'] ?? $satchelRanks[0]),
            'weight_grams' => ! $isPhysical ? null : ($validated['weight_grams'] ?? null),
            'box_only' => ! $isPhysical ? false : $request->boolean('box_only'),
            'length_mm' => ! $isPhysical ? null : ($validated['length_mm'] ?? null),
            'width_mm' => ! $isPhysical ? null : ($validated['width_mm'] ?? null),
            'height_mm' => ! $isPhysical ? null : ($validated['height_mm'] ?? null),
            'is_featured' => $isFeatured,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'low_stock_threshold' => ! $isPhysical ? null : ($validated['low_stock_threshold'] ?? 5),
        ]);
        $product->fill([
            'material_kind' => $recipeData['material_kind'],
            'purchase_unit' => $recipeData['purchase_unit'],
            'purchase_quantity' => $recipeData['purchase_quantity'],
            'purchase_pack_units' => $recipeData['purchase_pack_units'],
            'material_measure_unit' => $recipeData['material_measure_unit'],
            'material_length_mm' => $recipeData['material_length_mm'],
            'material_width_mm' => $recipeData['material_width_mm'],
            'unit_cost_ex_tax' => $recipeData['unit_cost_ex_tax'] ?? null,
            'operational_cost_ex_tax' => $isPhysical ? ($recipeData['operational_cost_ex_tax'] ?? null) : null,
            'time_cost_ex_tax' => $isPhysical ? ($recipeData['time_cost_ex_tax'] ?? null) : null,
            'automatic_pricing' => $isPhysical && $request->boolean('automatic_pricing'),
            'markup_percent' => $isPhysical ? ($recipeData['markup_percent'] ?? null) : null,
            'bulk_discount_percent' => $isPhysical ? (array_key_exists('bulk_discount_percent', $recipeData) ? $recipeData['bulk_discount_percent'] : $product->bulk_discount_percent) : null,
            'automatic_allocation' => $isPhysical && ($request->exists('automatic_allocation') ? $request->boolean('automatic_allocation') : (bool) $product->automatic_allocation),
            'purchase_cost_centre' => $recipeData['purchase_cost_centre'] ?? $product->purchase_cost_centre ?? 'auto',
            'allocation_remainder' => array_key_exists('allocation_remainder', $recipeData) ? $recipeData['allocation_remainder'] : $product->allocation_remainder,
        ]);
        $product->save();
        $product->categories()->sync(
            $selectedCategories
                ->values()
                ->mapWithKeys(fn (ProductCategory $category, int $index) => [
                    $category->id => ['sort_order' => $index],
                ])
                ->all()
        );
        $product->updateFiles($request->input('gallery_files'), 'gallery');
        $product->updateFiles($isDigital ? $request->input('download_files') : null, 'downloads');

        if ($unified && ! $isMaterial && ! $product->usesVariantRecords()) {
            $original = app(ProductVariantUpgrade::class)->convert($product, migrateReferences: ! $product->wasRecentlyCreated);
            $normalizedVariants = $normalizedVariants->map(function (array $row) use ($original): array {
                if ($row['original_option'] && $row['id'] === null) {
                    $row['id'] = $original->id;
                }

                return $row;
            });
            if ($previousAutomaticPricing['base']) {
                $previousAutomaticPricing['variants'][] = $original->id;
            }
        }
        if ($unified && ! $product->shared_inventory && ! $isMaterial) {
            $product->inventory_quantity = null;
            $product->save();
        }
        $savedVariants = $this->syncVariants($product, $normalizedVariants, ! $isPhysical);
        if ($unified && ! $isMaterial) {
            $active = $normalizedVariants->where('is_active', true)->sortBy('sort_order');
            $oldBase = $normalizedVariants->firstWhere('id', $product->default_variant_id);
            $next = $active->first(function (array $row) use ($oldBase, $previousDefaultVariant): bool {
                $order = $oldBase['sort_order'] ?? $previousDefaultVariant->sort_order ?? -1;

                return $row['sort_order'] > $order
                    || $row['sort_order'] === $order && ($oldBase
                        ? $row['row_index'] > $oldBase['row_index']
                        : strcasecmp($row['name'], $previousDefaultVariant->name ?? '') > 0);
            }) ?? $active->first();
            $base = $active->firstWhere('is_base', true)
                ?? $active->firstWhere('id', $product->default_variant_id)
                ?? $next;
            $product->default_variant_id = $base ? $savedVariants[$base['row_index']]->id : null;
            $product->save();
            $product->load('variants');
            foreach ($product->orderedVariants() as $index => $variant) {
                if ((int) $variant->sort_order !== $index) {
                    $variant->update(['sort_order' => $index]);
                }
            }
        }
        app(ProductAllocationEditor::class)->save($product, $savedVariants, $productAllocations, $request->user()->id);
        $recipes->save($product, $recipeData, $savedVariants, $previousAutomaticPricing);
        $product->refresh();
        $freshProduct = $product->fresh('variants');
        $this->allocateRestockedInventory($freshProduct, $previousProductInventory, $previousVariantInventory, $allocator);

        if ($freshProduct instanceof Product
            && ($freshProduct->inventory_quantity === null
                || $freshProduct->effectiveLowStockThreshold() === null
                || (int) $freshProduct->inventory_quantity > $freshProduct->effectiveLowStockThreshold())
        ) {
            $freshProduct->low_stock_alert_sent_at = null;
            $freshProduct->save();
        }
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function availableCategories(): Collection
    {
        return ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, ProductCategory>
     */
    private function resolveSelectedCategories(array $validated, string $legacyCategory): Collection
    {
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($categoryId) => (int) $categoryId)
            ->filter(fn (int $categoryId) => $categoryId > 0)
            ->unique()
            ->values();

        $categories = $categoryIds->isNotEmpty()
            ? ProductCategory::query()
                ->whereIn('id', $categoryIds->all())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $legacyCategory = trim($legacyCategory);
        if ($categories->isEmpty() && $legacyCategory !== '') {
            $resolvedCategory = ProductCategory::resolveFromLabel($legacyCategory);
            if ($resolvedCategory instanceof ProductCategory) {
                $categories = collect([$resolvedCategory]);
            }
        }

        return $categories->values();
    }

    private function uniqueProductSkuSeed(Request $request, Product $product): string
    {
        $seed = trim((string) $request->input('slug', ''));
        if ($seed === '') {
            $seed = trim((string) $request->input('title', ''));
        }
        if ($seed === '') {
            $seed = 'product';
        }

        $candidate = Str::slug($seed);
        if ($candidate === '') {
            $candidate = 'product';
        }

        $candidate = strtolower($candidate);

        $suffix = 2;
        while ($this->productSkuIsTaken($candidate, $product)) {
            $suffixText = '-'.$suffix;
            $base = $candidate;
            $maxBaseLength = max(1, 120 - strlen($suffixText));
            if (strlen($base) > $maxBaseLength) {
                $base = substr($base, 0, $maxBaseLength);
                $base = rtrim($base, '-');
                if ($base === '') {
                    $base = 'product';
                }
            }

            $candidate = $base.$suffixText;
            $suffix += 1;
        }

        return $candidate;
    }

    private function uniqueCopySlug(string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'product';
        }

        $candidateBase = Str::limit($base, 249, '');
        $candidate = $candidateBase.'-copy';
        $suffix = 2;

        while (Product::query()->where('slug', $candidate)->exists()) {
            $suffixText = '-copy-'.$suffix;
            $candidate = Str::limit($base, 255 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueCopySku(string $source): string
    {
        $base = trim($source);
        if ($base === '') {
            $base = 'product';
        }

        $suffixText = '-copy';
        $candidate = rtrim(substr($base, 0, 120 - strlen($suffixText)), '-').$suffixText;
        $suffix = 2;

        while ($this->skuExists($candidate)) {
            $suffixText = '-copy-'.$suffix;
            $candidateBase = rtrim(substr($base, 0, 120 - strlen($suffixText)), '-');
            $candidate = ($candidateBase !== '' ? $candidateBase : 'product').$suffixText;
            $suffix++;
        }

        return $candidate;
    }

    private function skuExists(string $sku): bool
    {
        $skuKey = mb_strtolower(trim($sku));

        return Product::query()->whereRaw('LOWER(sku) = ?', [$skuKey])->exists()
            || ProductVariant::query()->whereRaw('LOWER(sku) = ?', [$skuKey])->exists();
    }

    private function productSkuIsTaken(string $sku, Product $product): bool
    {
        $skuKey = mb_strtolower(trim($sku));

        if ($skuKey === '') {
            return false;
        }

        $productSkuExists = Product::query()
            ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))
            ->whereRaw('LOWER(sku) = ?', [$skuKey])
            ->exists();
        if ($productSkuExists) {
            return true;
        }

        return ProductVariant::query()
            ->whereRaw('LOWER(sku) = ?', [$skuKey])
            ->exists();
    }

    private function backorderShippingEstimateType(?string $type, mixed $estimate, mixed $offsetDays): string
    {
        $normalizedType = strtolower(trim((string) $type));
        if (in_array($normalizedType, [
            Product::BACKORDER_SHIPPING_ESTIMATE_STATIC,
            Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC,
        ], true)) {
            return $normalizedType;
        }

        if ($offsetDays !== null && $offsetDays !== '') {
            return Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC;
        }

        if ($estimate !== null && $estimate !== '') {
            return Product::BACKORDER_SHIPPING_ESTIMATE_STATIC;
        }

        return Product::BACKORDER_SHIPPING_ESTIMATE_STATIC;
    }

    /** @param array<int, array{key: string, value: string}> $details
     * @return array<int, array{key: string, value: string}>
     */
    private function skuLastProductDetails(array $details): array
    {
        $rows = collect($details);
        [$skuRows, $otherRows] = $rows->partition(fn (array $detail): bool => mb_strtolower(trim($detail['key'])) === 'sku');

        return $otherRows->concat($skuRows)->values()->all();
    }

    /** @param array<int, array{key: string, value: string}> $details
     * @return array<int, array{key: string, value: string}>
     */
    private function ensureBaseSkuProductDetails(array $details): array
    {
        $rows = collect($details);
        [$skuRows, $otherRows] = $rows->partition(fn (array $detail): bool => mb_strtolower(trim($detail['key'])) === 'sku');
        $sku = $skuRows->first();

        return $otherRows->push([
            'key' => 'SKU',
            'value' => trim((string) data_get($sku, 'value', '')) ?: '{sku}',
        ])->values()->all();
    }

    private function normalizeVariants(array $rawVariants, Product $product): Collection
    {
        $variants = collect($rawVariants)
            ->map(function ($variant, int $index): array {
                $variant = is_array($variant) ? $variant : [];

                return [
                    'row_index' => $index,
                    'id' => isset($variant['id']) ? (int) $variant['id'] : null,
                    'name' => trim((string) ($variant['name'] ?? '')),
                    'description' => trim((string) ($variant['description'] ?? '')),
                    'product_details' => $this->skuLastProductDetails(collect($variant['product_details'] ?? [])
                        ->map(fn ($detail): array => [
                            'key' => trim((string) data_get($detail, 'key')),
                            'value' => trim((string) data_get($detail, 'value')),
                        ])
                        ->filter(fn (array $detail): bool => $detail['key'] !== '')
                        ->values()
                        ->all()),
                    'sku' => trim((string) ($variant['sku'] ?? '')),
                    'price' => ($variant['price'] ?? '') !== '' ? round((float) $variant['price'], 2) : null,
                    'compare_at_price' => ($variant['compare_at_price'] ?? '') !== '' ? round((float) $variant['compare_at_price'], 2) : null,
                    'inventory_units' => max(1, (int) ($variant['inventory_units'] ?? 1)),
                    'inventory_quantity' => ($variant['inventory_quantity'] ?? '') !== '' ? (int) $variant['inventory_quantity'] : null,
                    'unit_cost_ex_tax' => ($variant['unit_cost_ex_tax'] ?? '') !== '' && ($variant['unit_cost_ex_tax'] ?? null) !== null ? round((float) $variant['unit_cost_ex_tax'], 4) : null,
                    'bulk_discount_percent' => ($variant['bulk_discount_percent'] ?? '') !== '' && ($variant['bulk_discount_percent'] ?? null) !== null
                        ? round((float) $variant['bulk_discount_percent'], 2)
                        : null,
                    'automatic_pricing' => filter_var($variant['automatic_pricing'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    'weight_grams' => ($variant['weight_grams'] ?? '') !== '' ? (int) $variant['weight_grams'] : null,
                    'length_mm' => ($variant['length_mm'] ?? '') !== '' ? (int) $variant['length_mm'] : null,
                    'width_mm' => ($variant['width_mm'] ?? '') !== '' ? (int) $variant['width_mm'] : null,
                    'height_mm' => ($variant['height_mm'] ?? '') !== '' ? (int) $variant['height_mm'] : null,
                    'low_stock_threshold' => ($variant['low_stock_threshold'] ?? '') !== '' ? (int) $variant['low_stock_threshold'] : null,
                    'allow_backorder' => filter_var($variant['allow_backorder'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    'backorder_shipping_estimate_type' => $this->backorderShippingEstimateType(
                        isset($variant['backorder_shipping_estimate_type']) ? (string) $variant['backorder_shipping_estimate_type'] : null,
                        $variant['backorder_shipping_estimate'] ?? null,
                        $variant['backorder_shipping_offset_days'] ?? null,
                    ),
                    'backorder_shipping_estimate' => ($variant['backorder_shipping_estimate'] ?? '') !== '' ? (string) $variant['backorder_shipping_estimate'] : null,
                    'backorder_shipping_offset_days' => ($variant['backorder_shipping_offset_days'] ?? '') !== '' ? (int) $variant['backorder_shipping_offset_days'] : null,
                    'sort_order' => (int) ($variant['sort_order'] ?? $index),
                    'is_active' => filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    'is_base' => filter_var($variant['is_base'] ?? false, FILTER_VALIDATE_BOOL),
                    'original_option' => filter_var($variant['original_option'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            })
            ->filter(function (array $variant): bool {
                return $variant['name'] !== ''
                    || $variant['description'] !== ''
                    || $variant['product_details'] !== []
                    || $variant['sku'] !== ''
                    || $variant['price'] !== null
                    || $variant['compare_at_price'] !== null
                    || $variant['inventory_quantity'] !== null
                    || $variant['unit_cost_ex_tax'] !== null
                    || $variant['bulk_discount_percent'] !== null
                    || $variant['automatic_pricing']
                    || $variant['weight_grams'] !== null
                    || $variant['length_mm'] !== null
                    || $variant['width_mm'] !== null
                    || $variant['height_mm'] !== null
                    || $variant['low_stock_threshold'] !== null
                    || $variant['allow_backorder']
                    || $variant['backorder_shipping_estimate'] !== null
                    || $variant['backorder_shipping_offset_days'] !== null;
            })
            ->values();

        $errors = [];
        $seenSkus = [];
        $existingVariantIds = $product->exists
            ? $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        foreach ($variants as $variant) {
            $path = 'variants.'.$variant['row_index'];

            if ($variant['name'] === '') {
                $errors[$path.'.name'][] = 'Each added variant needs a name.';
            }

            if ($variant['id'] !== null && ! in_array($variant['id'], $existingVariantIds, true)) {
                $errors[$path.'.id'][] = 'Invalid variant selection.';
            }

            if ($variant['allow_backorder']) {
                if ($variant['backorder_shipping_estimate_type'] === Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC) {
                    if ($variant['backorder_shipping_offset_days'] === null) {
                        $errors[$path.'.backorder_shipping_offset_days'][] = 'A day offset is required for dynamic backorder-enabled variants.';
                    }
                } elseif ($variant['backorder_shipping_estimate'] === null) {
                    $errors[$path.'.backorder_shipping_estimate'][] = 'An estimated shipping date is required for backorder-enabled variants.';
                }
            }

            if ($variant['sku'] !== '') {
                $normalizedSku = strtoupper($variant['sku']);
                if (in_array($normalizedSku, $seenSkus, true)) {
                    $errors[$path.'.sku'][] = 'Variant SKUs must be unique within the product.';
                }
                $seenSkus[] = $normalizedSku;

                $isOriginal = $variant['id'] !== null && $variant['id'] === $product->legacy_base_variant_id
                    || ! $product->usesVariantRecords() && $variant['original_option'] && $variant['id'] === null;
                $productSkuExists = Product::query()
                    ->when($isOriginal && $product->exists, fn ($query) => $query->where('id', '!=', $product->id))
                    ->where('sku', $variant['sku'])
                    ->exists();
                if ($productSkuExists) {
                    $errors[$path.'.sku'][] = 'That variant SKU is already in use by a product.';
                }

                $skuExists = ProductVariant::query()
                    ->when($variant['id'] !== null, fn ($query) => $query->where('id', '!=', $variant['id']))
                    ->where('sku', $variant['sku'])
                    ->exists();
                if ($skuExists) {
                    $errors[$path.'.sku'][] = 'That variant SKU is already in use.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $variants;
    }

    private function syncVariants(Product $product, Collection $variants, bool $isDigital): array
    {
        $existingVariants = $product->variants()->get()->keyBy('id');
        $submittedIds = [];
        $savedVariants = [];

        foreach ($variants as $variantData) {
            $variant = $variantData['id'] !== null
                ? $existingVariants->get($variantData['id'])
                : new ProductVariant(['product_id' => $product->id]);

            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $variant->product_id = $product->id;
            $variant->name = $variantData['name'];
            $variant->description = $variantData['description'] !== '' ? $variantData['description'] : null;
            $variant->product_details = $variantData['product_details'] !== [] ? $variantData['product_details'] : null;
            $variant->sku = $variantData['sku'] !== '' ? $variantData['sku'] : null;
            if ($isDigital || ! $variantData['automatic_pricing'] || ! $variant->exists) {
                $variant->price = $variantData['price'];
            }
            $variant->compare_at_price = $variantData['compare_at_price'];
            $variant->shipping_rate = null;
            $variant->shipping_units = null;
            $variant->inventory_units = $variantData['inventory_units'];
            $variant->inventory_quantity = $isDigital ? null : $variantData['inventory_quantity'];
            $variant->stock_item_id = null;
            $variant->stock_quantity_per_sale = null;
            $variant->unit_cost_ex_tax = $isDigital ? null : $variantData['unit_cost_ex_tax'];
            $variant->bulk_discount_percent = $isDigital ? 0 : $variantData['bulk_discount_percent'];
            $variant->automatic_pricing = ! $isDigital && $variantData['automatic_pricing'];
            $variant->weight_grams = $isDigital ? null : $variantData['weight_grams'];
            $variant->is_preorder = false;
            $variant->preorder_shipping_estimate = null;
            $variant->allow_backorder = $isDigital ? false : (bool) $variantData['allow_backorder'];
            $variant->backorder_shipping_estimate_type = ! $isDigital && $variantData['allow_backorder']
                ? $variantData['backorder_shipping_estimate_type']
                : null;
            $variant->backorder_shipping_estimate = ! $isDigital && $variantData['allow_backorder'] && $variantData['backorder_shipping_estimate_type'] === Product::BACKORDER_SHIPPING_ESTIMATE_STATIC
                ? $variantData['backorder_shipping_estimate']
                : null;
            $variant->backorder_shipping_offset_days = ! $isDigital && $variantData['allow_backorder'] && $variantData['backorder_shipping_estimate_type'] === Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC
                ? $variantData['backorder_shipping_offset_days']
                : null;
            $variant->length_mm = $isDigital ? null : $variantData['length_mm'];
            $variant->width_mm = $isDigital ? null : $variantData['width_mm'];
            $variant->height_mm = $isDigital ? null : $variantData['height_mm'];
            $variant->low_stock_threshold = $isDigital ? null : $variantData['low_stock_threshold'];
            $variant->is_active = (bool) $variantData['is_active'];
            $variant->sort_order = (int) $variantData['sort_order'];
            $variant->save();

            if (! $variant->isLowStock() && $variant->low_stock_alert_sent_at !== null) {
                $variant->low_stock_alert_sent_at = null;
                $variant->save();
            }

            $submittedIds[] = (int) $variant->id;
            $savedVariants[$variantData['row_index']] = $variant;
        }

        foreach ($existingVariants as $existingVariant) {
            if (in_array((int) $existingVariant->id, $submittedIds, true)) {
                continue;
            }

            if ($existingVariant->deletionDisabledReason($product) !== null) {
                $existingVariant->is_active = false;
                $existingVariant->save();

                continue;
            }

            $existingVariant->delete();
        }

        return $savedVariants;
    }

    private function allocateRestockedInventory(
        Product $product,
        ?int $previousProductInventory,
        array $previousVariantInventory,
        StoreInventoryAllocatorService $allocator,
    ): void {
        if ($product->hasLinkedStock()) {
            return;
        }

        $currentProductInventory = $product->inventory_quantity !== null ? (int) $product->inventory_quantity : null;
        if ($currentProductInventory !== null && $currentProductInventory > ($previousProductInventory ?? 0)) {
            $allocator->allocateForProduct($product);
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        foreach ($variants as $variant) {
            if ($product->hasLinkedStock($variant)) {
                continue;
            }
            $currentInventory = $variant->inventory_quantity !== null ? (int) $variant->inventory_quantity : null;
            $previousInventory = array_key_exists((int) $variant->id, $previousVariantInventory)
                ? $previousVariantInventory[(int) $variant->id]
                : 0;

            if ($currentInventory !== null && $currentInventory > ($previousInventory ?? 0)) {
                $allocator->allocateForVariant($variant);
            }
        }
    }

    /**
     * @return array{
     *     base: array{awaiting:int,reserved:int},
     *     variants: array<int, array{awaiting:int,reserved:int}>
     * }
     */
    private function inventoryContexts(Product $product): array
    {
        $contexts = [
            'base' => [
                'awaiting' => 0,
                'reserved' => 0,
            ],
            'variants' => $product->variants
                ->mapWithKeys(fn (ProductVariant $variant) => [
                    (int) $variant->id => [
                        'awaiting' => 0,
                        'reserved' => 0,
                    ],
                ])
                ->all(),
        ];

        $items = StoreOrderItem::query()
            ->where('product_id', $product->id)
            ->with([
                'order:id,shipping_method_code',
                'trackingEntries' => fn ($query) => $query->select([
                    'id',
                    'store_order_item_id',
                    'shipment_type',
                    'quantity',
                ]),
                'collectionEntries:id,store_order_item_id,collection_type,pickup_state,quantity',
            ])
            ->get([
                'id',
                'store_order_id',
                'product_variant_id',
                'quantity',
                'available_now_quantity',
                'delayed_quantity',
                'inventory_reserved_quantity',
                'cancelled_available_quantity',
                'cancelled_delayed_quantity',
            ]);

        foreach ($items as $item) {
            if ($item->product_variant_id === null) {
                $contexts['base']['awaiting'] += $item->remainingOrderFulfillableQuantity();
                $contexts['base']['reserved'] += $item->reservedInventory();

                continue;
            }

            $variantId = (int) $item->product_variant_id;
            if (! isset($contexts['variants'][$variantId])) {
                $contexts['variants'][$variantId] = [
                    'awaiting' => 0,
                    'reserved' => 0,
                ];
            }

            $contexts['variants'][$variantId]['awaiting'] += $item->remainingOrderFulfillableQuantity();
            $contexts['variants'][$variantId]['reserved'] += $item->reservedInventory();
        }

        return $contexts;
    }

    private function normalizeIndexFilter(mixed $filter): string
    {
        $normalized = trim((string) $filter);

        return match ($normalized) {
            'actionable', 'low_stock' => 'low_stock',
            'archived' => 'archived',
            default => 'all',
        };
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function paginateProducts(Collection $products, Request $request): LengthAwarePaginator
    {
        $perPage = ListPageSize::resolve(20);
        $page = max(1, (int) ($request->query('page') ?? Paginator::resolveCurrentPage('page')));
        $items = $products->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator(
            $items,
            $products->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ))->onEachSide(1);
    }
}
