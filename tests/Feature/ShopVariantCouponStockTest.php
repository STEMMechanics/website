<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShopVariantCouponStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_checkout_a_variant_with_coupon_and_satchel_shipping(): void
    {
        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createPayment')->once()->andReturn([
            'payment' => [
                'id' => 'sq-payment-variant-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-variant-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 5295],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 20.00,
            'inventory_quantity' => null,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 2,
            'weight_grams' => 500,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large Kit',
            'price' => 25.00,
            'inventory_quantity' => 5,
            'weight_grams' => 800,
        ]);
        Coupon::factory()->create([
            'code' => 'SAVE10',
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => 10,
            'minimum_order_amount' => 20,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $this->post(route('shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
            'shipping_country' => 'Australia',
        ])->assertRedirect(route('shop.cart.show', ['shipping_country' => 'Australia']));

        $response = $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_name' => 'Avery Example',
            'shipping_phone' => '0400123456',
            'shipping_address' => '123 Maker Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
        ]);

        $response->assertRedirect(route('shop.checkout.payment'));
        $this->assertSame(0, StoreOrder::query()->count());

        $response = $this->post(route('shop.checkout.payment.process'), [
            'source_id' => 'cnon:card-nonce-ok',
        ]);

        $order = StoreOrder::query()->with('items')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(10.00, round((float) $order->discount_amount, 2));
        $this->assertSame('Satchel shipping', $order->shipping_method);
        $this->assertSame('1 x Medium Satchel', $order->shipping_package_summary);
        $this->assertSame(12.95, round((float) $order->shipping_amount, 2));
        $this->assertSame(52.95, round((float) $order->total_amount, 2));
        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame('Large Kit', $item->variant_name);
        $this->assertSame(2, (int) $item->inventory_reserved_quantity);
        $this->assertSame(3, (int) $variant->fresh()->inventory_quantity);

        $response->assertRedirect(route('shop.order.show', [
            'storeOrder' => $order,
            'accessToken' => $order->access_token,
        ]));
    }

    public function test_cancelling_an_order_releases_reserved_stock(): void
    {
        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createPayment')->once()->andReturn([
            'payment' => [
                'id' => 'sq-payment-variant-2',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-variant-2',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 4295],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 30.00,
            'inventory_quantity' => 3,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
            'weight_grams' => 600,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ]);

        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Taylor Example',
            'billing_email' => 'taylor@example.com',
            'billing_phone' => '0400789000',
            'shipping_name' => 'Taylor Example',
            'shipping_phone' => '0400789000',
            'shipping_address' => '42 Test Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
        ])->assertRedirect(route('shop.checkout.payment'));

        $this->post(route('shop.checkout.payment.process'), [
            'source_id' => 'cnon:card-nonce-ok',
        ])->assertRedirect();

        $order = StoreOrder::query()->with('items')->firstOrFail();
        $this->assertSame(1, (int) $product->fresh()->inventory_quantity);

        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_CANCELLED,
                'notes' => 'Cancelled for test coverage.',
            ])
            ->assertRedirect();

        $this->assertSame(3, (int) $product->fresh()->inventory_quantity);
        $this->assertSame(0, (int) $order->fresh('items')->items->first()->inventory_reserved_quantity);
    }
}
