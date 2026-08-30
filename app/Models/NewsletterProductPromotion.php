<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NewsletterProductPromotion extends Model
{
    protected $fillable = [
        'title',
        'intro',
        'product_ids',
        'sections',
        'subject',
        'hero_header',
        'hero_cta',
        'content_order',
        'is_active',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'sections' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        $ids = collect($this->product_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(6)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->with(['hero', 'categories', 'variants'])
            ->active()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        return $ids->map(fn (int $id) => $products->get($id))
            ->filter(fn ($product) => $product instanceof Product)
            ->values();
    }
}
