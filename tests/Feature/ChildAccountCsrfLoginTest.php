<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildAccountCsrfLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_password_prompt_and_login_work_with_csrf_enabled(): void
    {
        config(['security.altcha_enabled' => false]);

        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-csrf',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $loginPage = $this->get(route('login'));
        $loginPage->assertOk();
        $firstToken = $this->extractCsrfToken($loginPage->getContent());

        $identifierResponse = $this->post(route('login.store'), [
            '_token' => $firstToken,
            'login' => 'kid-csrf',
            'remember_email' => '0',
        ]);

        $identifierResponse->assertOk();
        $identifierResponse->assertSee('Enter your password');
        $secondToken = $this->extractCsrfToken($identifierResponse->getContent());

        $passwordResponse = $this->post(route('login.store'), [
            '_token' => $secondToken,
            'login' => 'kid-csrf',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $passwordResponse->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($child);
    }

    public function test_child_can_log_out_and_log_back_in_with_csrf_enabled(): void
    {
        config(['security.altcha_enabled' => false]);

        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-relogin-csrf',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $this->completeChildPasswordLogin('kid-relogin-csrf', 'secret1234');
        $this->assertAuthenticatedAs($child);

        $logoutPage = $this->actingAs($child)->get(route('logout.show'));
        $logoutPage->assertOk();
        $logoutToken = $this->extractCsrfToken($logoutPage->getContent());

        $logoutResponse = $this->post(route('logout'), [
            '_token' => $logoutToken,
        ]);

        $logoutResponse->assertRedirect(route('index'));
        $this->assertGuest();

        $this->completeChildPasswordLogin('kid-relogin-csrf', 'secret1234');
        $this->assertAuthenticatedAs($child);
    }

    private function completeChildPasswordLogin(string $username, string $password): void
    {
        $loginPage = $this->get(route('login'));
        $loginPage->assertOk();
        $firstToken = $this->extractCsrfToken($loginPage->getContent());

        $identifierResponse = $this->post(route('login.store'), [
            '_token' => $firstToken,
            'login' => $username,
            'remember_email' => '0',
        ]);

        $identifierResponse->assertOk();
        $identifierResponse->assertSee('Enter your password');
        $secondToken = $this->extractCsrfToken($identifierResponse->getContent());

        $passwordResponse = $this->post(route('login.store'), [
            '_token' => $secondToken,
            'login' => $username,
            'password' => $password,
            'remember_email' => '0',
        ]);

        $passwordResponse->assertRedirect(route('index'));
    }

    private function extractCsrfToken(string $html): string
    {
        preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1] ?? null, 'Unable to find CSRF token in HTML response.');

        return (string) $matches[1];
    }
}
