<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => '<p>' . implode('</p><p>', fake()->paragraphs()) . '</p>',
            'user_id' => 1,
            'status' => 'published',
            'published_at' => now(),
            'hero_media_name' => 'stemmechanics-logo.png'
        ];
    }
}
