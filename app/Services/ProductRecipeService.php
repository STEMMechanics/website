<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCutSize;
use App\Models\ProductPurchase;
use App\Models\ProductRecipeComponent;
use App\Models\ProductVariant;
use App\Models\SiteOption;
use App\Services\Finance\ProductCostAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductRecipeService
{
    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    public function reset(): void
    {
        $this->products = null;
    }

    /** Load the recipe graph once, including purchase costs, for recursive costing. */
    private function products(): Collection
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $this->products = Product::query()->with([
            'cutSizes', 'variants', 'purchases.expense',
            'recipeComponents.cutSize', 'recipeComponents.variant',
        ])->get()->keyBy('id');
        foreach ($this->products as $product) {
            foreach ($product->recipeComponents as $row) {
                $row->setRelation('component', $this->products->get((int) $row->component_product_id));
            }
        }

        return $this->products;
    }

    public function defaultMarkup(): float
    {
        return max(0, (float) (SiteOption::value('store.costing.markup-percent') ?? config('product-costing.markup_percent')));
    }

    public function defaultBulkDiscount(): float
    {
        return (float) SiteOption::value('store.costing.bulk-discount-percent', (string) config('product-costing.bulk_discount_percent', 20));
    }

    public function bulkDiscount(Product $product, ?ProductVariant $variant = null): float
    {
        return (float) ($variant->bulk_discount_percent ?? $product->bulk_discount_percent ?? $this->defaultBulkDiscount());
    }

    public function operationalCost(Product $product): float
    {
        return (float) ($product->operational_cost_ex_tax ?? SiteOption::value('store.costing.'.($product->isKit() ? 'kit' : 'item').'-operational', (string) config('product-costing.'.($product->isKit() ? 'kit' : 'item').'_operational_cost')));
    }

    public function timeCost(Product $product): float
    {
        $itemTime = (float) SiteOption::value('store.costing.item-time', (string) config('product-costing.item_time_cost'));

        return (float) ($product->time_cost_ex_tax ?? $itemTime * ($product->isKit() ? 2 : 1));
    }

    public function materialCost(Product $product, ?ProductVariant $variant = null, array $seen = []): ?float
    {
        $product = $this->products()->get((int) $product->id) ?? $product;
        if (isset($seen[$product->id])) {
            return null;
        }
        $seen[$product->id] = true;
        if ($product->recipeComponents->isNotEmpty()) {
            $cost = 0.0;
            foreach ($product->recipeComponents as $row) {
                $component = $this->products()->get((int) $row->component_product_id);
                $units = $row->purchasedUnits();
                $unitCost = $component ? $this->materialCost($component, $row->variant, $seen) : null;
                if ($units === null || $unitCost === null) {
                    return null;
                }
                $cost += $units * $unitCost;
            }
            $ratio = $variant && $product->isPhysical()
                ? $product->saleUnits($variant) / $product->saleUnits()
                : 1;

            $cost = round($cost * $ratio, 4);

            return is_finite($cost) ? $cost : null;
        }

        $purchaseCost = $this->purchaseCost($product, $variant);
        if ($purchaseCost !== null) {
            return $purchaseCost;
        }
        if ($variant) {
            if ($variant->unit_cost_ex_tax !== null) {
                return (float) $variant->unit_cost_ex_tax;
            }
            $base = $this->materialCost($product, null, array_diff_key($seen, [$product->id => true]));
            $ratio = $product->isPhysical() ? $product->saleUnits($variant) / $product->saleUnits() : 1;

            return $base === null ? null : round($base * $ratio, 4);
        }

        return $product->unit_cost_ex_tax === null ? null : (float) $product->unit_cost_ex_tax;
    }

    /** Convert the latest purchase cost to the units in the chosen retail pack. */
    public function purchaseCost(Product $product, ?ProductVariant $variant = null): ?float
    {
        $purchases = $this->scopedPurchases($product, $variant);
        $unitCost = $this->latestPurchaseUnitCost($product, $purchases);
        $saleUnits = $product->isPhysical() ? $product->saleUnits($variant) : 1;

        return $unitCost === null ? null : round($unitCost * $saleUnits, 4);
    }

    private function scopedPurchases(Product $product, ?ProductVariant $variant = null): Collection
    {
        $product->loadMissing(['purchases.expense', 'variants']);

        return $product->purchases->filter(fn (ProductPurchase $row): bool => ($product->isPhysical() && ($product->shared_inventory || $row->individual_units || $row->purchase_pack))
            || ($variant === null || $variant->exists) && (int) ($row->product_variant_id ?? 0) === (int) ($variant->id ?? 0));
    }

    public function purchaseSource(Product $product, ?ProductVariant $variant = null): ?ProductPurchase
    {
        return $this->latestPurchase($this->scopedPurchases($product, $variant));
    }

    /** Keep component classifications while scaling the same material costs used in pricing. */
    public function materialCostsByCentre(Product $product, ?ProductVariant $variant = null, array $seen = []): ?array
    {
        $product = $this->products()->get((int) $product->id) ?? $product;
        if (isset($seen[$product->id])) {
            return null;
        }
        $cost = $this->materialCost($product, $variant);
        if ($cost === null) {
            return null;
        }
        $centre = $product->purchase_cost_centre ?? 'auto';
        if ($centre !== 'auto') {
            return [$centre => $cost];
        }
        if ($product->recipeComponents->isEmpty()) {
            if ($variant && $variant->unit_cost_ex_tax === null && $this->purchaseCost($product, $variant) === null) {
                $base = $this->materialCostsByCentre($product, null, $seen);
                $total = $base === null ? 0 : array_sum($base);

                return $base === null ? null : array_map(fn ($amount): float => $total > 0 ? $amount * $cost / $total : 0.0, $base);
            }

            return [app(ProductCostAllocation::class)->purchaseCentre($product, $variant) => $cost];
        }
        $seen[$product->id] = true;
        $costs = ['stock' => 0.0, 'consumables' => 0.0];
        $ratio = $variant && $product->isPhysical() ? $product->saleUnits($variant) / $product->saleUnits() : 1;
        foreach ($product->recipeComponents as $row) {
            $component = $this->products()->get((int) $row->component_product_id);
            $units = $row->purchasedUnits();
            $parts = $component ? $this->materialCostsByCentre($component, $row->variant, $seen) : null;
            if ($parts === null || $units === null) {
                return null;
            }
            foreach ($parts as $name => $amount) {
                $costs[$name] += $units * $amount * $ratio;
            }
        }

        $total = array_sum($costs);

        return array_map(fn ($amount): float => $total > 0 ? $amount * $cost / $total : 0.0, $costs);
    }

    public function purchaseCostCentres(Product $product): array
    {
        $service = app(ProductCostAllocation::class);
        $separate = (clone $product)->forceFill(['shared_inventory' => false]);

        return [
            'base' => $service->purchaseCentre($separate),
            'shared' => $service->purchaseCentre((clone $product)->forceFill(['shared_inventory' => true])),
            'variants' => $product->variants->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $service->purchaseCentre($separate, $variant)])->all(),
            'newVariant' => $service->purchaseCentre($separate, new ProductVariant),
        ];
    }

    /** Present the existing base pack fallback as a supplier pack cost without changing its stored meaning. */
    public function fallbackPurchasePackCost(Product $product): ?float
    {
        if ($product->unit_cost_ex_tax === null) {
            return null;
        }

        return (float) $product->unit_cost_ex_tax * max(1, (int) $product->purchase_pack_units) / $product->saleUnits();
    }

    /** Unrounded costs per individual unit, so the editor can preview pack and stock changes. */
    public function purchaseCostInputs(Product $product): array
    {
        $product->loadMissing(['purchases.expense', 'variants']);
        $scoped = fn (?int $variantId): Collection => $product->purchases->filter(fn (ProductPurchase $row): bool => ($product->isPhysical() && ($row->individual_units || $row->purchase_pack))
            || (int) ($row->product_variant_id ?? 0) === (int) ($variantId ?? 0));

        return [
            'shared' => $product->isPhysical() ? $this->latestPurchaseUnitCost($product, $product->purchases) : null,
            'base' => $this->latestPurchaseUnitCost($product, $scoped(null)),
            'variants' => $product->variants->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => $this->latestPurchaseUnitCost($product, $scoped((int) $variant->id)),
            ])->all(),
            'newVariant' => $this->latestPurchaseUnitCost($product, $product->purchases->filter(fn (ProductPurchase $row): bool => $product->isPhysical() && ($row->individual_units || $row->purchase_pack))),
        ];
    }

    private function latestPurchaseUnitCost(Product $product, Collection $purchases): ?float
    {
        $latest = $this->latestPurchase($purchases);
        if (! $latest) {
            return null;
        }
        $lines = $purchases->where('expense_id', $latest->expense_id);
        $quantity = (float) $lines->sum(function (ProductPurchase $row) use ($product): float {
            $units = 1;
            if ($product->isPhysical() && ! $row->individual_units) {
                $purchasedVariant = $row->product_variant_id ? $product->variants->find($row->product_variant_id) : null;
                $units = $row->units_per_purchase ?? $product->saleUnits($purchasedVariant);
            }

            return (float) $row->quantity * max(1, $units);
        });
        $cost = $quantity > 0 ? (float) $lines->sum('total_cost_ex_tax') / $quantity : null;

        return $cost !== null && is_finite($cost) ? $cost : null;
    }

    private function latestPurchase(Collection $purchases): ?ProductPurchase
    {
        return $purchases->sortByDesc(fn (ProductPurchase $row): string => ($row->expense?->paid_on?->format('Y-m-d') ?? $row->created_at?->format('Y-m-d') ?? '').str_pad((string) $row->id, 20, '0', STR_PAD_LEFT))->first();
    }

    public function suggestedPrice(Product $product, ?ProductVariant $variant = null): ?float
    {
        return $this->pricingDetails($product, $variant)['price'];
    }

    /** @return array{price: ?float, minimum_price: ?float, bulk_price: ?float, minimum_applied: bool} */
    public function pricingDetails(Product $product, ?ProductVariant $variant = null): array
    {
        $pricing = ['price' => null, 'minimum_price' => null, 'bulk_price' => null, 'minimum_applied' => false];
        if (! $product->isPhysical()) {
            return $pricing;
        }
        $materials = $this->materialCost($product, $variant);
        if ($materials === null) {
            return $pricing;
        }
        $handling = $this->operationalCost($product) + $this->timeCost($product);
        $priceFactor = (1 + (float) ($product->markup_percent ?? $this->defaultMarkup()) / 100) * (1 + (float) $product->tax_rate);
        $incTax = ($materials + $handling) * $priceFactor;
        $minimum = $this->roundSellingPrice($incTax);
        $pricing['minimum_price'] = $minimum;
        if ($minimum === null) {
            return $pricing;
        }
        $minimumPack = $product->usesVariantRecords()
            ? $product->purchasableVariants()->sortBy(fn (ProductVariant $option): array => [$product->saleUnits($option), (int) $option->id])->first()
            : null;
        $minimumUnits = $minimumPack ? $product->saleUnits($minimumPack) : $product->saleUnits();
        if (! $variant || $product->saleUnits($variant) <= $minimumUnits || $product->usesVariantRecords() && ! $minimumPack) {
            $pricing['price'] = $minimum;

            return $pricing;
        }

        if ($minimumPack) {
            // An underpriced manual pack must not lower the bulk reference.
            // Automatic references remain independent of update order and old prices.
            $basePrice = $this->suggestedPrice($product, $minimumPack);
            if (! $minimumPack->automatic_pricing) {
                $basePrice = max($basePrice ?? 0, $minimumPack->effectivePrice());
            }
            $discount = $this->bulkDiscount($product, $variant);
            if ($basePrice === null || ! is_finite($basePrice) || $basePrice < 0 || ! is_finite($discount) || $discount < 0 || $discount > 100) {
                return $pricing;
            }
            $referencePrice = $basePrice * $product->saleUnits($variant) / $minimumUnits;
            $bulkPrice = $this->roundSellingPrice($referencePrice * (1 - $discount / 100));
            // A price ending must not make a bulk pack dearer per item than its reference.
            $ceiling = $this->roundSellingPrice($referencePrice, roundUp: false);
            $bulkPrice = $bulkPrice === null || $ceiling === null ? null : min($bulkPrice, $ceiling);
        } else {
            // Scale only the item portion. Handling belongs to the finished pack once,
            // and rounding a small pack must not be multiplied into larger packs.
            $baseMaterials = $this->materialCost($product);
            if ($product->automatic_pricing && $baseMaterials === null) {
                $pricing['price'] = $minimum;

                return $pricing;
            }
            $handlingPrice = $handling * $priceFactor;
            $itemPrice = $product->automatic_pricing
                ? $baseMaterials * $priceFactor
                : max(0, (float) $product->price - $handlingPrice);
            $discount = $this->bulkDiscount($product, $variant);
            if (! is_finite($itemPrice) || $itemPrice < 0 || ! is_finite($discount) || $discount < 0 || $discount > 100) {
                return $pricing;
            }
            $bulkPrice = $this->roundSellingPrice($handlingPrice + $itemPrice * $product->saleUnits($variant) / $product->saleUnits() * (1 - $discount / 100));
        }
        if ($bulkPrice === null) {
            return $pricing;
        }
        $pricing['bulk_price'] = $bulkPrice;
        $pricing['price'] = max($minimum, $bulkPrice);
        $pricing['minimum_applied'] = $minimum > $bulkPrice + 0.001;

        return $pricing;
    }

    private function roundSellingPrice(float $amount, bool $roundUp = true): ?float
    {
        if (! is_finite($amount) || $amount < 0) {
            return null;
        }
        $ending = (float) config('product-costing.price_ending', 0.95);

        $rounded = $roundUp ? ceil($amount - $ending - 0.0000001) : floor($amount - $ending + 0.0000001);
        $price = round(max($ending, $rounded + $ending), 2);

        return is_finite($price) && $price <= 99999999.99 ? $price : null;
    }

    /** Automatic pricing is opt-in. Missing costs preserve the last selling price. */
    public function refreshPrices(): void
    {
        $this->reset();
        foreach ($this->products() as $product) {
            if (! $product->isPhysical()) {
                continue;
            }
            $price = $product->automatic_pricing ? $this->suggestedPrice($product) : null;
            if ($price !== null) {
                if (abs((float) $product->price - $price) > 0.001) {
                    $product->update(['price' => $price]);
                }
            }
            foreach ($product->variants as $variant) {
                if ($variant->automatic_pricing) {
                    $variantPrice = $this->suggestedPrice($product, $variant);
                    if ($variantPrice === null) {
                        continue;
                    }
                    if ($variant->price === null || abs((float) $variant->price - $variantPrice) > 0.001) {
                        $variant->update(['price' => $variantPrice]);
                    }
                }
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function catalog(?Product $excluding = null): array
    {
        return $this->products()->filter(fn (Product $product): bool => ! $product->isDigital()
            && $product->status === Product::STATUS_ACTIVE
            && (int) $product->id !== (int) $excluding?->id)
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->title,
                'sku' => (string) $product->sku,
                'base_option_name' => $product->usesVariantRecords() ? 'Reference pack' : $product->baseOptionName(),
                'status' => (string) $product->status,
                'is_kit' => $product->isKit(),
                'product_type' => (string) $product->product_type,
                'has_material_measurements' => $product->hasMaterialMeasurements(),
                'material_kind' => (string) $product->material_kind,
                'purchase_unit' => $product->purchaseUnitLabel(),
                'purchase_quantity' => (float) ($product->purchase_quantity ?? 1),
                'purchase_pack_units' => max(1, (int) $product->purchase_pack_units),
                'measure_unit' => (string) ($product->material_measure_unit ?? 'mm'),
                'pack_units' => $product->saleUnits(),
                'unit_cost' => $this->materialCost($product),
                'cost_centres' => $this->materialCostsByCentre($product),
                'url' => route('admin.shop.product.edit', $product),
                'cut_sizes' => $product->cutSizes->map(fn (ProductCutSize $cut): array => [
                    'id' => (int) $cut->id, 'label' => $cut->label(), 'yield' => (int) $cut->yield,
                ])->all(),
                'variants' => $product->variants->where('is_active', true)->map(fn (ProductVariant $variant): array => [
                    'id' => (int) $variant->id, 'name' => (string) $variant->displayName(), 'unit_cost' => $this->materialCost($product, $variant),
                    'pack_units' => $product->saleUnits($variant),
                    'cost_centres' => $this->materialCostsByCentre($product, $variant),
                ])->values()->all(),
                'inactive_variants' => $product->variants->where('is_active', false)->map(fn (ProductVariant $variant): array => [
                    'id' => (int) $variant->id, 'name' => (string) $variant->displayName(),
                ])->values()->all(),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    public function validate(Request $request, Product $product): array
    {
        $isMaterial = $request->input('product_type') === Product::PRODUCT_TYPE_MATERIAL;
        $isStoreItem = $request->input('product_type') === Product::PRODUCT_TYPE_PHYSICAL;
        $useAsMaterial = $request->exists('use_as_material') ? $request->boolean('use_as_material')
            : ($product->hasMaterialMeasurements() || in_array($request->input('material_kind'), ['length', 'sheet'], true));
        $hasMeasurements = $isMaterial || ($isStoreItem && $useAsMaterial);
        $kind = $request->input('material_kind');
        $data = $request->validate([
            'use_as_material' => ['nullable', 'boolean'],
            'material_kind' => [Rule::requiredIf($hasMeasurements), 'nullable', Rule::in(['unit', 'length', 'sheet'])],
            'purchase_unit' => [Rule::requiredIf($hasMeasurements), 'nullable', 'string', 'max:40'],
            'purchase_quantity' => [Rule::requiredIf($hasMeasurements && ($kind === 'length' || ($isMaterial && $kind === 'unit'))), 'nullable', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,4'],
            'purchase_pack_units' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'material_measure_unit' => [Rule::requiredIf($hasMeasurements && $kind === 'length'), 'nullable', Rule::in(array_keys(ProductRecipeComponent::LENGTH_UNITS))],
            'material_length_mm' => [Rule::requiredIf($hasMeasurements && $kind === 'sheet'), 'nullable', 'numeric', 'gt:0', 'max:10000000'],
            'material_width_mm' => [Rule::requiredIf($hasMeasurements && $kind === 'sheet'), 'nullable', 'numeric', 'gt:0', 'max:10000000'],
            'unit_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'purchase_pack_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:10000000000000'],
            'operational_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'time_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'automatic_pricing' => ['nullable', 'boolean'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'bulk_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'automatic_allocation' => ['nullable', 'boolean'],
            'purchase_cost_centre' => ['nullable', Rule::in(['auto', 'stock', 'consumables'])],
            'allocation_remainder_json' => ['sometimes', 'required', 'string', 'max:10000'],
            'cut_sizes' => ['nullable', 'array', 'max:100'],
            'cut_sizes.*.id' => ['nullable', 'integer'],
            'cut_sizes.*.length_mm' => ['required', 'numeric', 'gt:0'],
            'cut_sizes.*.width_mm' => ['required', 'numeric', 'gt:0'],
            'cut_sizes.*.yield' => ['required', 'integer', 'min:1', 'max:1000000'],
            'recipe' => ['nullable', 'array', 'max:100'],
            'recipe.*.component_product_id' => ['required', 'integer', 'exists:products,id'],
            'recipe.*.component_variant_id' => ['nullable', 'integer'],
            'recipe.*.cut_size_id' => ['nullable', 'integer'],
            'recipe.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
            'recipe.*.quantity_unit' => ['nullable', Rule::in(ProductRecipeComponent::QUANTITY_UNITS)],
            'recipe.*.length' => ['nullable', 'numeric', 'gt:0', 'max:10000000', 'decimal:0,4'],
            'recipe.*.length_unit' => ['nullable', Rule::in(array_keys(ProductRecipeComponent::LENGTH_UNITS))],
            'recipe.*.note' => ['nullable', 'string', 'max:500'],
            'variants.*.unit_cost_ex_tax' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);
        $data['material_kind'] = $hasMeasurements ? $data['material_kind'] : null;
        if ($request->exists('allocation_remainder_json')) {
            $data['allocation_remainder'] = app(ProductCostAllocation::class)->parseRemainder($data['allocation_remainder_json'], 'allocation_remainder_json', inherit: true);
        }
        $data['purchase_unit'] = $hasMeasurements ? trim($data['purchase_unit']) : null;
        $data['purchase_quantity'] = $hasMeasurements ? ($kind === 'sheet' || ($isStoreItem && $kind === 'unit') ? 1 : (float) $data['purchase_quantity']) : null;
        $data['purchase_pack_units'] = $isStoreItem && $hasMeasurements ? (int) ($data['purchase_pack_units'] ?? $product->purchase_pack_units ?? 1) : 1;
        if ($isStoreItem && $hasMeasurements && $request->exists('purchase_pack_cost_ex_tax')) {
            $purchasePackCost = $data['purchase_pack_cost_ex_tax'] ?? null;
            $basePackUnits = max(1, $request->integer('inventory_units', 1));
            $baseCost = $purchasePackCost === null ? null : (float) $purchasePackCost * $basePackUnits / $data['purchase_pack_units'];
            if ($baseCost !== null && (! is_finite($baseCost) || $baseCost > 10000000)) {
                throw ValidationException::withMessages(['purchase_pack_cost_ex_tax' => 'The calculated base pack cost must not exceed 10,000,000. Check the supplier and retail pack sizes.']);
            }
            $data['unit_cost_ex_tax'] = $baseCost === null ? null : round($baseCost, 4);
        }
        unset($data['purchase_pack_cost_ex_tax']);
        $data['material_measure_unit'] = $hasMeasurements && $kind === 'length' ? $data['material_measure_unit'] : null;
        $data['material_length_mm'] = $hasMeasurements && $kind === 'sheet' ? $data['material_length_mm'] : null;
        $data['material_width_mm'] = $hasMeasurements && $kind === 'sheet' ? $data['material_width_mm'] : null;
        if ($product->exists && $product->product_type !== $request->input('product_type')
            && ! ($product->isPhysical() && in_array($request->input('product_type'), Product::PHYSICAL_TYPES, true))
            && ($product->usedInRecipes()->exists() || $product->purchases()->exists() || $product->storeOrderItems()->exists() || $product->hasPickListReferences())) {
            throw ValidationException::withMessages(['product_type' => 'This item is used in recipes, pick lists, purchases or orders. Create a separate item for the new type.']);
        }
        if ($product->exists && $product->hasMaterialMeasurements() && ! $hasMeasurements
            && ($product->usedInRecipes()->exists() || $product->hasPickListReferences() || $product->purchases()->where('purchase_pack', true)->exists())) {
            throw ValidationException::withMessages([$isStoreItem ? 'use_as_material' : 'product_type' => 'This material is linked to recipes, pick lists or supplier pack purchases. Keep Use as a material enabled on a Store Item while those links exist.']);
        }
        if ($product->exists && $product->hasMaterialMeasurements() && ($product->usedInRecipes()->exists() || $product->hasPickListReferences())
            && ($product->material_kind !== $data['material_kind']
                || $product->purchase_unit !== $data['purchase_unit']
                || (float) $product->purchase_quantity !== (float) ($data['purchase_quantity'] ?? 1)
                || $product->material_measure_unit !== ($data['material_measure_unit'] ?? null))) {
            throw ValidationException::withMessages(['purchase_unit' => 'These measurements are used in recipes or pick lists. Keep them unchanged and create a separate item for a different unit or roll length.']);
        }
        if ($product->exists && $product->shared_inventory !== $request->boolean('shared_inventory')
            && $product->purchases()->where('inventory_added', '>', 0)->exists()) {
            throw ValidationException::withMessages(['shared_inventory' => 'This item has recorded stock purchases. Keep its shared inventory setting unchanged so those purchases remain linked to the right stock count.']);
        }
        $cuts = $hasMeasurements && $kind === 'sheet' ? ($data['cut_sizes'] ?? []) : [];
        foreach ($cuts as $index => $cut) {
            if (! empty($cut['id']) && ! $product->cutSizes()->whereKey($cut['id'])->exists()) {
                throw ValidationException::withMessages(['cut_sizes' => 'A cut size does not belong to this material.']);
            }
            $fits = ($cut['length_mm'] <= $data['material_length_mm'] && $cut['width_mm'] <= $data['material_width_mm'])
                || ($cut['width_mm'] <= $data['material_length_mm'] && $cut['length_mm'] <= $data['material_width_mm']);
            $area = (float) $data['material_length_mm'] * (float) $data['material_width_mm'];
            if (! $fits || (float) $cut['length_mm'] * (float) $cut['width_mm'] * (int) $cut['yield'] > $area + 0.001) {
                throw ValidationException::withMessages(['cut_sizes.'.$index.'.yield' => 'The cut size and usable yield must fit within the purchased sheet.']);
            }
        }
        $recipe = in_array($request->input('product_type'), Product::PHYSICAL_TYPES, true) ? ($data['recipe'] ?? []) : [];
        foreach ($recipe as $index => $row) {
            $this->validateComponent($row, 'recipe.'.$index);
            if ((int) $row['component_product_id'] === (int) $product->id
                || $this->reaches((int) $row['component_product_id'], (int) $product->id)) {
                throw ValidationException::withMessages(['recipe.'.$index.'.component_product_id' => 'A recipe cannot include itself, including through another kit.']);
            }
        }
        $data['recipe'] = $recipe;
        $data['cut_sizes'] = $cuts;

        return $data;
    }

    public function validateComponent(array $row, string $key, bool $allowInactiveVariant = false): void
    {
        $component = $this->products()->get((int) $row['component_product_id']);
        if (! $component || $component->isDigital() || $component->status !== Product::STATUS_ACTIVE) {
            throw ValidationException::withMessages([$key.'.component_product_id' => 'Choose an active Store Item or Material.']);
        }
        $variants = $allowInactiveVariant ? $component->variants : $component->variants->where('is_active', true);
        if (! empty($row['component_variant_id']) && ! $variants->contains('id', (int) $row['component_variant_id'])) {
            throw ValidationException::withMessages([$key.'.component_variant_id' => 'Choose a variant belonging to this item.']);
        }
        if (! empty($row['cut_size_id']) && ($component->material_kind !== 'sheet' || ! $component->cutSizes->contains('id', (int) $row['cut_size_id']))) {
            throw ValidationException::withMessages([$key.'.cut_size_id' => 'Choose a cut size belonging to this material.']);
        }
        $hasLength = ($row['length'] ?? null) !== null && ($row['length'] ?? '') !== '';
        $preparation = new ProductRecipeComponent($row);
        $preparation->setRelation('component', $component);
        $quantityUnit = $preparation->resolvedQuantityUnit();
        if (($row['quantity_unit'] ?? null) === 'item' && ! $component->hasMaterialMeasurements()) {
            throw ValidationException::withMessages([$key.'.quantity_unit' => 'Enable Use as a material on this Store Item before selecting individual units.']);
        }
        if ($component->isMaterial() && ($row['quantity_unit'] ?? null) === 'pack') {
            throw ValidationException::withMessages([$key.'.quantity_unit' => 'Materials use individual pieces, lengths or cut sizes.']);
        }
        if ($quantityUnit === 'pack' && ($hasLength || ! empty($row['cut_size_id']))) {
            throw ValidationException::withMessages([$key.'.quantity_unit' => 'Choose individual units to use a length or cut size.']);
        }
        if ($component->material_kind === 'length' && ($component->isMaterial() || $hasLength)
            && ((float) ($row['length'] ?? 0) <= 0 || ! isset(ProductRecipeComponent::LENGTH_UNITS[$row['length_unit'] ?? '']))) {
            throw ValidationException::withMessages([$key.'.length' => 'Enter the length of each piece and its unit.']);
        }
        if ($component->material_kind === 'length' && $hasLength) {
            $cut = (float) $row['length'] * ProductRecipeComponent::LENGTH_UNITS[$row['length_unit']];
            $purchased = (float) $component->purchase_quantity * (ProductRecipeComponent::LENGTH_UNITS[$component->material_measure_unit] ?? 0);
            if ($cut > $purchased) {
                throw ValidationException::withMessages([$key.'.length' => 'A single piece cannot be longer than the purchased material.']);
            }
        }
        if ($component->hasMaterialMeasurements() && $quantityUnit === 'item'
            && ($component->isPhysical() || in_array($component->material_kind, ['sheet', 'length'], true))
            && abs((float) $row['quantity'] - round((float) $row['quantity'])) > 0.00001) {
            throw ValidationException::withMessages([$key.'.quantity' => 'Enter a whole number of units.']);
        }
        if ($component->material_kind !== 'length' && ! empty($row['length'])) {
            throw ValidationException::withMessages([$key.'.length' => 'Lengths are only used with a length material.']);
        }
    }

    private function reaches(int $from, int $target, array $seen = []): bool
    {
        if ($target === 0 || isset($seen[$from])) {
            return false;
        }
        if ($from === $target) {
            return true;
        }
        $seen[$from] = true;
        foreach ($this->products()->get($from)->recipeComponents ?? [] as $row) {
            if ($this->reaches((int) $row->component_product_id, $target, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, ProductVariant>  $savedVariants
     * @param  array{base?: bool, variants?: list<int>}  $previousAutomaticPricing
     */
    public function save(Product $product, array $data, array $savedVariants = [], array $previousAutomaticPricing = []): void
    {
        $cuts = $data['cut_sizes'];
        $keptIds = [];
        foreach ($cuts as $index => $cut) {
            $saved = ! empty($cut['id']) ? $product->cutSizes()->findOrFail($cut['id']) : new ProductCutSize(['product_id' => $product->id]);
            $saved->fill(['length_mm' => $cut['length_mm'], 'width_mm' => $cut['width_mm'], 'yield' => $cut['yield'], 'sort_order' => $index]);
            $saved->save();
            $keptIds[] = $saved->id;
        }
        foreach ($product->cutSizes()->whereNotIn('id', $keptIds)->get() as $removed) {
            if (ProductRecipeComponent::query()->where('cut_size_id', $removed->id)->exists()
                || $product->hasPickListReferences((int) $removed->id)) {
                throw ValidationException::withMessages(['cut_sizes' => 'A cut size is used in a recipe, blueprint or workshop. Remove those links before deleting it.']);
            }
            $removed->delete();
        }
        $product->recipeComponents()->delete();
        foreach ($data['recipe'] as $index => $row) {
            $product->recipeComponents()->create([
                'component_product_id' => $row['component_product_id'],
                'component_variant_id' => ($row['component_variant_id'] ?? null) ?: null,
                'cut_size_id' => ($row['cut_size_id'] ?? null) ?: null,
                'quantity' => $row['quantity'],
                'quantity_unit' => $row['quantity_unit'] ?? null,
                'length' => ($row['length'] ?? null) ?: null,
                'length_unit' => ! empty($row['length']) ? ($row['length_unit'] ?? null) : null,
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);
        }
        $this->reset();
        if ($product->automatic_pricing && empty($previousAutomaticPricing['base']) && $this->suggestedPrice($product) === null) {
            throw ValidationException::withMessages(['automatic_pricing' => 'Add valid purchase costs for every recipe item before enabling automatic pricing.']);
        }
        foreach ($savedVariants as $index => $variant) {
            if ($variant->automatic_pricing && ! in_array((int) $variant->id, $previousAutomaticPricing['variants'] ?? [], true)
                && $this->suggestedPrice($product, $variant) === null) {
                throw ValidationException::withMessages(['variants.'.$index.'.automatic_pricing' => 'Add valid purchase costs for this variant before enabling automatic pricing.']);
            }
        }
        $this->refreshPrices();
    }

    /** @return list<array<string, mixed>> */
    public function preparationRows(Product $product, float $kits = 1, array $seen = [], int $depth = 0, ?ProductVariant $variant = null, string $prefix = ''): array
    {
        $product = $this->products()->get((int) $product->id) ?? $product;
        if (isset($seen[$product->id])) {
            return [];
        }
        $seen[$product->id] = true;
        $ratio = $variant && $product->isPhysical() ? $product->saleUnits($variant) / $product->saleUnits() : 1;
        $rows = [];
        foreach ($product->recipeComponents as $row) {
            $component = $this->products()->get((int) $row->component_product_id);
            if (! $component) {
                continue;
            }
            $quantity = (float) $row->quantity * $kits * $ratio;
            $key = $prefix.'recipe:'.$row->id;
            $rows[] = [
                'key' => $key,
                'product_id' => (int) $component->id,
                'item_name' => $row->preparationLabel(),
                'quantity' => $quantity,
                'quantity_per_kit' => (float) $row->quantity * $ratio,
                'quantity_unit' => $row->resolvedQuantityUnit(),
                'unit' => $row->preparationUnit(),
                'depth' => $depth,
                'note' => (string) ($row->note ?? ''),
                'raw_note' => $row->preparationNote($kits * $ratio),
                'is_kit' => $component->isKit(),
                'has_recipe' => $component->recipeComponents->isNotEmpty(),
                'admin_url' => route('admin.shop.product.edit', $component),
            ];
            if ($component->isKit()) {
                array_push($rows, ...$this->preparationRows($component, $quantity, $seen, $depth + 1, $row->variant, $key.':'));
            }
        }

        return $rows;
    }
}
