<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
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
        $location = Location::factory()->create();
        $media = Media::query()->create([
            'name' => 'dashboard-workplan.png',
            'title' => 'Dashboard workshop',
            'hash' => str_repeat('d', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $admin->id,
        ]);
        $workshop = Workshop::factory()->create([
            'title' => 'Public workplan workshop',
            'starts_at' => now()->addHours(2),
            'location_id' => $location->id,
            'user_id' => $admin->id,
            'hero_media_name' => $media->name,
        ]);
        Invoice::factory()->create([
            'status' => Invoice::STATUS_SENT,
            'due_date' => today()->addDay(),
            'total_amount' => 125,
        ]);
        Invoice::factory()->create([
            'status' => Invoice::STATUS_DRAFT,
            'scheduled_email' => true,
            'issue_date' => today()->addDay(),
            'total_amount' => 125,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Top 10 Workshop Activity')
            ->assertSee('Top 10 Store Item Views and Sales')
            ->assertSee('Workshop views')
            ->assertSee('Profit')
            ->assertSee('Website Traffic')
            ->assertSee('Top 10 Traffic Sources')
            ->assertSee('Percentage')
            ->assertSee('View full analytics report')
            ->assertSee('Financial Performance')
            ->assertSee('Workshop Activity')
            ->assertSee('Ticket Activity')
            ->assertSee('Store Activity')
            ->assertSee('Audience Growth')
            ->assertSee('Total users')
            ->assertSee('Total subscriptions')
            ->assertSee('Overview (12 months)')
            ->assertSee('value="overview" selected', false)
            ->assertSee('trend graph')
            ->assertDontSee('Selected range')
            ->assertSee('Fortnightly Workplan')
            ->assertSee('Next newsletter')
            ->assertSee('Subject:')
            ->assertSee('Review newsletter')
            ->assertSee('Suggested follow-ups')
            ->assertSee('Coming up this fortnight')
            ->assertSee('Invoices due')
            ->assertSee('Invoice email scheduled')
            ->assertSee('Payment due')
            ->assertSee('fa-arrow-up-right-from-square', false)
            ->assertSee('grid gap-5 lg:grid-cols-2', false)
            ->assertSee('self-start grid-cols-2 gap-3 md:grid-cols-5', false)
            ->assertSee(route('workshop.show', $workshop), false)
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
