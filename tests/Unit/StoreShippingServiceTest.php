<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\StoreShippingService;
use Tests\TestCase;

class StoreShippingServiceTest extends TestCase
{
    public function test_it_packs_small_items_into_the_smallest_satchel(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Microbit', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 200,
            ], 2),
        ]));

        $this->assertTrue($quote['can_checkout']);
        $this->assertFalse($quote['boxed_shipping_required']);
        $this->assertSame('1 x Small Satchel', $quote['package_summary']);
        $this->assertSame(9.95, round((float) $quote['amount'], 2));
        $this->assertSame(1, (int) $quote['parcel_count']);
    }

    public function test_it_respects_minimum_satchel_rank_when_packing(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Craft kit', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 3,
                'weight_grams' => 900,
            ]),
            $this->line('Sticker sheet', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 50,
            ]),
        ]));

        $this->assertTrue($quote['can_checkout']);
        $this->assertSame('1 x Large Satchel', $quote['package_summary']);
        $this->assertSame(15.95, round((float) $quote['amount'], 2));
        $this->assertSame('large', $quote['parcels'][0]['code']);
    }

    public function test_it_splits_parcels_when_known_weight_exceeds_the_limit(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Heavy part', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 3000,
            ], 2),
        ]));

        $this->assertTrue($quote['can_checkout']);
        $this->assertSame('2 x Small Satchels', $quote['package_summary']);
        $this->assertSame(19.90, round((float) $quote['amount'], 2));
        $this->assertSame(2, (int) $quote['parcel_count']);
    }

    public function test_it_falls_back_to_boxed_shipping_for_box_only_items(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Framed print', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 4,
                'box_only' => true,
            ]),
        ]));

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['boxed_shipping_required']);
        $this->assertTrue($quote['requires_manual_quote']);
    }

    public function test_it_falls_back_to_boxed_shipping_when_satchel_units_are_missing(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Unknown pack size', [
                'shipping_units' => 0,
                'min_satchel_rank' => 1,
            ]),
        ]));

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['boxed_shipping_required']);
        $this->assertSame('Some physical products do not have satchel shipping units configured.', $quote['reason']);
    }

    private function service(): StoreShippingService
    {
        return app(StoreShippingService::class);
    }

    private function line(string $title, array $attributes, int $quantity = 1): object
    {
        $product = new Product(array_merge([
            'title' => $title,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ], $attributes));

        return (object) [
            'product' => $product,
            'quantity' => $quantity,
            'display_title' => $title,
            'unit_shipping_units' => (float) ($attributes['shipping_units'] ?? 0),
            'unit_min_satchel_rank' => (int) ($attributes['min_satchel_rank'] ?? 1),
            'unit_weight_grams' => $attributes['weight_grams'] ?? null,
            'box_only' => (bool) ($attributes['box_only'] ?? false),
        ];
    }
}
