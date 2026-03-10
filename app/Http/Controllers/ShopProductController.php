<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
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

        $products = $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title')->paginate(20)->onEachSide(1);

        return view('admin.shop.product.index', [
            'products' => $products,
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.product.edit', [
            'existingCategories' => $this->existingCategories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = new Product();
        $this->saveProduct($request, $product);

        session()->flash('message', 'Product has been created.');
        session()->flash('message-title', 'Product created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.product.index');
    }

    public function edit(Product $product): View
    {
        return view('admin.shop.product.edit', [
            'product' => $product->load(['hero', 'galleryMedia', 'downloadMedia', 'variants']),
            'existingCategories' => $this->existingCategories(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->saveProduct($request, $product);

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

    private function saveProduct(Request $request, Product $product): void
    {
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
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
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
            'gallery_files' => ['nullable', 'string'],
            'download_files' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.inventory_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ]);

        $isDigital = (string) $validated['product_type'] === Product::PRODUCT_TYPE_DIGITAL;
        $normalizedVariants = $isDigital
            ? collect()
            : $this->normalizeVariants($validated['variants'] ?? [], $product);

        $product->fill([
            'title' => trim((string) $validated['title']),
            'slug' => trim((string) ($validated['slug'] ?? '')) ?: null,
            'category' => trim((string) ($validated['category'] ?? '')) ?: null,
            'sku' => trim((string) ($validated['sku'] ?? '')) ?: null,
            'status' => (string) $validated['status'],
            'product_type' => (string) $validated['product_type'],
            'short_description' => trim((string) ($validated['short_description'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'hero_media_name' => trim((string) ($validated['hero_media_name'] ?? '')) ?: null,
            'price' => round((float) $validated['price'], 2),
            'compare_at_price' => ($validated['compare_at_price'] ?? null) !== null ? round((float) $validated['compare_at_price'], 2) : null,
            'shipping_rate' => 0,
            'tax_rate' => 0.10,
            'inventory_quantity' => $validated['inventory_quantity'] ?? null,
            'shipping_units' => $isDigital ? 0 : round((float) ($validated['shipping_units'] ?? 0), 2),
            'min_satchel_rank' => $isDigital ? 1 : (int) ($validated['min_satchel_rank'] ?? $satchelRanks[0]),
            'weight_grams' => $isDigital ? null : ($validated['weight_grams'] ?? null),
            'box_only' => $isDigital ? false : $request->boolean('box_only'),
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);
        $product->save();
        $product->updateFiles($request->input('gallery_files'), 'gallery');
        $product->updateFiles($isDigital ? $request->input('download_files') : null, 'downloads');

        $this->syncVariants($product, $normalizedVariants, $isDigital);
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
                    'sku' => trim((string) ($variant['sku'] ?? '')),
                    'price' => ($variant['price'] ?? '') !== '' ? round((float) $variant['price'], 2) : null,
                    'compare_at_price' => ($variant['compare_at_price'] ?? '') !== '' ? round((float) $variant['compare_at_price'], 2) : null,
                    'inventory_quantity' => ($variant['inventory_quantity'] ?? '') !== '' ? (int) $variant['inventory_quantity'] : null,
                    'weight_grams' => ($variant['weight_grams'] ?? '') !== '' ? (int) $variant['weight_grams'] : null,
                    'sort_order' => (int) ($variant['sort_order'] ?? $index),
                    'is_active' => filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                ];
            })
            ->filter(function (array $variant): bool {
                return $variant['name'] !== ''
                    || $variant['sku'] !== ''
                    || $variant['price'] !== null
                    || $variant['compare_at_price'] !== null
                    || $variant['inventory_quantity'] !== null
                    || $variant['weight_grams'] !== null;
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
                $errors[$path.'.name'][] = 'Each variant needs a name.';
            }

            if ($variant['id'] !== null && ! in_array($variant['id'], $existingVariantIds, true)) {
                $errors[$path.'.id'][] = 'Invalid variant selection.';
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
            $variant->sku = $variantData['sku'] !== '' ? $variantData['sku'] : null;
            $variant->price = $variantData['price'];
            $variant->compare_at_price = $variantData['compare_at_price'];
            $variant->shipping_rate = null;
            $variant->inventory_quantity = $variantData['inventory_quantity'];
            $variant->weight_grams = $isDigital ? null : $variantData['weight_grams'];
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
}
