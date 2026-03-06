<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use App\Services\MinecraftWebhookBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MinecraftWebhookBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_request_is_signed_and_logged(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/webhooks/stemcraft/server',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake([
            'https://example.test/webhooks/stemcraft/server' => Http::response([
                'ok' => true,
                'status' => [
                    'server_name' => 'STEMCraft',
                    'minecraft_version' => '1.21.11',
                    'players' => ['online' => 0, 'max' => 20],
                    'memory' => ['used_bytes' => 1, 'max_bytes' => 2],
                    'loaded_chunks' => 0,
                    'worlds' => [],
                    'tps' => ['one_minute' => 20.0, 'five_minute' => 20.0, 'fifteen_minute' => 20.0],
                    'timestamp' => '2026-03-04T12:34:56Z',
                ],
            ], 200),
        ]);

        $status = app(MinecraftWebhookBridgeService::class)->requestStatus();

        $this->assertSame('STEMCraft', $status['server_name']);

        Http::assertSent(function ($request): bool {
            $timestamp = (string) $request->header('X-Minecraft-Timestamp')[0];
            $signature = (string) $request->header('X-Minecraft-Signature')[0];
            $body = $request->body();
            $decoded = json_decode($body, true);

            return $request->url() === 'https://example.test/webhooks/stemcraft/server'
                && str_contains($body, '"event":"server.status.request"')
                && is_array($decoded)
                && is_string($decoded['occurred_at'] ?? null)
                && trim((string) $decoded['occurred_at']) !== ''
                && hash_equals(MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret'), $signature);
        });

        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'server.status.request',
            'status' => 'delivered',
            'response_status' => 200,
        ]);
    }

    public function test_player_stats_request_is_signed_with_uuid_and_period_filters(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/webhooks/stemcraft/server',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake([
            'https://example.test/webhooks/stemcraft/server' => Http::response([
                'ok' => true,
                'period' => 'month',
                'period_days' => 30,
                'stats' => [
                    [
                        'key' => 'mob_kills',
                        'title' => 'Mob Kills',
                        'description' => 'Number of mob kills made by the player.',
                    ],
                    [
                        'key' => 'play_time',
                        'title' => 'Play Time',
                        'description' => 'Total play time recorded by the server in ticks.',
                    ],
                ],
                'players' => [
                    [
                        'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                        'username' => 'PlayerOne',
                        'updated_at' => '2026-03-04T14:10:00+10:00',
                        'stats' => [[
                            'key' => 'mob_kills',
                            'title' => 'Mob Kills',
                            'description' => 'Number of mob kills made by the player.',
                            'value' => 42,
                            'updated_at' => '2026-03-04T14:09:00+10:00',
                        ]],
                    ],
                ],
                'count' => 1,
                'timestamp' => '2026-03-04T14:10:00+10:00',
            ], 200),
        ]);

        $response = app(MinecraftWebhookBridgeService::class)->requestPlayerStats(
            uuid: '123e4567-e89b-12d3-a456-426614174000',
            period: 'month',
        );

        $this->assertSame(1, $response['count']);
        $this->assertSame('month', $response['period']);
        $this->assertSame('PlayerOne', $response['players'][0]['username']);

        Http::assertSent(function ($request): bool {
            $timestamp = (string) $request->header('X-Minecraft-Timestamp')[0];
            $signature = (string) $request->header('X-Minecraft-Signature')[0];
            $body = $request->body();
            $decoded = json_decode($body, true);

            return $request->url() === 'https://example.test/webhooks/stemcraft/server'
                && str_contains($body, '"event":"server.player-stats.request"')
                && str_contains($body, '"uuid":"123e4567-e89b-12d3-a456-426614174000"')
                && str_contains($body, '"period":"month"')
                && is_array($decoded)
                && is_string($decoded['occurred_at'] ?? null)
                && trim((string) $decoded['occurred_at']) !== ''
                && hash_equals(MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret'), $signature);
        });

        $this->assertDatabaseHas('minecraft_webhook_logs', [
            'direction' => 'outbound',
            'event' => 'server.player-stats.request',
            'status' => 'delivered',
            'response_status' => 200,
        ]);
    }
}
