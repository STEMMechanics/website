<?php

namespace Database\Factories;

use App\Models\SiteOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteOptionFactory extends Factory
{
    protected $model = SiteOption::class;

    public function definition(): array
    {
        return [
            'name' => 'option_'.fake()->unique()->lexify('??????'),
            'value' => fake()->sentence(),
        ];
    }
}
