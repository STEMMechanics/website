<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'line_number' => 1,
            'kind' => 'generic',
            'description' => fake()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'details_json' => [],
            'quantity' => 1,
            'unit_price_ex_tax' => fake()->randomFloat(2, 1, 100),
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => fake()->randomFloat(2, 1, 100),
            'tax_amount' => fake()->randomFloat(2, 0, 10),
            'line_total_inc_tax' => fake()->randomFloat(2, 1, 110),
            'source_type' => null,
            'source_id' => null,
            'original_invoice_line_id' => null,
        ];
    }
}
