<?php

namespace Tests\Feature;

use App\Models\MinecraftAccount;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftDetachedAccountClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_user_can_claim_existing_detached_whitelisted_account(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'minecraft']);

        $account = MinecraftAccount::query()->create([
            'user_id' => null,
            'platform' => 'java',
            'username' => 'PlayerOne',
            'is_whitelisted' => true,
            'admin_notes' => 'Parent contact on file.',
        ]);

        $response = $this->actingAs($user)->post(route('account.stemcraft.store'), [
            'platform' => 'java',
            'username' => 'PlayerOne',
        ]);

        $response->assertRedirect(route('account.stemcraft.index'));
        $response->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame((string) $user->id, (string) $account->user_id);
        $this->assertTrue($account->is_whitelisted);
        $this->assertSame('Parent contact on file.', $account->admin_notes);
    }

    public function test_user_claiming_detached_non_whitelisted_account_forces_whitelist_on(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'minecraft']);

        $account = MinecraftAccount::query()->create([
            'user_id' => null,
            'platform' => 'java',
            'username' => 'PlayerTwo',
            'is_whitelisted' => false,
            'admin_notes' => 'Previously disabled.',
        ]);

        $response = $this->actingAs($user)->post(route('account.stemcraft.store'), [
            'platform' => 'java',
            'username' => 'PlayerTwo',
        ]);

        $response->assertRedirect(route('account.stemcraft.index'));
        $response->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame((string) $user->id, (string) $account->user_id);
        $this->assertTrue($account->is_whitelisted);
        $this->assertSame('Previously disabled.', $account->admin_notes);
    }

    public function test_non_privileged_user_cannot_link_more_than_five_accounts(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'minecraft']);

        foreach (range(1, 5) as $index) {
            MinecraftAccount::query()->create([
                'user_id' => $user->id,
                'platform' => $index % 2 === 0 ? 'bedrock' : 'java',
                'username' => 'Player'.$index,
                'is_whitelisted' => true,
            ]);
        }

        $response = $this->from(route('account.stemcraft.index'))
            ->actingAs($user)
            ->post(route('account.stemcraft.store'), [
                'platform' => 'java',
                'username' => 'PlayerSix',
            ]);

        $response->assertRedirect(route('account.stemcraft.index'));
        $response->assertSessionHasErrors(['username']);

        $this->assertSame(5, $user->minecraftAccounts()->count());
        $this->assertDatabaseMissing('minecraft_accounts', [
            'user_id' => $user->id,
            'platform' => 'java',
            'username' => 'PlayerSix',
        ]);
    }

    public function test_admin_and_minecraft_org_users_can_link_more_than_five_accounts(): void
    {
        foreach (['admin', 'minecraft-org'] as $exemptGroup) {
            $user = User::factory()->create();
            $user->groups()->create(['slug' => 'minecraft']);
            $user->groups()->create(['slug' => $exemptGroup]);

            foreach (range(1, 5) as $index) {
                MinecraftAccount::query()->create([
                    'user_id' => $user->id,
                    'platform' => $index % 2 === 0 ? 'bedrock' : 'java',
                    'username' => sprintf('%s-Player%d', $exemptGroup, $index),
                    'is_whitelisted' => true,
                ]);
            }

            $newUsername = sprintf('%s-Player6', $exemptGroup);
            $response = $this->from(route('account.stemcraft.index'))
                ->actingAs($user)
                ->post(route('account.stemcraft.store'), [
                    'platform' => 'java',
                    'username' => $newUsername,
                ]);

            $response->assertRedirect(route('account.stemcraft.index'));
            $response->assertSessionHasNoErrors();

            $this->assertDatabaseHas('minecraft_accounts', [
                'user_id' => $user->id,
                'platform' => 'java',
                'username' => $newUsername,
            ]);
        }
    }

    public function test_user_over_limit_can_resubmit_an_already_linked_account(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'minecraft']);

        foreach (range(1, 6) as $index) {
            MinecraftAccount::query()->create([
                'user_id' => $user->id,
                'platform' => $index % 2 === 0 ? 'bedrock' : 'java',
                'username' => 'Linked'.$index,
                'is_whitelisted' => true,
            ]);
        }

        $response = $this->from(route('account.stemcraft.index'))
            ->actingAs($user)
            ->post(route('account.stemcraft.store'), [
                'platform' => 'java',
                'username' => 'Linked1',
            ]);

        $response->assertRedirect(route('account.stemcraft.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(6, $user->minecraftAccounts()->count());
    }
}
