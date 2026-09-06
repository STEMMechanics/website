<?php

namespace Tests\Feature;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderBulkEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $user->id, 'slug' => 'admin']);
        return $user;
    }

    private function reminder(array $attributes = []): Reminder
    {
        return Reminder::create(array_merge(['kind' => 'test', 'recipient_email' => 'test@example.com', 'subject' => 'Test reminder', 'status' => Reminder::STATUS_QUEUED, 'scheduled_at' => now()->subHour()], $attributes));
    }

    public function test_bulk_cancel_prevents_a_waiting_job_from_sending_and_leaves_unselected_rows_alone(): void
    {
        Mail::fake();
        $selected = $this->reminder();
        $other = $this->reminder();
        $this->actingAs($this->admin())->putJson(route('admin.reminder.bulk.update'), ['reminder_ids' => [$selected->id], 'action' => 'cancel'])->assertOk();
        (new SendReminder($selected->id))->handle();
        Mail::assertNothingSent();
        $this->assertSame(Reminder::STATUS_CANCELLED, $selected->fresh()->status);
        $this->assertSame(Reminder::STATUS_QUEUED, $other->fresh()->status);
    }

    public function test_requeue_resets_delivery_state_and_keeps_future_schedule_without_sending_immediately(): void
    {
        Queue::fake();
        Mail::fake();
        $this->travelTo(now()->startOfSecond());
        $sent = $this->reminder(['status' => Reminder::STATUS_SENT, 'sent_at' => now()->subHour(), 'failed_at' => now(), 'failure_message' => 'Old failure']);
        $future = $this->reminder(['status' => Reminder::STATUS_CANCELLED, 'scheduled_at' => now()->addDay()]);
        $this->actingAs($this->admin())->postJson(route('admin.reminder.bulk.edit'), ['reminder_ids' => [$sent->id, $future->id]])->assertOk()->assertJsonStructure(['html']);
        $this->putJson(route('admin.reminder.bulk.update'), ['reminder_ids' => [$sent->id, $future->id], 'action' => 'requeue'])->assertOk();
        $this->assertSame(Reminder::STATUS_PENDING, $sent->fresh()->status);
        $this->assertTrue($sent->fresh()->scheduled_at->equalTo(now()));
        $this->assertNull($sent->fresh()->sent_at);
        $this->assertNull($sent->fresh()->failure_message);
        $this->assertTrue($future->fresh()->scheduled_at->equalTo(now()->addDay()));
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_select_all_respects_filters_and_bulk_mutations_require_admin_access_and_valid_ids(): void
    {
        $pending = $this->reminder(['status' => Reminder::STATUS_PENDING]);
        $this->reminder(['status' => Reminder::STATUS_SENT]);
        $this->actingAs($this->admin())->getJson(route('admin.reminder.index', ['view' => 'upcoming', 'select_listing' => 1, 'page' => 2]))->assertOk()->assertExactJson(['names' => [(string) $pending->id]]);
        $this->get(route('admin.reminder.index', ['view' => 'all']))->assertOk()->assertSee('data-reminder-select', false)->assertSee('reminder-actions-', false);
        $this->putJson(route('admin.reminder.bulk.update'), ['reminder_ids' => [$pending->id, 999999], 'action' => 'cancel'])->assertUnprocessable();
        $this->assertSame(Reminder::STATUS_PENDING, $pending->fresh()->status);
        $this->actingAs(User::factory()->create())->putJson(route('admin.reminder.bulk.update'), ['reminder_ids' => [$pending->id], 'action' => 'cancel'])->assertForbidden();
    }
}
