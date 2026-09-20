<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAvailabilityEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_dates_use_expected_soon_for_products_and_variants_without_changing_inventory(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(15, 0));
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE, 'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'inventory_quantity' => 0, 'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-09-17', 'low_stock_threshold' => 5,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id, 'inventory_quantity' => 0, 'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-09-17',
        ]);
        $this->assertSame('Available to order. More expected soon', $product->availabilityLabel());
        $this->assertSame('Available to order. More expected soon', $product->availabilityLabel(variant: $variant));
        $this->get(route('shop.product.show', $product))->assertOk()
            ->assertSeeText('Available to order. More expected soon')
            ->assertDontSeeText('More expected September 17th');
        $this->assertSame('2026-09-17', $product->fresh()->backorder_shipping_estimate->toDateString());
        $this->assertSame(0, $product->fresh()->inventory_quantity);
        $product->inventory_quantity = 2;
        $this->assertSame('Low stock. More expected soon', $product->availabilityLabel());
        $product->inventory_quantity = 20;
        $this->assertSame('In stock', $product->availabilityLabel());
    }

    public function test_today_future_and_dynamic_estimates_keep_their_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(23, 59));
        $product = Product::factory()->create([
            'inventory_quantity' => 0, 'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-09-20',
        ]);
        $this->assertSame('Available to order. More expected September 20th', $product->availabilityLabel());
        $product->backorder_shipping_estimate = '2026-09-25';
        $this->assertSame('Available to order. More expected September 25th', $product->availabilityLabel());
        $product->backorder_shipping_estimate_type = Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC;
        $product->backorder_shipping_offset_days = 3;
        $this->assertSame('Available to order. More expected September 23rd', $product->availabilityLabel());
    }
}
