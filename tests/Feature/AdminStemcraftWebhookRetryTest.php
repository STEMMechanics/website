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

        $initialLogCount = MinecraftWebhookLog::query()->count();
        $response = $this->actingAs($admin)->post(route('admin.stemcraft.webhooks.retry', $log));

        $response->assertStatus(422);
        $this->assertSame($initialLogCount, MinecraftWebhookLog::query()->count());
    }

    public function test_webhook_index_does_not_render_an_actions_column(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftWebhookLog::query()->create([
            'direction' => MinecraftWebhookLog::DIRECTION_OUTBOUND,
            'status' => MinecraftWebhookLog::STATUS_FAILED,
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

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.webhooks.index'));

        $response->assertOk();
        $response->assertDontSee('>Actions<', false);
        $response->assertSeeText('Retry');
        $response->assertSee('stemcraft\\/webhooks\\/snapshot', false);
    }

    public function test_webhook_snapshot_returns_rendered_results_html(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $log = MinecraftWebhookLog::query()->create([
            'direction' => MinecraftWebhookLog::DIRECTION_OUTBOUND,
            'status' => MinecraftWebhookLog::STATUS_FAILED,
            'event' => 'player.message',
            'delivery_id' => '11111111-2222-3333-4444-555555555555',
            'method' => 'POST',
            'payload' => [
                'event' => 'player.message',
            ],
            'attempt_count' => 1,
            'last_attempted_at' => now(),
            'error_message' => 'Request timed out',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.stemcraft.webhooks.snapshot', ['status' => 'failed']));

        $response->assertOk();
        $response->assertJsonStructure(['resultsHtml']);
        $this->assertStringContainsString('player.message', (string) $response->json('resultsHtml'));
        $this->assertStringContainsString('Request timed out', (string) $response->json('resultsHtml'));
        $this->assertStringContainsString(
            'data-refresh-key="webhook-'.$log->id.'-desktop-content"',
            (string) $response->json('resultsHtml')
        );
    }
}
