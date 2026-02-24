<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'created_by' => User::query()->value('id') ?? User::factory(),
            'supplier' => fake()->company(),
            'description' => fake()->sentence(),
            'paid_on' => fake()->date(),
            'total_amount' => fake()->randomFloat(2, 1, 1000),
            'gst_amount' => fake()->randomFloat(2, 0, 100),
            'receipt_document_path' => null,
            'receipt_document_name' => null,
        ];
    }
}
