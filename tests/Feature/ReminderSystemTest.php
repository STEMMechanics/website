<?php

namespace Tests\Feature;

use App\Jobs\SendReminder;
use App\Mail\ReminderNotification;
use App\Models\Location;
use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_task_reminder_is_scheduled_for_the_facilitator(): void
    {
        $creator = User::factory()->create(['email' => 'creator@example.com']);
        $facilitator = User::factory()->create(['email' => 'facilitator@example.com']);
        Location::factory()->create();
        Media::query()->create([
            'name' => 'reminder-workshop.png',
            'title' => 'Reminder Workshop',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $creator->id,
        ]);
        $template = PickListTemplate::query()->create(['name' => 'Paper Speakers']);
        $task = $template->tasks()->create([
            'name' => 'Publish before post',
            'notes' => 'Use the workshop photo and registration link.',
            'reminder_enabled' => true,
            'reminder_offset_days' => -5,
            'reminder_time' => '06:00',
            'sort_order' => 10,
        ]);
        $workshop = Workshop::factory()->create([
            'user_id' => $creator->id,
            'facilitator_user_id' => $facilitator->id,
            'hero_media_name' => 'reminder-workshop.png',
            'pick_list_template_id' => $template->id,
            'starts_at' => now()->addMonth()->setTime(10, 0),
        ]);

        app(ReminderService::class)->syncWorkshop($workshop);

        $reminder = Reminder::query()->sole();
        $this->assertSame($facilitator->id, $reminder->recipient_user_id);
        $this->assertSame('Workshop task: Publish before post — '.$workshop->title, $reminder->subject);
        $this->assertSame('facilitator@example.com', $reminder->recipient_email);
        $this->assertSame($task->id, (int) $reminder->source_id);
        $this->assertSame(
            $workshop->starts_at->copy()->subDays(5)->startOfDay()->setTime(6, 0)->toDateTimeString(),
            $reminder->scheduled_at->toDateTimeString()
        );
        $this->assertStringContainsString('#task-'.$task->id, (string) $reminder->action_url);

        UserGroup::query()->create(['user_id' => $creator->id, 'slug' => 'admin']);
        $this->actingAs($creator)
            ->get(route('admin.workshop.run-sheet', $workshop))
            ->assertOk()
            ->assertSeeText($reminder->scheduled_at->format('D j M, g:ia'))
            ->assertSeeText('Pending')
            ->assertSeeText($facilitator->getName());

        $renderedEmail = (new ReminderNotification($reminder->load('remindable')))->render();
        $this->assertStringContainsString($workshop->title, $renderedEmail);
        $this->assertStringContainsString('Task Notes', $renderedEmail);
        $this->assertStringContainsString('Use the workshop photo and registration link.', $renderedEmail);
        $this->assertStringContainsString('Mark as Complete', $renderedEmail);

        UserGroup::query()->create(['user_id' => $facilitator->id, 'slug' => 'admin']);
        $this->actingAs($facilitator)
            ->get(route('admin.workshop.run-sheet.task.complete', [$workshop, $task]))
            ->assertRedirect(route('admin.workshop.run-sheet', $workshop).'#task-'.$task->id);
        $this->assertSame([$task->id], $workshop->fresh()->run_sheet_completed_task_ids);

        $workshop->update(['status' => 'cancelled']);
        app(ReminderService::class)->syncWorkshop($workshop->fresh());
        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
    }

    public function test_workshop_task_notes_replace_workshop_placeholders(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');

        $creator = User::factory()->create(['email' => 'creator@example.com']);
        $facilitator = User::factory()->create(['email' => 'facilitator@example.com']);
        $location = Location::factory()->create(['name' => 'Innovation Centre']);
        Media::query()->create([
            'name' => 'placeholder-workshop.png',
            'title' => 'Placeholder Workshop',
            'hash' => str_repeat('e', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $creator->id,
        ]);
        $template = PickListTemplate::query()->create(['name' => 'Social media tasks']);
        $template->tasks()->create([
            'name' => 'Publish social post',
            'notes' => implode("\n", [
                '{date-short}',
                '<strong>{date-long}</strong>',
                '{date-ddd dd/mm/yyyy}',
                '{start-time}–{end-time}',
                '{time-range}',
                '{location}',
                'Ages {ages}',
                '{cost}',
            ]),
            'reminder_enabled' => true,
            'reminder_offset_days' => -5,
            'reminder_time' => '06:00',
            'sort_order' => 10,
        ]);
        $workshop = Workshop::factory()->create([
            'user_id' => $creator->id,
            'facilitator_user_id' => $facilitator->id,
            'hero_media_name' => 'placeholder-workshop.png',
            'pick_list_template_id' => $template->id,
            'location_id' => $location->id,
            'starts_at' => '2026-08-27 09:30:00',
            'ends_at' => '2026-08-27 11:00:00',
            'ages' => '8–12',
            'price' => '25',
        ]);

        app(ReminderService::class)->syncWorkshop($workshop);

        $this->assertSame(implode("\n", [
            '27/08/2026',
            '<strong>Thursday 27 August</strong>',
            'Thu 27/08/2026',
            '9:30am–11:00am',
            '9:30-11:00am',
            'Innovation Centre',
            'Ages 8–12',
            '$25.00',
        ]), Reminder::query()->sole()->message);

        $workshop->update(['price' => 'Free']);
        app(ReminderService::class)->syncWorkshop($workshop->fresh());

        $this->assertStringEndsWith("\nFree", (string) Reminder::query()->sole()->message);
    }

    public function test_resync_updates_the_same_workshop_task_reminder_without_duplication(): void
    {
        $creator = User::factory()->create(['email' => 'creator@example.com']);
        $facilitator = User::factory()->create(['email' => 'facilitator@example.com']);
        Location::factory()->create();
        Media::query()->create([
            'name' => 'resync-reminder-workshop.png',
            'title' => 'Reminder Workshop',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $creator->id,
        ]);
        $template = PickListTemplate::query()->create(['name' => 'Paper Speakers']);
        $task = $template->tasks()->create([
            'name' => 'Publish before post',
            'notes' => 'Original notes',
            'reminder_enabled' => true,
            'reminder_offset_days' => -5,
            'reminder_time' => '06:00',
            'sort_order' => 10,
        ]);
        $workshop = Workshop::factory()->create([
            'user_id' => $creator->id,
            'facilitator_user_id' => $facilitator->id,
            'hero_media_name' => 'resync-reminder-workshop.png',
            'pick_list_template_id' => $template->id,
            'starts_at' => now()->addMonth()->setTime(10, 0),
        ]);
        $service = app(ReminderService::class);
        $service->syncWorkshop($workshop);
        $original = Reminder::query()->sole();
        $original->update(['status' => Reminder::STATUS_QUEUED, 'queued_at' => now()]);

        $task->update([
            'notes' => 'Updated notes',
            'reminder_offset_days' => -2,
            'reminder_time' => '12:00',
        ]);
        $service->syncWorkshop($workshop->fresh());

        $reminder = Reminder::query()->sole();
        $this->assertSame($original->id, $reminder->id);
        $this->assertSame(Reminder::STATUS_PENDING, $reminder->status);
        $this->assertSame('Updated notes', $reminder->message);
        $this->assertNull($reminder->queued_at);
        $this->assertSame(
            $workshop->starts_at->copy()->subDays(2)->startOfDay()->setTime(12, 0)->toDateTimeString(),
            $reminder->scheduled_at->toDateTimeString(),
        );
    }

    public function test_due_reminder_is_emailed_and_retained_as_sent_history(): void
    {
        Queue::fake();
        Mail::fake();
        $recipient = User::factory()->create(['email' => 'facilitator@example.com']);
        $reminder = Reminder::query()->create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND,
            'recipient_user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'subject' => 'Workshop task: Send follow-up post',
            'message' => 'Thank attendees and share the gallery.',
            'action_url' => 'https://example.test/admin/workshops/1/pick-list#task-2',
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('reminders:send-due');

        Queue::assertPushedOn('mail', SendReminder::class);
        $this->assertSame(Reminder::STATUS_QUEUED, $reminder->fresh()->status);

        (new SendReminder((int) $reminder->id))->handle();

        Mail::assertSent(ReminderNotification::class, fn (ReminderNotification $mail) => $mail->hasTo('facilitator@example.com'));
        $this->assertSame(Reminder::STATUS_SENT, $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_historical_workshop_task_reminder_is_cancelled_instead_of_sent_late(): void
    {
        Queue::fake();
        $reminder = Reminder::query()->create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND,
            'recipient_email' => 'facilitator@example.com',
            'subject' => 'Workshop task: Historical task',
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => now()->subMonth(),
        ]);

        Artisan::call('reminders:send-due');

        Queue::assertNothingPushed();
        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
    }

    public function test_admin_can_queue_a_pending_reminder_for_immediate_delivery(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $reminder = Reminder::query()->create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND,
            'recipient_email' => 'facilitator@example.com',
            'subject' => 'Workshop task: Test reminder',
            'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => now()->addWeek(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reminder.send-now', $reminder))
            ->assertRedirect();

        Mail::assertSent(ReminderNotification::class);
        $this->assertSame(Reminder::STATUS_SENT, $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->queued_at);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_admin_can_resend_a_sent_reminder_for_template_testing(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $originalSentAt = now()->subDay();
        $reminder = Reminder::query()->create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND,
            'recipient_email' => 'facilitator@example.com',
            'subject' => 'Workshop task: Test resend',
            'status' => Reminder::STATUS_SENT,
            'scheduled_at' => now()->subWeek(),
            'sent_at' => $originalSentAt,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reminder.send-now', $reminder))
            ->assertRedirect();

        Mail::assertSent(ReminderNotification::class);
        $this->assertSame(Reminder::STATUS_SENT, $reminder->fresh()->status);
        $this->assertTrue($reminder->fresh()->sent_at->greaterThan($originalSentAt));
    }
}
