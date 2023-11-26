<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workshop>
 */
class WorkshopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::parse($this->faker->dateTimeBetween('now', '+1 year'));
        $endDate = Carbon::parse($this->faker->dateTimeBetween($startDate, '+1 year'));
        $publishDate = Carbon::parse($this->faker->dateTimeBetween('-1 month', '+1 month'));

        return [
            'title' => $this->faker->sentence(),
            'start_at' => $startDate,
            'end_at' => $endDate,
            'publish_at' => $publishDate,
            'status' => $this->faker->randomElement(['draft', 'soon', 'open', 'closed', 'cancelled']),
            'content' => $this->faker->paragraphs(3, true),
        ];
    }
}
