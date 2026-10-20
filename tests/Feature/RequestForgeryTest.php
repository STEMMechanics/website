<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestForgeryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the application's real middleware configuration without
        // Laravel's automatic CSRF bypass for test requests.
        $this->app['env'] = 'local';
        config(['analytics.enabled' => false]);
        Route::middleware(['web', PreventRequestForgery::class])->post('/upgrade-forgery-check', fn () => response('accepted'));
    }

    public function test_same_origin_browser_requests_are_accepted_without_a_token(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('/upgrade-forgery-check')->assertOk()->assertSee('accepted');
    }

    public static function untrustedOrigins(): array
    {
        return ['cross site' => ['cross-site'], 'sibling subdomain' => ['same-site'], 'no fetch metadata' => [null]];
    }

    #[DataProvider('untrustedOrigins')]
    public function test_other_origins_still_require_a_valid_session_token(?string $origin): void
    {
        if ($origin !== null) {
            $this->withHeader('Sec-Fetch-Site', $origin);
        }
        $this->post('/upgrade-forgery-check')->assertStatus(419);
        $this->withSession(['_token' => 'upgrade-test-token'])
            ->post('/upgrade-forgery-check', ['_token' => 'upgrade-test-token'])->assertOk();
    }

    public function test_signed_webhook_routes_keep_their_csrf_exemptions(): void
    {
        // Reaching each webhook's own signature validation proves the renamed
        // middleware retained the exclusions configured during bootstrap.
        config([
            'services.square.enabled' => true,
            'services.square.webhook_signature_key' => 'upgrade-test-signature-key',
            'services.square.webhook_url' => route('webhook.square'),
        ]);
        $this->postJson('/webhooks/square', [])->assertUnauthorized()->assertJsonPath('message', 'Invalid signature');
        $this->postJson('/webhooks/smsflow', [])->assertUnauthorized();
    }
}
