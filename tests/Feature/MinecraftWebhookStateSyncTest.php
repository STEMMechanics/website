<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftPenalty;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MinecraftWebhookStateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_sync_players_uses_minecraft_identity_but_preserves_laravel_whitelist_truth(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $existing = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'OldName',
            'is_whitelisted' => true,
        ]);
        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => null,
            'username' => 'SiteOnlyPlayer',
            'is_whitelisted' => true,
        ]);

        $payload = [
            'event' => 'server.sync.players',
            'server_name' => 'survival',
            'reason' => 'startup',
            'plugin_version' => '1.2.3',
            'players' => [
                [
                    'uuid' => '11111111-2222-3333-4444-555555555555',
                    'username' => 'RenamedInMinecraft',
                    'platform' => 'java',
                    'is_whitelisted' => false,
                    'updated_at' => '2026-03-05T11:30:00Z',
                ],
                [
                    'uuid' => '66666666-7777-8888-9999-000000000000',
                    'username' => 'NewFromMinecraft',
                    'platform' => 'java',
                    'is_whitelisted' => true,
                    'updated_at' => '2026-03-05T11:31:00Z',
                ],
                [
                    'uuid' => null,
                    'username' => 'SiteOnlyPlayer',
                    'platform' => 'java',
                    'is_whitelisted' => false,
                    'updated_at' => '2026-03-05T11:32:00Z',
                ],
            ],
        ];

        $response = $this->postSignedWebhook(route('webhook.stemcraft.server'), $payload, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $response->assertOk()->assertJson([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'request' => [
                    'server_name' => 'survival',
                    'reason' => 'startup',
                    'plugin_version' => '1.2.3',
                ],
                'counts' => [
                    'players_received' => 3,
                    'players_total' => 3,
                    'whitelisted_players' => 2,
                ],
            ],
        ]);

        $existing->refresh();
        $this->assertSame('RenamedInMinecraft', $existing->username);
        $this->assertTrue($existing->is_whitelisted, 'Laravel should remain source-of-truth for whitelist.');

        $this->assertDatabaseHas('minecraft_accounts', [
            'uuid' => '66666666-7777-8888-9999-000000000000',
            'username' => 'NewFromMinecraft',
            'platform' => 'java',
            'is_whitelisted' => false,
        ]);
        $siteOnly = MinecraftAccount::query()
            ->where('username', 'SiteOnlyPlayer')
            ->where('platform', 'java')
            ->firstOrFail();
        $this->assertTrue($siteOnly->uuid === null || $siteOnly->uuid === '');

        $players = collect($response->json('sync.players', []));
        $this->assertCount(3, $players);
        $this->assertTrue($players->contains(fn (array $player): bool => ($player['username'] ?? null) === 'SiteOnlyPlayer'));
        $this->assertTrue($players->contains(fn (array $player): bool => ($player['username'] ?? null) === 'RenamedInMinecraft' && ($player['is_whitelisted'] ?? null) === true));
        $this->assertTrue($players->contains(fn (array $player): bool => ($player['username'] ?? null) === 'NewFromMinecraft' && ($player['is_whitelisted'] ?? null) === false));
    }

    public function test_server_sync_players_ignores_stale_identity_rows_based_on_updated_at(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $existing = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'username' => 'CurrentName',
            'is_whitelisted' => true,
            'last_seen_at' => now(),
        ]);

        $payload = [
            'event' => 'server.sync.players',
            'players' => [
                [
                    'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                    'username' => 'StaleOldName',
                    'platform' => 'java',
                    'updated_at' => now()->subDay()->toIso8601String(),
                ],
            ],
        ];

        $response = $this->postSignedWebhook(route('webhook.stemcraft.server'), $payload, 'adadadad-1111-2222-3333-bcbcbcbcbcbc');
        $response->assertOk()->assertJson([
            'ok' => true,
            'sync' => [
                'counts' => [
                    'players_received' => 1,
                ],
            ],
        ]);

        $existing->refresh();
        $this->assertSame('CurrentName', $existing->username);
    }

    public function test_server_sync_penalties_reconciles_by_updated_at_and_returns_authoritative_snapshot(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $uuid = '11111111-2222-3333-4444-555555555555';

        $startedLocalNewer = Carbon::parse('2026-03-05T10:00:00Z')->setTimezone((string) config('app.timezone'));
        $startedPluginNewer = Carbon::parse('2026-03-05T10:10:00Z')->setTimezone((string) config('app.timezone'));
        $startedDeleted = Carbon::parse('2026-03-05T10:20:00Z')->setTimezone((string) config('app.timezone'));
        $startedNew = Carbon::parse('2026-03-05T10:30:00Z')->setTimezone((string) config('app.timezone'));

        $localNewer = MinecraftPenalty::query()->create([
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Laravel newer reason',
            'started_at' => $startedLocalNewer,
            'is_permanent' => true,
        ]);
        DB::table('minecraft_penalties')->where('id', $localNewer->id)->update([
            'created_at' => '2026-03-05 20:00:00',
            'updated_at' => '2026-03-05 20:00:00',
        ]);

        $pluginNewerTarget = MinecraftPenalty::query()->create([
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_MUTE,
            'reason' => 'Laravel old reason',
            'started_at' => $startedPluginNewer,
            'duration_seconds' => 120,
            'is_permanent' => false,
        ]);
        DB::table('minecraft_penalties')->where('id', $pluginNewerTarget->id)->update([
            'created_at' => '2026-03-05 19:00:00',
            'updated_at' => '2026-03-05 19:00:00',
        ]);

        $deletedLocal = MinecraftPenalty::query()->create([
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Already deleted in Laravel',
            'started_at' => $startedDeleted,
            'is_permanent' => true,
        ]);
        DB::table('minecraft_penalties')->where('id', $deletedLocal->id)->update([
            'deleted_at' => '2026-03-05 19:20:00',
            'created_at' => '2026-03-05 19:00:00',
            'updated_at' => '2026-03-05 19:20:00',
        ]);

        $payload = [
            'event' => 'server.sync.penalties',
            'starting_from' => '2026-03-05T00:00:00Z',
            'penalties' => [
                [
                    'uuid' => $uuid,
                    'username' => 'PlayerOne',
                    'type' => 'ban',
                    'reason' => 'Plugin older reason',
                    'started_at' => $startedLocalNewer->toIso8601String(),
                    'is_permanent' => true,
                    'updated_at' => '2026-03-05T09:00:00Z',
                ],
                [
                    'uuid' => $uuid,
                    'username' => 'PlayerOne',
                    'type' => 'mute',
                    'reason' => 'Plugin newer reason',
                    'duration_seconds' => 600,
                    'started_at' => $startedPluginNewer->toIso8601String(),
                    'is_permanent' => false,
                    'updated_at' => '2026-03-05T21:00:00Z',
                ],
                [
                    'uuid' => $uuid,
                    'username' => 'PlayerOne',
                    'type' => 'kick',
                    'reason' => 'Plugin new record',
                    'started_at' => $startedNew->toIso8601String(),
                    'is_permanent' => false,
                    'updated_at' => '2026-03-05T21:10:00Z',
                ],
                [
                    'uuid' => $uuid,
                    'username' => 'PlayerOne',
                    'type' => 'ban',
                    'reason' => 'Plugin confirms delete',
                    'started_at' => $startedDeleted->toIso8601String(),
                    'is_permanent' => true,
                    'deleted_at' => '2026-03-05T21:20:00Z',
                    'updated_at' => '2026-03-05T21:20:00Z',
                ],
            ],
        ];

        $response = $this->postSignedWebhook(route('webhook.stemcraft.server'), $payload, 'ffffffff-1111-2222-3333-444444444444');
        $response->assertOk()->assertJson([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'counts' => [
                    'penalties_received' => 4,
                    'penalties_added' => 1,
                    'penalties_updated' => 2,
                    'penalties_ignored' => 1,
                ],
            ],
        ]);
        $this->assertNotNull($response->json('sync.starting_from'));

        $this->assertDatabaseHas('minecraft_penalties', [
            'uuid' => $uuid,
            'started_at' => $startedLocalNewer->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'reason' => 'Laravel newer reason',
        ]);
        $this->assertDatabaseHas('minecraft_penalties', [
            'uuid' => $uuid,
            'started_at' => $startedPluginNewer->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'reason' => 'Plugin newer reason',
            'duration_seconds' => 600,
        ]);
        $this->assertDatabaseHas('minecraft_penalties', [
            'uuid' => $uuid,
            'started_at' => $startedNew->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'type' => 'kick',
            'reason' => 'Plugin new record',
        ]);

        $deleted = MinecraftPenalty::query()->withTrashed()
            ->where('uuid', $uuid)
            ->whereBetween('started_at', [
                $startedDeleted->copy()->setTimezone(config('app.timezone'))->startOfSecond(),
                $startedDeleted->copy()->setTimezone(config('app.timezone'))->endOfSecond(),
            ])
            ->firstOrFail();
        $this->assertNotNull($deleted->deleted_at);

        $returned = collect($response->json('sync.penalties', []));
        $this->assertCount(4, $returned);
        $this->assertTrue($returned->contains(fn (array $penalty): bool => ($penalty['reason'] ?? null) === 'Laravel newer reason'));
        $this->assertTrue($returned->contains(fn (array $penalty): bool => ($penalty['reason'] ?? null) === 'Plugin newer reason'));
        $this->assertTrue($returned->contains(fn (array $penalty): bool => ($penalty['reason'] ?? null) === 'Plugin new record'));
        $this->assertTrue($returned->contains(fn (array $penalty): bool => ($penalty['deleted_at'] ?? null) !== null));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(string $url, array $payload, string $deliveryId)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($body);

        $timestamp = (string) time();
        $signature = MinecraftSyncService::signPayload($body, $timestamp, 'shared-secret');

        return $this->withHeaders([
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
            'X-Minecraft-Delivery-Id' => $deliveryId,
        ])->postJson($url, $payload);
    }
}
