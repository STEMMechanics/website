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
}
