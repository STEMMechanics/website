<?php

namespace Tests\Unit;

use App\Models\Workshop;
use Tests\TestCase;

class WorkshopPricingTest extends TestCase
{
    public function test_ticket_pricing_exposes_original_price_when_early_bird_is_active(): void
    {
        $workshop = new Workshop();
        $workshop->forceFill([
            'price' => '120.00',
            'early_bird_price' => '90.00',
            'early_bird_ends_at' => now()->addDay(),
            'early_bird_ticket_limit' => 10,
        ]);
        $workshop->setAttribute('active_tickets_count', 0);

        $pricing = $workshop->ticketPricing();

        $this->assertSame(90.0, $pricing['ticketPriceAmount']);
        $this->assertSame(120.0, $pricing['nonDiscountAmount']);
        $this->assertSame('Save $30.00 with earlybird pricing. Ends '.now()->addDay()->format('d M').'. Limited tickets available', $pricing['earlyBirdSummary']);
    }

    public function test_ticket_pricing_uses_the_current_price_when_no_discount_exists(): void
    {
        $workshop = new Workshop();
        $workshop->forceFill([
            'price' => '45.00',
        ]);

        $pricing = $workshop->ticketPricing();

        $this->assertSame(45.0, $pricing['ticketPriceAmount']);
        $this->assertSame(45.0, $pricing['nonDiscountAmount']);
        $this->assertNull($pricing['earlyBirdSummary']);
    }

    public function test_ticket_invoice_line_notes_include_an_early_bird_marker_only_when_the_ticket_is_early_bird(): void
    {
        $workshop = new Workshop();
        $workshop->forceFill([
            'title' => 'Pinball Workshop',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHour(),
        ]);

        $earlyBirdTicket = new \App\Models\Ticket();
        $earlyBirdTicket->forceFill([
            'is_early_bird' => true,
        ]);

        $standardTicket = new \App\Models\Ticket();
        $standardTicket->forceFill([
            'is_early_bird' => false,
        ]);

        $earlyBirdNotes = $workshop->ticketInvoiceLineNotes($earlyBirdTicket, ['Reserved by admin as pay-at-door ticket.']);
        $standardNotes = $workshop->ticketInvoiceLineNotes($standardTicket);

        $this->assertStringContainsString('Workshop date/time:', $earlyBirdNotes);
        $this->assertStringContainsString('Workshop location:', $earlyBirdNotes);
        $this->assertStringContainsString('Early Bird ticket.', $earlyBirdNotes);
        $this->assertStringContainsString('Reserved by admin as pay-at-door ticket.', $earlyBirdNotes);
        $this->assertStringNotContainsString('Early Bird ticket.', $standardNotes);
    }

    public function test_early_bird_note_does_not_create_an_offer_on_its_own(): void
    {
        $workshop = new Workshop();
        $workshop->forceFill([
            'price' => '45.00',
            'early_bird_note' => 'Gift pack included',
        ]);

        $pricing = $workshop->ticketPricing();

        $this->assertSame(45.0, $pricing['ticketPriceAmount']);
        $this->assertSame(45.0, $pricing['nonDiscountAmount']);
        $this->assertNull($pricing['earlyBirdSummary']);
        $this->assertFalse($workshop->hasEarlyBirdOffer());
    }
}
