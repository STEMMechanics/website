<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_order_item_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->string('collection_type', 32)->index();
            $table->unsignedInteger('quantity');
            $table->foreignUuid('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();
        });

        $pickupTrackingRows = DB::table('store_order_item_trackings as trackings')
            ->join('store_order_items as items', 'items.id', '=', 'trackings.store_order_item_id')
            ->join('store_orders as orders', 'orders.id', '=', 'items.store_order_id')
            ->where('orders.shipping_method_code', 'pickup')
            ->orderBy('trackings.id')
            ->select([
                'trackings.id',
                'trackings.store_order_item_id',
                'trackings.shipment_type',
                'trackings.quantity',
                'trackings.notes',
                'trackings.dispatched_at',
                'trackings.created_at',
                'trackings.updated_at',
            ])
            ->get();

        foreach ($pickupTrackingRows as $row) {
            DB::table('store_order_item_collections')->insert([
                'store_order_item_id' => (int) $row->store_order_item_id,
                'collection_type' => (string) $row->shipment_type,
                'quantity' => max(0, (int) $row->quantity),
                'collected_by_user_id' => null,
                'notes' => $row->notes,
                'collected_at' => $row->dispatched_at ?? $row->created_at ?? now(),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        $collectedPickupOrders = DB::table('store_orders')
            ->where('shipping_method_code', 'pickup')
            ->where('status', 'collected')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('store_order_item_collections as collections')
                    ->join('store_order_items as items', 'items.id', '=', 'collections.store_order_item_id')
                    ->whereColumn('items.store_order_id', 'store_orders.id');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('store_order_item_trackings as trackings')
                    ->join('store_order_items as items', 'items.id', '=', 'trackings.store_order_item_id')
                    ->whereColumn('items.store_order_id', 'store_orders.id');
            })
            ->select(['id', 'fulfilled_at', 'updated_at'])
            ->orderBy('id')
            ->get();

        foreach ($collectedPickupOrders as $orderRow) {
            $items = DB::table('store_order_items')
                ->where('store_order_id', (int) $orderRow->id)
                ->orderBy('id')
                ->get([
                    'id',
                    'available_now_quantity',
                    'delayed_quantity',
                    'cancelled_available_quantity',
                    'cancelled_delayed_quantity',
                    'quantity',
                    'created_at',
                    'updated_at',
                ]);

            foreach ($items as $itemRow) {
                $availableQuantity = max(0, (int) ($itemRow->available_now_quantity ?? 0) - max(0, (int) ($itemRow->cancelled_available_quantity ?? 0)));
                $delayedQuantity = max(0, (int) ($itemRow->delayed_quantity ?? 0) - max(0, (int) ($itemRow->cancelled_delayed_quantity ?? 0)));

                if ($availableQuantity <= 0 && $delayedQuantity <= 0) {
                    $fallbackQuantity = max(0, (int) ($itemRow->quantity ?? 0));
                    if ($fallbackQuantity <= 0) {
                        continue;
                    }

                    $availableQuantity = $fallbackQuantity;
                }

                if ($availableQuantity > 0) {
                    DB::table('store_order_item_collections')->insert([
                        'store_order_item_id' => (int) $itemRow->id,
                        'collection_type' => 'available',
                        'quantity' => $availableQuantity,
                        'collected_by_user_id' => null,
                        'notes' => null,
                        'collected_at' => $orderRow->fulfilled_at ?? $itemRow->updated_at ?? now(),
                        'created_at' => $itemRow->updated_at ?? now(),
                        'updated_at' => $itemRow->updated_at ?? now(),
                    ]);
                }

                if ($delayedQuantity > 0) {
                    DB::table('store_order_item_collections')->insert([
                        'store_order_item_id' => (int) $itemRow->id,
                        'collection_type' => 'delayed',
                        'quantity' => $delayedQuantity,
                        'collected_by_user_id' => null,
                        'notes' => null,
                        'collected_at' => $orderRow->fulfilled_at ?? $itemRow->updated_at ?? now(),
                        'created_at' => $itemRow->updated_at ?? now(),
                        'updated_at' => $itemRow->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_item_collections');
    }
};
