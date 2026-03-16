<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreInventoryAllocatorService
{
    public function __construct(
        private readonly StoreOrderUpdateService $updates,
    ) {}

    public function allocateForProduct(Product $product): int
    {
        return $this->allocateForProductId((int) $product->id);
    }

    public function allocateForVariant(ProductVariant $variant): int
    {
        return $this->allocateForVariantId((int) $variant->id);
    }

    public function allocateForOrder(StoreOrder $order): int
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->get();

        $sources = $items
            ->filter(fn (StoreOrderItem $item): bool => (string) $item->delayed_fulfilment_type === 'backorder')
            ->map(function (StoreOrderItem $item): string {
                return $item->product_variant_id
                    ? 'variant:'.$item->product_variant_id
                    : 'product:'.$item->product_id;
            })
            ->filter(fn (?string $source): bool => $source !== null && ! str_ends_with($source, ':'))
            ->unique()
            ->values();

        $allocated = 0;

        foreach ($sources as $source) {
            [$type, $id] = explode(':', (string) $source, 2);
            $sourceId = (int) $id;
            if ($sourceId <= 0) {
                continue;
            }

            $allocated += $type === 'variant'
                ? $this->allocateForVariantId($sourceId)
                : $this->allocateForProductId($sourceId);
        }

        return $allocated;
    }

    public function allocateForOrderItem(StoreOrderItem $item): int
    {
        if ($item->product_variant_id) {
            return $this->allocateForVariantId((int) $item->product_variant_id);
        }

        if ($item->product_id) {
            return $this->allocateForProductId((int) $item->product_id);
        }

        return 0;
    }

    private function allocateForProductId(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($productId): int {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (! $product instanceof Product || $product->inventory_quantity === null) {
                return 0;
            }

            $availableInventory = max(0, (int) $product->inventory_quantity);
            if ($availableInventory <= 0) {
                return 0;
            }

            $allocated = $this->allocateAgainstCandidates(
                $availableInventory,
                $this->productCandidatesQuery($productId)->get()
            );

            if ($allocated <= 0) {
                return 0;
            }

            $product->inventory_quantity = max(0, (int) $product->inventory_quantity - $allocated);
            $product->save();

            return $allocated;
        });
    }

    private function allocateForVariantId(int $variantId): int
    {
        if ($variantId <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($variantId): int {
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->lockForUpdate()
                ->first();

            if (! $variant instanceof ProductVariant || $variant->inventory_quantity === null) {
                return 0;
            }

            $availableInventory = max(0, (int) $variant->inventory_quantity);
            if ($availableInventory <= 0) {
                return 0;
            }

            $allocated = $this->allocateAgainstCandidates(
                $availableInventory,
                $this->variantCandidatesQuery($variantId)->get()
            );

            if ($allocated <= 0) {
                return 0;
            }

            $variant->inventory_quantity = max(0, (int) $variant->inventory_quantity - $allocated);
            $variant->save();

            return $allocated;
        });
    }

    /**
     * @param  Collection<int, StoreOrderItem>  $candidates
     */
    private function allocateAgainstCandidates(int $availableInventory, Collection $candidates): int
    {
        $allocated = 0;

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof StoreOrderItem || $availableInventory <= 0) {
                break;
            }

            $remainingDelayed = $candidate->remainingDelayedQuantity();
            if ($remainingDelayed <= 0) {
                continue;
            }

            $quantity = min($availableInventory, $remainingDelayed);
            if ($quantity <= 0) {
                continue;
            }

            $candidate->available_now_quantity = max(0, (int) $candidate->available_now_quantity) + $quantity;
            $candidate->delayed_quantity = max(0, (int) $candidate->delayed_quantity - $quantity);
            $candidate->inventory_reserved_quantity = max(0, (int) $candidate->inventory_reserved_quantity) + $quantity;

            if ((int) $candidate->delayed_quantity <= 0) {
                $candidate->delayed_fulfilment_type = null;
                $candidate->delayed_shipping_estimate = null;
            }

            $candidate->save();

            $availableInventory -= $quantity;
            $allocated += $quantity;

            if ($candidate->order instanceof StoreOrder) {
                $this->updates->recordBackorderAllocation(
                    $candidate->order,
                    $candidate,
                    $quantity,
                    $candidate->remainingDelayedQuantity(),
                );
            }
        }

        return $allocated;
    }

    private function productCandidatesQuery(int $productId)
    {
        return StoreOrderItem::query()
            ->with(['order', 'trackingEntries'])
            ->select('store_order_items.*')
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->where('store_order_items.product_id', $productId)
            ->whereNull('store_order_items.product_variant_id')
            ->where('store_order_items.delayed_fulfilment_type', 'backorder')
            ->where('store_order_items.delayed_quantity', '>', 0)
            ->whereNotNull('store_orders.paid_at')
            ->where('store_orders.status', '!=', StoreOrder::STATUS_CANCELLED)
            ->orderBy('store_orders.paid_at')
            ->orderBy('store_orders.created_at')
            ->orderBy('store_orders.id')
            ->orderBy('store_order_items.id')
            ->lockForUpdate();
    }

    private function variantCandidatesQuery(int $variantId)
    {
        return StoreOrderItem::query()
            ->with(['order', 'trackingEntries'])
            ->select('store_order_items.*')
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->where('store_order_items.product_variant_id', $variantId)
            ->where('store_order_items.delayed_fulfilment_type', 'backorder')
            ->where('store_order_items.delayed_quantity', '>', 0)
            ->whereNotNull('store_orders.paid_at')
            ->where('store_orders.status', '!=', StoreOrder::STATUS_CANCELLED)
            ->orderBy('store_orders.paid_at')
            ->orderBy('store_orders.created_at')
            ->orderBy('store_orders.id')
            ->orderBy('store_order_items.id')
            ->lockForUpdate();
    }
}
