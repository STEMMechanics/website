<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\WeeklyWorkplan;
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
        $this->assertStringContainsString('Last week at a glance', $html);
    }
}
