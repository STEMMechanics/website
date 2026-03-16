<?php

namespace Tests\Feature;

use App\Mail\UpcomingWorkshops;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopVisibilityRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_workshops_are_excluded_from_public_index_search_and_upcoming_email(): void
    {
        $hiddenWorkshop = $this->createWorkshop(
            title: 'Hidden Robotics Session',
            status: 'open',
            isHidden: true,
            publishAt: now()->subDay()
        );

        $indexResponse = $this->get(route('workshop.index'));
        $indexResponse->assertOk();
        $indexResponse->assertViewHas('workshops', function ($workshops) use ($hiddenWorkshop) {
            return ! collect($workshops->items())->contains(fn (Workshop $workshop) => $workshop->id === $hiddenWorkshop->id);
        });

        $searchResponse = $this->get(route('search.index', ['q' => 'Robotics']));
        $searchResponse->assertOk();
        $searchResponse->assertViewHas('workshops', function ($workshops) use ($hiddenWorkshop) {
            return ! collect($workshops->items())->contains(fn (Workshop $workshop) => $workshop->id === $hiddenWorkshop->id);
        });

        $mailable = new UpcomingWorkshops('tester@example.com');
        $this->assertFalse(
            $mailable->workshops->contains(fn (Workshop $workshop) => $workshop->id === $hiddenWorkshop->id)
        );
    }

    public function test_hidden_workshop_is_accessible_by_direct_url_even_before_publish_date(): void
    {
        $hiddenWorkshop = $this->createWorkshop(
            title: 'Hidden Direct Access Workshop',
            status: 'open',
            isHidden: true,
            publishAt: now()->addDays(3)
        );

        $this->get(route('workshop.show', $hiddenWorkshop))
            ->assertOk()
            ->assertSee('Hidden Direct Access Workshop')
            ->assertSee('meta name="robots" content="noindex, nofollow"', false);
    }

    public function test_non_hidden_workshop_with_future_publish_date_is_not_accessible_by_direct_url(): void
    {
        $workshop = $this->createWorkshop(
            title: 'Future Published Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->addDays(2)
        );

        $this->get(route('workshop.show', $workshop))
            ->assertNotFound();
    }

    public function test_workshop_show_redirects_non_canonical_slugs_to_the_canonical_url(): void
    {
        $workshop = $this->createWorkshop(
            title: 'Canonical Robotics Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay()
        );

        $this->get('/workshops/old-title-'.$workshop->id)
            ->assertRedirect(route('workshop.show', $workshop));
    }

    private function createWorkshop(string $title, string $status, bool $isHidden, \DateTimeInterface $publishAt): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create(['name' => 'City Lab']);
        $heroName = 'hero-'.strtolower((string) str()->random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('a', 64),
            'mime_type' => 'image/png',
            'size' => 2048,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => $title,
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'publish_at' => $publishAt,
            'closes_at' => now()->addDays(9),
            'status' => $status,
            'is_hidden' => $isHidden,
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }
}
