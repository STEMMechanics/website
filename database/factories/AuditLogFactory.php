<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event' => fake()->randomElement(['created', 'updated', 'deleted', 'login']),
            'auditable_type' => User::class,
            'auditable_id' => (string) (User::query()->value('id') ?? User::factory()->create()->id),
            'actor_user_id' => User::query()->value('id') ?? User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => fake()->url(),
            'old_values' => null,
            'new_values' => ['example' => true],
        ];
    }
}
