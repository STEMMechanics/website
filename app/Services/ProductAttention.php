<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StoreOrderItem;
use App\Services\Finance\ProductAllocationEditor;
use App\Support\AdminBadgeCache;
use Illuminate\Support\Collection;

class ProductAttention
{
    public function counts(): array
    {
        return app(AdminBadgeCache::class)->remember('products', function (): array {
            $allocationIds = app(ProductAllocationEditor::class)->attentionIds();
            $inventoryIds = [];
            $currentIds = [];
            Product::query()->active()->with('variants')
                ->chunkById(200, function ($products) use (&$inventoryIds, &$currentIds): void {
                    $currentIds = array_merge($currentIds, $products->modelKeys());
                    foreach ($this->inventorySummaries($products) as $id => $summary) {
                        if ($summary['actionable']) {
                            $inventoryIds[] = $id;
                        }
                    }
                });
            $allocationIds = array_intersect($allocationIds, $currentIds);

            return [
                'inventory' => count($inventoryIds),
                'allocation' => count($allocationIds),
                'total' => count(array_unique(array_merge($inventoryIds, $allocationIds))),
            ];
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array{available:int|null,awaiting:int,reserved:int,backorder:int,preorder:int,low_stock_threshold:int|null,low_stock:bool,actionable:bool}>
     */
    public function inventorySummaries(Collection $products): array
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

            $summaries[$productId]['awaiting'] += $item->remainingOrderFulfillableQuantity();
            $summaries[$productId]['reserved'] += $item->reservedInventory();

            $remainingDelayedQuantity = $item->remainingOrderDelayedQuantity();
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
                && ($available <= 0 || ($threshold !== null && $available <= $threshold));
            $summaries[$productId]['actionable'] = $summaries[$productId]['low_stock'];
        }

        return $summaries;
    }
}
