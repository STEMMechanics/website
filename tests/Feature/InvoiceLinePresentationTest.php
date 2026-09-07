<?php

namespace Tests\Feature;

use App\Services\Finance\InvoicePdfLines;
use App\Services\Finance\WorkshopLine;
use Tests\TestCase;

class InvoiceLinePresentationTest extends TestCase
{
    public function test_hours_are_stored_with_quarter_hour_allocation_units(): void
    {
        $item = WorkshopLine::normalize(['kind' => 'travel', 'travel_hours' => 2.25]);
        $this->assertSame(2.25, $item['quantity']);
        $this->assertSame(9, $item['details_json']['travel']['billable_units']);
        $this->assertSame('hours', $item['details_json']['travel']['quantity_basis']);
    }

    public function test_pdf_preserves_separate_delivery_items_and_legacy_travel_value(): void
    {
        $line = ['kind' => 'workshop', 'description' => 'Term 1', 'quantity' => 10, 'unit_price_ex_tax' => 12, 'notes' => 'Original notes'];
        $travel = ['kind' => 'travel', 'quantity' => 9, 'unit_price_ex_tax' => 30.91, 'line_total_ex_tax' => 278.18, 'details_json' => ['travel' => ['billable_units' => 9]]];
        $items = InvoicePdfLines::prepare([$line, $travel, $line]);
        $this->assertCount(3, $items);
        $this->assertSame($line, $items[0]);
        $this->assertSame($line, $items[2]);
        $this->assertSame(2.25, $items[1]['quantity']);
        $this->assertSame(123.64, $items[1]['unit_price_ex_tax']);
        $this->assertSame(278.18, $items[1]['line_total_ex_tax']);
    }

    public function test_multi_workshop_notes_are_generated_from_validated_metadata(): void
    {
        $line = WorkshopLine::normalize(['kind' => 'multi_workshop', 'notes' => 'stale', 'workshops' => [
            ['description' => 'Library', 'workshop_date' => '2026-09-01', 'workshop_hours' => 2, 'workshop_seats' => 15, 'venue_supplied' => true],
        ]]);
        $this->assertSame(30.0, $line['quantity']);
        $this->assertSame('- 01/09/2026 - Library - (2 hr / 15 seats)', $line['notes']);
        unset($line['workshops']);
        $this->assertSame($line, WorkshopLine::normalize($line));
        $this->assertCount(2, InvoicePdfLines::prepare([$line, $line]));
    }
    public function test_saved_group_price_converts_to_seat_hours_without_changing_amount(): void
    {
        $line = ['kind' => 'multi_workshop', 'quantity' => 1, 'unit_price' => 500, 'details_json' => ['multi_workshop' => ['rows' => [
            ['description' => 'Library', 'workshop_hours' => 1, 'workshop_seats' => 40],
        ]]]];
        $line = WorkshopLine::normalize($line);
        $this->assertSame(40.0, $line['quantity']);
        $this->assertSame(12.5, $line['unit_price']);
        $this->assertSame(550.0, WorkshopLine::amounts($line, $line['quantity'], $line['unit_price'], 0.1)['gross']);
        $this->assertSame($line, WorkshopLine::normalize($line));
    }

}
