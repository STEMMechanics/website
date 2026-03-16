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
        if (Schema::hasTable('store_order_item_cancellations')) {
            return;
        }

        Schema::create('store_order_item_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->foreignUuid('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('delayed_quantity')->default(0);
            $table->text('reason');
            $table->timestamps();

            $table->index('store_order_item_id', 'so_item_cancels_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_order_item_cancellations');
    }
};
