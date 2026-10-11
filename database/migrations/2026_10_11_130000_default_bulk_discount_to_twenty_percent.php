<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('bulk_discount_percent', 5, 2)->default(20)->change();
        });

        // Initialize the new setting on existing larger packs. Explicit zeros remain valid afterwards.
        DB::table('product_variants')
            ->where('bulk_discount_percent', 0)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('products')
                    ->whereColumn('products.id', 'product_variants.product_id')
                    ->whereIn('products.product_type', ['physical', 'kit'])
                    ->whereColumn('product_variants.inventory_units', '>', 'products.inventory_units');
            })
            ->update(['bulk_discount_percent' => 20]);
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('bulk_discount_percent', 5, 2)->default(0)->change();
        });
    }
};
