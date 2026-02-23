<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserGroupFactory extends Factory
{
    protected $model = UserGroup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::query()->value('id') ?? User::factory(),
            'slug' => 'group_'.fake()->unique()->lexify('????'),
        ];
    }
}
