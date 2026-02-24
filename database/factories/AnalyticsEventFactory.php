<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => fake()->randomElement([AnalyticsEvent::TYPE_PAGE_VIEW, AnalyticsEvent::TYPE_SEARCH]),
            'session_token' => hash('sha256', (string) fake()->uuid()),
            'visitor_hash' => hash('sha256', (string) fake()->uuid()),
            'path' => '/'.fake()->slug(),
            'route_name' => fake()->optional()->slug(),
            'workshop_id' => Workshop::query()->value('id'),
            'search_term' => fake()->optional()->word(),
            'referrer_host' => fake()->optional()->domainName(),
            'http_method' => 'GET',
            'created_at' => now(),
        ];
    }
}
