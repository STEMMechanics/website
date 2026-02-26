<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkshopAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkshopAttendanceFactory extends Factory
{
    protected $model = WorkshopAttendance::class;

    public function definition(): array
    {
        return [
            'workshop_id' => function (): string {
                $existingWorkshopId = Ticket::query()->value('workshop_id');

                if (is_string($existingWorkshopId) && $existingWorkshopId !== '') {
                    return $existingWorkshopId;
                }

                return (string) Ticket::factory()->create()->workshop_id;
            },
            'ticket_id' => null,
            'user_id' => User::query()->value('id') ?? User::factory(),
            'created_by' => User::query()->value('id') ?? User::factory(),
            'source' => 'dropin',
            'child_name' => fake()->name(),
            'firstname' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'guardian_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'media_consent' => fake()->boolean(),
            'attended_at' => now(),
        ];
    }
}
