<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->unique()->words(2, true),
            'sku' => 'VAR-'.fake()->unique()->numerify('####'),
            'price' => fake()->randomFloat(2, 5, 150),
            'compare_at_price' => null,
            'shipping_rate' => null,
            'inventory_quantity' => null,
            'weight_grams' => fake()->numberBetween(100, 2500),
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
