<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMillimetreColumns();

        DB::table('products')->update([
            'length_mm' => DB::raw('ROUND(length_cm * 10)'),
            'width_mm' => DB::raw('ROUND(width_cm * 10)'),
            'height_mm' => DB::raw('ROUND(height_cm * 10)'),
        ]);
        DB::table('product_variants')->update([
            'length_mm' => DB::raw('ROUND(length_cm * 10)'),
            'width_mm' => DB::raw('ROUND(width_cm * 10)'),
            'height_mm' => DB::raw('ROUND(height_cm * 10)'),
        ]);
        DB::table('store_order_items')->update([
            'unit_length_mm' => DB::raw('ROUND(unit_length_cm * 10)'),
            'unit_width_mm' => DB::raw('ROUND(unit_width_cm * 10)'),
            'unit_height_mm' => DB::raw('ROUND(unit_height_cm * 10)'),
        ]);

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['length_cm', 'width_cm', 'height_cm']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['length_cm', 'width_cm', 'height_cm']));
        Schema::table('store_order_items', fn (Blueprint $table) => $table->dropColumn(['unit_length_cm', 'unit_width_cm', 'unit_height_cm']));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
        });
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->decimal('unit_length_cm', 8, 2)->nullable();
            $table->decimal('unit_width_cm', 8, 2)->nullable();
            $table->decimal('unit_height_cm', 8, 2)->nullable();
        });

        DB::table('products')->update(['length_cm' => DB::raw('length_mm / 10.0'), 'width_cm' => DB::raw('width_mm / 10.0'), 'height_cm' => DB::raw('height_mm / 10.0')]);
        DB::table('product_variants')->update(['length_cm' => DB::raw('length_mm / 10.0'), 'width_cm' => DB::raw('width_mm / 10.0'), 'height_cm' => DB::raw('height_mm / 10.0')]);
        DB::table('store_order_items')->update(['unit_length_cm' => DB::raw('unit_length_mm / 10.0'), 'unit_width_cm' => DB::raw('unit_width_mm / 10.0'), 'unit_height_cm' => DB::raw('unit_height_mm / 10.0')]);

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['length_mm', 'width_mm', 'height_mm']));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['length_mm', 'width_mm', 'height_mm']));
        Schema::table('store_order_items', fn (Blueprint $table) => $table->dropColumn(['unit_length_mm', 'unit_width_mm', 'unit_height_mm']));
    }

    private function addMillimetreColumns(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
        });
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->unsignedInteger('unit_length_mm')->nullable();
            $table->unsignedInteger('unit_width_mm')->nullable();
            $table->unsignedInteger('unit_height_mm')->nullable();
        });
    }
};
