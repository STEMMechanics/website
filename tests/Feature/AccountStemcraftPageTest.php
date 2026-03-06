<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\MinecraftPenalty;
use App\Models\MinecraftPlayerStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStemcraftPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifted_penalties_are_shown_as_lifted_in_account_history(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Minecraft',
            'surname' => 'Member',
        ]);
        $user->groups()->create(['slug' => 'minecraft']);

        $account = MinecraftAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
        ]);

        MinecraftPenalty::query()->create([
            'minecraft_account_id' => $account->id,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'type' => MinecraftPenalty::TYPE_BAN,
            'reason' => 'Blocked chat by content filter: profanity',
            'started_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
            'is_permanent' => false,
            'by_username' => '<server>',
            'lifted_at' => now()->subDay(),
            'lifted_by_username' => 'Admin User',
            'lift_reason' => 'Appeal accepted',
        ]);

        $response = $this->actingAs($user)->get(route('account.stemcraft.index'));

        $response->assertOk();
        $response->assertSeeText('Lifted');
        $response->assertSeeText('Appeal accepted');
        $response->assertDontSeeText('Permanent');
    }

    public function test_cached_player_stats_are_shown_on_the_account_page(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'minecraft']);

        $account = MinecraftAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
        ]);

        MinecraftPlayerStat::query()->create([
            'uuid' => $account->uuid,
            'username' => $account->username,
            'period' => 'all',
            'captured_at' => now()->subHours(2),
            'fetched_at' => now()->subHour(),
            'stats' => [[
                'key' => 'play_time',
                'title' => 'Play Time',
                'description' => 'Total play time recorded by the server in ticks.',
                'value' => 72000,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'mob_kills',
                'title' => 'Mob Kills',
                'description' => 'Number of mob kills made by the player.',
                'value' => 42,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'fish_caught',
                'title' => 'Fish Caught',
                'description' => 'Number of fish caught by the player.',
                'value' => 5,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'bucket_fills',
                'title' => 'Bucket Fills',
                'description' => 'Number of times the player filled a bucket with water or lava.',
                'value' => 3,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'distance_walked_cm',
                'title' => 'Distance Walked',
                'description' => 'Distance walked by the player in centimeters.',
                'value' => 145230,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'jumps',
                'title' => 'Jumps',
                'description' => 'Number of jumps made by the player.',
                'value' => 800,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ], [
                'key' => 'quests_completed',
                'title' => 'Quests Completed',
                'description' => 'Completed quests tracked by another plugin.',
                'value' => 12,
                'updated_at' => now()->subHours(2)->toIso8601String(),
            ]],
        ]);

        $response = $this->actingAs($user)->get(route('account.stemcraft.index'));

        $response->assertOk();
        $response->assertSeeText('Player stats');
        $response->assertSeeText('Play Time');
        $response->assertSeeText('1h');
        $response->assertSeeText('Total play time recorded by the server in ticks.');
        $response->assertSeeText('Mob Kills');
        $response->assertSeeText('42');
        $response->assertSeeText('Fish Caught');
        $response->assertSeeText('5');
        $response->assertSeeText('Bucket Fills');
        $response->assertSeeText('3');
        $response->assertSeeText('Distance Walked');
        $response->assertSeeText('1.45 km');
        $response->assertSeeText('All cached stats');
        $response->assertSeeText('Quests Completed');
    }
}
