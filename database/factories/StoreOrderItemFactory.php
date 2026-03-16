<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreOrderItemFactory extends Factory
{
    protected $model = StoreOrderItem::class;

    public function definition(): array
    {
        return [
            'store_order_id' => StoreOrder::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'invoice_line_id' => null,
            'product_title' => fake()->words(2, true),
            'product_slug' => fake()->slug(),
            'variant_name' => null,
            'product_sku' => 'SKU-'.fake()->numerify('####'),
            'variant_sku' => null,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'delayed_fulfilment_type' => null,
            'delayed_shipping_estimate' => null,
            'inventory_reserved_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
            'unit_price' => 24.95,
            'unit_shipping_rate' => 8,
            'tax_rate' => 0.1000,
            'unit_weight_grams' => 800,
            'unit_length_cm' => 25,
            'unit_width_cm' => 15,
            'unit_height_cm' => 8,
            'line_price_amount' => 24.95,
            'line_shipping_amount' => 8,
            'line_gst_amount' => 3,
            'line_total_amount' => 32.95,
        ];
    }
}
