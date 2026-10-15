<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreShippingMethod;
use App\Services\StoreCartService;
use App\Services\StoreOrderService;
use App\Services\StoreShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShopCartValueShippingTest extends TestCase
{
    use RefreshDatabase;

    public static function cartValues(): array
    {
        return [
            'zero value goods' => [0.00, 12.95],
            'freight must not count toward the threshold' => [137.05, 12.95],
            'one cent below' => [149.99, 12.95],
            'exact threshold including GST' => [150.00, 0.00],
            'one cent above' => [150.01, 0.00],
        ];
    }

    #[DataProvider('cartValues')]
    public function test_cart_and_order_apply_the_same_inclusive_cart_value_tier(float $goodsValue, float $freight): void
    {
        $this->configureCartValuePricing();
        $this->addProduct($goodsValue);
        $summary = app(StoreCartService::class)->summary();

        $this->assertSame($goodsValue, $summary['subtotal']);
        $this->assertSame($freight, $summary['shipping']);
        $this->assertSame($freight, $summary['shipping_methods'][0]['estimated_amount']);
        $this->assertSame($goodsValue, (float) $summary['shipping_quote']['cart_value']);
        $payload = app(StoreCartService::class)->payload();
        $this->assertFalse($payload['has_selected_shipping_method']);
        $this->assertSame($freight, $payload['summary']['shipping']);
        $this->assertOrderMatchesSummary($summary);
    }

    public static function coupons(): array
    {
        return [
            'fixed discount drops below threshold' => [159.99, Coupon::DISCOUNT_TYPE_FIXED_AMOUNT, 10, 149.99, 12.95, 10.00],
            'fixed discount leaves exact threshold' => [160.00, Coupon::DISCOUNT_TYPE_FIXED_AMOUNT, 10, 150.00, 0.00, 10.00],
            'percentage rounds below threshold' => [166.66, Coupon::DISCOUNT_TYPE_PERCENTAGE, 10, 149.99, 12.95, 16.67],
            'percentage leaves exact threshold' => [166.67, Coupon::DISCOUNT_TYPE_PERCENTAGE, 10, 150.00, 0.00, 16.67],
            'shipping voucher waives paid freight' => [149.99, Coupon::DISCOUNT_TYPE_FREE_SHIPPING, 0, 149.99, 12.95, 12.95],
            'shipping voucher does not discount already free freight' => [150.00, Coupon::DISCOUNT_TYPE_FREE_SHIPPING, 0, 150.00, 0.00, 0.00],
        ];
    }

    #[DataProvider('coupons')]
    public function test_tiers_use_goods_value_after_product_discounts_and_handle_shipping_vouchers(
        float $price, string $couponType, float $couponAmount, float $cartValue, float $freight, float $discount,
    ): void {
        $this->configureCartValuePricing();
        $this->addProduct($price);
        Coupon::factory()->create(['code' => 'FREIGHTTEST', 'discount_type' => $couponType, 'amount' => $couponAmount]);
        $response = $this->postJson(route('shop.cart.coupon.apply'), ['coupon_code' => 'FREIGHTTEST'])->assertOk();
        $this->assertSame($freight, (float) $response->json('cart.summary.shipping'));
        $this->assertSame($discount, (float) $response->json('cart.summary.discount'));

        $summary = app(StoreCartService::class)->summary();
        $this->assertSame($cartValue, (float) $summary['shipping_quote']['cart_value']);
        $this->assertOrderMatchesSummary($summary, 'FREIGHTTEST');
    }

    public function test_split_shipments_have_only_one_order_charge_without_packing(): void
    {
        $this->configureCartValuePricing();
        $this->addProduct(20, [
            'inventory_quantity' => 1, 'allow_backorder' => true, 'weight_grams' => 3000,
            'backorder_shipping_estimate' => now()->addWeek(),
        ], 3);
        $cart = app(StoreCartService::class);
        $split = $cart->summary();
        $this->assertTrue($split['shipping_quote']['split_shipments']);
        $this->assertSame(0, $split['shipping_quote']['parcel_count']);
        $this->assertSame([], $split['shipping_quote']['parcels']);
        $this->assertSame([], $split['shipping_quote']['package_breakdown']);
        $this->assertSame(12.95, $split['shipping']);
        $this->assertSame([12.95, 0.0], array_column($split['shipping_quote']['shipments'], 'amount'));
        $this->assertSame(0.0, $split['shipping_quote']['second_shipment_charge_amount']);
        $this->assertSame(0.0, $split['shipping_quote']['consolidation_savings_amount']);

        $combined = $cart->summary(['consolidate_shipments' => true]);
        $this->assertSame(12.95, $combined['shipping']);
        $this->assertSame(1, $combined['shipping_quote']['shipment_count']);
        $this->assertSame(0.0, $combined['shipping_quote']['consolidation_savings_amount']);
        $this->assertOrderMatchesSummary($split);
    }

    public static function productMeasurements(): array
    {
        return [
            'missing measurements' => [[
                'shipping_units' => 0, 'weight_grams' => null,
                'length_mm' => null, 'width_mm' => null, 'height_mm' => null,
            ]],
            'oversized and overweight' => [[
                'shipping_units' => 999, 'min_satchel_rank' => 99, 'box_only' => true,
                'length_mm' => 10000, 'width_mm' => 10000, 'height_mm' => 10000, 'weight_grams' => 100000000,
            ]],
        ];
    }

    #[DataProvider('productMeasurements')]
    public function test_cart_value_prices_ignore_product_measurements_and_saved_box_limits(array $attributes): void
    {
        $method = $this->configureCartValuePricing();
        $method->update([
            'cubic_divisor' => 4000, 'calculated_packaging_cost' => 99,
            'weight_tiers' => [['max_weight_grams' => 1, 'price' => 99]],
        ]);
        $method->packageOptions()->create([
            'code' => 'tiny', 'label' => 'Tiny box', 'capacity' => 0.01, 'price' => 99,
            'internal_length_mm' => 1, 'internal_width_mm' => 1, 'internal_height_mm' => 1,
            'max_weight_grams' => 1, 'is_active' => true,
        ]);
        $this->addProduct(20, $attributes, 2);
        $summary = app(StoreCartService::class)->summary(['shipping_method_code' => 'regular']);

        $this->assertTrue($summary['can_checkout']);
        $this->assertSame('regular', $summary['shipping_method_code']);
        $this->assertFalse($summary['shipping_quote']['requires_manual_quote']);
        $this->assertFalse($summary['shipping_quote']['is_pickup']);
        $this->assertSame([], $summary['shipping_quote']['parcels']);
        $this->assertSame(12.95, $summary['shipping']);
        $this->assertOrderMatchesSummary($summary);
    }

    public function test_highest_matching_tier_wins_and_other_channels_keep_calculated_prices(): void
    {
        $method = $this->configureCartValuePricing();
        $method->update(['cart_value_tiers' => [
            ['min_cart_value' => 300, 'price' => 0],
            ['min_cart_value' => 0, 'price' => 12.95],
            ['min_cart_value' => 100, 'price' => 5],
        ]]);
        $this->addProduct(200);
        $summary = app(StoreCartService::class)->summary();

        $this->assertSame(5.0, $summary['shipping']);
        $this->assertSame(13.43, collect($summary['shipping_methods'])->firstWhere('code', 'express')['estimated_amount']);
        $this->assertSame(0.0, collect($summary['shipping_methods'])->firstWhere('code', 'pickup')['estimated_amount']);
    }

    public function test_a_missing_cart_value_price_cannot_be_used_as_free_delivery(): void
    {
        $method = $this->configureCartValuePricing();
        $method->update(['cart_value_tiers' => []]);
        $this->addProduct(200);

        $quote = app(StoreShippingService::class)->quote(app(StoreCartService::class)->lines(), 'Australia', 'regular');
        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['requires_manual_quote']);
        $this->assertSame('This delivery channel has no matching cart value price.', $quote['reason']);
    }

    public function test_any_channel_can_use_pickup_and_orders_remember_their_original_delivery_type(): void
    {
        $method = $this->configureCartValuePricing();
        $method->update(['pricing_type' => StoreShippingMethod::PRICING_PICKUP]);
        $this->addProduct(0);
        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Pickup Example', 'billing_email' => 'pickup@example.com', 'billing_phone' => '0400123456',
            'billing_address' => '123 Maker Street', 'billing_city' => 'Brisbane', 'billing_state' => 'QLD',
            'billing_postcode' => '4000', 'billing_country' => 'Australia', 'shipping_method_code' => 'regular',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $order = StoreOrder::query()->firstOrFail();
        $this->assertTrue($order->usesPickup());
        $this->assertSame(0.0, (float) $order->shipping_amount);
        $this->assertEmpty($order->shipping_address);

        $method->update(['pricing_type' => StoreShippingMethod::PRICING_CART_VALUE]);
        $this->assertTrue($order->fresh()->usesPickup());
    }

    private function configureCartValuePricing(): StoreShippingMethod
    {
        $method = StoreShippingMethod::query()->where('code', 'regular')->firstOrFail();
        $method->update(['pricing_type' => StoreShippingMethod::PRICING_CART_VALUE, 'cart_value_tiers' => [
            ['min_cart_value' => 0, 'price' => 12.95],
            ['min_cart_value' => 150, 'price' => 0],
        ]]);
        $method->packageOptions()->delete();

        return $method;
    }

    private function addProduct(float $price, array $attributes = [], int $quantity = 1): void
    {
        $product = Product::factory()->create(array_merge([
            'status' => Product::STATUS_ACTIVE, 'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => $price, 'tax_rate' => 0.1, 'inventory_quantity' => null,
            'shipping_units' => 0.5, 'min_satchel_rank' => 1, 'weight_grams' => 200,
        ], $attributes));
        $this->postJson(route('shop.cart.add', $product), ['quantity' => $quantity])->assertOk();
    }

    private function assertOrderMatchesSummary(array $summary, ?string $couponCode = null): void
    {
        $order = app(StoreOrderService::class)->createFromCart(app(StoreCartService::class)->lines(), [
            'billing_name' => 'Freight Example', 'billing_email' => 'freight@example.com', 'billing_phone' => '0400123456',
            'shipping_name' => 'Freight Example', 'shipping_phone' => '0400123456',
            'shipping_address' => '123 Maker Street', 'shipping_city' => 'Brisbane', 'shipping_state' => 'QLD',
            'shipping_postcode' => '4000', 'shipping_country' => 'Australia', 'shipping_method_code' => 'regular',
            'coupon_code' => $couponCode,
        ], sendNotifications: false);

        $this->assertSame($summary['shipping'], (float) $order->shipping_amount);
        $this->assertSame($summary['discount'], (float) $order->discount_amount);
        $this->assertSame($summary['total'], (float) $order->total_amount);
        $this->assertSame($summary['gst'], (float) $order->gst_amount);
        $this->assertEquals($summary['shipping_quote']['matched_cart_value_tier'], $order->shippingBreakdown()['matched_cart_value_tier']);
        $this->assertSame($summary['shipping'], (float) collect($order->shippingBreakdown()['shipments'])->sum('amount'));

        StoreShippingMethod::query()->where('code', 'regular')->update(['pricing_type' => StoreShippingMethod::PRICING_CALCULATED]);
        $this->assertSame($summary['shipping'], (float) $order->fresh()->shipping_amount);
    }
}
