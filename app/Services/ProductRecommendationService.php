<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    /** @return Collection<int, Product> */
    public function forProduct(Product $product): Collection
    {
        $categoryIds = $product->categories->modelKeys();
        if ($categoryIds === []) {
            return collect();
        }

        return Product::query()->active()
            ->whereKeyNot($product->getKey())
            ->whereHas('categories', fn ($query) => $query->whereIn('product_categories.id', $categoryIds))
            ->withCount(['categories as shared_categories_count' => fn ($query) => $query->whereIn('product_categories.id', $categoryIds)])
            ->with(['hero', 'variants' => fn ($query) => $query->active()])
            ->orderByDesc('shared_categories_count')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lazy(50)
            ->filter(fn (Product $candidate) => $candidate->isPurchasable())
            ->take(3)
            ->collect();
    }
}
