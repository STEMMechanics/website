<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'description' => fake()->sentence(),
            'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => fake()->randomFloat(2, 5, 25),
            'minimum_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'applies_to_products' => true,
            'applies_to_workshops' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
