<?php

namespace Tests\Feature;

use App\Models\MinecraftWebhookLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStemcraftWebhookRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_pending_outbound_webhook_cannot_be_manually_retried(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $log = MinecraftWebhookLog::query()->create([
            'direction' => MinecraftWebhookLog::DIRECTION_OUTBOUND,
            'status' => MinecraftWebhookLog::STATUS_PENDING,
            'event' => 'player.penalty.created',
            'delivery_id' => '11111111-2222-3333-4444-555555555555',
            'method' => 'POST',
            'payload' => [
                'event' => 'player.penalty.created',
                'external_id' => 'penalty-123',
            ],
            'attempt_count' => 1,
            'last_attempted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.webhooks.retry', $log));

        $response->assertStatus(422);
        $this->assertSame(1, MinecraftWebhookLog::query()->count());
    }
}
