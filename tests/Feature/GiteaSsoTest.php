<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class GiteaSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_gitea_auth_endpoint_returns_headers_for_logged_in_user(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('gitea.auth'))
            ->assertNoContent()
            ->assertHeader('X-WEBAUTH-USER', $user->username)
            ->assertHeader('X-WEBAUTH-EMAIL', $user->email)
            ->assertHeader('X-WEBAUTH-FULLNAME', 'Jane Doe');
    }

    public function test_gitea_auth_endpoint_returns_401_for_guest(): void
    {
        $this->get(route('gitea.auth'))
            ->assertStatus(401);
    }

    public function test_login_redirects_back_to_gitea_after_successful_login(): void
    {
        config([
            'services.gitea.base_url' => 'https://git.example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'secret1234',
        ]);

        $returnTo = 'https://git.example.com/projects/acme';

        $this->get(route('login', [
            'redirect_to' => $returnTo,
        ]))
            ->assertOk()
            ->assertSessionHas('url.intended', $returnTo);

        $this->withSession([
            'altcha_trusted_until' => now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'secret1234',
            'remember_email' => '0',
        ])->assertRedirect($returnTo);
    }

    public function test_login_ignores_external_redirect_targets(): void
    {
        config([
            'services.gitea.base_url' => 'https://git.example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'secret1234',
        ]);

        $this->get(route('login', [
            'redirect_to' => 'https://evil.example.com/phish',
        ]))
            ->assertOk()
            ->assertSessionMissing('url.intended');

        $this->withSession([
            'altcha_trusted_until' => now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'secret1234',
            'remember_email' => '0',
        ])->assertRedirect(route('index'));
    }
}
