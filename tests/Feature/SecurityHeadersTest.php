<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_callback_origins_are_allowed_in_form_action_csp(): void
    {
        app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Gitea',
            ['https://git.stemmechanics.com.au/user/oauth2/STEMMechanics/callback'],
            true
        );

        $middleware = app(SecurityHeaders::class);
        $request = Request::create('/oauth/authorize', 'GET');
        $response = $middleware->handle($request, static fn () => response('ok'));

        $csp = (string) $response->headers->get('Content-Security-Policy', '');

        $this->assertStringContainsString("form-action 'self' https://git.stemmechanics.com.au", $csp);
        $this->assertStringNotContainsString('GITEA_BASE_URL', $csp);
    }
}
