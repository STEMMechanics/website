<?php

namespace App\Services\Finance;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class DocumentProductCatalog
{
    public function products(array $lineItems = []): array
    {
        $referencedVariants = collect($lineItems)->map(fn (array $item) => $item['source_variant_id']
            ?? data_get($item, 'details_json.variant_id')
            ?? data_get($item, 'details_json.store_context.variant_id')
            ?? data_get($item, 'store_context.variant_id'))->filter()->unique()->all();

        return Product::query()
            ->with(['variants' => fn ($query) => $query
                ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $referencedVariants))
                ->orderBy('sort_order')->orderBy('name')])
            ->orderBy('title')->get()->map(function (Product $product): array {
                return [
                    'id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'slug' => (string) $product->slug,
                    'sku' => (string) ($product->sku ?? ''),
                    'base_option_name' => (string) $product->baseOptionName(),
                    'has_option_choices' => $product->hasOptionChoices() || $product->variants->isNotEmpty(),
                    'price' => round((float) $product->price, 2),
                    'tax_rate' => round((float) $product->tax_rate, 4),
                    'product_type' => (string) ($product->product_type ?? ''),
                    'shipping_units' => round((float) ($product->shipping_units ?? 0), 3),
                    'min_satchel_rank' => $product->min_satchel_rank,
                    'weight_grams' => $product->weight_grams,
                    'box_only' => (bool) $product->box_only,
                    'summary' => trim((string) ($product->short_description ?: Str::limit(strip_tags((string) $product->description), 180, ''))),
                    'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                        'id' => (int) $variant->id,
                        'name' => (string) $variant->displayName(),
                        'sku' => (string) ($variant->sku ?? ''),
                        'summary' => trim((string) ($variant->description ?? '')),
                        'price' => round((float) ($variant->price ?? $product->price), 2),
                    ])->values()->all(),
                ];
            })->all();
    }
}
