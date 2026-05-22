<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SiteOption;
use App\Services\StoreShippingService;
use App\Support\ShopShippingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StoreShippingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_packs_small_items_into_the_smallest_package(): void
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
        $this->assertSame('1 x Small', $quote['package_summary']);
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
        $this->assertSame('1 x Large', $quote['package_summary']);
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
        $this->assertSame('2 x Small', $quote['package_summary']);
        $this->assertSame(19.90, round((float) $quote['amount'], 2));
        $this->assertSame(2, (int) $quote['parcel_count']);
    }

    public function test_box_only_items_can_use_rigid_package_channels(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Framed print', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 4,
                'box_only' => true,
            ]),
        ]));

        $this->assertTrue($quote['can_checkout']);
        $this->assertFalse($quote['boxed_shipping_required']);
        $this->assertSame('1 x Extra Large', $quote['package_summary']);
        $this->assertSame(18.95, round((float) $quote['amount'], 2));
    }

    public function test_it_falls_back_to_boxed_shipping_when_package_units_are_missing(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Unknown pack size', [
                'shipping_units' => 0,
                'min_satchel_rank' => 1,
            ]),
        ]));

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['boxed_shipping_required']);
        $this->assertSame('Some physical products do not have package units configured.', $quote['reason']);
    }

    public function test_it_marks_lines_that_trigger_a_manual_quote(): void
    {
        config()->set('store.shipping.boxed_shipping.amount', null);

        $quote = $this->service()->quote(collect([
            $this->line('Framed print', [
                'shipping_units' => 0.0,
                'min_satchel_rank' => 1,
                'key' => 'line-framed-print',
            ]),
        ]));

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['requires_manual_quote']);
        $this->assertSame(['line-framed-print'], $quote['manual_quote_line_keys']);
    }

    public function test_it_only_marks_the_line_that_triggers_a_manual_quote_in_a_mixed_cart(): void
    {
        config()->set('store.shipping.boxed_shipping.amount', null);

        $quote = $this->service()->quote(collect([
            $this->line('Manual quote item', [
                'shipping_units' => 0.0,
                'min_satchel_rank' => 1,
                'key' => 'manual-quote-item',
            ]),
            $this->line('Regular item', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 1,
                'key' => 'regular-item',
            ]),
        ]));

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue($quote['requires_manual_quote']);
        $this->assertSame(['manual-quote-item'], $quote['manual_quote_line_keys']);
    }

    public function test_pickup_method_returns_a_free_quote(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Workshop kit', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 2,
                'weight_grams' => 700,
            ]),
        ]), 'Australia', 'pickup');

        $this->assertTrue($quote['can_checkout']);
        $this->assertSame('pickup', $quote['selected_method_code']);
        $this->assertSame('Pick up', $quote['method']);
        $this->assertTrue($quote['is_pickup']);
        $this->assertSame(0.00, round((float) $quote['amount'], 2));
    }

    public function test_express_method_uses_its_own_eta_and_rate_multiplier(): void
    {
        $quote = $this->service()->quote(collect([
            $this->line('Workshop kit', [
                'shipping_units' => 1.0,
                'min_satchel_rank' => 1,
                'weight_grams' => 700,
            ]),
        ]), 'Australia', 'express');

        $this->assertTrue($quote['can_checkout']);
        $this->assertSame('express', $quote['selected_method_code']);
        $this->assertSame('Express shipping', $quote['method']);
        $this->assertSame('1-3 business days', $quote['delivery_estimate_label']);
        $this->assertSame(13.43, round((float) $quote['amount'], 2));
    }

    public function test_it_can_split_and_consolidate_delayed_shipments(): void
    {
        $lines = collect([
            $this->line('Circuit kit', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 150,
                'quantity' => 2,
                'available_now_quantity' => 1,
                'delayed_quantity' => 1,
                'delayed_fulfilment_type' => 'backorder',
                'delayed_shipping_estimate' => Carbon::parse('2026-04-20'),
            ], 2),
        ]);

        $splitQuote = $this->service()->quote($lines, 'Australia', 'regular', false);
        $consolidatedQuote = $this->service()->quote($lines, 'Australia', 'regular', true);

        $this->assertTrue($splitQuote['split_shipments']);
        $this->assertSame(2, (int) $splitQuote['shipment_count']);
        $this->assertSame(9.95, round((float) $splitQuote['second_shipment_charge_amount'], 2));
        $this->assertSame(9.95, round((float) $splitQuote['consolidation_savings_amount'], 2));
        $this->assertSame(19.90, round((float) $splitQuote['amount'], 2));
        $this->assertSame('Shipment 2: Ships later - Estimated April 20th 2026', $splitQuote['shipments'][1]['title']);
        $this->assertSame('Shipment 2: Ships later', $splitQuote['shipments'][1]['title_primary']);
        $this->assertSame('Estimated April 20th 2026', $splitQuote['shipments'][1]['title_meta']);
        $this->assertArrayNotHasKey('shipping_estimate', $splitQuote['shipments'][0]['items'][0]);
        $this->assertArrayNotHasKey('shipping_estimate', $splitQuote['shipments'][1]['items'][0]);

        $this->assertFalse($consolidatedQuote['split_shipments']);
        $this->assertTrue($consolidatedQuote['consolidate_shipments']);
        $this->assertSame(1, (int) $consolidatedQuote['shipment_count']);
        $this->assertSame(9.95, round((float) $consolidatedQuote['amount'], 2));
        $this->assertSame(9.95, round((float) $consolidatedQuote['consolidation_savings_amount'], 2));
        $this->assertSame('Single shipment once all items are available - Estimated April 20th 2026', $consolidatedQuote['shipments'][0]['title']);
        $this->assertSame('Single shipment once all items are available', $consolidatedQuote['shipments'][0]['title_primary']);
        $this->assertSame('Estimated April 20th 2026', $consolidatedQuote['shipments'][0]['title_meta']);
    }

    public function test_it_pushes_shipments_back_when_the_store_is_away(): void
    {
        $pauseUntil = Carbon::parse('2026-06-01')->startOfDay();
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::PROCESSING_PAUSE_UNTIL_OPTION],
            ['value' => $pauseUntil->toDateString()],
        );

        $lines = collect([
            $this->line('Circuit kit', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 150,
                'quantity' => 2,
                'available_now_quantity' => 1,
                'delayed_quantity' => 1,
                'delayed_fulfilment_type' => 'backorder',
                'delayed_shipping_estimate' => Carbon::parse('2026-05-25'),
            ], 2),
        ]);

        $quote = $this->service()->quote($lines, 'Australia', 'regular', false);

        $this->assertSame('2026-06-01', $quote['processing_pause_until']);
        $this->assertSame('We are away for workshops until June 1st 2026. Orders placed now will be processed after we return.', $quote['processing_pause_notice']);
        $this->assertSame('2026-06-01', $quote['shipments'][0]['dispatch_date']);
        $this->assertSame('Processing from June 1st 2026', $quote['shipments'][0]['title_meta']);
        $this->assertSame('Shipment 1 - Processing from June 1st 2026', $quote['shipments'][0]['title']);
        $this->assertSame('2026-06-01', $quote['shipments'][1]['dispatch_date']);
        $this->assertSame('Shipment 2: Ships later - Estimated June 1st 2026', $quote['shipments'][1]['title']);
        $this->assertSame('Estimated June 1st 2026', $quote['shipments'][1]['title_meta']);
        $this->assertSame('2026-06-01', $quote['delayed_dispatch_date']);
    }

    public function test_pickup_uses_collection_terminology_for_split_availability(): void
    {
        $lines = collect([
            $this->line('Workshop kit', [
                'shipping_units' => 0.5,
                'min_satchel_rank' => 1,
                'weight_grams' => 150,
                'quantity' => 2,
                'available_now_quantity' => 1,
                'delayed_quantity' => 1,
                'delayed_fulfilment_type' => 'backorder',
            ], 2),
        ]);

        $quote = $this->service()->quote($lines, 'Australia', 'pickup', false);

        $this->assertTrue($quote['is_pickup']);
        $this->assertSame('Collection 1: Available now', $quote['shipments'][0]['title']);
        $this->assertSame('Collection 2: Available later', $quote['shipments'][1]['title']);
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
            'key' => (string) ($attributes['key'] ?? str($title)->slug('-')->value()),
            'product' => $product,
            'quantity' => $quantity,
            'display_title' => $title,
            'unit_shipping_units' => (float) ($attributes['shipping_units'] ?? 0),
            'unit_min_satchel_rank' => (int) ($attributes['min_satchel_rank'] ?? 1),
            'unit_weight_grams' => $attributes['weight_grams'] ?? null,
            'box_only' => (bool) ($attributes['box_only'] ?? false),
            'available_now_quantity' => $attributes['available_now_quantity'] ?? $quantity,
            'delayed_quantity' => $attributes['delayed_quantity'] ?? 0,
            'delayed_fulfilment_type' => $attributes['delayed_fulfilment_type'] ?? null,
            'delayed_shipping_estimate' => $attributes['delayed_shipping_estimate'] ?? null,
            'is_preorder' => (bool) ($attributes['is_preorder'] ?? false),
            'unit_price' => 0,
        ];
    }
}
