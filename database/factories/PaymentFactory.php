<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'kind' => Payment::KIND_PAYMENT,
            'refund_of_payment_id' => null,
            'user_id' => User::query()->value('id') ?? User::factory(),
            'created_by' => User::query()->value('id') ?? User::factory(),
            'received_on' => now(),
            'payment_method' => fake()->randomElement(Payment::PAYMENT_METHODS),
            'reference' => fake()->optional()->bothify('REF-####'),
            'total_amount' => fake()->randomFloat(2, 1, 1000),
            'gst_amount' => fake()->randomFloat(2, 0, 100),
            'notes' => fake()->optional()->sentence(),
            'gateway_provider' => null,
            'gateway_status' => null,
            'gateway_reference_id' => null,
            'square_payment_id' => null,
            'square_order_id' => null,
            'square_location_id' => null,
            'square_receipt_url' => null,
            'square_card_brand' => null,
            'square_card_last4' => null,
            'square_paid_money_amount' => null,
            'square_refunded_money_amount' => 0,
            'square_gateway_created_at' => null,
            'square_gateway_updated_at' => null,
            'square_last_event_type' => null,
            'square_last_event_id' => null,
            'square_last_event_at' => null,
            'square_webhook_payload' => null,
        ];
    }
}
