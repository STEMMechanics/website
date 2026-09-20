<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stage')->default('cart');
            $table->string('outcome')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->boolean('manual_quote')->default(false);
            $table->timestamp('checkout_started_at')->nullable();
            $table->timestamp('last_activity_at')->index();
            $table->boolean('payment_failed')->default(false);
            $table->boolean('payment_cancelled')->default(false);
            $table->timestamps();
            $table->index(['created_at', 'outcome']);
        });
        Schema::create('store_checkout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('checkout_id')->constrained('store_checkout_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('line_key');
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->unique(['checkout_id', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_checkout_items');
        Schema::dropIfExists('store_checkout_sessions');
    }
};
