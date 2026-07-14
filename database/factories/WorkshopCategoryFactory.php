<?php

namespace Database\Factories;

use App\Models\WorkshopCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkshopCategory>
 */
class WorkshopCategoryFactory extends Factory
{
    protected $model = WorkshopCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name),
            'icon_class' => 'fa-solid fa-tag',
            'hide_in_footer' => false,
        ];
    }
}
