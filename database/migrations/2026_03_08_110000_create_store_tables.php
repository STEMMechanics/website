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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('sku')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('product_type', 20)->default('physical')->index();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('hero_media_name')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('shipping_rate', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0.1000);
            $table->unsignedInteger('inventory_quantity')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('access_token', 80)->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 30)->default('pending_payment')->index();
            $table->boolean('contains_digital')->default(false);
            $table->boolean('contains_physical')->default(false);
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postcode')->nullable();
            $table->string('shipping_country')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->string('product_title');
            $table->string('product_slug')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('product_type', 20);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('unit_shipping_rate', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0.1000);
            $table->decimal('line_price_amount', 10, 2)->default(0);
            $table->decimal('line_shipping_amount', 10, 2)->default(0);
            $table->decimal('line_gst_amount', 10, 2)->default(0);
            $table->decimal('line_total_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('store_order_item_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->string('media_name');
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_order_item_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_order_item_downloads');
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('products');
    }
};
