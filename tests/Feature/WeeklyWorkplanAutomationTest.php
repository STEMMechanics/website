<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\WeeklyWorkplan;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\WeeklyWorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeeklyWorkplanAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_workplan_is_queued_for_admins(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        $this->artisan('workplan:send-weekly')->assertSuccessful();

        Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'admin@example.com' && $job->mailable instanceof WeeklyWorkplan);
    }

    public function test_weekly_workplan_email_renders(): void
    {
        $html = (new WeeklyWorkplan(app(WeeklyWorkplanService::class)->build()))->render();

        $this->assertStringContainsString('Weekly Workplan', $html);
        $this->assertStringContainsString('Next newsletter', $html);
        $this->assertStringContainsString('Review or change the newsletter', $html);
        $this->assertStringContainsString('Last week at a glance', $html);
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

    public function test_workplan_includes_unpaid_invoices_due_this_week(): void
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
