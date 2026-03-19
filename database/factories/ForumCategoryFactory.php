<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumCategory>
 */
class ForumCategoryFactory extends Factory
{
    protected $model = ForumCategory::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(fake()->numberBetween(1, 3), true));
        $slug = ForumCategory::normalizeSlug($name);

        if ($slug === '') {
            $slug = fake()->unique()->bothify('category-####');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => fake()->optional()->sentence(),
            'icon_class' => fake()->optional()->randomElement([
                'fa-regular fa-comments',
                'fa-regular fa-lightbulb',
                'fa-solid fa-gear',
            ]),
            'color_hex' => fake()->optional()->passthrough(sprintf('#%06X', fake()->numberBetween(0, 0xFFFFFF))),
            'read_group_slug' => null,
            'write_group_slug' => null,
            'sort_order' => fake()->numberBetween(0, 25),
        ];
    }
}
