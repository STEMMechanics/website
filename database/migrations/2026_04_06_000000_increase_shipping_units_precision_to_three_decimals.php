<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('shipping_units', 8, 3)->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('shipping_units', 8, 3)->nullable()->change();
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->decimal('unit_shipping_units', 8, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('shipping_units', 8, 2)->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('shipping_units', 8, 2)->nullable()->change();
        });

        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->decimal('unit_shipping_units', 8, 2)->nullable()->change();
        });
    }
};
