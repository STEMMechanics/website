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
        $line = WorkshopLine::normalize(['kind' => 'multi_workshop', 'workshops' => [
            ['description' => 'Library', 'workshop_date' => '2026-09-01', 'workshop_hours' => 2, 'workshop_seats' => 15, 'venue_supplied' => true],
        ]]);
        $this->assertSame(30.0, $line['quantity']);
        $this->assertSame('- 01/09/2026 - Library - (2 hrs × 15 seats)', $line['notes']);
        $custom = array_replace($line, ['notes' => 'Custom delivery instructions']);
        $this->assertSame('Custom delivery instructions', WorkshopLine::normalize($custom)['notes']);
        unset($line['workshops']);
        $this->assertSame($line, WorkshopLine::normalize($line));
        $this->assertCount(2, InvoicePdfLines::prepare([$line, $line]));
    }

    public function test_multi_workshop_spacing_is_preserved_in_pdf_notes(): void
    {
        $line = WorkshopLine::normalize(['kind' => 'multi_workshop', 'workshops' => [
            ['description' => 'Term 1', 'workshop_date' => '2026-09-01', 'workshop_hours' => 1, 'workshop_seats' => 15, 'spacer_after' => true],
            ['description' => 'Term 2', 'workshop_date' => '2026-10-01', 'workshop_hours' => 1, 'workshop_seats' => 15],
        ]]);

        $this->assertTrue($line['details_json']['multi_workshop']['rows'][0]['spacer_after']);
        $this->assertSame(
            "- 01/09/2026 - Term 1 - (1 hr × 15 seats)\n\n- 01/10/2026 - Term 2 - (1 hr × 15 seats)",
            InvoicePdfLines::notesForPdf($line)
        );
    }

    public function test_multi_workshop_spacing_aliases_hide_delivery_data_and_do_not_change_totals(): void
    {
        $line = WorkshopLine::normalize(['kind' => 'multi_workshop', 'workshops' => [
            ['description' => 'Library A', 'workshop_date' => '2026-09-01', 'workshop_hours' => 1, 'workshop_seats' => 15],
            ['description' => 'space'],
            ['description' => 'Library B', 'workshop_date' => '2026-10-01', 'workshop_hours' => 2, 'workshop_seats' => 10],
        ]]);

        $this->assertSame(35.0, $line['quantity']);
        $this->assertSame('spacing', $line['details_json']['multi_workshop']['rows'][1]['row_type']);
        $this->assertSame('- (space)', $line['details_json']['multi_workshop']['rows'][1]['description']);
        $this->assertSame(
            "- 01/09/2026 - Library A - (1 hr × 15 seats)\n\n- 01/10/2026 - Library B - (2 hrs × 10 seats)",
            InvoicePdfLines::notesForPdf($line)
        );
        $this->assertCount(2, WorkshopLine::allocationLines([$line]));
    }

    public function test_empty_multi_workshop_rows_do_not_create_seat_hours(): void
    {
        $line = WorkshopLine::normalize(['kind' => 'multi_workshop', 'workshops' => [
            ['description' => '', 'workshop_date' => '', 'workshop_hours' => '', 'workshop_seats' => '', 'venue_supplied' => true],
        ]]);

        $this->assertSame(0, $line['quantity']);
        $this->assertSame([], $line['details_json']['multi_workshop']['rows']);
    }

    public function test_multi_travel_rows_share_one_hourly_price_and_are_presented_together(): void
    {
        $line = WorkshopLine::normalize(['kind' => 'multi_travel', 'travel_rows' => [
            ['description' => 'Library A', 'travel_date' => '2026-09-01', 'travel_hours' => 0.5],
            ['description' => 'space'],
            ['description' => 'Library B', 'travel_date' => '2026-10-01', 'travel_hours' => 1.25],
        ]]);

        $this->assertSame(1.75, $line['quantity']);
        $this->assertSame(2, $line['details_json']['multi_travel']['rows'][0]['details_json']['travel']['billable_units']);
        $this->assertSame('- (space)', $line['details_json']['multi_travel']['rows'][1]['description']);
        $this->assertSame(
            "- 01/09/2026 - Library A - 0.5 hrs\n\n- 01/10/2026 - Library B - 1.25 hrs",
            InvoicePdfLines::notesForPdf($line)
        );
        $expanded = WorkshopLine::allocationLines([$line]);
        $this->assertSame(['travel', 'travel'], array_column($expanded, 'kind'));
        $this->assertSame([2, 5], array_map(fn (array $item): int => $item['details_json']['travel']['billable_units'], $expanded));
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
