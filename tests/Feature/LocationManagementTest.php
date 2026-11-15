<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_location_via_ajax_without_following_a_delete_redirect(): void
    {
        $this->admin();
        $location = Location::factory()->create();

        $this->deleteJson(route('admin.location.destroy', $location))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'redirect' => route('admin.location.index'),
            ]);

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_location_list_links_names_and_shows_the_complete_address(): void
    {
        $this->admin();
        $location = Location::factory()->create([
            'name' => 'Mossman Library',
            'address' => '14 Mill Street',
            'suburb' => 'Mossman',
            'state' => 'QLD',
            'postcode' => '4873',
        ]);

        $this->get(route('admin.location.index'))
            ->assertOk()
            ->assertSee(route('admin.location.edit', $location), false)
            ->assertSee('14 Mill Street, Mossman, QLD 4873');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }
}
