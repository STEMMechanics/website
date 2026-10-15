<?php

use App\Models\Product;
use App\Services\ProductVariantUpgrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'legacy_base_variant_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_base_variant_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('products', 'default_variant_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('default_variant_id')->nullable()->index();
            });
        }
        Product::query()->sellable()->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                app(ProductVariantUpgrade::class)->convert($product);
            }
        });
    }

    public function down(): void
    {
        Product::query()->whereNotNull('legacy_base_variant_id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                app(ProductVariantUpgrade::class)->restore($product);
            }
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['legacy_base_variant_id', 'default_variant_id']);
        });
    }
};
