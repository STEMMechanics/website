<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_get_shows_confirmation_and_post_logs_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/logout')
            ->assertOk()
            ->assertSee('Confirm logout from your account.');

        $this->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('logout'), ['_token' => 'test-csrf-token'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('index'));
    }
}
