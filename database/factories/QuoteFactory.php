<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'quote_number' => 'Q-'.fake()->unique()->numerify('######'),
            'user_id' => User::query()->value('id') ?? User::factory(),
            'quote_date' => fake()->date(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'line_items' => [],
            'subtotal_amount' => fake()->randomFloat(2, 10, 500),
            'gst_amount' => fake()->randomFloat(2, 1, 50),
            'total_amount' => fake()->randomFloat(2, 11, 550),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
