<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkshopTicketEmailFactory extends Factory
{
    protected $model = WorkshopTicketEmail::class;

    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'ticket_ids' => [],
            'invoice_id' => Invoice::factory(),
            'payment_id' => Payment::factory(),
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'amount' => fake()->randomFloat(2, 0, 100),
            'status' => WorkshopTicketEmail::STATUS_PENDING,
            'queued_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ];
    }
}
