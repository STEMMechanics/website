<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Services\MinecraftSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MinecraftWebhookSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('site_options')) {
            Schema::create('site_options', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('minecraft_accounts')) {
            Schema::create('minecraft_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id')->nullable();
                $table->string('platform', 20);
                $table->string('uuid', 64)->nullable();
                $table->string('username', 80);
                $table->boolean('is_whitelisted')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamp('last_logout_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('minecraft_penalties')) {
            Schema::create('minecraft_penalties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('minecraft_account_id')->nullable();
                $table->string('external_id', 120)->nullable()->unique();
                $table->string('uuid', 64)->nullable();
                $table->string('username', 80);
                $table->string('type', 20);
                $table->text('reason')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_permanent')->default(false);
                $table->string('by_uuid', 64)->nullable();
                $table->uuid('by_user_id')->nullable();
                $table->string('by_username', 80)->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->string('lifted_by_uuid', 64)->nullable();
                $table->uuid('lifted_by_user_id')->nullable();
                $table->string('lifted_by_username', 80)->nullable();
                $table->text('lift_reason')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        SiteOption::query()->where('name', 'minecraft.webhook-secret')->delete();
    }

    protected function tearDown(): void
    {
        SiteOption::query()->where('name', 'minecraft.webhook-secret')->delete();

        parent::tearDown();
    }

    public function test_minecraft_webhook_rejects_replayed_delivery_ids(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $payload = json_encode([
            'event' => 'player.profile.updated',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($payload);

        $timestamp = (string) time();
        $deliveryId = '9d4d8d8e-8d47-4db1-9415-a4b67a5b1c77';
        $signature = MinecraftSyncService::signPayload($payload, $timestamp, 'shared-secret');
        $headers = [
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
            'X-Minecraft-Delivery-Id' => $deliveryId,
        ];

        $firstResponse = $this->withHeaders($headers)->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));
        $firstResponse->assertOk()->assertJson(['ok' => true]);

        $secondResponse = $this->withHeaders($headers)->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));
        $secondResponse->assertStatus(409)->assertJson(['ok' => false, 'error' => 'replay_detected']);
    }

    public function test_minecraft_webhook_ignores_duplicate_event_ids_with_new_delivery_ids(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $eventId = '11111111-2222-4333-8444-555555555555';
        $payload = [
            'event' => 'server.health.ping',
            'event_id' => $eventId,
            'server_name' => 'survival',
            'plugin_version' => '2.5.0',
            'queue_depth' => 12,
        ];

        $firstResponse = $this->postSignedMinecraftWebhook($payload, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $firstResponse->assertOk()->assertJson([
            'ok' => true,
            'event' => 'server.health.pong',
        ]);

        $secondResponse = $this->postSignedMinecraftWebhook($payload, 'ffffffff-1111-2222-3333-444444444444');
        $secondResponse->assertOk()->assertJson([
            'ok' => true,
            'ignored' => true,
            'reason' => 'duplicate_event_id',
        ]);
    }

    public function test_minecraft_webhook_requires_a_delivery_id(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $payload = json_encode([
            'event' => 'player.profile.updated',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($payload);

        $timestamp = (string) time();
        $signature = MinecraftSyncService::signPayload($payload, $timestamp, 'shared-secret');

        $response = $this->withHeaders([
            'X-Minecraft-Timestamp' => $timestamp,
            'X-Minecraft-Signature' => $signature,
        ])->postJson(route('webhook.stemcraft.server'), json_decode($payload, true));

        $response->assertStatus(409)->assertJson(['ok' => false, 'error' => 'replay_detected']);
    }

    public function test_minecraft_webhook_ignores_stale_penalty_create_after_lift_for_same_penalty_key(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $startedAt = now()->subMinutes(10)->startOfSecond();
        $liftedAt = now()->subMinute()->startOfSecond();

        $updatePayload = [
            'event' => 'player.penalty.updated',
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => 'ban',
            'started_at' => $startedAt->toIso8601String(),
            'is_permanent' => true,
            'lifted_at' => $liftedAt->toIso8601String(),
            'occurred_at' => $liftedAt->toIso8601String(),
        ];
        $createPayload = [
            'event' => 'player.penalty.created',
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => 'ban',
            'started_at' => $startedAt->toIso8601String(),
            'occurred_at' => $startedAt->toIso8601String(),
            'is_permanent' => true,
        ];

        $firstResponse = $this->postSignedMinecraftWebhook($updatePayload, '11111111-2222-3333-4444-555555555555');
        $firstResponse->assertOk()->assertJson(['ok' => true]);

        $secondResponse = $this->postSignedMinecraftWebhook($createPayload, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $secondResponse->assertOk()->assertJson(['ok' => true, 'ignored' => true]);

        $penalty = Model::resolveConnection()
            ->table('minecraft_penalties')
            ->where('uuid', $uuid)
            ->where('started_at', $startedAt)
            ->first();
        $this->assertNotNull($penalty);
        $this->assertSame('ban', $penalty->type);
        $this->assertSame($uuid, $penalty->uuid);
        $this->assertNotNull($penalty->lifted_at);
    }

    public function test_minecraft_webhook_ignores_stale_penalty_update_by_updated_at(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $startedAt = now()->subMinutes(10)->startOfSecond();

        $insertedId = Model::resolveConnection()->table('minecraft_penalties')->insertGetId([
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => 'ban',
            'reason' => 'Current reason',
            'started_at' => $startedAt,
            'is_permanent' => true,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $this->assertGreaterThan(0, $insertedId);

        $updatePayload = [
            'event' => 'player.penalty.updated',
            'uuid' => $uuid,
            'username' => 'PlayerOne',
            'type' => 'ban',
            'reason' => 'Older reason should be ignored',
            'started_at' => $startedAt->toIso8601String(),
            'is_permanent' => true,
            'updated_at' => now()->subHours(2)->toIso8601String(),
            'occurred_at' => now()->subHours(2)->toIso8601String(),
        ];

        $response = $this->postSignedMinecraftWebhook($updatePayload, '99999999-aaaa-bbbb-cccc-dddddddddddd');
        $response->assertOk()->assertJson([
            'ok' => true,
            'ignored' => true,
        ]);

        $penalty = Model::resolveConnection()
            ->table('minecraft_penalties')
            ->where('uuid', $uuid)
            ->where('started_at', $startedAt)
            ->first();

        $this->assertNotNull($penalty);
        $this->assertSame('Current reason', $penalty->reason);
    }

    public function test_minecraft_webhook_server_health_ping_returns_pong_shape(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $payload = [
            'event' => 'server.health.ping',
            'server_name' => 'survival',
            'plugin_version' => '2.5.0',
            'queue_depth' => 3,
        ];

        $response = $this->postSignedMinecraftWebhook($payload, 'abababab-1111-2222-3333-cdcdcdcdcdcd');
        $response->assertOk()->assertJson([
            'ok' => true,
            'event' => 'server.health.pong',
            'capabilities' => [
                'supports_event_id' => true,
            ],
            'request' => [
                'server_name' => 'survival',
                'plugin_version' => '2.5.0',
                'queue_depth' => 3,
            ],
        ]);
        $this->assertIsArray($response->json('sync.last_inbound_sync_at'));
        $this->assertIsArray($response->json('sync.required'));
    }

    public function test_minecraft_webhook_rejects_deprecated_or_unknown_events(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        $deprecatedLiftPayload = [
            'event' => 'player.penalty.lifted',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'type' => 'ban',
            'occurred_at' => now()->toIso8601String(),
        ];
        $legacySyncPayload = [
            'event' => 'server.sync.request',
            'reason' => 'startup',
        ];
        $legacyStatsPayload = [
            'event' => 'server.player-stats.sync',
            'period' => 'month',
            'players' => [],
        ];
        $legacyBlacklistSyncPayload = [
            'event' => 'blacklist.sync',
            'blacklist' => [
                'username' => 'PlayerOne',
            ],
        ];
        $legacyBlacklistRemovePayload = [
            'event' => 'blacklist.remove',
            'blacklist' => [
                'username' => 'PlayerOne',
            ],
        ];
        $legacyAccountSyncPayload = [
            'event' => 'account.sync',
            'account' => [
                'username' => 'PlayerOne',
                'platform' => 'java',
            ],
        ];
        $legacyAccountRemovePayload = [
            'event' => 'account.remove',
            'account' => [
                'username' => 'PlayerOne',
                'platform' => 'java',
            ],
        ];

        $deprecatedResponse = $this->postSignedMinecraftWebhook($deprecatedLiftPayload, 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb');
        $deprecatedResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacySyncResponse = $this->postSignedMinecraftWebhook($legacySyncPayload, 'cccccccc-1111-2222-3333-dddddddddddd');
        $legacySyncResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacyStatsResponse = $this->postSignedMinecraftWebhook($legacyStatsPayload, 'eeeeeeee-1111-2222-3333-ffffffffffff');
        $legacyStatsResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacyBlacklistSyncResponse = $this->postSignedMinecraftWebhook($legacyBlacklistSyncPayload, '11111111-2222-4333-8444-555555555555');
        $legacyBlacklistSyncResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacyBlacklistRemoveResponse = $this->postSignedMinecraftWebhook($legacyBlacklistRemovePayload, '66666666-7777-4888-8999-000000000000');
        $legacyBlacklistRemoveResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacyAccountSyncResponse = $this->postSignedMinecraftWebhook($legacyAccountSyncPayload, 'aaaaaaaa-2222-4333-8444-bbbbbbbbbbbb');
        $legacyAccountSyncResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);

        $legacyAccountRemoveResponse = $this->postSignedMinecraftWebhook($legacyAccountRemovePayload, 'cccccccc-2222-4333-8444-dddddddddddd');
        $legacyAccountRemoveResponse
            ->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'error' => 'unknown_event',
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedMinecraftWebhook(array $payload, string $deliveryId)
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
