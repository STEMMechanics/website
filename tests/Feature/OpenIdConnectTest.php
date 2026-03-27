<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OpenIdConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_document_exposes_the_expected_endpoints(): void
    {
        $response = $this->getJson('/.well-known/openid-configuration')->assertOk();

        $this->assertSame(route('passport.authorizations.authorize'), $response->json('authorization_endpoint'));
        $this->assertSame(route('passport.token'), $response->json('token_endpoint'));
        $this->assertSame(route('openid.userinfo'), $response->json('userinfo_endpoint'));
        $this->assertSame(route('openid.jwks'), $response->json('jwks_uri'));
        $this->assertContains('openid', $response->json('scopes_supported'));
        $this->assertContains('profile', $response->json('scopes_supported'));
        $this->assertContains('email', $response->json('scopes_supported'));
    }

    public function test_jwks_endpoint_returns_a_public_key_set(): void
    {
        $response = $this->getJson(route('openid.jwks'))->assertOk();

        $this->assertNotEmpty($response->json('keys'));
        $this->assertSame('RSA', $response->json('keys.0.kty'));
        $this->assertSame('sig', $response->json('keys.0.use'));
    }

    public function test_userinfo_endpoint_returns_profile_and_email_claims_for_requested_scopes(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane@example.com',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['openid', 'profile', 'email']);

        $response = $this->getJson(route('openid.userinfo'))->assertOk();

        $this->assertSame((string) $user->id, $response->json('sub'));
        $this->assertSame('Jane Doe', $response->json('name'));
        $this->assertSame('Jane', $response->json('given_name'));
        $this->assertSame('Doe', $response->json('family_name'));
        $this->assertSame($user->username, $response->json('nickname'));
        $this->assertSame($user->username, $response->json('preferred_username'));
        $this->assertSame(route('account.show'), $response->json('profile'));
        $this->assertSame('jane@example.com', $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
    }

    public function test_userinfo_endpoint_omits_profile_claims_when_profile_scope_is_missing(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane@example.com',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['openid', 'email']);

        $response = $this->getJson(route('openid.userinfo'))->assertOk();

        $this->assertSame((string) $user->id, $response->json('sub'));
        $this->assertSame('jane@example.com', $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
        $this->assertArrayNotHasKey('name', $response->json());
        $this->assertArrayNotHasKey('nickname', $response->json());
        $this->assertArrayNotHasKey('preferred_username', $response->json());
        $this->assertArrayNotHasKey('profile', $response->json());
    }

    public function test_userinfo_endpoint_requires_a_valid_access_token(): void
    {
        $this->getJson(route('openid.userinfo'))
            ->assertUnauthorized();
    }

}
