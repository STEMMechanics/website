<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . '.' . $this->faker->fileExtension(),
            'title' => $this->faker->sentence,
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(1000, 1000000),
            'user_id' => $this->faker->uuid,
        ];
    }
}
