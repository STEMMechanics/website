<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $userId = $this->resolveUserId();
        $workshopId = $this->resolveWorkshopId($userId);

        return [
            'status' => Ticket::STATUS_PAID,
            'user_id' => $userId,
            'workshop_id' => $workshopId,
            'invoice_id' => null,
            'invoice_line_id' => null,
            'firstname' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'attended_at' => null,
            'reissued_to_ticket_id' => null,
            'reissued_from_ticket_id' => null,
        ];
    }

    private function resolveUserId(): string
    {
        $existingUserId = User::query()->value('id');
        if (is_string($existingUserId) && $existingUserId !== '') {
            return $existingUserId;
        }

        return (string) User::factory()->create()->id;
    }

    private function resolveWorkshopId(string $userId): string
    {
        $existingWorkshopId = Workshop::query()->value('id');
        if (is_string($existingWorkshopId) && $existingWorkshopId !== '') {
            return $existingWorkshopId;
        }

        $locationId = $this->resolveLocationId();
        $heroMediaName = $this->resolveHeroMediaName($userId);
        $startsAt = now()->addDays(3);

        $workshop = Workshop::query()->create([
            'title' => fake()->sentence(4),
            'hero_media_name' => $heroMediaName,
            'content' => '<p>'.fake()->sentence(8).'</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHours(2),
            'status' => 'open',
            'registration' => 'none',
            'registration_data' => null,
            'location_id' => $locationId,
            'user_id' => $userId,
        ]);

        return (string) $workshop->id;
    }

    private function resolveLocationId(): string
    {
        $existingLocationId = Location::query()->value('id');
        if (is_string($existingLocationId) && $existingLocationId !== '') {
            return $existingLocationId;
        }

        return (string) Location::factory()->create()->id;
    }

    private function resolveHeroMediaName(string $userId): string
    {
        $existingMediaName = Media::query()->value('name');
        if (is_string($existingMediaName) && $existingMediaName !== '') {
            return $existingMediaName;
        }

        $name = strtolower(fake()->bothify('hero-######')).'.png';

        Media::query()->create([
            'name' => $name,
            'title' => 'Factory Hero',
            'mime_type' => 'image/png',
            'size' => 2048,
            'hash' => str_repeat('a', 64),
            'user_id' => $userId,
        ]);

        return $name;
    }
}
