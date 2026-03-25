<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreOrder;
use App\Services\StoreCartService;
use App\Services\StoreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopPreorderShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_preorder_product_adds_without_acknowledgement_and_behaves_as_backorder(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'is_preorder' => true,
            'preorder_shipping_estimate' => Carbon::parse('2026-04-15'),
            'inventory_quantity' => 0,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
            'weight_grams' => 400,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $lines = app(StoreCartService::class)->lines();

        $this->assertCount(1, $lines);
        $this->assertSame('backorder', $lines->first()->delayed_fulfilment_type);
    }

    public function test_pickup_checkout_is_free_and_does_not_require_shipping_address(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 0,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
            'weight_grams' => 400,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_method_code' => 'pickup',
            'notes' => 'Pickup please.',
        ]);

        $order = StoreOrder::query()->firstOrFail();

        $response->assertRedirect(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $this->assertSame('pickup', $order->shipping_method_code);
        $this->assertSame('Pick up', $order->shipping_method);
        $this->assertSame(0.00, round((float) $order->shipping_amount, 2));
        $this->assertSame('Avery Example', $order->shipping_name);
        $this->assertSame('', (string) $order->shipping_address);
    }

    public function test_legacy_preorder_checkout_snapshots_as_backorder_metadata(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 0,
            'is_preorder' => true,
            'preorder_shipping_estimate' => Carbon::parse('2026-04-15'),
            'inventory_quantity' => 0,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
            'weight_grams' => 400,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertDontSee('Pre-order Acknowledgement');

        $response = $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Taylor Example',
            'billing_email' => 'taylor@example.com',
            'billing_phone' => '0400789000',
            'shipping_method_code' => 'pickup',
        ]);

        $order = StoreOrder::query()->with('items')->firstOrFail();
        $item = $order->items->first();
        $this->assertNotNull($item);

        $response->assertRedirect(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $this->assertFalse($order->contains_preorder);
        $this->assertFalse($order->preorder_acknowledged);
        $this->assertFalse($item->is_preorder);
        $this->assertNull($item->preorder_shipping_estimate);
        $this->assertSame('backorder', $item->delayed_fulfilment_type);
        $this->assertSame('2026-04-15', optional($item->delayed_shipping_estimate)->toDateString());
        $this->assertSame(0, (int) $item->inventory_reserved_quantity);
    }

    public function test_cart_payload_exposes_shipping_tiers_and_split_shipment_state_for_backorders(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => Carbon::parse('2026-04-22'),
            'inventory_quantity' => 1,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->getJson(route('shop.cart.show'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.shipping_quote.split_shipments', true)
            ->assertJsonPath('cart.summary.shipping_quote.shipment_count', 2)
            ->assertJsonPath('cart.summary.shipping_quote.offers_consolidation', true);

        $shippingCodes = collect($response->json('cart.summary.shipping_methods'))->pluck('code')->all();

        $this->assertSame(['regular', 'express', 'pickup'], $shippingCodes);
    }

    public function test_backorder_order_snapshots_split_shipments_and_only_reserves_available_stock(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 0,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => Carbon::parse('2026-04-22'),
            'inventory_quantity' => 1,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $order = app(StoreOrderService::class)->createFromCart(
            app(StoreCartService::class)->lines(),
            [
                'billing_name' => 'Jordan Example',
                'billing_email' => 'jordan@example.com',
                'billing_phone' => '0400555123',
                'shipping_name' => 'Jordan Example',
                'shipping_phone' => '0400555123',
                'shipping_address' => '123 Example Street',
                'shipping_city' => 'Brisbane',
                'shipping_state' => 'QLD',
                'shipping_postcode' => '4000',
                'shipping_country' => 'Australia',
                'shipping_method_code' => 'regular',
                'consolidate_shipments' => false,
            ],
        )->fresh(['items']);

        $item = $order->items->first();

        $this->assertNotNull($item);
        $this->assertSame(StoreOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertTrue($order->split_shipments);
        $this->assertFalse($order->consolidate_shipments);
        $this->assertSame(2, (int) $order->shipment_count);
        $this->assertCount(2, $order->shippingBreakdown()['shipments'] ?? []);
        $this->assertSame(19.90, round((float) $order->shipping_amount, 2));
        $this->assertSame(1, (int) $item->available_now_quantity);
        $this->assertSame(1, (int) $item->delayed_quantity);
        $this->assertSame('backorder', $item->delayed_fulfilment_type);
        $this->assertSame(1, (int) $item->inventory_reserved_quantity);
        $this->assertSame(0, (int) $product->fresh()->inventory_quantity);
    }

    public function test_cart_preferences_can_toggle_split_shipment_consolidation_and_reprice_the_quote(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => Carbon::parse('2026-04-22'),
            'inventory_quantity' => 1,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $this->postJson(route('shop.cart.preferences'), [
            'shipping_method_code' => 'regular',
            'consolidate_shipments' => false,
            'shipping_country' => 'Australia',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.shipping', 19.90)
            ->assertJsonPath('cart.summary.shipping_quote.split_shipments', true)
            ->assertJsonPath('cart.summary.shipping_quote.shipment_count', 2)
            ->assertJsonPath('cart.summary.shipping_quote.consolidation_savings_amount', 9.95)
            ->assertJsonPath('cart.summary.consolidate_shipments', false);

        $this->postJson(route('shop.cart.preferences'), [
            'shipping_method_code' => 'regular',
            'consolidate_shipments' => true,
            'shipping_country' => 'Australia',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.shipping', 9.95)
            ->assertJsonPath('cart.summary.shipping_quote.split_shipments', false)
            ->assertJsonPath('cart.summary.shipping_quote.shipment_count', 1)
            ->assertJsonPath('cart.summary.shipping_quote.consolidation_savings_amount', 9.95)
            ->assertJsonPath('cart.summary.consolidate_shipments', true);

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Single shipment once all items are available')
            ->assertSee('Estimated April 22nd 2026')
            ->assertDontSee('Shipment Plan');
    }

    public function test_checkout_and_payment_pages_render_split_shipment_details_for_backorders(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 12.00,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => Carbon::parse('2026-04-22'),
            'inventory_quantity' => 1,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Express shipping')
            ->assertSee('Shipment 2: Ships later - Estimated April 22nd 2026')
            ->assertSee('Save $9.95 by sending everything together.')
            ->assertDontSee('Shipment Plan')
            ->assertDontSee('Backordered until approximately April 22nd 2026');

        $this->from(route('shop.checkout'))->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Jordan Example',
            'billing_email' => 'jordan@example.com',
            'billing_phone' => '0400555123',
            'shipping_name' => 'Jordan Example',
            'shipping_phone' => '0400555123',
            'shipping_address' => '123 Example Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'shipping_method_code' => 'regular',
        ])->assertRedirect(route('shop.checkout'));

        $this->followRedirects($this->get(route('shop.checkout.payment')))
            ->assertOk()
            ->assertSee('Payment Details')
            ->assertSee('Shipment 2: Ships later - Estimated April 22nd 2026')
            ->assertSee('1 ships now, 1 ships later from April 22nd 2026');
    }
}
