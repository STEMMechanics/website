<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('discount_type', 30)->default('fixed_amount');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('shipping_rate', 10, 2)->nullable();
            $table->unsignedInteger('inventory_quantity')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('weight_grams')->nullable()->after('inventory_quantity');
            $table->decimal('length_cm', 8, 2)->nullable()->after('weight_grams');
            $table->decimal('width_cm', 8, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 8, 2)->nullable()->after('width_cm');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->after('invoice_id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->string('coupon_type', 30)->nullable()->after('coupon_code');
            $table->string('shipping_method')->nullable()->after('shipping_country');
            $table->string('shipping_zone', 20)->nullable()->after('shipping_method');
            $table->unsignedInteger('shipping_chargeable_weight_grams')->nullable()->after('shipping_zone');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('shipping_amount');
            $table->timestamp('order_confirmation_emailed_at')->nullable()->after('fulfilled_at');
            $table->timestamp('order_paid_emailed_at')->nullable()->after('order_confirmation_emailed_at');
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name')->nullable()->after('product_slug');
            $table->string('variant_sku')->nullable()->after('product_sku');
            $table->unsignedInteger('inventory_reserved_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('unit_weight_grams')->nullable()->after('tax_rate');
            $table->decimal('unit_length_cm', 8, 2)->nullable()->after('unit_weight_grams');
            $table->decimal('unit_width_cm', 8, 2)->nullable()->after('unit_length_cm');
            $table->decimal('unit_height_cm', 8, 2)->nullable()->after('unit_width_cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn([
                'variant_name',
                'variant_sku',
                'inventory_reserved_quantity',
                'unit_weight_grams',
                'unit_length_cm',
                'unit_width_cm',
                'unit_height_cm',
            ]);
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn([
                'coupon_code',
                'coupon_type',
                'shipping_method',
                'shipping_zone',
                'shipping_chargeable_weight_grams',
                'discount_amount',
                'order_confirmation_emailed_at',
                'order_paid_emailed_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'weight_grams',
                'length_cm',
                'width_cm',
                'height_cm',
            ]);
        });

        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('coupons');
    }
};
