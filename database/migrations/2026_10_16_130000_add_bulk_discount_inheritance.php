<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('bulk_discount_percent', 5, 2)->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('bulk_discount_percent', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Preserve each inherited rate before restoring the required variant value.
        $default = (float) (DB::table('site_options')->where('name', 'store.costing.bulk-discount-percent')->value('value')
            ?? config('product-costing.bulk_discount_percent', 20));
        DB::table('product_variants')->whereNull('bulk_discount_percent')->chunkById(100, function ($variants) use ($default): void {
            $discounts = DB::table('products')->whereIn('id', $variants->pluck('product_id'))->pluck('bulk_discount_percent', 'id');
            foreach ($variants as $variant) {
                DB::table('product_variants')->where('id', $variant->id)->update([
                    'bulk_discount_percent' => $discounts[$variant->product_id] ?? $default,
                ]);
            }
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('bulk_discount_percent', 5, 2)->nullable(false)->default(20)->change();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('bulk_discount_percent');
        });
    }
};
