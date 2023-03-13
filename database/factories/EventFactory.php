<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $startDate = Carbon::parse($this->faker->dateTimeBetween('now', '+1 year'));
        $endDate = Carbon::parse($this->faker->dateTimeBetween($startDate, '+1 year'));
        $publishDate = Carbon::parse($this->faker->dateTimeBetween('-1 month', '+1 month'));

        return [
            'title' => $this->faker->sentence(),
            'location' => $this->faker->randomElement(['online', 'physical']),
            'address' => $this->faker->address,
            'start_at' => $startDate,
            'end_at' => $endDate,
            'publish_at' => $publishDate,
            'status' => $this->faker->randomElement(['draft', 'soon', 'open', 'closed', 'cancelled']),
            'registration_type' => $this->faker->randomElement(['none', 'email', 'link', 'message']),
            'registration_data' => $this->faker->sentence(),
            'hero' => $this->faker->uuid,
            'content' => $this->faker->paragraphs(3, true),
            'price' => $this->faker->numberBetween(0, 150),
            'ages' => $this->faker->regexify('\d+(\+|\-\d+)?'),
        ];
    }
}
