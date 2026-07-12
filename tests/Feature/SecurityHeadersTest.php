<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_form_action_csp_is_limited_to_self(): void
    {
        $middleware = app(SecurityHeaders::class);
        $request = Request::create('/login', 'GET');
        $response = $middleware->handle($request, static fn () => response('ok'));

        $csp = (string) $response->headers->get('Content-Security-Policy', '');

        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringNotContainsString('https://git.stemmechanics.com.au', $csp);
    }
}
