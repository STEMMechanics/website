<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductPurchaseService
{
    public function validate(Request $request): ?array
    {
        $data = $request->validate([
            'product_purchases_changed' => ['nullable', 'boolean'],
            'product_purchases' => ['nullable', 'array', 'max:100'],
            'product_purchases.*.id' => ['nullable', 'integer'],
            'product_purchases.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'product_purchases.*.product_variant_id' => ['nullable', 'integer'],
            'product_purchases.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
            'product_purchases.*.total_cost_ex_tax' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,4'],
            'product_purchases.*.add_inventory' => ['nullable', 'boolean'],
            'product_purchases.*.individual_units' => ['nullable', 'boolean'],
            'product_purchases.*.purchase_pack' => ['nullable', 'boolean'],
        ]);
        if (! $request->boolean('product_purchases_changed') && ! $request->exists('product_purchases')) {
            return null;
        }
        $rows = array_values($data['product_purchases'] ?? []);
        $ids = collect($rows)->pluck('id')->filter();
        if ($ids->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['product_purchases' => 'A saved purchase may only be included once.']);
        }

        return $rows;
    }

    /** Save cost evidence and optional saleable inventory in the expense transaction. */
    public function sync(Expense $expense, array $rows): array
    {
        if (array_sum(array_column($rows, 'total_cost_ex_tax')) > (float) $expense->total_amount - (float) $expense->gst_amount + 0.011) {
            throw ValidationException::withMessages(['product_purchases' => 'The tagged purchase totals exceed this expense excluding GST. Include each cost only once.']);
        }
        $existing = $expense->productPurchases()->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $affected = [];
        foreach ($rows as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            $old = $id ? $existing->get($id) : null;
            if ($id && ! $old) {
                throw ValidationException::withMessages(['product_purchases' => 'A saved purchase no longer belongs to this expense. Reload and try again.']);
            }
            $product = Product::query()->with('variants')->whereKey($row['product_id'])->lockForUpdate()->firstOrFail();
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            $variant = $variantId ? $product->variants->find($variantId) : null;
            if ($product->isDigital() || ($product->status !== Product::STATUS_ACTIVE && (int) $old?->product_id !== (int) $product->id)
                || ($variantId && (! $variant || (! $variant->is_active && (int) $old?->product_variant_id !== $variantId)))) {
                throw ValidationException::withMessages(['product_purchases.'.$index.'.product_id' => 'Choose an active Store Item, Kit or Material and a valid variant.']);
            }
            $individualUnits = ! empty($row['individual_units']);
            if ($individualUnits && (! $product->isPhysical() || $variantId)) {
                throw ValidationException::withMessages(['product_purchases.'.$index.'.product_id' => 'Individual units must be linked to a physical product without a retail variant.']);
            }
            $sameItem = $old && (int) $old->product_id === (int) $product->id && (int) ($old->product_variant_id ?? 0) === $variantId;
            $purchasePack = ! empty($row['purchase_pack'] ?? ($sameItem ? $old->purchase_pack : false));
            if ($purchasePack && ($individualUnits || $variantId || ! $product->isPhysical()
                || (! $product->hasMaterialMeasurements() && ! ($sameItem && $old->purchase_pack)))) {
                throw ValidationException::withMessages(['product_purchases.'.$index.'.purchase_pack' => 'Supplier packs must be linked to a Store Item used as a material, without a retail variant.']);
            }
            $samePurchaseUnit = $sameItem && (bool) $old->individual_units === $individualUnits && (bool) $old->purchase_pack === $purchasePack;
            $unitsPerPurchase = $individualUnits || ! $product->isPhysical()
                ? 1
                : ($samePurchaseUnit && $old->units_per_purchase !== null ? $old->units_per_purchase
                    : ($purchasePack ? max(1, (int) $product->purchase_pack_units) : $product->saleUnits($variant)));
            $inventory = 0;
            if (! empty($row['add_inventory']) && $product->isPhysical() && $product->tracksInventory($variant)) {
                if (($individualUnits || $purchasePack) && ! $product->shared_inventory && $product->saleUnits() > 1) {
                    throw ValidationException::withMessages(['product_purchases.'.$index.'.add_inventory' => 'Use shared individual stock on this product, or leave Add to product stock off for a purchase of individual units or supplier packs.']);
                }
                if (floor((float) $row['quantity']) !== (float) $row['quantity']) {
                    throw ValidationException::withMessages(['product_purchases.'.$index.'.quantity' => 'Use a whole number of purchased items when adding inventory.']);
                }
                $units = $samePurchaseUnit && $old->inventory_added > 0
                    ? (float) $old->inventory_added / (float) $old->quantity
                    : ($purchasePack ? $unitsPerPurchase : ($individualUnits ? 1 : $product->inventoryUnits($variant)));
                if ((float) $row['quantity'] * $units > 2147483647) {
                    throw ValidationException::withMessages(['product_purchases.'.$index.'.quantity' => 'This purchase exceeds the maximum stock count.']);
                }
                $inventory = (int) round((float) $row['quantity'] * $units);
            }
            if ($old && ((int) $old->product_id !== (int) $product->id || (int) $old->product_variant_id !== $variantId)) {
                $this->adjustInventory($old, -$old->inventory_added);
                $this->adjustInventory(new ProductPurchase(['product_id' => $product->id, 'product_variant_id' => $variantId ?: null]), $inventory);
                $affected[] = (int) $old->product_id;
            } else {
                $this->adjustInventory(new ProductPurchase(['product_id' => $product->id, 'product_variant_id' => $variantId ?: null]), $inventory - (int) ($old->inventory_added ?? 0));
            }
            $purchase = $old ?? new ProductPurchase(['expense_id' => $expense->id]);
            $purchase->fill([
                'product_id' => $product->id, 'product_variant_id' => $variantId ?: null,
                'quantity' => (float) $row['quantity'], 'total_cost_ex_tax' => (float) $row['total_cost_ex_tax'],
                'inventory_added' => $inventory,
                'individual_units' => $individualUnits, 'purchase_pack' => $purchasePack, 'units_per_purchase' => $unitsPerPurchase,
            ])->save();
            $kept[] = $purchase->id;
            $affected[] = (int) $product->id;
        }
        foreach ($existing as $purchase) {
            if (in_array($purchase->id, $kept, true)) {
                continue;
            }
            $this->adjustInventory($purchase, -$purchase->inventory_added);
            $affected[] = (int) $purchase->product_id;
            $purchase->delete();
        }
        app(ProductRecipeService::class)->refreshPrices();

        return array_values(array_unique($affected));
    }

    private function adjustInventory(ProductPurchase $purchase, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $product = Product::query()->whereKey($purchase->product_id)->lockForUpdate()->firstOrFail();
        $variantId = $purchase->product_variant_id ?: $product->legacy_base_variant_id;
        $inventory = $product->shared_inventory || ! $variantId
            ? $product
            : $product->variants()->whereKey($variantId)->lockForUpdate()->firstOrFail();
        if ($inventory->inventory_quantity === null) {
            return;
        }
        if ((int) $inventory->inventory_quantity + $delta < 0) {
            throw ValidationException::withMessages(['product_purchases' => 'Some of this purchase stock has already been used. Adjust the product inventory before reducing or removing the purchase.']);
        }
        if ((int) $inventory->inventory_quantity + $delta > 2147483647) {
            throw ValidationException::withMessages(['product_purchases' => 'This purchase exceeds the maximum stock count.']);
        }
        $inventory->inventory_quantity = (int) $inventory->inventory_quantity + $delta;
        $inventory->save();
    }

    public function catalog(?Expense $expense = null): array
    {
        $linkedIds = $expense?->productPurchases()->pluck('product_id')->all() ?? [];

        return Product::query()->with('variants')->whereIn('product_type', [...Product::PHYSICAL_TYPES, Product::PRODUCT_TYPE_MATERIAL])
            ->where(fn ($query) => $query->where('status', Product::STATUS_ACTIVE)->orWhereIn('id', $linkedIds))
            ->orderBy('title')->get()->map(fn (Product $product): array => [
                'id' => (int) $product->id, 'name' => (string) $product->title, 'sku' => (string) $product->sku,
                'url' => route('admin.shop.product.edit', $product),
                'base_option_name' => $product->usesVariantRecords() ? 'Reference pack' : $product->baseOptionName(),
                'pack_units' => $product->saleUnits(), 'shared_inventory' => (bool) $product->shared_inventory,
                'product_type' => (string) $product->product_type, 'is_kit' => $product->isKit(),
                'has_material_measurements' => $product->hasMaterialMeasurements(),
                'purchase_pack_units' => max(1, (int) $product->purchase_pack_units),
                'status' => (string) $product->status, 'unit' => $product->purchaseUnitLabel(),
                'tracks_inventory' => $product->isPhysical() && $product->inventory_quantity !== null,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => ['id' => (int) $variant->id, 'name' => $variant->displayName(), 'pack_units' => $product->saleUnits($variant), 'tracks_inventory' => $product->isPhysical() && $product->tracksInventory($variant)])->all(),
            ])->all();
    }
}
