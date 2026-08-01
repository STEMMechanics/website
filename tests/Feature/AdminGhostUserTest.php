<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGhostUserTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    public function test_admin_can_create_multiple_users_without_email_addresses(): void
    {
        $admin = $this->createAdmin();

        foreach (['Facebook Contact', 'In Person Contact'] as $firstName) {
            $this->actingAs($admin)
                ->post(route('admin.user.store'), [
                    'firstname' => $firstName,
                    'surname' => '',
                    'email' => '',
                    'phone' => '',
                    'groups' => '',
                    'account_terms_days' => 0,
                ])
                ->assertRedirect(route('admin.user.index'));
        }

        $users = User::query()
            ->whereIn('firstname', ['Facebook Contact', 'In Person Contact'])
            ->orderBy('firstname')
            ->get();

        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertNull($user->email);
            $this->assertNull($user->email_verified_at);
            $this->assertFalse($user->canReceiveEmail());
            $this->assertFalse($user->canUseEmailLogin());
            $this->assertFalse($user->canUsePasswordLogin());
        }
    }

    public function test_first_name_is_required_but_surname_and_email_are_optional(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.user.store'), [
                'firstname' => '',
                'surname' => '',
                'email' => '',
            ])
            ->assertSessionHasErrors('firstname');

        $this->actingAs($admin)
            ->postJson(route('admin.user.store-inline'), [
                'firstname' => 'Facebook Contact',
                'surname' => '',
                'email' => '',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'firstname' => 'Facebook Contact',
            'surname' => '',
            'email' => null,
        ]);
    }

    public function test_email_less_user_is_available_to_ghost_contact_search(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'firstname' => 'Facebook Contact',
            'surname' => '',
            'email' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.organisation.contact-options', [
                'search' => 'Facebook',
                'include_ghost' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('users.0.id', (string) $user->id)
            ->assertJsonPath('users.0.email', '');
    }

    public function test_user_editor_explains_why_login_is_unavailable(): void
    {
        $admin = $this->createAdmin();
        $withoutEmail = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('admin.user.edit', $withoutEmail))
            ->assertOk()
            ->assertSee('This user cannot log in because they do not have an email address.');

        $this->actingAs($admin)
            ->get(route('admin.user.edit', $unverified))
            ->assertOk()
            ->assertSee('This user cannot log in because their email address is not verified.');
    }
}
