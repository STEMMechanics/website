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
        $response->assertSeeText('View player stats');
        $response->assertDontSeeText('All cached stats');
        $response->assertDontSeeText('Play Time');
    }

    public function test_child_account_with_linked_minecraft_profile_can_view_read_only_stemcraft_page(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-player',
            'email' => null,
            'email_verified_at' => null,
        ]);

        $account = MinecraftAccount::query()->create([
            'user_id' => $child->id,
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'KidPlayer',
            'is_whitelisted' => true,
        ]);

        $response = $this->actingAs($child)->get(route('account.stemcraft.index'));

        $response->assertOk();
        $response->assertSeeText('Read-only access');
        $response->assertSeeText('View your linked STEMCraft accounts and whitelist status.');
        $response->assertSeeText('KidPlayer');
        $response->assertSeeText('View player stats');
        $response->assertDontSeeText('Add Minecraft Account');
        $response->assertDontSeeText('Remove account');
        $this->assertSame((string) $child->id, (string) $account->user_id);
    }

    public function test_parent_can_reassign_minecraft_account_to_a_child_without_needing_minecraft_group_access(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-owner',
            'email' => null,
            'email_verified_at' => null,
        ]);

        $account = MinecraftAccount::query()->create([
            'user_id' => $parent->id,
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'FamilyPlayer',
            'is_whitelisted' => true,
        ]);

        $this->actingAs($parent)
            ->patch(route('account.stemcraft.owner.update', $account), [
                'user_id' => (string) $child->id,
            ])
            ->assertRedirect(route('account.stemcraft.index'));

        $account->refresh();
        $this->assertSame((string) $child->id, (string) $account->user_id);

        $this->actingAs($parent)
            ->get(route('account.stemcraft.index'))
            ->assertOk()
            ->assertSeeText('FamilyPlayer');

        $this->actingAs($child)
            ->get(route('account.stemcraft.index'))
            ->assertOk()
            ->assertSeeText('Read-only access')
            ->assertSeeText('FamilyPlayer')
            ->assertDontSeeText('Add Minecraft Account');
    }

    public function test_parent_without_minecraft_group_can_view_stemcraft_but_cannot_add_accounts(): void
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)
            ->get(route('account.stemcraft.index'))
            ->assertOk()
            ->assertSeeText('Limited access')
            ->assertDontSeeText('Add Minecraft Account');

        $this->actingAs($parent)
            ->post(route('account.stemcraft.store'), [
                'platform' => 'java',
                'username' => 'ParentPlayer',
            ])
            ->assertForbidden();
    }
}
