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

    public function test_enforced_script_policy_is_opt_in_and_contains_the_reviewed_hosts(): void
    {
        config(['security.csp_report_only' => false, 'security.csp_enforce' => true]);

        $response = app(SecurityHeaders::class)->handle(
            Request::create('/checkout', 'GET'),
            static fn () => response('ok')
        );

        $csp = (string) $response->headers->get('Content-Security-Policy', '');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString('https://web.squarecdn.com', $csp);
        $this->assertStringContainsString('https://sandbox.web.squarecdn.com', $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_security_txt_uses_the_published_security_contact_and_policy(): void
    {
        $securityTxt = (string) file_get_contents(public_path('.well-known/security.txt'));

        $this->assertStringContainsString('Contact: mailto:hello@stemmechanics.com.au', $securityTxt);
        $this->assertStringContainsString('Policy: https://github.com/stemmechanics/website/blob/main/SECURITY.md', $securityTxt);
    }
}
