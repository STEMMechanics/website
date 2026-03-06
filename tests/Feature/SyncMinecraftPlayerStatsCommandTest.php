<?php

namespace Tests\Feature;

use App\Models\MinecraftPlayerStat;
use App\Models\SiteOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

class SyncMinecraftPlayerStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_caches_player_stats_from_the_plugin(): void
    {
        config(['app.timezone' => 'Australia/Brisbane']);

        SiteOption::query()->create([
            'name' => 'minecraft.server-webhook-url',
            'value' => 'https://example.test/webhooks/stemcraft/server',
        ]);
        SiteOption::query()->create([
            'name' => 'minecraft.webhook-secret',
            'value' => 'shared-secret',
        ]);

        Http::fake(function (Request $request) {
            /** @var array<string, mixed>|null $payload */
            $payload = json_decode($request->body(), true);
            $period = (string) ($payload['period'] ?? 'all');
            $periodDays = match ($period) {
                'week' => 7,
                'month' => 30,
                'year' => 365,
                default => null,
            };

            return Http::response([
                'ok' => true,
                'period' => $period,
                'period_days' => $periodDays,
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
                    [
                        'key' => 'distance_walked_cm',
                        'title' => 'Distance Walked',
                        'description' => 'Distance walked by the player in centimeters.',
                    ],
                ],
                'players' => [
                    [
                        'uuid' => '123e4567-e89b-12d3-a456-426614174000',
                        'username' => 'PlayerOne',
                        'updated_at' => '2026-03-04T09:50:00Z',
                        'stats' => [
                            [
                                'key' => 'mob_kills',
                                'title' => 'Mob Kills',
                                'description' => 'Number of mob kills made by the player.',
                                'value' => 42,
                                'updated_at' => '2026-03-04T09:49:00Z',
                            ],
                            [
                                'key' => 'play_time',
                                'title' => 'Play Time',
                                'description' => 'Total play time recorded by the server in ticks.',
                                'value' => 72000,
                                'updated_at' => '2026-03-04T09:48:00Z',
                            ],
                            [
                                'key' => 'distance_walked_cm',
                                'title' => 'Distance Walked',
                                'description' => 'Distance walked by the player in centimeters.',
                                'value' => 145230,
                                'updated_at' => '2026-03-04T09:47:00Z',
                            ],
                        ],
                    ],
                ],
                'count' => 1,
                'timestamp' => '2026-03-04T09:50:40Z',
            ], 200);
        });

        $this->artisan('minecraft:player-stats:sync')
            ->expectsOutput('Minecraft player stats sync complete.')
            ->expectsOutput('Periods synced: all, week, month, year')
            ->expectsOutput('Unique players received: 1')
            ->expectsOutput('Unique players saved: 1')
            ->expectsOutput('Period snapshots received: 4')
            ->expectsOutput('Period snapshots saved: 4')
            ->expectsOutput('Response timestamp: 2026-03-04T19:50:40+10:00')
            ->assertSuccessful();

        $this->assertDatabaseHas('minecraft_player_stats', [
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'period' => 'all',
        ]);

        $this->assertSame(4, MinecraftPlayerStat::query()->count());

        $playerStat = MinecraftPlayerStat::query()
            ->where('uuid', '123e4567-e89b-12d3-a456-426614174000')
            ->where('period', 'all')
            ->firstOrFail();

        $storedStats = collect($playerStat->stats)->keyBy('key');

        $this->assertSame('Mob Kills', data_get($storedStats->get('mob_kills'), 'title'));
        $this->assertSame(42, data_get($storedStats->get('mob_kills'), 'value'));
        $this->assertSame('2026-03-04T19:49:00+10:00', data_get($storedStats->get('mob_kills'), 'updated_at'));
        $this->assertSame(72000, data_get($storedStats->get('play_time'), 'value'));
        $this->assertSame(145230, data_get($storedStats->get('distance_walked_cm'), 'value'));
        $this->assertSame('2026-03-04 19:50', $playerStat->captured_at?->format('Y-m-d H:i'));
    }
}
