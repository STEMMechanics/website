<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('shared_inventory')->default(false);
            $table->unsignedInteger('inventory_units')->default(1);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('inventory_units')->default(1);
        });
        Schema::table('store_order_items', function (Blueprint $table) {
            $table->boolean('shared_inventory')->default(false);
            $table->unsignedInteger('inventory_units')->default(1);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('inventory_reservations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('inventory_reservations');
        });
        Schema::table('store_order_items', function (Blueprint $table) {
            $table->dropColumn(['shared_inventory', 'inventory_units']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('inventory_units');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['shared_inventory', 'inventory_units']);
        });
    }
};
