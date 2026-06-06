<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_is_visible_to_admin_users_and_admin_root_redirects(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Top Workshop Views and Ticket Sales')
            ->assertSee('Top Store Item Views and Sales')
            ->assertSee('Workshop views')
            ->assertSee('Profit')
            ->assertSee('Selected range')
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertDontSee('g:ia', false);
    }

    public function test_admin_dashboard_is_forbidden_to_non_admin_users(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
