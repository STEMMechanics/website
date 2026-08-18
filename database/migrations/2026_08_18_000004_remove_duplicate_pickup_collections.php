<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_order_item_collections')) {
            return;
        }

        DB::table('store_order_items')
            ->orderBy('id')
            ->select([
                'id',
                'quantity',
                'available_now_quantity',
                'delayed_quantity',
                'cancelled_available_quantity',
                'cancelled_delayed_quantity',
            ])
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $availableQuantity = max(0, (int) ($item->available_now_quantity ?? 0));
                    $delayedQuantity = max(0, (int) ($item->delayed_quantity ?? 0));
                    if ($availableQuantity <= 0 && $delayedQuantity <= 0) {
                        $availableQuantity = max(0, (int) ($item->quantity ?? 0));
                    }

                    $this->trimCollectedRows(
                        (int) $item->id,
                        'available',
                        max(0, $availableQuantity - (int) ($item->cancelled_available_quantity ?? 0)),
                    );
                    $this->trimCollectedRows(
                        (int) $item->id,
                        'delayed',
                        max(0, $delayedQuantity - (int) ($item->cancelled_delayed_quantity ?? 0)),
                    );
                }
            });
    }

    public function down(): void
    {
        // Duplicate collection rows cannot be restored safely.
    }

    private function trimCollectedRows(int $itemId, string $collectionType, int $maximumQuantity): void
    {
        $remaining = $maximumQuantity;
        $rows = DB::table('store_order_item_collections')
            ->where('store_order_item_id', $itemId)
            ->where('collection_type', $collectionType)
            ->where('pickup_state', 'collected')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'quantity']);

        foreach ($rows as $row) {
            $quantity = max(0, (int) $row->quantity);

            if ($remaining <= 0 || $quantity <= 0) {
                DB::table('store_order_item_collections')->where('id', (int) $row->id)->delete();

                continue;
            }

            if ($quantity > $remaining) {
                DB::table('store_order_item_collections')
                    ->where('id', (int) $row->id)
                    ->update(['quantity' => $remaining, 'updated_at' => now()]);
                $remaining = 0;

                continue;
            }

            $remaining -= $quantity;
        }
    }
};
