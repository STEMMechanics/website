<?php

namespace Database\Factories;

use App\Models\TaxAdjustment;
use App\Models\TaxAdjustmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxAdjustmentLineFactory extends Factory
{
    protected $model = TaxAdjustmentLine::class;

    public function definition(): array
    {
        return [
            'tax_adjustment_id' => TaxAdjustment::factory(),
            'invoice_line_id' => null,
            'line_number' => 1,
            'description' => fake()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'quantity' => 1,
            'unit_price_ex_tax' => fake()->randomFloat(2, 1, 100),
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => fake()->randomFloat(2, 1, 100),
            'tax_amount' => fake()->randomFloat(2, 0, 10),
            'line_total_inc_tax' => fake()->randomFloat(2, 1, 110),
        ];
    }
}
