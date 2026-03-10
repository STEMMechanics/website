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
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('shipping_units', 6, 2)->default(0)->after('inventory_quantity');
            $table->unsignedTinyInteger('min_satchel_rank')->default(1)->after('shipping_units');
            $table->boolean('box_only')->default(false)->after('weight_grams');
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('shipping_package_summary')->nullable()->after('shipping_method');
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->decimal('unit_shipping_units', 6, 2)->nullable()->after('inventory_reserved_quantity');
            $table->unsignedTinyInteger('unit_min_satchel_rank')->nullable()->after('unit_shipping_units');
            $table->boolean('box_only')->default(false)->after('product_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'unit_shipping_units',
                'unit_min_satchel_rank',
                'box_only',
            ]);
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_package_summary');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'shipping_units',
                'min_satchel_rank',
                'box_only',
            ]);
        });
    }
};
