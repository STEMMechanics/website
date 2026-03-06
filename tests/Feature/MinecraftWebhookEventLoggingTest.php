<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftPlayerStat;
use App\Models\MinecraftSession;
use App\Models\SiteOption;
use App\Models\User;
use App\Services\MinecraftSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftWebhookEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_event_is_persisted_with_moderation_result_for_linked_accounts(): void
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
            'event' => 'player.message',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'platform' => 'java',
            'message_type' => 'chat',
            'server_name' => 'hub-1',
            'message' => 'Hello from chat',
            'occurred_at' => now()->toIso8601String(),
            'world' => 'world',
            'x' => 10.5,
            'y' => 64.0,
            'z' => -22.25,
            'yaw' => 90,
            'pitch' => 15,
            'context' => ['channel' => 'global'],
        ];

        $response = $this->postSignedWebhook($payload, '11111111-2222-4333-8444-555555555555');
        $response->assertOk()->assertExactJson([
            'pass' => true,
            'filtered_message' => null,
            'reason' => null,
            'reason_detail' => null,
        ]);

        $this->assertDatabaseHas('minecraft_messages', [
            'minecraft_account_id' => $account->id,
            'message_type' => 'chat',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'server_name' => 'hub-1',
            'world' => 'world',
            'raw_message' => 'Hello from chat',
            'filtered_message' => null,
            'passed' => true,
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

    public function test_message_event_updates_username_for_known_uuid_and_records_audit_event(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $account = MinecraftAccount::query()->create([
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'OldName',
            'is_whitelisted' => true,
        ]);

        $payload = [
            'event' => 'player.message',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'NewName',
            'platform' => 'java',
            'message_type' => 'sign',
            'server_name' => 'survival',
            'message' => 'Welcome to our base',
            'occurred_at' => now()->toIso8601String(),
            'world' => 'world',
            'x' => 1,
            'y' => 65,
            'z' => 2,
            'context' => ['side' => 'front'],
        ];

        $response = $this->postSignedWebhook($payload, '33333333-4444-4555-8666-777777777777');

        $response->assertOk()->assertJson(['pass' => true]);
        $this->assertSame('NewName', $account->refresh()->username);
        $this->assertDatabaseHas('minecraft_event_logs', [
            'minecraft_account_id' => $account->id,
            'event' => 'player.username.changed',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'NewName',
            'server_name' => 'survival',
        ]);
    }

    public function test_profile_updated_reconciles_split_uuid_and_identity_accounts(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $user = User::factory()->create();
        $uuidAccount = MinecraftAccount::query()->create([
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => '00000000-0000-0000-0009-01f9f9705d5b',
            'username' => '.nomadjimbob',
            'is_whitelisted' => false,
            'last_seen_at' => now()->subMinutes(5),
        ]);
        $linkedAccount = MinecraftAccount::query()->create([
            'user_id' => (string) $user->id,
            'platform' => MinecraftAccount::PLATFORM_BEDROCK,
            'uuid' => null,
            'username' => 'nomadjimbob',
            'is_whitelisted' => true,
        ]);

        $session = MinecraftSession::query()->create([
            'minecraft_account_id' => (int) $uuidAccount->id,
            'session_uuid' => '11111111-2222-4333-8444-555555555555',
            'server_name' => 'STEMCraft',
            'logged_in_at' => now()->subMinutes(10),
            'logged_out_at' => null,
            'duration_seconds' => null,
        ]);

        $payload = [
            'event' => 'player.profile.updated',
            'uuid' => '00000000-0000-0000-0009-01f9f9705d5b',
            'username' => 'nomadjimbob',
            'platform' => 'bedrock',
            'occurred_at' => now()->toIso8601String(),
            'server_name' => 'STEMCraft',
        ];

        $response = $this->postSignedWebhook($payload, '19191919-2222-4333-8444-555555555555');
        $response->assertOk()->assertJson(['ok' => true]);

        $linkedAccount->refresh();
        $this->assertSame('00000000-0000-0000-0009-01f9f9705d5b', $linkedAccount->uuid);
        $this->assertNotNull($linkedAccount->last_seen_at);

        $this->assertDatabaseMissing('minecraft_accounts', [
            'id' => $uuidAccount->id,
        ]);
        $this->assertDatabaseHas('minecraft_sessions', [
            'id' => $session->id,
            'minecraft_account_id' => $linkedAccount->id,
        ]);
    }

    public function test_penalty_updated_event_persists_lift_reason_from_server(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $startedAt = now()->subHour()->startOfSecond();
        MinecraftPenalty::query()->create([
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Original ban',
            'started_at' => $startedAt,
            'ends_at' => null,
            'is_permanent' => true,
        ]);

        $payload = [
            'event' => 'player.penalty.updated',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_BAN,
            'started_at' => $startedAt->toIso8601String(),
            'occurred_at' => now()->toIso8601String(),
            'is_permanent' => true,
            'lifted_at' => now()->toIso8601String(),
            'lifted_by_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'lifted_by_username' => 'ModeratorOne',
            'lift_reason' => 'Appeal accepted in-game',
        ];

        $response = $this->postSignedWebhook($payload, '44444444-5555-4666-8777-888888888888');

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('minecraft_penalties', [
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'lifted_by_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'lifted_by_username' => 'ModeratorOne',
            'lift_reason' => 'Appeal accepted in-game',
        ]);
    }

    public function test_penalty_create_event_converts_zulu_timestamp_into_app_timezone_before_save(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $payload = [
            'event' => 'player.penalty.created',
            'uuid' => 'c7bf8530-64ce-4ca7-b601-f64bdb8d562a',
            'username' => 'nomadjimbob',
            'type' => 'ban',
            'reason' => 'Blocked chat by content filter: profanity',
            'occurred_at' => '2026-03-04T01:44:49.703051Z',
            'is_permanent' => false,
            'duration_seconds' => 604800,
            'by_uuid' => '00000000-0000-0000-0000-000000000000',
            'by_username' => '<server>',
        ];

        $response = $this->postSignedWebhook($payload, '55555555-6666-4777-8888-999999999999');

        $response->assertOk()->assertJson(['ok' => true]);

        $penalty = MinecraftPenalty::query()->where('uuid', 'c7bf8530-64ce-4ca7-b601-f64bdb8d562a')->firstOrFail();

        $this->assertSame('2026-03-04 11:44:49', $penalty->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('4 Mar 2026 11:44 am', $penalty->started_at?->format('j M Y g:i a'));
        $this->assertSame('2026-03-11 11:44:49', $penalty->ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_penalty_create_event_can_include_optional_lifted_details(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $payload = [
            'event' => 'player.penalty.created',
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'username' => 'LiftedFast',
            'type' => 'mute',
            'reason' => 'Muted and then immediately lifted by automation',
            'started_at' => '2026-03-05T00:00:00Z',
            'occurred_at' => '2026-03-05T00:00:00Z',
            'is_permanent' => false,
            'duration_seconds' => 600,
            'lifted_at' => '2026-03-05T00:00:05Z',
            'lifted_by_uuid' => '00000000-0000-0000-0000-000000000000',
            'lifted_by_username' => '<server>',
            'lift_reason' => 'Rule match cleared',
        ];

        $response = $this->postSignedWebhook($payload, 'abababab-1111-4222-8333-cdcdcdcdcdcd');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('minecraft_penalties', [
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'type' => 'mute',
            'lifted_by_username' => '<server>',
            'lift_reason' => 'Rule match cleared',
        ]);
    }

    public function test_penalty_deleted_event_soft_deletes_matching_penalty_key(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $startedAt = now()->subHour()->startOfSecond();
        MinecraftPenalty::query()->create([
            'uuid' => 'ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb',
            'username' => 'DeleteMe',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Temporary ban',
            'started_at' => $startedAt,
            'is_permanent' => true,
        ]);

        $payload = [
            'event' => 'player.penalty.deleted',
            'uuid' => 'ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb',
            'started_at' => $startedAt->toIso8601String(),
            'occurred_at' => now()->toIso8601String(),
        ];

        $response = $this->postSignedWebhook($payload, 'efefefef-1111-4222-8333-dededededede');
        $response->assertOk()->assertJson(['ok' => true]);

        $penalty = MinecraftPenalty::query()
            ->withTrashed()
            ->where('uuid', 'ffffffff-eeee-dddd-cccc-bbbbbbbbbbbb')
            ->where('type', MinecraftPenalty::TYPE_BAN)
            ->firstOrFail();
        $this->assertNotNull($penalty->deleted_at);
    }

    public function test_player_stats_sync_event_persists_server_snapshot(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        $payload = [
            'event' => 'server.sync.players.stats',
            'timestamp' => '2026-03-05T00:00:00Z',
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
            'periods' => [[
                'period' => 'month',
                'period_days' => 30,
                'players' => [
                    [
                        'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                        'username' => 'PlayerOne',
                        'platform' => 'bedrock',
                        'updated_at' => '2026-03-04T23:59:00Z',
                        'stats' => [
                            [
                                'key' => 'mob_kills',
                                'title' => 'Mob Kills',
                                'description' => 'Number of mob kills made by the player.',
                                'value' => 42,
                                'updated_at' => '2026-03-04T23:58:00Z',
                            ],
                            [
                                'key' => 'play_time',
                                'title' => 'Play Time',
                                'description' => 'Total play time recorded by the server in ticks.',
                                'value' => 72000,
                            ],
                        ],
                    ],
                ],
            ]],
        ];

        $response = $this->postSignedWebhook($payload, '66666666-7777-4888-8999-aaaaaaaaaaaa');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('minecraft_player_stats', [
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'platform' => 'bedrock',
            'username' => 'PlayerOne',
            'period' => 'month',
            'period_days' => 30,
        ]);

        $playerStat = MinecraftPlayerStat::query()
            ->where('uuid', '123e4567-e89b-12d3-a456-426614174000')
            ->where('platform', 'bedrock')
            ->where('period', 'month')
            ->firstOrFail();

        $storedStats = collect($playerStat->stats)->keyBy('key');
        $this->assertSame(42, data_get($storedStats->get('mob_kills'), 'value'));
        $this->assertSame(72000, data_get($storedStats->get('play_time'), 'value'));
        $this->assertSame('Play Time', data_get($storedStats->get('play_time'), 'title'));
        $this->assertSame('Total play time recorded by the server in ticks.', data_get($storedStats->get('play_time'), 'description'));
    }

    public function test_player_stats_sync_event_accepts_empty_players_and_clears_period_cache(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        MinecraftPlayerStat::query()->create([
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'period' => 'month',
            'period_days' => 30,
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subMinutes(30),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 42,
                'updated_at' => now()->subHour()->toIso8601String(),
            ]],
        ]);

        $payload = [
            'event' => 'server.sync.players.stats',
            'timestamp' => '2026-03-05T11:02:21.490292Z',
            'stats' => [
                [
                    'key' => 'mob_kills',
                    'title' => 'Mob Kills',
                    'description' => 'Number of mob kills made by the player.',
                ],
            ],
            'periods' => [[
                'period' => 'month',
                'period_days' => 30,
                'players' => [],
            ]],
        ];

        $response = $this->postSignedWebhook($payload, '77777777-7777-4777-8777-bbbbbbbbbbbb');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('minecraft_player_stats', [
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'period' => 'month',
        ]);
    }

    public function test_player_stats_sync_event_replaces_period_rows_and_prunes_missing_periods(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'minecraft.webhook-secret'],
            ['value' => 'shared-secret']
        );

        MinecraftPlayerStat::query()->create([
            'uuid' => 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb',
            'username' => 'OldMonthA',
            'period' => 'month',
            'period_days' => 30,
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 1,
            ]],
        ]);
        MinecraftPlayerStat::query()->create([
            'uuid' => 'cccccccc-1111-2222-3333-dddddddddddd',
            'username' => 'OldMonthB',
            'period' => 'month',
            'period_days' => 30,
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 2,
            ]],
        ]);
        MinecraftPlayerStat::query()->create([
            'uuid' => 'eeeeeeee-1111-2222-3333-ffffffffffff',
            'username' => 'OldWeek',
            'period' => 'week',
            'period_days' => 7,
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 3,
            ]],
        ]);

        $payload = [
            'event' => 'server.sync.players.stats',
            'timestamp' => '2026-03-05T12:00:00Z',
            'stats' => [
                [
                    'key' => 'mob_kills',
                    'title' => 'Mob Kills',
                    'description' => 'Number of mob kills made by the player.',
                ],
            ],
            'periods' => [[
                'period' => 'month',
                'period_days' => 30,
                'players' => [[
                    'uuid' => 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb',
                    'username' => 'FreshMonthA',
                    'platform' => 'bedrock',
                    'updated_at' => '2026-03-05T12:00:00Z',
                    'stats' => [[
                        'key' => 'mob_kills',
                        'value' => 99,
                        'updated_at' => '2026-03-05T11:59:00Z',
                    ]],
                ]],
            ]],
        ];

        $response = $this->postSignedWebhook($payload, '12121212-7777-4777-8777-bbbbbbbbbbbb');
        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('minecraft_player_stats', [
            'uuid' => 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb',
            'platform' => 'bedrock',
            'username' => 'FreshMonthA',
            'period' => 'month',
        ]);
        $this->assertDatabaseMissing('minecraft_player_stats', [
            'uuid' => 'aaaaaaaa-1111-2222-3333-bbbbbbbbbbbb',
            'platform' => 'java',
            'period' => 'month',
        ]);
        $this->assertDatabaseMissing('minecraft_player_stats', [
            'uuid' => 'cccccccc-1111-2222-3333-dddddddddddd',
            'period' => 'month',
        ]);
        $this->assertDatabaseMissing('minecraft_player_stats', [
            'uuid' => 'eeeeeeee-1111-2222-3333-ffffffffffff',
            'period' => 'week',
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
