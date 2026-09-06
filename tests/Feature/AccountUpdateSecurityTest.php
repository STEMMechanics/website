<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

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
                'dashboard_email_opt_in' => 'on',
                'tfa_secret' => 'FORGED',
                'email_verified_at' => now()->subYear()->toDateTimeString(),
                'agree_tos' => 1,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->dashboard_email_opt_in);
        $this->assertNull($user->tfa_secret);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame(1, (int) $user->agree_tos);
    }
    public function test_admin_can_opt_in_and_out_of_dashboard_emails_in_their_profile(): void
    {
        $admin = User::factory()->create();
        \App\Models\UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->post(route('account.update'), ['email' => $admin->email, 'dashboard_email_opt_in' => 'on'])->assertRedirect();
        $this->assertTrue($admin->fresh()->dashboard_email_opt_in);
        $this->post(route('account.update'), ['email' => $admin->email])->assertRedirect();
        $this->assertFalse($admin->fresh()->dashboard_email_opt_in);
    }
}
