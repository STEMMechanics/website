<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $publishDate = Carbon::parse($this->faker->dateTimeBetween('-1 month', '+1 month'));

        return [
            'title' => $this->faker->sentence(),
            'slug' => $this->faker->slug(),
            'publish_at' => $publishDate,
            'content' => $this->faker->paragraphs(3, true),
            'user_id' => $this->faker->uuid,
            'hero' => $this->faker->uuid,
        ];
    }
}
