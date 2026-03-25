<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreOrderFactory extends Factory
{
    protected $model = StoreOrder::class;

    public function definition(): array
    {
        return [
            'order_number' => (string) fake()->unique()->numberBetween(1000, 999999),
            'access_token' => Str::random(40),
            'user_id' => User::query()->value('id') ?? User::factory(),
            'invoice_id' => Invoice::factory(),
            'quote_id' => null,
            'coupon_id' => null,
            'status' => StoreOrder::STATUS_PENDING_PAYMENT,
            'contains_digital' => false,
            'contains_physical' => true,
            'billing_name' => fake()->name(),
            'billing_email' => fake()->safeEmail(),
            'billing_phone' => fake()->phoneNumber(),
            'billing_company' => null,
            'shipping_name' => fake()->name(),
            'shipping_phone' => fake()->phoneNumber(),
            'shipping_address' => fake()->streetAddress(),
            'shipping_address2' => null,
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->stateAbbr(),
            'shipping_postcode' => fake()->postcode(),
            'shipping_country' => 'Australia',
            'shipping_method' => 'Standard Post',
            'shipping_zone' => 'AU',
            'shipping_chargeable_weight_grams' => 1500,
            'coupon_code' => null,
            'coupon_type' => null,
            'notes' => null,
            'subtotal_amount' => 40,
            'shipping_amount' => 12,
            'discount_amount' => 0,
            'gst_amount' => 4.73,
            'total_amount' => 52,
            'paid_at' => null,
            'fulfilled_at' => null,
            'order_confirmation_emailed_at' => null,
            'order_paid_emailed_at' => null,
        ];
    }
}
