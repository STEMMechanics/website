<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\WorkshopLine;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWorkshopDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    private function group(int $seats = 12): array
    {
        return [
            'kind' => 'multi_workshop', 'description' => '', 'notes' => '',
            'unit_price_inc_tax' => 13.75, 'unit_price' => 12.5, 'gst_applicable' => true,
            'workshops' => [
                ['description' => 'Library Minecraft', 'workshop_date' => '2027-02-02', 'workshop_hours' => 1, 'workshop_seats' => $seats],
                ['description' => 'Library STEM', 'workshop_date' => '2027-03-02', 'workshop_hours' => 1, 'workshop_seats' => $seats],
            ],
        ];
    }

    private function quotePayload(Quote $quote, array $items): array
    {
        return [
            'quote_number' => $quote->quote_number,
            'user_id' => $quote->user_id,
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-10-12',
            'line_items_json' => json_encode($items),
        ];
    }

    public function test_clearing_a_saved_group_description_preserves_workshops_amounts_and_invoice_conversion(): void
    {
        $group = WorkshopLine::normalize(array_replace($this->group(), ['description' => 'Charged per hour, per seat']));
        $group += ['line_total' => 300, 'tax_amount' => 30, 'line_total_inc_tax' => 330];
        $other = [
            'kind' => 'custom', 'description' => 'Materials', 'quantity' => 1, 'unit_price' => 10, 'gst_applicable' => true,
            'line_total' => 10, 'tax_amount' => 1, 'line_total_inc_tax' => 11,
        ];
        $quote = Quote::factory()->create([
            'line_items' => [$group, $other], 'subtotal_amount' => 310, 'gst_amount' => 31, 'total_amount' => 341,
        ]);
        $group['description'] = '';

        $this->put(route('admin.quote.update', $quote), $this->quotePayload($quote, [$group, $other]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $quote->refresh();

        $this->assertCount(2, $quote->line_items);
        $this->assertSame('', $quote->line_items[0]['description']);
        $this->assertSame('', $quote->line_items[0]['notes']);
        $this->assertCount(2, $quote->line_items[0]['details_json']['multi_workshop']['rows']);
        $this->assertSame(24.0, (float) $quote->line_items[0]['quantity']);
        $this->assertSame(330.0, (float) $quote->line_items[0]['line_total_inc_tax']);
        $this->assertSame('341.00', $quote->total_amount);
        $this->get(route('admin.quote.edit', $quote))->assertOk();

        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('', $invoice->lines[0]->description);
        $this->assertSame('341.00', $invoice->total_amount);
        $this->assertSame($quote->line_items[0]['details_json']['multi_workshop'], $invoice->lines[0]->details_json['multi_workshop']);
        $html = view('pdf.quote', ['quote' => $quote])->render();
        $this->assertStringContainsString('Workshop Delivery', $html);
        $this->assertStringContainsString('02/02/2027 - Library Minecraft - (1 hr × 12 seats)', $html);
        $this->assertStringNotContainsString('Charged per hour, per seat', $html);
    }

    public function test_new_groups_with_blank_descriptions_are_saved_including_zero_seat_groups(): void
    {
        foreach ([12, 0] as $seats) {
            $quote = Quote::factory()->make(['quote_number' => 'BLANK-GROUP-'.$seats]);
            $this->post(route('admin.quote.store'), $this->quotePayload($quote, [$this->group($seats)]))
                ->assertSessionHasNoErrors()->assertRedirect();
            $saved = Quote::query()->where('quote_number', $quote->quote_number)->firstOrFail();

            $this->assertCount(1, $saved->line_items);
            $this->assertSame('', $saved->line_items[0]['description']);
            $this->assertCount(2, $saved->line_items[0]['details_json']['multi_workshop']['rows']);
            $this->assertSame($seats * 2.0, (float) $saved->line_items[0]['quantity']);
            $this->assertSame($seats * 27.5, (float) $saved->total_amount);
        }
    }

    public function test_blank_group_descriptions_and_zero_seats_survive_draft_invoice_saves(): void
    {
        foreach ([12, 0] as $seats) {
            $number = 'BLANK-INVOICE-'.$seats;
            $payload = ['invoice_number' => $number, 'issue_date' => '2026-10-12', 'line_items_json' => json_encode([$this->group($seats)])];
            $this->post(route('admin.invoice.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $invoice = Invoice::query()->where('invoice_number', $number)->firstOrFail();
            $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $invoice->refresh();

            $this->assertCount(1, $invoice->lines);
            $this->assertSame('', $invoice->lines[0]->description);
            $this->assertCount(2, $invoice->lines[0]->details_json['multi_workshop']['rows']);
            $this->assertSame($seats * 27.5, (float) $invoice->total_amount);
        }
    }

    public function test_invalid_workshop_rows_leave_the_saved_quote_unchanged(): void
    {
        $group = WorkshopLine::normalize($this->group());
        $quote = Quote::factory()->create(['line_items' => [$group], 'total_amount' => 330]);
        $invalid = $this->group();
        $invalid['workshops'][0]['description'] = '';

        $this->put(route('admin.quote.update', $quote), $this->quotePayload($quote, [$invalid]))
            ->assertSessionHasErrors('rows.0.description');

        $this->assertSame($quote->line_items, $quote->fresh()->line_items);
        $this->assertSame('330.00', $quote->fresh()->total_amount);
    }
}
