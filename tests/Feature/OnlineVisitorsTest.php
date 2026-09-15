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

    public function test_disabled_analytics_and_cache_failure_show_unavailable(): void
    {
        config(['analytics.enabled' => false]);
        $this->assertNull(app(OnlineVisitors::class)->count());
        config(['analytics.enabled' => true]);
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Cache unavailable'));
        $this->assertNull(app(OnlineVisitors::class)->count());
    }
}
