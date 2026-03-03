<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftWebhookEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_event_is_persisted_for_linked_accounts(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $account = MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
        ]);

        $payload = [
            'event' => 'player.chat',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'server_name' => 'hub-1',
            'message' => 'Hello from chat',
            'occurred_at' => now()->toIso8601String(),
        ];

        $response = $this->postSignedWebhook($payload, '11111111-2222-4333-8444-555555555555');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('minecraft_event_logs', [
            'minecraft_account_id' => $account->id,
            'event' => 'player.chat',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'server_name' => 'hub-1',
            'message' => 'Hello from chat',
        ]);
    }

    public function test_gameplay_events_are_collected_even_without_a_matching_account(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $payload = [
            'event' => 'player.teleport',
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'username' => 'UnknownPlayer',
            'platform' => 'java',
            'server_name' => 'survival',
            'occurred_at' => now()->toIso8601String(),
            'to' => ['world' => 'world_nether', 'x' => 24.0, 'y' => 75.0, 'z' => -140.0],
        ];

        $response = $this->postSignedWebhook($payload, '22222222-3333-4444-8555-666666666666');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('minecraft_event_logs', [
            'event' => 'player.teleport',
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'username' => 'UnknownPlayer',
            'minecraft_account_id' => null,
            'server_name' => 'survival',
        ]);
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
