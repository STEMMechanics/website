<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_update_cannot_mass_assign_sensitive_user_fields(): void
    {
        $user = User::factory()->create([
            'tfa_secret' => null,
            'agree_tos' => 0,
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('account.update'), [
                '_token' => 'test-csrf-token',
                'email' => $user->email,
                'groups' => 'admin',
                'tfa_secret' => 'FORGED',
                'email_verified_at' => now()->subYear()->toDateTimeString(),
                'agree_tos' => 1,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->tfa_secret);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame(1, (int) $user->agree_tos);
    }
}
