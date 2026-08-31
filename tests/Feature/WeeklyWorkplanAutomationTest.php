<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\WeeklyWorkplan;
use App\Models\AnalyticsEvent;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Media;
use App\Models\Quote;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopTemplateTask;
use App\Services\ReminderService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeeklyWorkplanAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fortnightly_workplan_is_queued_for_admins(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        $this->artisan('workplan:send-fortnightly')->assertSuccessful();

        Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'admin@example.com' && $job->mailable instanceof WeeklyWorkplan);
    }

    public function test_fortnightly_workplan_email_renders(): void
    {
        $location = Location::factory()->create(['name' => 'Cairns Library']);
        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'email-location-workshop.png',
            'title' => 'Email location workshop',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);
        Workshop::factory()->create([
            'title' => 'Email location workshop',
            'starts_at' => now()->addDay(),
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $media->name,
        ]);
        $html = (new WeeklyWorkplan(app(WeeklyWorkplanService::class)->build()))->render();

        $this->assertStringContainsString('Fortnightly Workplan', $html);
        $this->assertStringContainsString('font-size: 32px', $html);
        $this->assertStringContainsString('font-size: 24px', $html);
        $this->assertStringContainsString('Cairns Library', $html);
        $this->assertStringContainsString('Next newsletter', $html);
        $this->assertStringContainsString('Review or change the newsletter', $html);
        $this->assertStringContainsString('Last fortnight at a glance', $html);
        $this->assertStringContainsString('no change', $html);
    }

    public function test_website_stats_compare_with_the_previous_fortnight(): void
    {
        AnalyticsEvent::factory()->count(2)->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'route_name' => 'shop.index',
            'created_at' => now()->subDays(2),
        ]);
        AnalyticsEvent::factory()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'route_name' => 'workshop.show',
            'created_at' => now()->subDays(20),
        ]);

        $workplan = app(WeeklyWorkplanService::class)->build();

        $this->assertSame(2, $workplan['stats']['page_views']);
        $this->assertSame('100.0% growth', $workplan['websiteChanges']['page_views']['label']);
        $this->assertSame('100.0% decline', $workplan['websiteChanges']['workshop_views']['label']);
    }

    public function test_cancelled_workshops_are_excluded_from_coming_up_this_fortnight(): void
    {
        $location = Location::factory()->create();
        $owner = User::factory()->create();
        $media = Media::query()->create([
            'name' => 'workplan-workshop.png',
            'title' => 'Workplan workshop',
            'hash' => str_repeat('w', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);
        $upcoming = Workshop::factory()->create([
            'starts_at' => now()->addDay(),
            'status' => 'open',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $media->name,
        ]);
        $cancelled = Workshop::factory()->create([
            'starts_at' => now()->addDays(2),
            'status' => 'cancelled',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $media->name,
        ]);

        $workshops = app(WeeklyWorkplanService::class)->build()['workshops'];

        $this->assertTrue($workshops->contains($upcoming));
        $this->assertFalse($workshops->contains($cancelled));
    }

    public function test_admin_can_open_a_workplan_pdf_inline(): void
    {
        $admin = User::factory()->create(['email' => 'reviewer@example.com']);
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard.workplan.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="fortnightly-workplan-'.today()->format('Y-m-d').'.pdf"');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Website last fortnight')
            ->assertSee('no change');

    }

    public function test_completed_workshop_tasks_are_crossed_out_on_the_dashboard_and_workplan_pdf(): void
    {
        $admin = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $location = Location::factory()->create();
        $media = Media::query()->create([
            'name' => 'completed-task-workshop.png',
            'title' => 'Completed task workshop',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'status' => 'open',
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $media->name,
            'run_sheet_completed_task_ids' => [321],
        ]);
        Reminder::query()->create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND,
            'remindable_type' => $workshop->getMorphClass(),
            'remindable_id' => $workshop->id,
            'source_type' => (new WorkshopTemplateTask)->getMorphClass(),
            'source_id' => 321,
            'recipient_user_id' => $admin->id,
            'recipient_email' => $admin->email,
            'subject' => 'Workshop task: Pack robots — Test workshop',
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => today()->addDay()->setTime(6, 0),
        ]);

        $workplan = app(WeeklyWorkplanService::class)->build();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('text-gray-400 line-through', false);
        $this->assertStringContainsString(
            '<li class="completed">Workshop task: Pack robots',
            view('pdf.weekly-workplan', ['workplan' => $workplan])->render(),
        );
    }

    public function test_quote_follow_ups_are_date_driven_and_can_be_snoozed(): void
    {
        $admin = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $due = Quote::factory()->create([
            'status' => Quote::STATUS_AWAITING_DECISION,
            'follow_up_at' => today(),
            'valid_until' => today()->addMonths(6),
        ]);
        $later = Quote::factory()->create([
            'status' => Quote::STATUS_OPEN,
            'follow_up_at' => today()->addMonth(),
        ]);
        Quote::factory()->create([
            'status' => Quote::STATUS_OPEN,
            'follow_up_at' => null,
        ]);

        $quotes = app(WeeklyWorkplanService::class)->build()['quotes'];
        $this->assertTrue($quotes->contains($due));
        $this->assertFalse($quotes->contains($later));

        $this->actingAs($admin)->post(route('admin.quote.snooze-follow-up', $due))->assertRedirect();
        $this->assertSame(today()->addDays(7)->toDateString(), $due->fresh()->follow_up_at?->toDateString());
        $this->assertFalse(app(WeeklyWorkplanService::class)->build()['quotes']->contains($due));
    }

    public function test_workplan_includes_unpaid_invoices_due_this_fortnight(): void
    {
        $due = Invoice::factory()->create([
            'status' => Invoice::STATUS_SENT,
            'due_date' => today()->addDay(),
            'total_amount' => 125,
        ]);
        $later = Invoice::factory()->create([
            'status' => Invoice::STATUS_SENT,
            'due_date' => today()->addWeeks(2),
            'total_amount' => 125,
        ]);

        $dueInvoices = app(WeeklyWorkplanService::class)->build()['dueInvoices'];

        $this->assertTrue($dueInvoices->contains($due));
        $this->assertFalse($dueInvoices->contains($later));
    }
}
