<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\SquareWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class SquareWebhookEventFactory extends Factory
{
    protected $model = SquareWebhookEvent::class;

    public function definition(): array
    {
        return [
            'event_id' => 'sqevt_'.fake()->unique()->lexify('????????????'),
            'event_type' => fake()->randomElement(['payment.created', 'payment.updated', 'refund.created']),
            'payment_id' => Payment::query()->value('id'),
            'payload' => ['data' => ['object' => ['id' => fake()->uuid()]]],
            'processed_at' => now(),
        ];
    }
}
