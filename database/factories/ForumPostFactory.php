<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    public function definition(): array
    {
        return [
            'forum_topic_id' => ForumTopic::query()->value('id') ?? ForumTopic::factory(),
            'parent_forum_post_id' => null,
            'user_id' => User::query()->value('id') ?? User::factory(),
            'approved_by_user_id' => null,
            'is_approved' => true,
            'body' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
            'edited_at' => null,
            'deleted_at' => null,
        ];
    }
}
