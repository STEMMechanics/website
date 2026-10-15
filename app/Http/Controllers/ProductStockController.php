<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StoreInventoryAllocatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductStockController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        abort_unless($product->isPhysical(), 404);

        return response()->json(['stock' => $this->snapshot($product->load('variants'))]);
    }

    public function update(Request $request, Product $product, StoreInventoryAllocatorService $allocator): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'change' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'expected_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($request, $product, $allocator, $data): array {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($product->isPhysical(), 404);
            $product->setRelation('variants', $product->variants()->lockForUpdate()->get());
            $variantId = (int) ($data['variant_id'] ?? 0);
            $variant = $variantId ? $product->variants->find($variantId) : null;
            if (($variantId && ! $variant) || ($product->shared_inventory && $variantId)) {
                throw ValidationException::withMessages(['variant_id' => 'Choose a stock count belonging to this product.']);
            }
            $stock = $variant ?? $product;
            if ($stock->inventory_quantity === null) {
                throw ValidationException::withMessages(['variant_id' => 'Stock is not tracked for this option. Enable stock tracking in the product editor first.']);
            }
            $before = (int) $stock->inventory_quantity;
            if ($before !== (int) $data['expected_quantity']) {
                throw ValidationException::withMessages(['expected_quantity' => 'Stock has changed since you opened this popup. Review the updated count before saving again.']);
            }
            $change = (int) $data['change'];
            $after = $before + $change;
            if ($after < 0 || $after > 2147483647) {
                throw ValidationException::withMessages(['change' => 'The resulting stock count must be between 0 and 2,147,483,647.']);
            }

            $stock->inventory_quantity = $after;
            $threshold = $stock->effectiveLowStockThreshold();
            if ($threshold === null || $after > $threshold) {
                $stock->low_stock_alert_sent_at = null;
            }
            $stock->save();
            if ($change > 0) {
                if ($stock instanceof ProductVariant) {
                    $allocator->allocateForVariant($stock);
                } else {
                    $allocator->allocateForProduct($product);
                }
            }
            $stock->refresh();
            $available = (int) $stock->inventory_quantity;
            $allocated = $after - $available;

            AuditLog::query()->create([
                'event' => 'stock_adjusted',
                'auditable_type' => $stock::class,
                'auditable_id' => (string) $stock->getKey(),
                'actor_user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->url(),
                'old_values' => ['inventory_quantity' => $before],
                'new_values' => [
                    'inventory_quantity' => $available,
                    'adjustment' => $change,
                    'allocated_to_orders' => $allocated,
                    'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
                ],
            ]);

            $unit = $this->unitLabel($product, $variant);
            $message = ($change < 0 ? 'Removed ' : 'Added ').abs($change).' '.Str::plural($unit, abs($change)).'.';
            if ($allocated > 0) {
                $message .= ' '.$allocated.' '.Str::plural($unit, $allocated).' allocated to waiting orders.';
            }
            $message .= ' '.$available.' '.Str::plural($unit, $available).' available.';

            return ['message' => $message, 'stock' => $this->snapshot($product->refresh()->load('variants'))];
        }, 3);

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product): array
    {
        $choices = [[
            'variant_id' => null,
            'name' => $product->shared_inventory ? 'Shared stock across all packs' : $product->baseOptionName(),
            'quantity' => $product->inventory_quantity,
            'unit' => $this->unitLabel($product),
            'plural_unit' => Str::plural($this->unitLabel($product)),
        ]];
        if ($product->usesVariantRecords() && ! $product->shared_inventory) {
            $choices = [];
        }
        if (! $product->shared_inventory) {
            foreach ($product->variants as $variant) {
                $choices[] = [
                    'variant_id' => (int) $variant->id,
                    'name' => $variant->displayName().($variant->is_active ? '' : ' (inactive)'),
                    'quantity' => $variant->inventory_quantity,
                    'unit' => $this->unitLabel($product, $variant),
                    'plural_unit' => Str::plural($this->unitLabel($product, $variant)),
                ];
            }
        }

        return [
            'title' => (string) $product->title,
            'shared' => (bool) $product->shared_inventory,
            'choices' => $choices,
            'edit_url' => route('admin.shop.product.edit', $product),
        ];
    }

    private function unitLabel(Product $product, ?ProductVariant $variant = null): string
    {
        if ($product->hasMaterialMeasurements() && ($product->shared_inventory || $product->saleUnits($variant) === 1)) {
            $unit = $product->purchaseUnitLabel();

            return in_array(strtolower($unit), ['each', 'unit', 'units'], true) ? 'piece' : $unit;
        }

        return $product->shared_inventory ? 'item' : 'pack';
    }
}
