<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
            'name' => storage_path('app/public/') . $this->faker->slug() . '.' . $this->faker->fileExtension,
            'mime_type' => $this->faker->mimeType,
            'user_id' => $this->faker->uuid,
            'size' => $this->faker->numberBetween(1000, 1000000)
        ];
    }
}
