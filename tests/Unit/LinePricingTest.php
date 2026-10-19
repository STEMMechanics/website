<?php

namespace Tests\Unit;

use App\Services\Finance\LinePricing;
use PHPUnit\Framework\TestCase;

class LinePricingTest extends TestCase
{
    public function test_displayed_exclusive_rates_use_short_precision_and_reproduce_saved_subtotals(): void
    {
        foreach ([
            [30, 14.5 / 1.1, 395.45, '13.1818'],
            [15, 11.5 / 1.1, 156.82, '10.4545'],
            [1, 14.5 / 1.1, 13.18, '13.18'],
            [2, 12.5, 25, '12.50'],
            [-15, 11.5 / 1.1, -156.82, '10.4545'],
            [30, -14.5 / 1.1, -395.45, '-13.1818'],
        ] as [$quantity, $price, $net, $expected]) {
            $item = ['quantity' => $quantity, 'unit_price' => $price, 'line_total' => $net];
            $this->assertSame($expected, LinePricing::formatExclusiveUnit($item));
            $this->assertEquals($net, round($quantity * (float) $expected, 2));
        }
    }

    public function test_historical_rounded_rates_and_large_quantities_keep_their_saved_totals(): void
    {
        foreach ([[30, 12.27, 368.18], [10000, 1.23456789, 12345.68], [24000000, 0.00001648, 395.45], [1.5, 10, 15]] as [$quantity, $price, $net]) {
            $item = ['quantity' => $quantity, 'unit_price' => $price, 'line_total' => $net];
            $before = $item;
            $displayed = (float) str_replace(',', '', LinePricing::formatExclusiveUnit($item));
            $this->assertEquals($net, round($quantity * $displayed, 2));
            $this->assertSame($before, $item);
        }
    }
}
