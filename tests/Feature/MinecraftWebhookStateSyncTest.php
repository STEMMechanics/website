<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftBlacklistEntry;
use App\Models\MinecraftPenalty;
use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftWebhookStateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_sync_request_returns_authoritative_state_snapshot(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $accountWhitelisted = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'is_whitelisted' => true,
        ]);
        MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_JAVA,
            'uuid' => '66666666-7777-8888-9999-000000000000',
            'username' => 'NotWhitelistedPlayer',
            'is_whitelisted' => false,
        ]);

        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'external_id' => 'penalty-active-ban',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Active permanent ban',
            'duration_seconds' => null,
            'started_at' => now()->subMinutes(30),
            'ends_at' => null,
            'is_permanent' => true,
            'lifted_at' => null,
        ]);
        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'external_id' => 'penalty-active-mute',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'type' => MinecraftPenalty::TYPE_MUTE,
            'reason' => 'Active timed mute',
            'duration_seconds' => 3600,
            'started_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
            'is_permanent' => false,
            'lifted_at' => null,
        ]);
        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'external_id' => 'penalty-expired-mute',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'type' => MinecraftPenalty::TYPE_MUTE,
            'reason' => 'Expired mute',
            'duration_seconds' => 60,
            'started_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'is_permanent' => false,
            'lifted_at' => null,
        ]);
        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'external_id' => 'penalty-lifted-ban',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Lifted ban',
            'duration_seconds' => null,
            'started_at' => now()->subHour(),
            'ends_at' => null,
            'is_permanent' => true,
            'lifted_at' => now()->subMinutes(5),
        ]);
        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'external_id' => 'penalty-kick',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'type' => MinecraftPenalty::TYPE_KICK,
            'reason' => 'Kick should not be in snapshot',
            'duration_seconds' => null,
            'started_at' => now()->subMinutes(2),
            'ends_at' => now()->subMinutes(2),
            'is_permanent' => false,
            'lifted_at' => null,
        ]);

        MinecraftBlacklistEntry::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'reason' => 'Legacy active blacklist',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_permanent' => true,
            'lifted_at' => null,
        ]);
        MinecraftBlacklistEntry::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'reason' => 'Legacy lifted blacklist',
            'starts_at' => now()->subDays(2),
            'ends_at' => null,
            'is_permanent' => true,
            'lifted_at' => now()->subDay(),
        ]);
        MinecraftBlacklistEntry::query()->create([
            'minecraft_account_id' => $accountWhitelisted->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'username' => 'WhitelistedPlayer',
            'reason' => 'Legacy expired blacklist',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'is_permanent' => false,
            'lifted_at' => null,
        ]);

        $payload = [
            'event' => 'server.sync.request',
            'server_name' => 'survival',
            'reason' => 'startup',
            'plugin_version' => '1.0.0',
        ];

        $response = $this->postSignedWebhook(route('webhook.stemcraft.server'), $payload, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $response->assertOk()->assertJson([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'counts' => [
                    'whitelisted_accounts' => 1,
                    'active_penalties' => 2,
                    'active_legacy_blacklist' => 1,
                ],
                'request' => [
                    'server_name' => 'survival',
                    'reason' => 'startup',
                    'plugin_version' => '1.0.0',
                ],
            ],
        ]);

        $accounts = collect($response->json('sync.accounts', []));
        $this->assertCount(1, $accounts);
        $this->assertTrue($accounts->contains(fn (array $account): bool => ($account['username'] ?? null) === 'WhitelistedPlayer'));
        $this->assertFalse($accounts->contains(fn (array $account): bool => ($account['username'] ?? null) === 'NotWhitelistedPlayer'));

        $penalties = collect($response->json('sync.penalties', []));
        $this->assertCount(2, $penalties);
        $this->assertTrue($penalties->contains(fn (array $penalty): bool => ($penalty['external_id'] ?? null) === 'penalty-active-ban'));
        $this->assertTrue($penalties->contains(fn (array $penalty): bool => ($penalty['external_id'] ?? null) === 'penalty-active-mute'));
        $this->assertFalse($penalties->contains(fn (array $penalty): bool => ($penalty['external_id'] ?? null) === 'penalty-expired-mute'));
        $this->assertFalse($penalties->contains(fn (array $penalty): bool => ($penalty['external_id'] ?? null) === 'penalty-lifted-ban'));
        $this->assertFalse($penalties->contains(fn (array $penalty): bool => ($penalty['external_id'] ?? null) === 'penalty-kick'));

        $legacyBlacklist = collect($response->json('sync.legacy_blacklist', []));
        $this->assertCount(1, $legacyBlacklist);
        $this->assertTrue($legacyBlacklist->contains(fn (array $entry): bool => ($entry['reason'] ?? null) === 'Legacy active blacklist'));
    }

    public function test_server_sync_request_works_on_minecraft_webhook_alias_route(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $payload = [
            'event' => 'server.sync.request',
            'reason' => 'manual',
        ];

        $response = $this->postSignedWebhook(route('webhook.minecraft.server'), $payload, 'ffffffff-1111-2222-3333-444444444444');
        $response->assertOk()->assertJson([
            'ok' => true,
            'sync' => [
                'mode' => 'replace',
                'counts' => [
                    'whitelisted_accounts' => 0,
                    'active_penalties' => 0,
                    'active_legacy_blacklist' => 0,
                ],
            ],
        ]);
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
