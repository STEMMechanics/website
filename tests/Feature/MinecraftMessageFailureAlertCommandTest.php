<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\MinecraftMessageFailureDigest;
use App\Models\MinecraftMessage;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MinecraftMessageFailureAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_messages_are_grouped_and_queued_after_the_quiet_period(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $first = MinecraftMessage::query()->create($this->blockedMessageAttributes([
            'username' => 'PlayerOne',
            'occurred_at' => now()->subMinutes(30),
        ]));
        $second = MinecraftMessage::query()->create($this->blockedMessageAttributes([
            'username' => 'PlayerTwo',
            'occurred_at' => now()->subMinutes(25),
        ]));
        MinecraftMessage::query()->create(array_merge($this->blockedMessageAttributes(), [
            'passed' => true,
            'failure_reason' => null,
            'failure_detail' => null,
            'filtered_message' => null,
            'occurred_at' => now()->subMinutes(25),
        ]));

        Artisan::call('minecraft:messages:send-failure-alerts');

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'admin@example.com'
                && $job->mailable instanceof MinecraftMessageFailureDigest
                && $job->mailable->messages->count() === 2;
        });

        $this->assertNotNull($first->refresh()->admin_failure_notification_queued_at);
        $this->assertNotNull($second->refresh()->admin_failure_notification_queued_at);
    }

    public function test_blocked_messages_wait_until_the_latest_failure_is_old_enough(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $older = MinecraftMessage::query()->create($this->blockedMessageAttributes([
            'occurred_at' => now()->subMinutes(30),
        ]));
        $latest = MinecraftMessage::query()->create($this->blockedMessageAttributes([
            'uuid' => 'bbbbbbbb-2222-3333-4444-555555555555',
            'username' => 'PlayerTwo',
            'occurred_at' => now()->subMinutes(10),
        ]));

        Artisan::call('minecraft:messages:send-failure-alerts');

        Queue::assertNothingPushed();
        $this->assertNull($older->refresh()->admin_failure_notification_queued_at);
        $this->assertNull($latest->refresh()->admin_failure_notification_queued_at);
    }

    public function test_force_option_bypasses_the_quiet_period(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        $message = MinecraftMessage::query()->create($this->blockedMessageAttributes([
            'occurred_at' => now()->subMinutes(2),
        ]));

        Artisan::call('minecraft:messages:send-failure-alerts', ['--force' => true]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'admin@example.com'
                && $job->mailable instanceof MinecraftMessageFailureDigest
                && $job->mailable->messages->count() === 1;
        });

        $this->assertNotNull($message->refresh()->admin_failure_notification_queued_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function blockedMessageAttributes(array $overrides = []): array
    {
        return array_merge([
            'occurred_at' => now()->subMinutes(30),
            'message_type' => 'chat',
            'platform' => 'java',
            'uuid' => 'aaaaaaaa-1111-2222-3333-444444444444',
            'username' => 'PlayerOne',
            'server_name' => 'survival',
            'world' => 'world',
            'x' => 1,
            'y' => 64,
            'z' => 2,
            'raw_message' => 'This includes fck directly.',
            'filtered_message' => null,
            'passed' => false,
            'failure_reason' => 'custom_regex',
            'failure_detail' => '\bfck\b',
            'context' => ['side' => 'front'],
        ], $overrides);
    }
}
