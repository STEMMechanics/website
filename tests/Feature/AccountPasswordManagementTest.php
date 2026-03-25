<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_security_card_shows_when_no_password_has_been_set(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('Login Authentication')
            ->assertSee('No password has been set for this account.')
            ->assertSee('Set password')
            ->assertDontSee('name="clear_password"', false);
    }

    public function test_user_can_set_password_from_account_security_card(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ])
            ->assertRedirect(route('account.show'));

        $user->refresh();

        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check('secret1234', (string) $user->password));
    }

    public function test_user_can_clear_password_from_account_security_card(): void
    {
        $user = User::factory()->create([
            'password' => 'secret1234',
        ]);

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'clear_password' => '1',
            ])
            ->assertRedirect(route('account.show'));

        $user->refresh();

        $this->assertNull($user->password);
        $this->assertFalse($user->canUsePasswordLogin());
    }
}
