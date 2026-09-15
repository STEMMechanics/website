<?php

use App\Support\ShopProductUrls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('url_slug')->nullable()->unique();
        });
        DB::table('product_variants')->orderBy('id')->chunkById(100, function ($variants): void {
            foreach ($variants as $variant) {
                DB::table('product_variants')->where('id', $variant->id)->update([
                    'url_slug' => ShopProductUrls::variantSlug((string) $variant->sku, (int) $variant->id),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('url_slug');
        });
    }
};
