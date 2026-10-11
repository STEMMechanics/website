<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These are new product features. No Stock Item data is converted.
        Schema::table('products', function (Blueprint $table): void {
            $table->string('material_kind', 16)->nullable();
            $table->string('purchase_unit', 40)->nullable();
            $table->decimal('purchase_quantity', 12, 4)->nullable();
            $table->string('material_measure_unit', 8)->nullable();
            $table->decimal('material_length_mm', 12, 3)->nullable();
            $table->decimal('material_width_mm', 12, 3)->nullable();
            $table->decimal('unit_cost_ex_tax', 12, 4)->nullable();
            $table->decimal('operational_cost_ex_tax', 12, 2)->nullable();
            $table->decimal('time_cost_ex_tax', 12, 2)->nullable();
            $table->boolean('automatic_pricing')->default(false);
            $table->decimal('markup_percent', 8, 2)->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('unit_cost_ex_tax', 12, 4)->nullable();
        });
        Schema::create('product_cut_sizes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('length_mm', 12, 3);
            $table->decimal('width_mm', 12, 3);
            $table->unsignedInteger('yield');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('product_recipe_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('component_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('cut_size_id')->nullable()->constrained('product_cut_sizes')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('length', 12, 4)->nullable();
            $table->string('length_unit', 8)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            // A material can appear repeatedly with different cuts in one recipe.
            $table->index(['product_id', 'sort_order']);
        });
        Schema::create('product_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('total_cost_ex_tax', 12, 4);
            $table->unsignedInteger('inventory_added')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'product_variant_id']);
        });
        Schema::table('pick_list_template_items', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cut_size_id')->nullable()->constrained('product_cut_sizes')->nullOnDelete();
            $table->decimal('length', 12, 4)->nullable();
            $table->string('length_unit', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pick_list_template_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('cut_size_id');
            $table->dropColumn(['length', 'length_unit']);
        });
        Schema::dropIfExists('product_purchases');
        Schema::dropIfExists('product_recipe_components');
        Schema::dropIfExists('product_cut_sizes');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('unit_cost_ex_tax'));
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['material_kind', 'purchase_unit', 'purchase_quantity', 'material_measure_unit', 'material_length_mm', 'material_width_mm', 'unit_cost_ex_tax', 'operational_cost_ex_tax', 'time_cost_ex_tax', 'automatic_pricing', 'markup_percent']);
        });
    }
};
