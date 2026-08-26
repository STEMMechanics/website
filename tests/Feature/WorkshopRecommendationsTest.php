<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Workshop;
use App\Services\WorkshopRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_prefer_the_same_organisation_family_over_the_same_suburb(): void
    {
        $user = User::factory()->create();
        $council = Organisation::factory()->create(['name' => 'Regional Council', 'parent_id' => null]);
        $libraryA = Organisation::factory()->create(['name' => 'North Library', 'parent_id' => $council->id]);
        $libraryB = Organisation::factory()->create(['name' => 'South Library', 'parent_id' => $council->id]);
        $otherCouncil = Organisation::factory()->create(['name' => 'Other Council']);
        $north = Location::factory()->create(['suburb' => 'Northville']);
        $south = Location::factory()->create(['suburb' => 'Southville']);

        $source = $this->workshop($user, $north, $libraryA, 'Source workshop', now()->addDay());
        $sameFamily = $this->workshop($user, $south, $libraryB, 'Same council workshop', now()->addDays(4));
        $sameSuburb = $this->workshop($user, $north, $otherCouncil, 'Same suburb workshop', now()->addDays(2));

        $results = app(WorkshopRecommendationService::class)->forWorkshop($source);

        $this->assertSame($sameFamily->id, $results->first()->id);
        $this->assertSame($sameSuburb->id, $results->get(1)->id);
    }

    public function test_suburb_page_and_recommendation_tracking_are_available(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['suburb' => 'Redlynch', 'state' => 'QLD']);
        $organisation = Organisation::factory()->create();
        $source = $this->workshop($user, $location, $organisation, 'Robotics at Redlynch', now()->addDay());
        $recommended = $this->workshop($user, $location, $organisation, 'Engineering at Redlynch', now()->addDays(2));

        $this->get(route('workshop.suburb', 'redlynch'))
            ->assertOk()
            ->assertSee('STEM Workshops in Redlynch')
            ->assertSee('Robotics at Redlynch');

        $this->postJson(route('workshop.recommendation.impression'), [
            'source_workshop_id' => $source->id,
            'workshop_ids' => [$recommended->id],
            'placement' => 'workshop',
        ])->assertOk();

        $this->get(route('workshop.recommendation.click', [
            'source' => $source,
            'workshop' => $recommended,
            'placement' => 'workshop',
        ]))->assertRedirect(route('workshop.show', $recommended));

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => AnalyticsEvent::TYPE_RECOMMENDATION_IMPRESSION,
            'source_workshop_id' => $source->id,
            'workshop_id' => $recommended->id,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_type' => AnalyticsEvent::TYPE_RECOMMENDATION_CLICK,
            'source_workshop_id' => $source->id,
            'workshop_id' => $recommended->id,
        ]);
    }

    private function workshop(User $user, Location $location, Organisation $organisation, string $title, $startsAt): Workshop
    {
        $hero = Media::query()->firstOrCreate(['name' => 'recommendation-test.jpg'], [
            'title' => 'Recommendation test image',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'user_id' => $user->id,
        ]);

        return Workshop::query()->create([
            'title' => $title,
            'content' => '<p>Workshop details.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'status' => 'open',
            'registration' => 'none',
            'type' => Workshop::TYPE_PHYSICAL,
            'is_private' => false,
            'is_hidden' => false,
            'location_id' => $location->id,
            'hosted_for_organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'hero_media_name' => $hero->name,
        ]);
    }
}
