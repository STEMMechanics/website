<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumTopic>
 */
class ForumTopicFactory extends Factory
{
    protected $model = ForumTopic::class;

    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->sentence(fake()->numberBetween(3, 6)));
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = fake()->unique()->bothify('topic-####');
        }

        $userId = User::query()->value('id');

        return [
            'forum_category_id' => ForumCategory::query()->value('id') ?? ForumCategory::factory(),
            'user_id' => $userId ?? User::factory(),
            'last_post_user_id' => $userId ?? User::factory(),
            'title' => $title,
            'slug' => $slug,
            'approved_by_user_id' => null,
            'is_approved' => true,
            'is_locked' => false,
            'is_pinned' => false,
            'view_count' => fake()->numberBetween(0, 250),
            'last_post_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
