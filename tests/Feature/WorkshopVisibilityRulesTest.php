<?php

namespace Tests\Feature;

use App\Mail\UpcomingWorkshops;
use App\Models\Location;
use App\Models\Media;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_public_workshop_page_exposes_an_rss_feed_and_feed_excludes_hidden_items(): void
    {
        $publicWorkshop = $this->createWorkshop(
            title: 'Public Robotics Session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            ages: '9-12',
            summary: 'A public workshop for the feed.',
            price: '27.5'
        );

        $hiddenWorkshop = $this->createWorkshop(
            title: 'Hidden RSS Session',
            status: 'open',
            isHidden: true,
            publishAt: now()->subDay()
        );

        $draftWorkshop = $this->createWorkshop(
            title: 'Draft RSS Session',
            status: 'draft',
            isHidden: false,
            publishAt: now()->subDay()
        );

        $indexResponse = $this->get(route('workshop.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('workshop.feed'));

        $feedResponse = $this->get(route('workshop.feed'));
        $feedResponse->assertOk();
        $feedResponse->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $feedResponse->assertSee('Public Robotics Session');
        $feedResponse->assertSee(route('workshop.show', $publicWorkshop));
        $feedResponse->assertSee($publicWorkshop->hero?->url('md'), false);
        $feedResponse->assertSee('type="image/png"', false);
        $feedResponse->assertSee('<sm:startDate>', false);
        $feedResponse->assertSee('<sm:endDate>', false);
        $feedResponse->assertSee($publicWorkshop->getPublicLocationLabel(), false);
        $feedResponse->assertSee('<sm:price>27.50</sm:price>', false);
        $feedResponse->assertSee('<sm:ages>9-12</sm:ages>', false);
        $feedResponse->assertSee('<sm:status>Open</sm:status>', false);
        $feedResponse->assertDontSee('Hidden RSS Session');
        $feedResponse->assertDontSee('Draft RSS Session');
        $feedResponse->assertSee('STEMMechanics Workshops');

        $this->assertTrue($publicWorkshop->isPubliclyVisible());
        $this->assertTrue($hiddenWorkshop->isPubliclyVisible());
        $this->assertFalse($draftWorkshop->isPubliclyVisible());
    }

    public function test_public_workshop_feed_hides_private_addresses_and_shows_hosted_for_labels(): void
    {
        $privateVenue = Location::query()->create([
            'name' => 'Secret Venue',
            'address' => '88 Secret Lane',
            'address_url' => 'https://example.com/secret-venue',
        ]);

        $privateWorkshop = $this->createWorkshop(
            title: 'Private feed session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            isPrivate: true
        );

        $privateWorkshop->update([
            'location_id' => $privateVenue->id,
            'hosted_for' => 'Primary School Robotics Club',
        ]);

        $feedResponse = $this->get(route('workshop.feed'));

        $feedResponse->assertOk();
        $feedResponse->assertSee('Primary School Robotics Club', false);
        $feedResponse->assertDontSee('88 Secret Lane');
        $feedResponse->assertDontSee('Secret Venue');
    }

    public function test_public_workshop_index_defaults_to_cards_and_exposes_a_calendar_toggle(): void
    {
        $publicWorkshop = $this->createWorkshop(
            title: 'Public Robotics Session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay()
        );

        $response = $this->get(route('workshop.index'));

        $response->assertOk();
        $response->assertSee('Public Robotics Session');
        $response->assertSee(route('workshop.feed'));
        $response->assertSee('title="Calendar view"', false);
        $response->assertDontSee('title="Card view"', false);
        $response->assertSee(route('workshop.index', [
            'view' => 'calendar',
            'month' => now()->format('Y-m'),
        ]));

        $this->assertTrue($publicWorkshop->isPubliclyVisible());
    }

    public function test_public_workshop_index_cards_show_a_compact_early_bird_badge(): void
    {
        $publicWorkshop = $this->createWorkshop(
            title: 'Public Early Bird Session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            price: '20.00'
        );
        $publicWorkshop->update([
            'early_bird_price' => '15.00',
            'early_bird_ticket_limit' => 3,
            'early_bird_ends_at' => now()->addDay(),
        ]);

        $response = $this->get(route('workshop.index'));

        $response->assertOk();
        $response->assertSee('Public Early Bird Session');
        $response->assertSee('Early Bird', false);
        $response->assertDontSee('Save $5.00 with earlybird pricing', false);
    }

    public function test_public_workshop_index_calendar_view_groups_workshops_and_shows_month_navigation(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 12, 0, 0, config('app.timezone')));

        try {
            $monthStart = now()->startOfMonth()->addDays(10);
            $nextMonthStart = now()->startOfMonth()->addMonthNoOverflow()->addDays(8);

            $this->createWorkshop(
                title: 'Workshop this month',
                status: 'open',
                isHidden: false,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $monthStart,
                'ends_at' => $monthStart->copy()->addHours(2),
            ]);

            $this->createWorkshop(
                title: 'Cancelled workshop',
                status: 'cancelled',
                isHidden: false,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $monthStart->copy()->addDays(1),
                'ends_at' => $monthStart->copy()->addDays(1)->addHours(2),
            ]);

            $this->createWorkshop(
                title: 'Workshop next month',
                status: 'open',
                isHidden: false,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $nextMonthStart,
                'ends_at' => $nextMonthStart->copy()->addHours(2),
            ]);

            $this->createWorkshop(
                title: 'Hidden calendar session',
                status: 'open',
                isHidden: true,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $monthStart->copy()->addDays(2),
                'ends_at' => $monthStart->copy()->addDays(2)->addHours(2),
            ]);

            $this->createWorkshop(
                title: 'Draft calendar session',
                status: 'draft',
                isHidden: false,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $monthStart->copy()->addDays(3),
                'ends_at' => $monthStart->copy()->addDays(3)->addHours(2),
            ]);

            $response = $this->get(route('workshop.index', [
                'view' => 'calendar',
                'month' => $monthStart->format('Y-m'),
            ]));

            $response->assertOk();
            $response->assertSee($monthStart->format('F Y'));
            $response->assertSeeInOrder(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
            $response->assertSee(route('workshop.index'));
            $response->assertSee('title="Card view"', false);
            $response->assertSee('title="Previous month"', false);
            $response->assertSee('title="Next month"', false);
            $response->assertSee('Workshop this month');
            $response->assertSee('Open');
            $response->assertSee('sm-banner-open', false);
            $response->assertSee('Cancelled workshop');
            $response->assertSee('Canc.', false);
            $response->assertSee('title="Cancelled"', false);
            $response->assertDontSee('Workshop next month');
            $response->assertDontSee('Hidden calendar session');
            $response->assertDontSee('Draft calendar session');
            $response->assertDontSee(route('workshop.past.index', [
                'view' => 'calendar',
                'month' => $monthStart->format('Y-m'),
            ]));
            $response->assertSee(route('workshop.index', [
                'view' => 'calendar',
                'month' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
            ]));
            $response->assertSee(route('workshop.index', [
                'view' => 'calendar',
                'month' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
            ]));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_workshop_index_calendar_view_shades_configured_school_holiday_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 12, 0, 0, config('app.timezone')));

        try {
            $holidayStart = Carbon::create(2026, 6, 27, 0, 0, 0, config('app.timezone'));
            $holidayEnd = Carbon::create(2026, 7, 12, 0, 0, 0, config('app.timezone'));
            SiteOption::query()->updateOrCreate(
                ['name' => 'workshops.school-holidays'],
                ['value' => $holidayStart->format('Y-m-d').' to '.$holidayEnd->format('Y-m-d')]
            );
            SiteOption::query()->updateOrCreate(
                ['name' => 'workshops.school-holidays-label'],
                ['value' => 'Term break']
            );

            $workshopStart = $holidayStart->copy()->addDays(3)->setTime(9, 30);
            $this->createWorkshop(
                title: 'Public holiday workshop',
                status: 'open',
                isHidden: false,
                publishAt: now()->subDay()
            )->update([
                'starts_at' => $workshopStart,
                'ends_at' => $workshopStart->copy()->addHours(2),
            ]);

            $response = $this->get(route('workshop.index', [
                'view' => 'calendar',
                'month' => $holidayStart->format('Y-m'),
            ]));

            $response->assertOk();
            $response->assertSee('Term break');
            $response->assertSee('bg-amber-50', false);
            $response->assertSee('ring-amber-200', false);
            $response->assertSee('Public holiday workshop');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_workshop_index_calendar_view_hides_private_addresses_but_shows_hosted_for_labels(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 12, 0, 0, config('app.timezone')));

        try {
            $monthStart = now()->startOfMonth()->addDays(10);
            $privateVenue = Location::query()->create([
                'name' => 'Secret Venue',
                'address' => '88 Secret Lane',
                'address_url' => 'https://example.com/secret-venue',
            ]);

            $workshop = $this->createWorkshop(
                title: 'Private robotics session',
                status: 'open',
                isHidden: false,
                publishAt: now()->subDay(),
                isPrivate: true
            );

            $workshop->update([
                'starts_at' => $monthStart,
                'ends_at' => $monthStart->copy()->addHours(2),
                'location_id' => $privateVenue->id,
                'hosted_for' => 'Primary School Robotics Club',
            ]);

            $response = $this->get(route('workshop.index', [
                'view' => 'calendar',
                'month' => $monthStart->format('Y-m'),
            ]));

            $response->assertOk();
            $response->assertSee('Primary School Robotics Club');
            $response->assertDontSee('88 Secret Lane');
            $response->assertDontSee('Secret Venue');
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_upcoming_workshops_mailable_splits_online_and_physical_workshops(): void
    {
        config()->set('newsletter.upcoming_workshops.hero_messages', [[
            'header' => 'Test header copy',
            'cta' => 'Test CTA copy',
            'subject' => 'Test subject copy',
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

        $laterOnlineWorkshop = Workshop::query()->create([
            'title' => 'Weekly Online Course',
            'content' => '<p>Course content</p>',
            'starts_at' => now()->addDays(12)->setTime(17, 30),
            'ends_at' => now()->addDays(12)->addHour(),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(11),
            'status' => 'open',
            'price' => 'Free',
            'registration' => 'tickets',
            'location_id' => null,
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
            $mailable->onlineWorkshops->contains(fn (Workshop $workshop) => $workshop->id === $laterOnlineWorkshop->id)
        );
        $this->assertStringNotContainsString('&lt;h2', $rendered);
        $this->assertStringNotContainsString('&lt;p', $rendered);
        $this->assertStringNotContainsString('Here’s a fresher look', $rendered);
        $this->assertStringContainsString('logo-dark.png', $rendered);
        $this->assertStringContainsString('Test header copy', $rendered);
        $this->assertStringContainsString('Test CTA copy', $rendered);
        $this->assertSame('Test subject copy', $mailable->subject);
        $this->assertStringContainsString('Browse Workshop List', $rendered);
        $this->assertStringContainsString('City Lab Robotics Workshop', $rendered);
        $this->assertStringContainsString('North Hall Robotics Workshop', $rendered);
        $this->assertStringContainsString('Weekly Online Course', $rendered);
        $this->assertSame(3, substr_count($rendered, '(2 hours)'));
        $this->assertStringContainsString('Get Tickets', $rendered);
        $this->assertGreaterThanOrEqual(5, substr_count($rendered, '?md'));
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

    public function test_ticketed_workshops_include_event_offers_even_when_the_price_is_written_as_free(): void
    {
        $workshop = $this->createWorkshop(
            title: 'Free Green Screen Workshop',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            registration: 'tickets',
            price: 'Free'
        );

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSee('"offers"', false)
            ->assertSee('"priceCurrency":"AUD"', false)
            ->assertSee('"price":"0.00"', false)
            ->assertSee(route('workshop.ticket.flow.start', $workshop), false);
    }

    public function test_stemcraft_workshop_show_page_uses_stemcraft_registration_and_location_copy(): void
    {
        $workshop = $this->createWorkshop(
            title: 'STEMCraft Build Night',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay(),
            isOnline: true,
            registration: 'none',
            type: Workshop::TYPE_STEMCRAFT
        );

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertSee('No registration required. Simply join the STEMCraft server at the workshop date and time.', false)
            ->assertSee('How to Join', false)
            ->assertSee(route('stemcraft.join'), false)
            ->assertSee('<a href="'.route('stemcraft.join').'" class="link">STEMCraft</a>', false)
            ->assertSee('STEMMechanics Minecraft Server', false);
    }

    public function test_admin_workshop_index_shows_copy_public_page_link_for_non_draft_workshops(): void
    {
        $admin = $this->createAdminUser();
        $publicWorkshop = $this->createWorkshop(
            title: 'Public Robotics Session',
            status: 'open',
            isHidden: false,
            publishAt: now()->subDay()
        );
        $draftWorkshop = $this->createWorkshop(
            title: 'Draft Robotics Session',
            status: 'draft',
            isHidden: false,
            publishAt: now()->subDay()
        );

        $response = $this->actingAs($admin)
            ->get(route('admin.workshop.index'));

        $response->assertOk()
            ->assertSeeText($publicWorkshop->title)
            ->assertSeeText($draftWorkshop->title);

        $this->assertSame(1, substr_count($response->getContent(), 'title="Copy public page link"'));
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
        bool $isOnline = false,
        string $registration = 'none',
        ?string $price = 'Free',
        string $type = Workshop::TYPE_PHYSICAL
    ): Workshop {
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
            'type' => $type,
            'price' => $price,
            'is_private' => $isPrivate,
            'is_hidden' => $isHidden,
            'registration' => $registration,
            'location_id' => $location?->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
