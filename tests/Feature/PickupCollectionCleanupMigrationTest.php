<?php

namespace Tests\Feature;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupCollectionCleanupMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_collected_quantity_beyond_the_ordered_quantity(): void
    {
        $order = StoreOrder::factory()->create(['shipping_method_code' => 'pickup']);
        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'quantity' => 2,
            'available_now_quantity' => 2,
            'delayed_quantity' => 0,
        ]);

        foreach ([now()->subHour(), now()] as $createdAt) {
            StoreOrderItemCollection::query()->create([
                'store_order_item_id' => $item->id,
                'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
                'quantity' => 2,
                'collected_at' => now(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $migration = require database_path('migrations/2026_08_18_000004_remove_duplicate_pickup_collections.php');
        $migration->up();

        $this->assertDatabaseCount('store_order_item_collections', 1);
        $this->assertSame(2, (int) $item->fresh()->collectedQuantity());
    }
}
