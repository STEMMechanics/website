<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftMessage;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftWebhookContentFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_event_blocks_content_and_returns_masked_message_for_profanity(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );
        MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
        ]);

        $payload = [
            'event' => 'player.message',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'message_type' => 'chat',
            'message' => 'This contains fuck and should fail.',
            'server_name' => 'hub-1',
            'occurred_at' => now()->toIso8601String(),
            'world' => 'world',
            'x' => 0,
            'y' => 64,
            'z' => 0,
        ];

        $response = $this->postSignedWebhook($payload, '11111111-2222-4333-8444-555555555555');

        $response->assertOk()->assertJson([
            'pass' => false,
            'filtered_message' => 'This contains **** and should fail.',
            'reason' => 'profanity',
            'reason_detail' => null,
        ]);
        $this->assertDatabaseHas('minecraft_messages', [
            'message_type' => 'chat',
            'username' => 'PlayerOne',
            'passed' => false,
            'failure_reason' => 'profanity',
            'filtered_message' => 'This contains **** and should fail.',
        ]);
    }

    public function test_message_event_records_custom_regex_failures_without_filtered_message(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => 'moderation.content-filter.custom-patterns'],
            ['value' => '\bfck\b']
        );

        $payload = [
            'event' => 'player.message',
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'username' => 'PatternPlayer',
            'platform' => 'java',
            'message_type' => 'book',
            'message' => 'This includes fck directly.',
            'server_name' => 'survival',
            'occurred_at' => now()->toIso8601String(),
            'world' => 'world_nether',
            'x' => 5,
            'y' => 66,
            'z' => -5,
        ];

        $response = $this->postSignedWebhook($payload, '22222222-3333-4444-8555-666666666666');

        $response->assertOk()->assertJson([
            'pass' => false,
            'filtered_message' => null,
            'reason' => 'custom_regex',
            'reason_detail' => '\bfck\b',
        ]);
        $message = MinecraftMessage::query()->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('custom_regex', $message->failure_reason);
        $this->assertSame('\bfck\b', $message->failure_detail);
        $this->assertNull($message->filtered_message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload, string $deliveryId)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($body);

        $timestamp = (string) time();
        $signature = MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret');

        return $this->withHeaders([
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
            'X-Minecraft-Delivery-Id' => $deliveryId,
        ])->postJson(route('webhook.stemcraft.server'), $payload);
    }
}
