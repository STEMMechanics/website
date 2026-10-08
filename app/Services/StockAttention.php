<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Facades\Schema;

class StockAttention
{
    public function count(): int
    {
        if (! Schema::hasTable('stock_items')) {
            return 0;
        }

        $stockItems = StockItem::query()
            ->where('status', StockItem::STATUS_ACTIVE)
            ->get();
        $reservedQuantities = app(StockInventoryService::class)->reservedQuantitiesForStockItems($stockItems);

        return $stockItems
            ->filter(function (StockItem $stockItem) use ($reservedQuantities): bool {
                $reserved = (float) ($reservedQuantities[(int) $stockItem->id] ?? 0);
                $onHand = (float) $stockItem->on_hand_quantity;
                $available = max(0, $onHand - $reserved);
                $hasReservationShortage = $reserved > $onHand + 0.0005;

                return $hasReservationShortage
                    || ((float) $stockItem->reorder_point > 0
                        && $available < (float) $stockItem->reorder_point);
            })
            ->count();
    }
}
