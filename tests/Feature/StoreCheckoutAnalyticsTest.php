<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StoreCartService;
use App\Services\StoreCheckoutAnalytics;
use App\Services\StoreCheckoutReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreCheckoutAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->create(['status' => Product::STATUS_ACTIVE, 'product_type' => Product::PRODUCT_TYPE_DIGITAL, 'price' => 25]);
    }

    private function summary(): array
    {
        return app(StoreCheckoutReport::class)->summary(today()->subDays(30), now()->endOfDay());
    }

    public function test_cart_refreshes_do_not_inflate_counts_and_returning_carts_reactivate(): void
    {
        $this->postJson(route('shop.cart.add', $this->product()), ['quantity' => 2])->assertSuccessful();
        $this->getJson(route('shop.cart.show'))->assertOk();
        $this->getJson(route('shop.cart.show'))->assertOk();
        $this->assertDatabaseCount('store_checkout_sessions', 1);
        $this->assertDatabaseCount('store_checkout_items', 1);
        $this->assertSame(1, $this->summary()['Active carts']);
        $this->travel(25)->hours();
        $this->assertSame(1, $this->summary()['Inactive carts']);
        $this->assertSame('$50.00', $this->summary()['Value in inactive carts']);
        $this->getJson(route('shop.cart.show'))->assertOk();
        $this->assertSame(0, $this->summary()['Inactive carts']);
        $this->assertSame(1, $this->summary()['Carts started']);
    }

    public function test_payment_events_are_cart_counts_and_server_owns_prices_and_outcomes(): void
    {
        $this->postJson(route('shop.cart.add', $this->product()), ['quantity' => 1]);
        $this->postJson(route('shop.checkout.activity'), ['stage' => 'payment', 'total' => 1, 'outcome' => 'completed'])->assertNoContent();
        $this->postJson(route('shop.checkout.activity'), ['stage' => 'payment_failed'])->assertNoContent();
        $this->postJson(route('shop.checkout.activity'), ['stage' => 'payment_failed'])->assertNoContent();
        $this->postJson(route('shop.checkout.activity'), ['stage' => 'payment_cancelled'])->assertNoContent();
        $this->postJson(route('shop.checkout.activity'), ['stage' => 'completed'])->assertUnprocessable();
        $this->assertDatabaseHas('store_checkout_sessions', ['stage' => 'payment', 'total' => 25, 'outcome' => null]);
        $this->assertSame(1, $this->summary()['Checkout started']);
        $this->assertSame(1, $this->summary()['Carts with payment failures']);
        $this->assertSame(1, $this->summary()['Carts with payment cancellations']);
        $this->getJson(route('shop.cart.show'));
        $this->assertDatabaseHas('store_checkout_sessions', ['stage' => 'payment']);
    }

    public function test_empty_carts_do_not_become_inactive_and_new_cart_has_new_identity(): void
    {
        $this->postJson(route('shop.cart.add', $this->product()), ['quantity' => 1]);
        $id = DB::table('store_checkout_sessions')->value('id');
        app(StoreCartService::class)->clear();
        $this->getJson(route('shop.cart.show'))->assertOk();
        $this->assertDatabaseHas('store_checkout_sessions', ['id' => $id, 'outcome' => 'cleared']);
        $this->postJson(route('shop.cart.add', $this->product()), ['quantity' => 1]);
        $this->assertDatabaseCount('store_checkout_sessions', 2);
        $this->travel(25)->hours();
        $this->assertSame(1, $this->summary()['Inactive carts']);
    }

    public function test_completed_orders_and_quote_requests_stop_counting_as_inactive(): void
    {
        foreach (['completed', 'quote_requested'] as $outcome) {
            $this->postJson(route('shop.cart.add', $this->product()), ['quantity' => 1]);
            $this->travel(25)->hours();
            $this->assertSame(1, $this->summary()['Inactive carts']);
            $request = request();
            app(StoreCheckoutAnalytics::class)->record($request, 'payment', $outcome, $outcome === 'completed' ? 123 : null);
            app(StoreCartService::class)->clear();
            $this->assertSame(0, $this->summary()['Inactive carts']);
        }
        $this->assertSame(1, $this->summary()['Orders completed']);
        $this->assertSame(1, $this->summary()['Shipping quote requests']);
    }

    public function test_admin_bots_and_disabled_analytics_are_not_tracked(): void
    {
        $product = $this->product();
        $this->withHeader('User-Agent', 'Googlebot')->postJson(route('shop.cart.add', $product), ['quantity' => 1]);
        $this->assertDatabaseCount('store_checkout_sessions', 0);
        $this->withHeader('User-Agent', 'Mozilla/5.0');
        config(['analytics.enabled' => false]);
        $this->getJson(route('shop.cart.show'));
        $this->assertDatabaseCount('store_checkout_sessions', 0);
        config(['analytics.enabled' => true]);
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->getJson(route('shop.cart.show'));
        $this->assertDatabaseCount('store_checkout_sessions', 0);
    }

    public function test_value_ranges_use_matching_carts_and_calculate_even_and_odd_medians(): void
    {
        $report = app(StoreCheckoutReport::class);
        $from = today()->subDays(7);
        $to = now();
        $this->assertSame(['lowest' => null, 'highest' => null, 'median' => null], $report->values($from, $to)['completed']);
        $insert = function (float $value, ?string $outcome, $created, $active): void {
            DB::table('store_checkout_sessions')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'subtotal' => $value,
                'outcome' => $outcome, 'created_at' => $created, 'updated_at' => $active,
                'last_activity_at' => $active,
            ]);
        };
        foreach ([0, 10, 30, 100] as $value) {
            $insert($value, 'completed', now()->subDays(2), now());
        }
        foreach ([10, 20, 90] as $value) {
            $insert($value, null, now()->subDays(2), now()->subHours(25));
        }
        $insert(999, null, now()->subDays(2), now()); // Still active.
        $insert(999, 'cleared', now()->subDays(2), now()->subDays(2));
        $insert(999, 'quote_requested', now()->subDays(2), now()->subDays(2));
        $insert(999, 'completed', now()->subDays(10), now()); // Outside cohort.
        $values = $report->values($from, $to);
        $this->assertSame(['lowest' => 0.0, 'highest' => 100.0, 'median' => 20.0], $values['completed']);
        $this->assertSame(['lowest' => 10.0, 'highest' => 90.0, 'median' => 20.0], $values['inactive']);
    }

    public function test_dashboard_renders_checkout_counts_from_live_and_cached_snapshots(): void
    {
        $product = $this->product();
        $this->postJson(route('shop.cart.add', $product), ['quantity' => 1]);
        $this->travel(25)->hours();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        config(['analytics.dashboard_snapshot_seconds' => 300]);
        $snapshot = app(\App\Services\DashboardSnapshot::class);
        $snapshot->refresh('overview');
        $data = $snapshot->get('overview');
        $this->assertSame(1, $data['checkoutActivity']['Inactive carts']);
        $this->assertEquals(['lowest' => 25, 'highest' => 25, 'median' => 25], $data['checkoutValues']['inactive']);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Successful checkouts')->assertSee('Abandoned carts')->assertSee('Median')->assertSee('$25.00');
    }

    public function test_reports_are_admin_only_and_filter_cart_summaries_by_item(): void
    {
        $one = $this->product();
        $two = $this->product();
        $this->postJson(route('shop.cart.add', $one), ['quantity' => 2]);
        app(StoreCheckoutAnalytics::class)->record(request(), 'payment', 'completed', 1);
        app(StoreCartService::class)->clear();
        $this->postJson(route('shop.cart.add', $two), ['quantity' => 3]);
        $this->travel(25)->hours();
        $this->get(route('admin.analytics.checkout'))->assertRedirect();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->get(route('admin.analytics.checkout', ['product' => $two->id]))
            ->assertOk()->assertSee('Store checkout activity')->assertSee('Inactive')->assertSee('3 ×')
            ->assertViewHas('carts', fn ($carts) => $carts->total() === 1)
            ->assertViewHas('items', fn ($items) => $items->total() === 2);
        $rows = app(StoreCheckoutReport::class)->items(today()->subDays(30), now())->get();
        $this->assertEquals(1, $rows->first()->inactive);
        $this->assertEquals(3, $rows->first()->inactive_quantity);
        $this->assertEquals(75, $rows->first()->inactive_value);
        $this->get(route('admin.analytics.checkout', ['from' => 'bad']))->assertSessionHasErrors('from');
    }
}
