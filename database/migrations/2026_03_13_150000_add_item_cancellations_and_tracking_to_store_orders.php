<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('store_order_items', 'cancelled_available_quantity')) {
            Schema::table('store_order_items', function (Blueprint $table): void {
                $table->unsignedInteger('cancelled_available_quantity')->default(0)->after('inventory_reserved_quantity');
            });
        }

        if (! Schema::hasColumn('store_order_items', 'cancelled_delayed_quantity')) {
            Schema::table('store_order_items', function (Blueprint $table): void {
                $table->unsignedInteger('cancelled_delayed_quantity')->default(0)->after('cancelled_available_quantity');
            });
        }

        DB::table('store_order_items')
            ->where('available_now_quantity', 0)
            ->where('delayed_quantity', 0)
            ->update([
                'available_now_quantity' => DB::raw('quantity'),
            ]);

        if (! Schema::hasTable('store_order_item_trackings')) {
            Schema::create('store_order_item_trackings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('store_order_item_id')->constrained('store_order_items')->cascadeOnDelete();
                $table->string('shipment_type', 20)->default('available');
                $table->unsignedInteger('quantity')->default(1);
                $table->string('carrier')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('tracking_url', 2048)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();

                $table->index(['store_order_item_id', 'shipment_type'], 'so_item_trackings_item_ship_idx');
            });
        }

        if (! Schema::hasTable('store_order_updates')) {
            Schema::create('store_order_updates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('store_order_id')->constrained('store_orders')->cascadeOnDelete();
                $table->foreignId('store_order_item_id')->nullable()->constrained('store_order_items')->nullOnDelete();
                $table->string('event_type', 40)->index();
                $table->boolean('customer_visible')->default(true);
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('customer_digest_queued_at')->nullable();
                $table->timestamp('admin_digest_queued_at')->nullable();
                $table->timestamps();

                $table->index(['store_order_id', 'occurred_at'], 'so_updates_order_occurred_idx');
                $table->index(['customer_digest_queued_at', 'occurred_at'], 'so_updates_customer_queue_idx');
                $table->index(['admin_digest_queued_at', 'occurred_at'], 'so_updates_admin_queue_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_order_updates');
        Schema::dropIfExists('store_order_item_trackings');

        $columnsToDrop = [];

        if (Schema::hasColumn('store_order_items', 'cancelled_available_quantity')) {
            $columnsToDrop[] = 'cancelled_available_quantity';
        }

        if (Schema::hasColumn('store_order_items', 'cancelled_delayed_quantity')) {
            $columnsToDrop[] = 'cancelled_delayed_quantity';
        }

        if ($columnsToDrop !== []) {
            Schema::table('store_order_items', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
