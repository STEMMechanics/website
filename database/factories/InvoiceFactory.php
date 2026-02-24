<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'user_id' => User::query()->value('id') ?? User::factory(),
            'billing_name' => fake()->name(),
            'billing_email' => fake()->safeEmail(),
            'billing_phone' => fake()->phoneNumber(),
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => fake()->date(),
            'issued_at' => null,
            'due_date' => fake()->optional()->date(),
            'purchase_order_number' => fake()->optional()->bothify('PO-####'),
            'subtotal_amount' => fake()->randomFloat(2, 10, 1000),
            'gst_amount' => fake()->randomFloat(2, 1, 100),
            'total_amount' => fake()->randomFloat(2, 11, 1100),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
