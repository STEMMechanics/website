<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\LinePricing;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InclusiveDocumentPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    private function saveInvoice(Invoice $invoice, array $items): void
    {
        $this->put(route('admin.invoice.update', $invoice), ['invoice_number' => $invoice->invoice_number,
            'user_id' => $invoice->user_id, 'status' => $invoice->status, 'issue_date' => today()->toDateString(),
            'line_items_json' => json_encode($items), 'notes' => 'Updated notes',
        ])->assertSessionHasNoErrors();
    }

    public function test_inclusive_quote_converts_and_invoice_round_trip_keeps_405(): void
    {
        $quote = Quote::factory()->create(['user_id' => auth()->id()]);
        $item = ['kind' => 'workshop', 'description' => 'Workshop Delivery', 'quantity' => 30, 'unit_price_inc_tax' => 13.50, 'gst_applicable' => true];
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id,
            'status' => Quote::STATUS_OPEN, 'quote_date' => today()->toDateString(), 'title' => 'Inclusive pricing', 'line_items_json' => json_encode([$item]),
        ])->assertSessionHasNoErrors();
        $quote->refresh();
        $this->assertSame('405.00', $quote->total_amount);
        $this->assertSame('368.18', $quote->subtotal_amount);
        $this->assertSame('36.82', $quote->gst_amount);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame('405.00', $invoice->total_amount);
        $this->assertSame('405.00', $invoice->lines[0]->line_total_inc_tax);
        $items = $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Unit price (inc GST)')->viewData('lineItemsSeed');
        $this->assertEquals(13.50, $items[0]['unit_price_inc_tax']);
        $this->saveInvoice($invoice, $items);
        $invoice->refresh();
        $this->assertSame('405.00', $invoice->total_amount);
        $this->assertSame('368.18', $invoice->lines[0]->line_total_ex_tax);
        $html = view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [$invoice->lines->map->toArray()->all()], 'adjustments' => collect(), 'publicPayUrl' => null])->render();
        $this->assertStringContainsString('$ 13.50', $html);
        $this->assertStringContainsString('$ 405.00', $html);
        $this->assertStringNotContainsString('$ 12.27', $html);
    }

    public function test_historical_invoice_rates_totals_and_tax_survive_description_only_edit(): void
    {
        $invoice = Invoice::factory()->create(['user_id' => auth()->id(), 'status' => 'draft', 'subtotal_amount' => 368.10, 'gst_amount' => 36.81, 'total_amount' => 404.91]);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 30, 'unit_price_ex_tax' => 12.27,
            'line_total_ex_tax' => 368.10, 'tax_amount' => 36.81, 'line_total_inc_tax' => 404.91, 'tax_rate' => 0.1]);
        $items = $this->get(route('admin.invoice.edit', $invoice))->assertOk()->viewData('lineItemsSeed');
        $this->assertEquals(13.497, $items[0]['unit_price_inc_tax']);
        $items[0]['description'] = 'Description corrected';
        $items[0]['saved_pricing']['gross'] = 999; // Client totals cannot rewrite history.
        $this->saveInvoice($invoice, $items);
        $this->assertSame('404.91', $invoice->fresh()->total_amount);
        $this->assertSame('368.10', $invoice->fresh()->lines[0]->line_total_ex_tax);
        $this->assertSame('36.81', $invoice->fresh()->lines[0]->tax_amount);
        $this->assertSame('12.27', $invoice->fresh()->lines[0]->unit_price_ex_tax);
    }

    public function test_existing_quote_retains_original_document_rounding_and_conversion_totals(): void
    {
        $line = ['kind' => 'custom', 'description' => 'Small item', 'quantity' => 1, 'unit_price' => 0.05, 'line_total' => 0.05, 'gst_applicable' => true];
        $quote = Quote::factory()->create(['user_id' => auth()->id(), 'line_items' => [$line, $line], 'subtotal_amount' => 0.10, 'gst_amount' => 0.01, 'total_amount' => 0.11]);
        $items = array_map(LinePricing::forEditor(...), $quote->line_items);
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id,
            'status' => Quote::STATUS_OPEN, 'quote_date' => today()->toDateString(), 'title' => 'New title', 'line_items_json' => json_encode($items),
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.11', $quote->fresh()->total_amount);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote->fresh());
        $this->assertSame('0.11', $invoice->total_amount);
        $this->saveInvoice($invoice, $this->get(route('admin.invoice.edit', $invoice))->viewData('lineItemsSeed'));
        $this->assertSame('0.11', $invoice->fresh()->total_amount);
    }

    public function test_new_inclusive_invoice_supports_fractional_quantities_mixed_gst_and_discounts(): void
    {
        $items = [
            ['description' => 'Taxed', 'quantity' => 30, 'unit_price_inc_tax' => 13.5, 'gst_applicable' => true],
            ['description' => 'GST free', 'quantity' => 1.5, 'unit_price_inc_tax' => 10, 'gst_applicable' => false],
            ['description' => 'Discount', 'quantity' => 1, 'unit_price_inc_tax' => -11, 'gst_applicable' => true],
        ];
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'INC-TEST', 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode($items)])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'INC-TEST')->firstOrFail();
        $this->assertSame('409.00', $invoice->total_amount);
        $this->assertSame('35.82', $invoice->gst_amount);
        $this->assertSame('0.00', $invoice->lines[1]->tax_amount);
    }

    public function test_partial_credits_exhaust_saved_inclusive_total_without_rounding_loss(): void
    {
        $invoice = Invoice::factory()->create(['user_id' => auth()->id(), 'status' => Invoice::STATUS_ISSUED, 'subtotal_amount' => 368.18, 'gst_amount' => 36.82, 'total_amount' => 405]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 30, 'unit_price_ex_tax' => 12.27,
            'line_total_ex_tax' => 368.18, 'tax_amount' => 36.82, 'line_total_inc_tax' => 405, 'tax_rate' => 0.1]);
        foreach ([10, 20] as $quantity) {
            $this->post(route('admin.tax_adjustment.store', $invoice), ['refund_qty' => [$line->id => $quantity]])->assertSessionHasNoErrors();
        }
        $this->assertEquals(405, $invoice->taxAdjustments()->get()->sum(fn ($note) => abs((float) $note->total_amount)));
        $this->assertEquals(36.82, $invoice->taxAdjustments()->get()->sum(fn ($note) => abs((float) $note->gst_amount)));
        $this->assertSame('405.00', $invoice->fresh()->total_amount);
    }
}
