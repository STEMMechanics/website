<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoicePaymentAllocationFactory extends Factory
{
    protected $model = InvoicePaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'tax_adjustment_id' => null,
            'allocated_amount' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
