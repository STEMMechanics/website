<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('purchase_pack_units')->default(1);
        });
        Schema::table('product_purchases', function (Blueprint $table): void {
            $table->boolean('purchase_pack')->default(false);
        });
        // Null retains the original meaning of existing links.
        Schema::table('product_recipe_components', function (Blueprint $table): void {
            $table->string('quantity_unit', 10)->nullable();
        });
        Schema::table('pick_list_template_items', function (Blueprint $table): void {
            $table->string('quantity_unit', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('purchase_pack_units'));
        Schema::table('product_purchases', fn (Blueprint $table) => $table->dropColumn('purchase_pack'));
        Schema::table('product_recipe_components', fn (Blueprint $table) => $table->dropColumn('quantity_unit'));
        Schema::table('pick_list_template_items', fn (Blueprint $table) => $table->dropColumn('quantity_unit'));
    }
};
