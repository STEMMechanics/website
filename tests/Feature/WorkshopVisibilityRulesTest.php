<?php

namespace Tests\Feature;

use App\Mail\UpcomingWorkshops;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_private_workshops_are_excluded_from_upcoming_email(): void
    {
        $privateWorkshop = $this->createWorkshop(
            title: 'Private Robotics Session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            isPrivate: true
        );

        $mailable = new UpcomingWorkshops('tester@example.com');

        $this->assertFalse(
            $mailable->workshops->contains(fn (Workshop $workshop) => $workshop->id === $privateWorkshop->id)
        );
    }

    public function test_upcoming_workshops_mailable_splits_online_workshops_and_courses(): void
    {
        config()->set('newsletter.upcoming_workshops.hero_messages', [[
            'header' => 'Test header copy',
            'cta' => 'Test CTA copy',
        ]]);
        config()->set('newsletter.upcoming_workshops.button_label', 'Browse Workshop List');

        $owner = User::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 2048,
            'user_id' => $owner->id,
        ]);

        $onlineWorkshop = Workshop::query()->create([
            'title' => 'Online Robotics Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(9),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'tickets',
            'location_id' => null,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $physicalWorkshop = Workshop::query()->create([
            'title' => 'City Lab Robotics Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(13),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'tickets',
            'location_id' => Location::factory()->create(['name' => 'City Lab'])->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $earlierWorkshop = Workshop::query()->create([
            'title' => 'North Hall Robotics Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(6),
            'ends_at' => now()->addDays(6)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'tickets',
            'location_id' => Location::factory()->create(['name' => 'North Hall'])->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $courseWorkshop = Workshop::query()->create([
            'title' => 'Weekly Online Course',
            'content' => '<p>Course content</p>',
            'starts_at' => now()->addDays(12)->setTime(17, 30),
            'ends_at' => now()->addDays(12)->addHour(),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(11),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'classroom',
            'location_id' => null,
            'classroom_sessions_json' => [
                [
                    'starts_at' => now()->addDays(12)->setTime(17, 30)->toDateTimeString(),
                    'ends_at' => now()->addDays(12)->setTime(18, 30)->toDateTimeString(),
                    'label' => 'Week 1',
                ],
                [
                    'starts_at' => now()->addDays(19)->setTime(17, 30)->toDateTimeString(),
                    'ends_at' => now()->addDays(19)->setTime(18, 30)->toDateTimeString(),
                    'label' => 'Week 2',
                ],
            ],
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);

        $mailable = new UpcomingWorkshops('tester@example.com');
        $rendered = $mailable->render();

        $this->assertTrue(
            $mailable->onlineWorkshops->contains(fn (Workshop $workshop) => $workshop->id === $onlineWorkshop->id)
        );
        $this->assertTrue(
            $mailable->workshops->contains(fn (Workshop $workshop) => $workshop->id === $physicalWorkshop->id)
        );
        $this->assertTrue(
            $mailable->workshops->contains(fn (Workshop $workshop) => $workshop->id === $earlierWorkshop->id)
        );
        $this->assertTrue(
            $mailable->courses->contains(fn (Workshop $workshop) => $workshop->id === $courseWorkshop->id)
        );
        $this->assertStringNotContainsString('&lt;h2', $rendered);
        $this->assertStringNotContainsString('&lt;p', $rendered);
        $this->assertStringNotContainsString('Here’s a fresher look', $rendered);
        $this->assertStringContainsString('logo-dark.svg', $rendered);
        $this->assertStringContainsString('Test header copy', $rendered);
        $this->assertStringContainsString('Test CTA copy', $rendered);
        $this->assertStringContainsString('Browse Workshop List', $rendered);
        $this->assertStringContainsString('City Lab Robotics Workshop', $rendered);
        $this->assertStringContainsString('North Hall Robotics Workshop', $rendered);
        $this->assertStringContainsString('Weekly Online Course', $rendered);
        $this->assertStringContainsString('Online - weekly', $rendered);
        $this->assertStringContainsString('Get Tickets', $rendered);
        $this->assertStringContainsString('Enrol Now', $rendered);
        $this->assertGreaterThanOrEqual(5, substr_count($rendered, '?md'));
        $mainSectionPos = strpos($rendered, 'Test CTA copy');
        $northPos = strpos($rendered, 'North Hall Robotics Workshop', $mainSectionPos);
        $onlinePos = strpos($rendered, 'Online Robotics Workshop', $mainSectionPos);
        $coursePos = strpos($rendered, 'Weekly Online Course', $mainSectionPos);
        $cityPos = strpos($rendered, 'City Lab Robotics Workshop', $mainSectionPos);

        $this->assertLessThan($onlinePos, $northPos);
        $this->assertLessThan($coursePos, $onlinePos);
        $this->assertLessThan($cityPos, $coursePos);
    }

    public function test_upcoming_workshop_cards_use_summary_when_present_and_fall_back_to_description_when_missing(): void
    {
        config()->set('newsletter.upcoming_workshops.hero_messages', [[
            'header' => 'Test header copy',
            'cta' => 'Test CTA copy',
        ]]);

        $summaryWorkshop = $this->createWorkshop(
            title: 'Custom Summary Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            summary: 'Custom newsletter summary',
            content: '<p>Custom content that should not be used.</p>'
        );

        $generatedWorkshop = $this->createWorkshop(
            title: 'Generated Summary Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            summary: null,
            content: '<p>Generated summary from workshop description.</p>'
        );

        $rendered = (new UpcomingWorkshops('tester@example.com'))->render();

        $this->assertStringContainsString('Custom newsletter summary', $rendered);
        $this->assertStringContainsString('Generated summary from workshop description.', $rendered);
        $this->assertTrue($summaryWorkshop->isPubliclyVisible());
        $this->assertTrue($generatedWorkshop->isPubliclyVisible());
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

    public function test_underage_warning_is_hidden_for_online_workshops(): void
    {
        $onlineWorkshop = $this->createWorkshop(
            title: 'Online Coding Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            ages: '8+',
            isOnline: true
        );

        $this->get(route('workshop.show', $onlineWorkshop))
            ->assertOk()
            ->assertDontSee('Parental supervision may be required for children 8 years of age and under.');
    }

    private function createWorkshop(
        string $title,
        string $status,
        bool $isHidden,
        \DateTimeInterface $publishAt,
        bool $isPrivate = false,
        ?string $ages = '8+',
        ?string $summary = null,
        ?string $content = null,
        bool $isOnline = false
    ): Workshop
    {
        $owner = User::factory()->create();
        $location = $isOnline ? null : Location::factory()->create(['name' => 'City Lab']);
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
            'content' => $content ?? '<p>Workshop content</p>',
            'summary' => $summary,
            'ages' => $ages,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'publish_at' => $publishAt,
            'closes_at' => now()->addDays(9),
            'status' => $status,
            'is_private' => $isPrivate,
            'is_hidden' => $isHidden,
            'registration' => 'none',
            'location_id' => $location?->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }
}
