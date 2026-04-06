<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'subtitle' => null,
            'sku' => 'SKU-'.fake()->unique()->numerify('####'),
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'is_preorder' => false,
            'preorder_shipping_estimate' => null,
            'allow_backorder' => false,
            'backorder_shipping_estimate' => null,
            'backorder_shipping_estimate_type' => null,
            'backorder_shipping_offset_days' => null,
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'base_variant_name' => null,
            'base_variant_description' => null,
            'private_notes' => null,
            'hero_media_name' => null,
            'price' => fake()->randomFloat(2, 5, 150),
            'compare_at_price' => null,
            'shipping_rate' => 0,
            'tax_rate' => 0.1000,
            'inventory_quantity' => null,
            'shipping_units' => fake()->randomElement([0.5, 1.0, 1.5, 2.0]),
            'min_satchel_rank' => fake()->numberBetween(1, 4),
            'weight_grams' => fake()->numberBetween(150, 2500),
            'box_only' => false,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'is_featured' => false,
            'sort_order' => 0,
            'low_stock_threshold' => 5,
            'low_stock_alert_sent_at' => null,
        ];
    }
}
