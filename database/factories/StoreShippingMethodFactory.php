<?php

namespace Database\Factories;

use App\Models\StoreShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreShippingMethodFactory extends Factory
{
    protected $model = StoreShippingMethod::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('method-###'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'shipment_label' => 'Shipment',
            'immediate_status_label' => 'Ships now',
            'delayed_status_label' => 'Ships later',
            'calculator' => StoreShippingMethod::CALCULATOR_PACKAGES,
            'flat_rate_amount' => null,
            'delivery_estimate_min_days' => 1,
            'delivery_estimate_max_days' => 3,
            'rate_multiplier' => 1,
            'rate_adjustment_amount' => 0,
            'is_pickup' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
