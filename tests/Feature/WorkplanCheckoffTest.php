<?php

namespace Tests\Feature;

use App\Models\PickListTemplate;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopTemplateTask;
use App\Services\ReminderService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkplanCheckoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Location::factory()->create();
        \App\Models\Media::create(['name' => 'stemmechanics-logo.png', 'title' => 'Checkoff', 'hash' => str_repeat('a', 64), 'mime_type' => 'image/png', 'size' => 1]);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    public function test_workshop_readiness_is_persistent_and_does_not_complete_the_workshop_or_tasks(): void
    {
        $this->admin();
        $workshop = Workshop::factory()->create(['user_id' => auth()->id(), 'status' => 'open', 'run_sheet_completed_task_ids' => [123]]);
        foreach ([true, false] as $checked) {
            $this->patchJson(route('admin.workplan.workshop.checkoff', $workshop), compact('checked'))->assertOk()->assertJsonPath('checked', $checked);
            $workshop->refresh();
            $this->assertSame($checked, $workshop->workplan_checked);
            $this->assertSame('open', $workshop->status);
            $this->assertSame([123], $workshop->run_sheet_completed_task_ids);
        }
    }

    public function test_task_completion_updates_run_sheet_and_keeps_cancelled_reminder_visible(): void
    {
        $this->admin();
        $template = PickListTemplate::create(['name' => 'Checklist']);
        $task = WorkshopTemplateTask::create(['pick_list_template_id' => $template->id, 'name' => 'Pack boxes']);
        $workshop = Workshop::factory()->create(['user_id' => auth()->id(), 'status' => 'open', 'pick_list_template_id' => $template->id]);
        $reminder = Reminder::create([
            'kind' => ReminderService::WORKSHOP_TASK_KIND, 'remindable_type' => $workshop->getMorphClass(),
            'remindable_id' => $workshop->id, 'source_type' => $task->getMorphClass(), 'source_id' => $task->id,
            'recipient_email' => 'test@example.com', 'subject' => 'Pack boxes', 'status' => Reminder::STATUS_PENDING,
            'scheduled_at' => today()->addDay(),
        ]);
        $url = route('admin.workplan.task.checkoff', ['workshop' => $workshop, 'task' => $task]);
        $this->patchJson($url, ['checked' => true])->assertOk();
        $this->assertContains($task->id, $workshop->fresh()->run_sheet_completed_task_ids);
        $this->assertSame(Reminder::STATUS_CANCELLED, $reminder->fresh()->status);
        $this->assertTrue(app(WeeklyWorkplanService::class)->build()['reminders']->contains('id', $reminder->id));
        $this->patchJson($url, ['checked' => false])->assertOk();
        $this->assertNotContains($task->id, $workshop->fresh()->run_sheet_completed_task_ids);
        $other = WorkshopTemplateTask::create(['pick_list_template_id' => PickListTemplate::create(['name' => 'Other'])->id, 'name' => 'Other']);
        $this->patchJson(route('admin.workplan.task.checkoff', ['workshop' => $workshop, 'task' => $other]), ['checked' => true])->assertNotFound();
    }

    public function test_non_admin_cannot_change_workplan_readiness(): void
    {
        $this->actingAs(User::factory()->create());
        $workshop = Workshop::factory()->create(['user_id' => auth()->id()]);
        $this->patchJson(route('admin.workplan.workshop.checkoff', $workshop), ['checked' => true])->assertForbidden();
    }

    public function test_invoice_contact_and_private_notes_do_not_change_payment_status(): void
    {
        $this->admin();
        $invoice = \App\Models\Invoice::factory()->create(['status' => 'sent', 'notes' => 'Call on Friday']);
        $url = route('admin.workplan.invoice.follow-up', $invoice);
        $this->patchJson($url, ['checked' => true])->assertOk();
        $this->assertNotNull($invoice->fresh()->follow_up_contacted_at);
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->patchJson($url, ['notes' => 'Promised payment', 'original_notes' => 'Call on Friday'])->assertOk();
        $this->assertSame('Promised payment', $invoice->fresh()->notes);
        $this->patchJson($url, ['notes' => 'Stale change', 'original_notes' => 'Call on Friday'])->assertUnprocessable();
        $this->patchJson($url, ['notes' => '', 'original_notes' => 'Promised payment'])->assertOk();
        $this->assertSame('', (string) $invoice->fresh()->notes);
        $this->patchJson($url, ['checked' => false])->assertOk();
        $this->assertNull($invoice->fresh()->follow_up_contacted_at);
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->actingAs(User::factory()->create())->patchJson($url, ['checked' => true])->assertForbidden();
    }
}
