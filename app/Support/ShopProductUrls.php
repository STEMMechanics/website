<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class ShopProductUrls
{
    public const RESERVED_SLUGS = ['cart', 'checkout', 'quote-requested'];

    public static function variantSlug(string $sku, ?int $ignoreId = null): string
    {
        $base = Str::slug($sku) ?: 'variant';
        $slug = $base;
        $suffix = 2;
        while (in_array($slug, self::RESERVED_SLUGS, true)
            || Product::query()->where('slug', $slug)->exists()
            || ProductVariant::query()->where('url_slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
