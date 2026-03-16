<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrderItem;
use App\Services\StoreInventoryAllocatorService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['hero', 'variants']);
        $selectedFilter = $this->normalizeIndexFilter($request->query('filter'));

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title');

        if ($selectedFilter === 'actionable') {
            $matchingProducts = $query->get();
            $inventorySummaries = $this->inventoryIndexSummaries($matchingProducts);
            $products = $this->paginateProducts(
                $matchingProducts
                    ->filter(fn (Product $product): bool => (bool) ($inventorySummaries[(int) $product->id]['actionable'] ?? false))
                    ->values(),
                $request
            );
        } else {
            $products = $query->paginate(20)->onEachSide(1);
            $inventorySummaries = $this->inventoryIndexSummaries($products->getCollection());
        }

        return view('admin.shop.product.index', [
            'products' => $products,
            'inventorySummaries' => $inventorySummaries,
            'selectedFilter' => $selectedFilter,
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.product.edit', [
            'existingCategories' => $this->existingCategories(),
        ]);
    }

    public function store(Request $request, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        $product = new Product();
        $this->saveProduct($request, $product, $allocator);

        session()->flash('message', 'Product has been created.');
        session()->flash('message-title', 'Product created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.index');
    }

    public function edit(Product $product): View
    {
        $product = $product->load(['hero', 'galleryMedia', 'downloadMedia', 'variants']);

        return view('admin.shop.product.edit', [
            'product' => $product,
            'existingCategories' => $this->existingCategories(),
            'inventoryContexts' => $this->inventoryContexts($product),
        ]);
    }

    public function update(Request $request, Product $product, StoreInventoryAllocatorService $allocator): RedirectResponse
    {
        $this->saveProduct($request, $product, $allocator);

        session()->flash('message', 'Product has been updated.');
        session()->flash('message-title', 'Product updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->storeOrderItems()->exists()) {
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

        return redirect()->route('admin.shop.product.index');
    }

    private function saveProduct(Request $request, Product $product, StoreInventoryAllocatorService $allocator): void
    {
        $previousProductInventory = $product->exists ? $product->inventory_quantity : null;
        $previousVariantInventory = $product->exists
            ? $product->variants()->pluck('inventory_quantity', 'id')->map(fn ($quantity) => $quantity !== null ? (int) $quantity : null)->all()
            : [];
        $satchelRanks = Product::satchelOptions()->pluck('rank')->map(fn ($rank) => (int) $rank)->values()->all();
        if ($satchelRanks === []) {
            $satchelRanks = [1];
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'category' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product->id)],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'product_type' => ['required', Rule::in(Product::PRODUCT_TYPES)],
            'is_preorder' => ['nullable', 'boolean'],
            'preorder_shipping_estimate' => ['nullable', 'date'],
            'allow_backorder' => ['nullable', 'boolean'],
            'backorder_shipping_estimate' => ['nullable', 'date'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'base_variant_name' => ['nullable', 'string', 'max:120'],
            'base_variant_description' => ['nullable', 'string', 'max:2000'],
            'private_notes' => ['nullable', 'string'],
            'hero_media_name' => ['nullable', 'exists:media,name'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'shipping_units' => ['nullable', 'numeric', 'min:0'],
            'min_satchel_rank' => ['nullable', 'integer', Rule::in($satchelRanks)],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
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
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.shipping_units' => ['nullable', 'numeric', 'min:0'],
            'variants.*.inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_preorder' => ['nullable', 'boolean'],
            'variants.*.preorder_shipping_estimate' => ['nullable', 'date'],
            'variants.*.allow_backorder' => ['nullable', 'boolean'],
            'variants.*.backorder_shipping_estimate' => ['nullable', 'date'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ]);

        $isDigital = (string) $validated['product_type'] === Product::PRODUCT_TYPE_DIGITAL;
        $isPreorder = ! $isDigital && $request->boolean('is_preorder');
        $allowsBackorder = ! $isDigital && $request->boolean('allow_backorder');

        if ($isPreorder && $allowsBackorder) {
            throw ValidationException::withMessages([
                'allow_backorder' => 'A product can be either pre-order or backorder-enabled, not both.',
            ]);
        }

        if ($isPreorder && ($validated['preorder_shipping_estimate'] ?? null) === null) {
            throw ValidationException::withMessages([
                'preorder_shipping_estimate' => 'An estimated shipping date is required for pre-order products.',
            ]);
        }

        if ($allowsBackorder && ($validated['backorder_shipping_estimate'] ?? null) === null) {
            throw ValidationException::withMessages([
                'backorder_shipping_estimate' => 'An estimated shipping date is required when backorders are allowed.',
            ]);
        }

        $normalizedVariants = $this->normalizeVariants($validated['variants'] ?? [], $product);
        $product->fill([
            'title' => trim((string) $validated['title']),
            'slug' => trim((string) ($validated['slug'] ?? '')) ?: null,
            'category' => trim((string) ($validated['category'] ?? '')) ?: null,
            'sku' => trim((string) ($validated['sku'] ?? '')) ?: null,
            'status' => (string) $validated['status'],
            'product_type' => (string) $validated['product_type'],
            'is_preorder' => $isPreorder,
            'preorder_shipping_estimate' => $isPreorder ? $validated['preorder_shipping_estimate'] : null,
            'allow_backorder' => $allowsBackorder,
            'backorder_shipping_estimate' => $allowsBackorder ? $validated['backorder_shipping_estimate'] : null,
            'short_description' => trim((string) ($validated['short_description'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'base_variant_name' => trim((string) ($validated['base_variant_name'] ?? '')) ?: null,
            'base_variant_description' => trim((string) ($validated['base_variant_description'] ?? '')) ?: null,
            'private_notes' => trim((string) ($validated['private_notes'] ?? '')) ?: null,
            'hero_media_name' => trim((string) ($validated['hero_media_name'] ?? '')) ?: null,
            'price' => round((float) $validated['price'], 2),
            'compare_at_price' => ($validated['compare_at_price'] ?? null) !== null ? round((float) $validated['compare_at_price'], 2) : null,
            'shipping_rate' => 0,
            'tax_rate' => 0.10,
            'inventory_quantity' => $isDigital ? null : ($validated['inventory_quantity'] ?? null),
            'shipping_units' => $isDigital ? 0 : round((float) ($validated['shipping_units'] ?? 0), 2),
            'min_satchel_rank' => $isDigital ? 1 : (int) ($validated['min_satchel_rank'] ?? $satchelRanks[0]),
            'weight_grams' => $isDigital ? null : ($validated['weight_grams'] ?? null),
            'box_only' => $isDigital ? false : $request->boolean('box_only'),
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'low_stock_threshold' => $isDigital ? null : ($validated['low_stock_threshold'] ?? 5),
        ]);
        $product->save();
        $product->updateFiles($request->input('gallery_files'), 'gallery');
        $product->updateFiles($isDigital ? $request->input('download_files') : null, 'downloads');

        $this->syncVariants($product, $normalizedVariants, $isDigital);
        $freshProduct = $product->fresh('variants');
        $this->allocateRestockedInventory($freshProduct, $previousProductInventory, $previousVariantInventory, $allocator);

        if ($freshProduct instanceof Product && ! $freshProduct->isLowStock()) {
            $freshProduct->low_stock_alert_sent_at = null;
            $freshProduct->save();
        }
    }

    private function existingCategories(): array
    {
        return Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
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
                    'sku' => trim((string) ($variant['sku'] ?? '')),
                    'price' => ($variant['price'] ?? '') !== '' ? round((float) $variant['price'], 2) : null,
                    'compare_at_price' => ($variant['compare_at_price'] ?? '') !== '' ? round((float) $variant['compare_at_price'], 2) : null,
                    'shipping_units' => ($variant['shipping_units'] ?? '') !== '' ? round((float) $variant['shipping_units'], 2) : null,
                    'inventory_quantity' => ($variant['inventory_quantity'] ?? '') !== '' ? (int) $variant['inventory_quantity'] : null,
                    'weight_grams' => ($variant['weight_grams'] ?? '') !== '' ? (int) $variant['weight_grams'] : null,
                    'is_preorder' => filter_var($variant['is_preorder'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    'preorder_shipping_estimate' => ($variant['preorder_shipping_estimate'] ?? '') !== '' ? (string) $variant['preorder_shipping_estimate'] : null,
                    'allow_backorder' => filter_var($variant['allow_backorder'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    'backorder_shipping_estimate' => ($variant['backorder_shipping_estimate'] ?? '') !== '' ? (string) $variant['backorder_shipping_estimate'] : null,
                    'sort_order' => (int) ($variant['sort_order'] ?? $index),
                    'is_active' => filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                ];
            })
            ->filter(function (array $variant): bool {
                return $variant['name'] !== ''
                    || $variant['description'] !== ''
                    || $variant['sku'] !== ''
                    || $variant['price'] !== null
                    || $variant['compare_at_price'] !== null
                    || $variant['shipping_units'] !== null
                    || $variant['inventory_quantity'] !== null
                    || $variant['weight_grams'] !== null
                    || $variant['is_preorder']
                    || $variant['preorder_shipping_estimate'] !== null
                    || $variant['allow_backorder']
                    || $variant['backorder_shipping_estimate'] !== null;
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

            if ($variant['is_preorder'] && $variant['allow_backorder']) {
                $errors[$path.'.allow_backorder'][] = 'A variant can be either pre-order or backorder-enabled, not both.';
            }

            if ($variant['is_preorder'] && $variant['preorder_shipping_estimate'] === null) {
                $errors[$path.'.preorder_shipping_estimate'][] = 'An estimated shipping date is required for pre-order variants.';
            }

            if ($variant['allow_backorder'] && $variant['backorder_shipping_estimate'] === null) {
                $errors[$path.'.backorder_shipping_estimate'][] = 'An estimated shipping date is required for backorder-enabled variants.';
            }

            if ($variant['sku'] !== '') {
                $normalizedSku = strtoupper($variant['sku']);
                if (in_array($normalizedSku, $seenSkus, true)) {
                    $errors[$path.'.sku'][] = 'Variant SKUs must be unique within the product.';
                }
                $seenSkus[] = $normalizedSku;

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

    private function syncVariants(Product $product, Collection $variants, bool $isDigital): void
    {
        $existingVariants = $product->variants()->get()->keyBy('id');
        $submittedIds = [];

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
            $variant->sku = $variantData['sku'] !== '' ? $variantData['sku'] : null;
            $variant->price = $variantData['price'];
            $variant->compare_at_price = $variantData['compare_at_price'];
            $variant->shipping_rate = null;
            $variant->shipping_units = $isDigital ? null : $variantData['shipping_units'];
            $variant->inventory_quantity = $isDigital ? null : $variantData['inventory_quantity'];
            $variant->weight_grams = $isDigital ? null : $variantData['weight_grams'];
            $variant->is_preorder = $isDigital ? false : (bool) $variantData['is_preorder'];
            $variant->preorder_shipping_estimate = ! $isDigital && $variantData['is_preorder']
                ? $variantData['preorder_shipping_estimate']
                : null;
            $variant->allow_backorder = $isDigital ? false : (bool) $variantData['allow_backorder'];
            $variant->backorder_shipping_estimate = ! $isDigital && $variantData['allow_backorder']
                ? $variantData['backorder_shipping_estimate']
                : null;
            $variant->length_cm = null;
            $variant->width_cm = null;
            $variant->height_cm = null;
            $variant->is_active = (bool) $variantData['is_active'];
            $variant->sort_order = (int) $variantData['sort_order'];
            $variant->save();

            $submittedIds[] = (int) $variant->id;
        }

        foreach ($existingVariants as $existingVariant) {
            if (in_array((int) $existingVariant->id, $submittedIds, true)) {
                continue;
            }

            if ($existingVariant->storeOrderItems()->exists()) {
                $existingVariant->is_active = false;
                $existingVariant->save();

                continue;
            }

            $existingVariant->delete();
        }
    }

    private function allocateRestockedInventory(
        Product $product,
        ?int $previousProductInventory,
        array $previousVariantInventory,
        StoreInventoryAllocatorService $allocator,
    ): void {
        $currentProductInventory = $product->inventory_quantity !== null ? (int) $product->inventory_quantity : null;
        if ($currentProductInventory !== null && $currentProductInventory > ($previousProductInventory ?? 0)) {
            $allocator->allocateForProduct($product);
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        foreach ($variants as $variant) {
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
                'trackingEntries' => fn ($query) => $query->select([
                    'id',
                    'store_order_item_id',
                    'shipment_type',
                    'quantity',
                ]),
            ])
            ->get([
                'id',
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
                $contexts['base']['awaiting'] += $item->remainingFulfillableQuantity();
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

            $contexts['variants'][$variantId]['awaiting'] += $item->remainingFulfillableQuantity();
            $contexts['variants'][$variantId]['reserved'] += $item->reservedInventory();
        }

        return $contexts;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array{available:int|null,awaiting:int,reserved:int,backorder:int,preorder:int,low_stock_threshold:int|null,low_stock:bool,actionable:bool}>
     */
    private function inventoryIndexSummaries(Collection $products): array
    {
        $summaries = $products
            ->mapWithKeys(function (Product $product): array {
                return [
                    (int) $product->id => [
                        'available' => $product->trackedInventoryTotal(),
                        'awaiting' => 0,
                        'reserved' => 0,
                        'backorder' => 0,
                        'preorder' => 0,
                        'low_stock_threshold' => $product->effectiveLowStockThreshold(),
                        'low_stock' => false,
                        'actionable' => false,
                    ],
                ];
            })
            ->all();

        if ($summaries === []) {
            return [];
        }

        $items = StoreOrderItem::query()
            ->whereIn('product_id', array_keys($summaries))
            ->with([
                'trackingEntries' => fn ($query) => $query->select([
                    'id',
                    'store_order_item_id',
                    'shipment_type',
                    'quantity',
                ]),
            ])
            ->get([
                'id',
                'product_id',
                'quantity',
                'available_now_quantity',
                'delayed_quantity',
                'delayed_fulfilment_type',
                'is_preorder',
                'inventory_reserved_quantity',
                'cancelled_available_quantity',
                'cancelled_delayed_quantity',
            ]);

        foreach ($items as $item) {
            $productId = (int) $item->product_id;

            if (! isset($summaries[$productId])) {
                continue;
            }

            $summaries[$productId]['awaiting'] += $item->remainingFulfillableQuantity();
            $summaries[$productId]['reserved'] += $item->reservedInventory();

            $remainingDelayedQuantity = $item->remainingDelayedQuantity();
            if ($remainingDelayedQuantity <= 0) {
                continue;
            }

            if ((bool) $item->is_preorder || (string) $item->delayed_fulfilment_type === 'preorder') {
                $summaries[$productId]['preorder'] += $remainingDelayedQuantity;

                continue;
            }

            $summaries[$productId]['backorder'] += $remainingDelayedQuantity;
        }

        foreach ($summaries as $productId => $summary) {
            $available = $summary['available'];
            $threshold = $summary['low_stock_threshold'];
            $summaries[$productId]['low_stock'] = $available !== null
                && $threshold !== null
                && $available <= $threshold;
            $summaries[$productId]['actionable'] = $summaries[$productId]['awaiting'] > 0
                || $summaries[$productId]['reserved'] > 0
                || $summaries[$productId]['backorder'] > 0
                || $summaries[$productId]['preorder'] > 0
                || $summaries[$productId]['low_stock'];
        }

        return $summaries;
    }

    private function normalizeIndexFilter(mixed $filter): string
    {
        $normalized = trim((string) $filter);

        return in_array($normalized, ['all', 'actionable'], true) ? $normalized : 'all';
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function paginateProducts(Collection $products, Request $request): LengthAwarePaginator
    {
        $perPage = 20;
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
