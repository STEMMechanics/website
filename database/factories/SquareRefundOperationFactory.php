<?php

namespace Database\Factories;

use App\Models\SquareRefundOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

class SquareRefundOperationFactory extends Factory
{
    protected $model = SquareRefundOperation::class;

    public function definition(): array
    {
        return [
            'invoice_id' => null,
            'tax_adjustment_id' => null,
            'ticket_id' => null,
            'payment_id' => null,
            'idempotency_key' => 'sro_'.fake()->unique()->lexify('????????????????'),
            'requested_cents' => fake()->numberBetween(100, 100000),
            'refunded_cents' => 0,
            'square_refund_id' => null,
            'status' => SquareRefundOperation::STATUS_PENDING,
            'failure_message' => null,
            'payload' => null,
            'processed_at' => null,
        ];
    }
}
