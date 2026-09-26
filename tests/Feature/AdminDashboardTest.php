<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Media;
use App\Models\StoreOrder;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\AdminDashboardActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_is_visible_to_admin_users_and_admin_root_redirects(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $media = Media::query()->create([
            'name' => 'dashboard-workplan.png',
            'title' => 'Dashboard workshop',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'title' => 'Public workplan workshop',
            'starts_at' => now()->addHours(2),
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $media->name,
        ]);
        Invoice::factory()->create([
            'status' => Invoice::STATUS_SENT,
            'due_date' => today()->addDay(),
            'total_amount' => 125,
        ]);
        Invoice::factory()->create([
            'status' => Invoice::STATUS_DRAFT,
            'scheduled_email' => true,
            'issue_date' => today()->addDay(),
            'total_amount' => 125,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Top 10 Workshop Activity')
            ->assertSee('Top 10 Store Item Views and Sales')
            ->assertSee('Workshop views')
            ->assertSee('Profit')
            ->assertSee('Website Traffic')
            ->assertSee('Top 10 Traffic Sources')
            ->assertSee('Percentage')
            ->assertSee('View full analytics report')
            ->assertSee('Financial Performance')
            ->assertSee('Workshop Activity')
            ->assertSee('Ticket Activity')
            ->assertSee('Store Activity')
            ->assertSee('Audience Growth')
            ->assertSee('Total users')
            ->assertSee('Total subscriptions')
            ->assertSee('Last 12 Months')
            ->assertSee('aria-label="Last 12 Months"', false)
            ->assertSee('All time')
            ->assertDontSee('class="sm-filter-chip"', false)
            ->assertSee('trend graph')
            ->assertDontSee('Selected range')
            ->assertSee('Fortnightly Workplan')
            ->assertSee('Next newsletter')
            ->assertSee('Subject:')
            ->assertSee('Review newsletter')
            ->assertSee('Suggested follow-ups')
            ->assertSee('Coming up this fortnight')
            ->assertSee('Invoices due')
            ->assertSee('Invoice email scheduled')
            ->assertSee('Payment due')
            ->assertSee('fa-arrow-up-right-from-square', false)
            ->assertSee('SM.workplanLayout()', false)
            ->assertSee('grid grid-cols-2 gap-3 md:grid-cols-5', false)
            ->assertSee('Suggested actions')
            ->assertSee('Add an expense')
            ->assertSee('sm-dashboard-action-grid', false)
            ->assertSee('min-h-24', false)
            ->assertSee('border-t border-gray-100', false)
            ->assertDontSee('pr-24', false)
            ->assertSee('cursor-pointer', false)
            ->assertSee(route('workshop.show', $workshop), false)
            ->assertSee('data-view-tabs', false)
            ->assertDontSee('g:ia', false);
    }

    public function test_admin_dashboard_is_forbidden_to_non_admin_users(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_all_time_dashboard_has_no_period_comparisons_or_filter_chips(): void
    {
        $response = $this->actingAs($this->createAdminUser())->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()->assertViewHas('period', 'all')
            ->assertDontSee('class="sm-filter-chip"', false)
            ->assertDontSee('vs previous period')->assertDontSee('Previous:')
            ->assertDontSee('Running total for the selected period.');
        $this->assertMatchesRegularExpression('/<a\b[^>]*aria-label="All time"[^>]*aria-current="page"/', $response->getContent());
    }

    public function test_dashboard_action_cards_include_attendance_for_a_recent_workshop_with_unmarked_ticket_holders(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $media = Media::factory()->create(['user_id' => $admin->id]);
        $startsAt = now()->subHours(2);
        $workshop = Workshop::factory()->create([
            'title' => 'Recent robotics workshop',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => 'closed',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $media->name,
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => null,
        ]);

        $cards = app(AdminDashboardActions::class)->build();
        $attendance = collect($cards)->firstWhere('title', 'Mark Attendance');

        $this->assertGreaterThanOrEqual(5, count($cards));
        $this->assertLessThanOrEqual(8, count($cards));
        $this->assertNotNull($attendance);
        $this->assertStringContainsString('Recent robotics workshop', $attendance['description']);
        $this->assertStringContainsString($location->name, $attendance['description']);
        $this->assertStringContainsString('0/1 marked', $attendance['description']);
        $this->assertSame('Recent robotics workshop', $attendance['attendance_details']['workshop']);
        $this->assertSame($location->name, $attendance['attendance_details']['location']);
        $this->assertSame('Needs attendance', $attendance['attendance_details']['phase']);
        $this->assertSame(0, $attendance['attendance_details']['attended']);
        $this->assertSame(1, $attendance['attendance_details']['total']);
        $this->assertSame(route('admin.workshop.attendance', $workshop), $attendance['url']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Mark Attendance',
                'Recent robotics workshop',
                $startsAt->format('D j M, g:i a'),
                $location->name,
            ])
            ->assertDontSee('Needs attendance')
            ->assertDontSee('0 of 1 marked');
    }

    public function test_course_attendance_action_opens_the_specific_session(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $media = Media::factory()->create(['user_id' => $admin->id]);
        $sessionId = '0d11028c-e6f3-47b7-8715-061e197d2e7a';
        $startsAt = now()->subHours(2);
        $endsAt = now()->subHour();
        $workshop = Workshop::factory()->create([
            'title' => 'Robotics course',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'format' => 'course',
            'course_sessions' => [[
                'id' => $sessionId,
                'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
                'ends_at' => $endsAt->format('Y-m-d\\TH:i'),
            ]],
            'status' => 'closed',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $media->name,
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'attended_at' => null,
        ]);

        $attendance = collect(app(AdminDashboardActions::class)->build())
            ->first(fn (array $card): bool => str_contains($card['url'], route('admin.workshop.attendance', $workshop)));

        $this->assertNotNull($attendance);
        $this->assertStringContainsString('session_id='.$sessionId, $attendance['url']);
    }

    public function test_dashboard_action_cards_have_a_fresh_json_endpoint(): void
    {
        $response = $this->actingAs($this->createAdminUser())->getJson(route('admin.dashboard.actions'));

        $response->assertOk()->assertJsonStructure([
            'actions' => [['title', 'description', 'url', 'icon', 'tone', 'dismiss_key', 'title_no_wrap']],
        ]);
        $this->assertGreaterThanOrEqual(5, count($response->json('actions')));
        $this->assertLessThanOrEqual(8, count($response->json('actions')));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_bas_action_appears_near_month_end_for_the_previous_month(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:00:00', config('app.timezone')));

        $bas = collect(app(AdminDashboardActions::class)->build())->firstWhere('title', 'Review BAS · Aug 2026');

        $this->assertNotNull($bas);
        $this->assertStringContainsString('month=2026-08', $bas['url']);
    }

    public function test_admin_can_hide_the_current_bas_action_until_the_next_period(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:00:00', config('app.timezone')));
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->postJson(route('admin.dashboard.actions.dismiss'), ['action_key' => 'bas:2026-08'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $cards = $this->getJson(route('admin.dashboard.actions'))->assertOk()->json('actions');
        $this->assertNull(collect($cards)->firstWhere('title', 'Review BAS · Aug 2026'));
        $this->assertDatabaseHas('admin_dashboard_action_dismissals', [
            'user_id' => $admin->id,
            'action_key' => 'bas:2026-08',
        ]);

        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', config('app.timezone')));
        $nextPeriodCards = $this->getJson(route('admin.dashboard.actions'))->assertOk()->json('actions');
        $this->assertNotNull(collect($nextPeriodCards)->firstWhere('title', 'Review BAS · Sep 2026'));
    }

    public function test_dashboard_can_fill_two_even_rows_when_eight_action_options_are_relevant(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:00:00', config('app.timezone')));
        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $media = Media::factory()->create(['user_id' => $admin->id]);

        foreach (['Robotics morning', 'Creative afternoon'] as $index => $title) {
            $startsAt = now()->subDays($index + 1)->setTime(10, 0);
            $workshop = Workshop::factory()->create([
                'title' => $title,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(2),
                'status' => 'closed',
                'registration' => 'tickets',
                'location_id' => $location->id,
                'user_id' => $admin->id,
                'hero_media_name' => $media->name,
            ]);
            Ticket::factory()->create([
                'workshop_id' => $workshop->id,
                'status' => Ticket::STATUS_PAID,
                'attended_at' => null,
            ]);
        }
        StoreOrder::factory()->create(['status' => StoreOrder::STATUS_PROCESSING]);
        Invoice::factory()->create([
            'status' => Invoice::STATUS_SENT,
            'due_date' => today()->subDay(),
            'total_amount' => 125,
        ]);

        $cards = app(AdminDashboardActions::class)->build((string) $admin->id);

        $this->assertCount(8, $cards);
        $this->assertCount(2, collect($cards)->where('title', 'Mark Attendance'));
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
