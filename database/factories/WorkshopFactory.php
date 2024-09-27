<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Workshop;
use DateInterval;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkshopFactory extends Factory
{
    protected $model = Workshop::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+1 year');

        return [
            'title' => fake()->sentence(),
            'content' => '<p>' . implode('</p><p>', fake()->paragraphs()) . '</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->add(DateInterval::createFromDateString('2 hours')),
            'publish_at' => now(),
            'closes_at' => $startsAt->sub(DateInterval::createFromDateString('2 hours')),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => Location::all()->random()->id,
            'user_id' => 1,
            'hero_media_name' => 'stemmechanics-logo.png'
        ];
    }
}
