<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\OnlineVisitors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnlineVisitorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_are_deduplicated_exclude_admins_and_expire(): void
    {
        $service = app(OnlineVisitors::class);
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $customer = User::factory()->create();
        $service->touch('guest', null);
        $service->touch('guest', null);
        $service->touch('customer-one', $customer->id);
        $service->touch('customer-two', $customer->id);
        $service->touch('admin', $admin->id);
        $this->assertSame(2, $service->count());
        $this->travel(6)->minutes();
        $this->assertSame(0, $service->count());
    }

    public function test_public_activity_is_recorded_but_bots_and_admins_are_not(): void
    {
        Queue::fake();
        $service = app(OnlineVisitors::class);
        $this->withSession(['analytics_session_token' => 'browser'])->get('/about')->assertOk();
        $this->assertSame(1, $service->count());
        $this->assertSame('/about', $service->visitors()->first()['path']);
        $this->withSession(['analytics_session_token' => 'bot'])->withHeader('User-Agent', 'Googlebot')->get('/about');
        $this->assertSame(1, $service->count());
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->withSession(['analytics_session_token' => 'browser'])->get('/about');
        $this->assertSame(0, $service->count());
    }

    public function test_live_endpoint_requires_admin_and_returns_only_a_count(): void
    {
        $this->getJson(route('admin.analytics.online'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson(route('admin.analytics.online'))->assertForbidden();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->getJson(route('admin.analytics.online'))->assertOk()->assertExactJson(['count' => 0]);
    }

    public function test_details_show_latest_page_and_identity_without_exposing_session_tokens(): void
    {
        $service = app(OnlineVisitors::class);
        $customer = User::factory()->create(['firstname' => 'Sample', 'surname' => 'Customer']);
        $service->touch('secret-guest-token', null, '/about');
        $service->touch('secret-customer-one', $customer->id, '/store');
        $this->travel(1)->seconds();
        $service->touch('secret-customer-two', $customer->id, '/store/example');

        $this->assertSame('/store/example', $service->visitors()->first()['path']);
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $service->touch('admin-secret-token', $admin->id, '/admin');

        $page = $this->actingAs($admin)->get(route('admin.analytics.visitors'))
            ->assertOk()->assertSee('Sample Customer')->assertSee('/store/example')
            ->assertSee('Anonymous visitor')->assertSee('/about')
            ->assertDontSee('secret-guest-token')->assertDontSee('secret-customer')
            ->assertDontSee('admin-secret-token');
        $this->assertMatchesRegularExpression('/<nav aria-label="Breadcrumb"[^>]*>.*?>Analytics<\/a>.*?<\/nav>/s', $page->getContent());
        $page->assertDontSee('Visitors active in the last 5 minutes');
        $response = $this->getJson(route('admin.analytics.visitors'))->assertOk()->assertJsonPath('count', 2);
        $this->assertStringContainsString('Sample Customer', $response->json('html'));
        $this->assertStringNotContainsString('secret-', $response->getContent());

        $this->travel(6)->minutes();
        $this->getJson(route('admin.analytics.visitors'))->assertJsonPath('count', 0);
    }

    public function test_visitor_details_require_admin(): void
    {
        $this->get(route('admin.analytics.visitors'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.analytics.visitors'))->assertForbidden();
        $this->getJson(route('admin.analytics.visitors'))->assertForbidden();
    }

    public function test_disabled_analytics_and_cache_failure_show_unavailable(): void
    {
        config(['analytics.enabled' => false]);
        $this->assertNull(app(OnlineVisitors::class)->count());
        config(['analytics.enabled' => true]);
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Cache unavailable'));
        $this->assertNull(app(OnlineVisitors::class)->count());
    }
}
