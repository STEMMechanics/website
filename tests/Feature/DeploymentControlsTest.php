<?php

namespace Tests\Feature;

use App\Http\Middleware\RequirePrivilegedMfa;
use App\Http\Middleware\TrustedIngress;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\AnalyticsEvent;
use App\Models\CustomPage;
use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_callbacks_fail_closed_before_storing_any_records(): void
    {
        config(['services.smsflow.webhook_secret' => str_repeat('a', 40)]);
        $this->postJson('/webhooks/smsflow', ['topic' => 'sms.incoming'])->assertUnauthorized();
        $this->withToken('wrong')->postJson('/webhooks/smsflow', [])->assertUnauthorized();
        $this->assertDatabaseCount('inbound_sms', 0);
        config(['services.smsflow.webhook_secret' => null]);
        $this->postJson('/webhooks/smsflow', [])->assertUnauthorized();
    }

    public function test_proxy_headers_are_only_used_from_configured_ingress(): void
    {
        config(['security.trusted_proxies' => ['10.1.2.3']]);
        $request = Request::create('http://example.com/', server: [
            'REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.2',
            'HTTP_X_FORWARDED_HOST' => 'evil.example', 'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        app(TrustedIngress::class)->handle($request, function ($request) {
            $this->assertSame('192.0.2.1', $request->ip());
            $this->assertFalse($request->isSecure());
            return response('ok');
        });
        $request->server->set('REMOTE_ADDR', '10.1.2.3');
        app(TrustedIngress::class)->handle($request, function ($request) {
            $this->assertSame('198.51.100.2', $request->ip());
            $this->assertSame('example.com', $request->getHost());
            $this->assertTrue($request->isSecure());
            return response('ok');
        });
    }

    public function test_staging_noindex_and_report_only_policy_preserve_existing_csp(): void
    {
        config(['security.indexable' => false]);
        $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy-Report-Only', "script-src 'self'; object-src 'none'; base-uri 'self'; report-uri /security/csp-reports");
        $this->postJson('/security/csp-reports', ['csp-report' => ['blocked-uri' => 'https://secret.example?token=private', 'effective-directive' => 'script-src-elem']])->assertNoContent();
    }

    public function test_administrator_enrolment_and_verification_are_required(): void
    {
        config(['security.admin_mfa_required' => true]);
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('account.show'));
        $this->get(route('account.show'))->assertOk();
        $admin->forceFill(['tfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->get(route('admin.dashboard'))->assertRedirect(route('security.mfa.show'));
        $this->post(route('security.mfa.verify'), ['code' => 'invalid'])->assertSessionHasErrors('code');
        $this->withSession(['privileged_mfa' => ['fingerprint' => RequirePrivilegedMfa::fingerprint($admin), 'expires' => now()->addHour()->timestamp]])
            ->get(route('admin.dashboard'))->assertOk();
        $this->delete(route('account.destroy.tfa'))->assertForbidden();
    }

    public function test_email_link_responses_remain_generic(): void
    {
        config(['security.generic_login' => true, 'security.altcha_enabled' => false]);
        Queue::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);
        foreach ([$user->email, 'missing@example.com'] as $login) {
            $this->post(route('login.store'), ['login' => $login, 'method' => 'email'])->assertViewIs('auth.login-link');
        }
    }

    public function test_queued_analytics_is_idempotent_and_does_not_store_bots(): void
    {
        Queue::fake([RecordAnalyticsEvent::class]);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/')->assertOk();
        Queue::assertPushed(RecordAnalyticsEvent::class, 1);
        $job = Queue::pushed(RecordAnalyticsEvent::class)->first();
        $job->handle();
        $job->handle();
        $this->assertSame(1, AnalyticsEvent::query()->count());
        $this->withHeader('User-Agent', 'Googlebot')->get('/')->assertOk();
        Queue::assertPushed(RecordAnalyticsEvent::class, 1);
    }

    public function test_public_pages_survive_analytics_queue_outages(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue unavailable'));
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/')->assertOk();
    }

    public function test_sitemap_has_valid_xml_real_dates_and_public_catalogue_entries(): void
    {
        $public = CustomPage::query()->create(['content' => '<p>Public</p>', 'title' => 'Public', 'path' => '/public-page', 'is_published' => true, 'seo_noindex' => false]);
        CustomPage::query()->create(['content' => '<p>Private</p>', 'title' => 'Private', 'path' => '/private-page', 'is_published' => false]);
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        $response = $this->get(route('sitemap.xml'))->assertOk()->assertSee('/public-page')->assertDontSee('/private-page')->assertSee($product->slug);
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(0, $xml->url[0]->lastmod);
    }

    public function test_snapshot_cache_round_trip_keeps_dates_and_rows_renderable(): void
    {
        config(['analytics.dashboard_snapshot_seconds' => 300]);
        $service = app(\App\Services\DashboardSnapshot::class);
        $fresh = $service->refresh('month');
        $cached = $service->get('month');
        $this->assertSame($fresh['cards'], $cached['cards']);
        $this->assertSame($fresh['periodStart']->timestamp, $cached['periodStart']->timestamp);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $cached['trafficSourceRows']);
        $this->assertSame($fresh['snapshotAt'], $cached['snapshotAt']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->assertStringContainsString('Dashboard', view('admin.dashboard.index', $cached + ['errors' => new \Illuminate\Support\ViewErrorBag(), 'workplan' => app(\App\Services\WeeklyWorkplanService::class)->build()])->render());
    }

    public function test_sitemap_paginates_without_omitting_or_duplicating_public_pages(): void
    {
        $rows = [];
        for ($i = 0; $i < 1005; $i++) {
            $rows[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'title' => 'Page '.$i, 'path' => '/page-'.$i, 'content' => 'Content', 'is_published' => true, 'seo_noindex' => false];
        }
        CustomPage::query()->insert($rows);
        $this->get(route('sitemap.xml'))->assertOk()->assertSee('sitemapindex');
        $first = simplexml_load_string($this->get(route('sitemap.xml', ['page' => 1]))->assertOk()->getContent());
        $second = simplexml_load_string($this->get(route('sitemap.xml', ['page' => 2]))->assertOk()->getContent());
        $this->assertCount(1000, $first->url);
        $urls = [];
        foreach ([$first, $second] as $xml) {
            foreach ($xml->url as $url) {
                $urls[] = (string) $url->loc;
            }
        }
        $this->assertCount(count($urls), array_unique($urls));
        $this->assertCount(1005, array_filter($urls, fn ($url) => str_contains($url, '/page-')));
        $this->get(route('sitemap.xml', ['page' => 3]))->assertNotFound();
    }

    public function test_valid_mfa_code_confirms_session_and_cannot_be_reused(): void
    {
        config(['security.admin_mfa_required' => true]);
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $admin->forceFill(['tfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $code = \App\Http\Controllers\AccountController::getTFAInstance()->getCode($admin->tfa_secret);
        $this->actingAs($admin)->post(route('security.mfa.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas('privileged_mfa');
        $this->withSession(['privileged_mfa' => []])->post(route('security.mfa.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_canonical_redirect_uses_configured_origin_and_preserves_signed_links(): void
    {
        config(['security.canonical_redirect' => true, 'app.url' => 'https://canonical.example']);
        $middleware = app(\App\Http\Middleware\CanonicalHost::class);
        $response = $middleware->handle(Request::create('http://alias.example/workshops?search=robot'), fn () => response('ok'));
        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('https://canonical.example/workshops?search=robot', $response->headers->get('Location'));
        $response = $middleware->handle(Request::create('https://alias.example/invoices/one?signature=abc'), fn () => response('ok'));
        $this->assertSame(200, $response->getStatusCode());
    }


    public function test_administrator_express_login_prefers_totp_even_when_a_password_exists(): void
    {
        config(['security.admin_mfa_required' => true, 'security.generic_login' => true, 'security.altcha_enabled' => false]);
        Queue::fake();
        $admin = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('StrongPassword123!')]);
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $admin->forceFill(['tfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $code = \App\Http\Controllers\AccountController::getTFAInstance()->getCode($admin->tfa_secret);
        $this->post(route('login.store'), ['login' => $admin->email])->assertViewIs('auth.login-2fa')->assertViewHas('allowPasswordMethod', true);
        $this->post(route('login.store'), ['login' => $admin->email, 'method' => 'password'])->assertViewIs('auth.login-password')->assertViewHas('allowAuthenticatorMethod', true);
        $this->post(route('login.store'), ['login' => $admin->email, 'totp' => $code])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas('privileged_mfa');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_passwordless_administrator_can_sign_in_with_totp_without_a_password(): void
    {
        config(['security.admin_mfa_required' => true, 'security.generic_login' => true, 'security.altcha_enabled' => false]);
        Queue::fake();
        $admin = User::factory()->create(['password' => null]);
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $admin->forceFill(['tfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->post(route('login.store'), ['login' => $admin->email])->assertViewIs('auth.login-2fa');
        $code = \App\Http\Controllers\AccountController::getTFAInstance()->getCode($admin->tfa_secret);
        $this->post(route('login.store'), ['login' => $admin->email, 'totp' => $code])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas('privileged_mfa');
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_express_login_falls_back_to_password_then_email(): void
    {
        config(['security.generic_login' => true, 'security.altcha_enabled' => false]);
        Queue::fake();
        $passwordUser = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('ExpressPassword123!')]);
        $emailUser = User::factory()->create(['password' => null]);
        $this->post(route('login.store'), ['login' => $passwordUser->email])->assertViewIs('auth.login-password');
        $this->post(route('login.store'), ['login' => $emailUser->email])->assertViewIs('auth.login-link');
        Queue::assertPushed(\App\Jobs\SendEmail::class);
    }
}
