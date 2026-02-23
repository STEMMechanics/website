<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBackupCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBackupCodeFactory extends Factory
{
    protected $model = UserBackupCode::class;

    public function definition(): array
    {
        return [
            'user_id' => User::query()->value('id') ?? User::factory(),
            'code' => fake()->regexify('[A-Z0-9]{10}'),
        ];
    }
}
