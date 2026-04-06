<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon_class', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['sort_order', 'name']);
        });

        Schema::create('product_category_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'product_category_id']);
            $table->index(['product_category_id', 'sort_order']);
        });

        $legacyCategories = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->orderBy('id')
            ->pluck('category')
            ->map(fn ($category) => trim((string) $category))
            ->filter()
            ->values();

        $categoriesByKey = [];
        $sortOrder = 0;

        foreach ($legacyCategories as $categoryName) {
            $key = mb_strtolower($categoryName);
            if (isset($categoriesByKey[$key])) {
                continue;
            }

            $slug = Str::slug($categoryName);
            if ($slug === '') {
                $slug = 'category';
            }

            $candidate = $slug;
            $suffix = 2;
            while (DB::table('product_categories')->where('slug', $candidate)->exists()) {
                $candidate = $slug.'-'.$suffix;
                $suffix += 1;
            }

            $categoryId = DB::table('product_categories')->insertGetId([
                'name' => $categoryName,
                'slug' => $candidate,
                'icon_class' => 'fa-solid fa-tag',
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $categoriesByKey[$key] = $categoryId;
            $sortOrder += 1;
        }

        $legacyProducts = DB::table('products')
            ->select(['id', 'category'])
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('id')
            ->get();

        foreach ($legacyProducts as $product) {
            $key = mb_strtolower(trim((string) $product->category));
            $categoryId = $categoriesByKey[$key] ?? null;

            if ($categoryId === null) {
                continue;
            }

            DB::table('product_category_product')->insert([
                'product_id' => (int) $product->id,
                'product_category_id' => (int) $categoryId,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
        Schema::dropIfExists('product_categories');
    }
};
