<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('store_order_updates')) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Repair migration: leave the table in place because an earlier migration may own it.
    }
};
