<?php

namespace Tests\Feature;

use App\Models\MinecraftPlayerStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StemcraftStatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_stats_page_renders_leaderboards_from_cached_stats(): void
    {
        MinecraftPlayerStat::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'username' => 'PlayerOne',
            'period' => 'all',
            'captured_at' => now()->subHours(2),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 42,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'play_time',
                'title' => 'Play Time',
                'description' => 'Total play time recorded by the server in ticks.',
                'value' => 72000,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'fish_caught',
                'title' => 'Fish Caught',
                'description' => 'Number of fish caught by the player.',
                'value' => 3,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ]],
        ]);

        MinecraftPlayerStat::query()->create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'platform' => 'bedrock',
            'username' => 'PlayerTwo',
            'period' => 'all',
            'captured_at' => now()->subHours(2),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 120,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'play_time',
                'title' => 'Play Time',
                'description' => 'Total play time recorded by the server in ticks.',
                'value' => 144000,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'fish_caught',
                'title' => 'Fish Caught',
                'description' => 'Number of fish caught by the player.',
                'value' => 8,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'bucket_fills',
                'title' => 'Bucket Fills',
                'description' => 'Number of times the player filled a bucket with water or lava.',
                'value' => 6,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'quests_completed',
                'title' => 'Quests Completed',
                'description' => 'Completed quests tracked by another plugin.',
                'value' => 7,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ]],
        ]);

        $response = $this->get(route('stemcraft.leaderboards'));

        $response->assertOk();
        $response->assertSeeText('Tracked players');
        $response->assertSeeText('2');
        $response->assertDontSeeText('Hours played');
        $response->assertSeeText('Mob Kills');
        $response->assertSeeTextInOrder(['PlayerTwo', '120', 'PlayerOne', '42']);
        $response->assertSeeText('PlayerTwo(Bedrock)');
        $response->assertSeeText('Play Time');
        $response->assertSeeText('2h');
        $response->assertSeeText('Fish Caught');
        $response->assertSeeTextInOrder(['PlayerTwo', '8', 'PlayerOne', '3']);
        $response->assertSeeText('Bucket Fills');
        $response->assertSeeText('6');
        $response->assertSeeText('Quests Completed');
    }

    public function test_public_stats_page_can_switch_to_a_cached_period(): void
    {
        MinecraftPlayerStat::query()->create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'username' => 'WeeklyOne',
            'period' => 'month',
            'period_days' => 30,
            'captured_at' => now()->subHours(3),
            'fetched_at' => now()->subHours(2),
            'stats' => [[
                'key' => 'blocks_broken_survival',
                'title' => 'Blocks Broken in Survival',
                'description' => 'Number of blocks broken while in survival mode.',
                'value' => 98,
                'updated_at' => now()->subHours(3)->toIso8601String(),
            ]],
        ]);

        MinecraftPlayerStat::query()->create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'username' => 'WeeklyTwo',
            'period' => 'month',
            'period_days' => 30,
            'captured_at' => now()->subHours(3),
            'fetched_at' => now()->subHours(2),
            'stats' => [[
                'key' => 'blocks_broken_survival',
                'title' => 'Blocks Broken in Survival',
                'description' => 'Number of blocks broken while in survival mode.',
                'value' => 120,
                'updated_at' => now()->subHours(3)->toIso8601String(),
            ]],
        ]);

        $response = $this->get(route('stemcraft.leaderboards', ['period' => 'month']));

        $response->assertOk();
        $response->assertSeeText('Last 30 days');
        $response->assertSeeText('Blocks Broken in Survival');
        $response->assertSeeTextInOrder(['WeeklyTwo', '120', 'WeeklyOne', '98']);
        $response->assertDontSeeText('Hours played');
    }

    public function test_public_stats_page_formats_play_time_when_server_reports_hours(): void
    {
        MinecraftPlayerStat::query()->create([
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'username' => 'HourlyPlayer',
            'period' => 'all',
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subMinutes(30),
            'stats' => [[
                'key' => 'play_time',
                'title' => 'Play Time',
                'description' => 'Total play time recorded by the server in hours.',
                'value' => 0.2929583333333333,
                'updated_at' => now()->subHour()->toIso8601String(),
            ]],
        ]);

        $response = $this->get(route('stemcraft.leaderboards'));

        $response->assertOk();
        $response->assertSeeText('Play Time');
        $response->assertSeeText('17m 35s');
        $response->assertDontSeeText('0s');
    }

    public function test_public_stats_page_formats_time_in_bucket_stats_as_hours(): void
    {
        MinecraftPlayerStat::query()->create([
            'uuid' => '66666666-6666-6666-6666-666666666666',
            'username' => 'BiomePlayer',
            'period' => 'all',
            'captured_at' => now()->subHour(),
            'fetched_at' => now()->subMinutes(30),
            'stats' => [[
                'key' => 'time_in_nether',
                'title' => 'Time In Nether',
                'description' => 'Total time spent in the Nether.',
                'value' => 0.75,
                'updated_at' => now()->subHour()->toIso8601String(),
            ]],
        ]);

        $response = $this->get(route('stemcraft.leaderboards'));

        $response->assertOk();
        $response->assertSeeText('Time In Nether');
        $response->assertSeeText('45m');
        $response->assertDontSeeText('0.75');
    }
}
