<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\TaxAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxAdjustmentFactory extends Factory
{
    protected $model = TaxAdjustment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'adjustment_number' => 'TA-'.fake()->unique()->numerify('######'),
            'issue_date' => fake()->optional()->date(),
            'subtotal_amount' => fake()->randomFloat(2, -200, 0),
            'gst_amount' => fake()->randomFloat(2, -20, 0),
            'total_amount' => fake()->randomFloat(2, -220, 0),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
