<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\LinePricing;
use App\Services\Finance\WorkshopLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineItemOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    private function items(): array
    {
        $group = WorkshopLine::normalize([
            'kind' => 'multi_workshop', 'description' => '', 'notes' => '', 'unit_price' => 1.23, 'gst_applicable' => true,
            'workshops' => [
                ['description' => 'Library Minecraft', 'workshop_hours' => 1, 'workshop_seats' => 2],
                ['description' => 'Library STEM', 'workshop_hours' => 1, 'workshop_seats' => 1],
            ],
        ]);
        $group += ['line_total' => 3.69, 'tax_amount' => 0.36, 'line_total_inc_tax' => 4.05];

        return [$group, [
            'kind' => 'custom', 'description' => 'Printed materials', 'notes' => '', 'quantity' => 2, 'unit_price' => 4.56,
            'gst_applicable' => true, 'line_total' => 9.12, 'tax_amount' => 0.92, 'line_total_inc_tax' => 10.04,
        ]];
    }

    public function test_quotes_save_the_new_order_without_repricing_or_losing_a_blank_description_group(): void
    {
        $items = $this->items();
        $quote = Quote::factory()->create([
            'line_items' => $items, 'subtotal_amount' => 12.81, 'gst_amount' => 1.28, 'total_amount' => 14.09,
        ]);
        $savedGroup = $quote->line_items[0]['details_json']['multi_workshop'];
        $reordered = array_reverse(array_map(
            fn (array $item, int $index): array => LinePricing::forEditor($item) + ['saved_line_index' => $index],
            $items, array_keys($items)
        ));

        $this->put(route('admin.quote.update', $quote), [
            'quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-10-12', 'line_items_json' => json_encode($reordered),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quote->refresh();

        $this->assertSame('Printed materials', $quote->line_items[0]['description']);
        $this->assertSame('', $quote->line_items[1]['description']);
        $this->assertSame(LinePricing::savedAmounts($items[1]), LinePricing::savedAmounts($quote->line_items[0]));
        $this->assertSame(LinePricing::savedAmounts($items[0]), LinePricing::savedAmounts($quote->line_items[1]));
        $this->assertSame($savedGroup, $quote->line_items[1]['details_json']['multi_workshop']);
        $this->assertSame('12.81', $quote->subtotal_amount);
        $this->assertSame('1.28', $quote->gst_amount);
        $this->assertSame('14.09', $quote->total_amount);
        $html = view('pdf.quote', ['quote' => $quote])->render();
        $this->assertLessThan(strpos($html, 'Library Minecraft'), strpos($html, 'Printed materials'));
    }

    public function test_draft_invoices_save_the_new_order_without_repricing_or_losing_workshops(): void
    {
        $items = $this->items();
        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_DRAFT, 'subtotal_amount' => 12.81, 'gst_amount' => 1.28, 'total_amount' => 14.09,
        ]);
        foreach ($items as $index => $item) {
            InvoiceLine::factory()->for($invoice)->create([
                'line_number' => $index + 1, 'kind' => $item['kind'], 'description' => $item['description'], 'notes' => '',
                'details_json' => $item['details_json'] ?? [], 'quantity' => $item['quantity'],
                'unit_price_ex_tax' => $item['unit_price'], 'tax_rate' => 0.1,
                'line_total_ex_tax' => $item['line_total'], 'tax_amount' => $item['tax_amount'],
                'line_total_inc_tax' => $item['line_total_inc_tax'],
            ]);
        }
        $reordered = array_reverse($invoice->lines->map(fn (InvoiceLine $line): array => LinePricing::forEditor($line->toArray()))->all());
        $savedGroup = $invoice->lines[0]->details_json['multi_workshop'];

        $this->put(route('admin.invoice.update', $invoice), [
            'invoice_number' => $invoice->invoice_number, 'user_id' => $invoice->user_id,
            'issue_date' => '2026-10-12', 'line_items_json' => json_encode($reordered),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $invoice->refresh();
        $lines = $invoice->lines;

        $this->assertSame('Printed materials', $lines[0]->description);
        $this->assertSame('', $lines[1]->description);
        $this->assertSame(1, $lines[0]->line_number);
        $this->assertSame(2, $lines[1]->line_number);
        $this->assertSame(LinePricing::savedAmounts($items[1]), LinePricing::savedAmounts($lines[0]));
        $this->assertSame(LinePricing::savedAmounts($items[0]), LinePricing::savedAmounts($lines[1]));
        $this->assertSame($savedGroup, $lines[1]->details_json['multi_workshop']);
        $this->assertSame('12.81', $invoice->subtotal_amount);
        $this->assertSame('1.28', $invoice->gst_amount);
        $this->assertSame('14.09', $invoice->total_amount);
        $html = view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [$lines], 'adjustments' => collect()])->render();
        $this->assertLessThan(strpos($html, 'Library Minecraft'), strpos($html, 'Printed materials'));
    }

    public function test_ordering_buttons_are_available_in_both_editors_and_follow_invoice_locks(): void
    {
        foreach (['admin.quote.create', 'admin.invoice.create'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('aria-label="Move up"', false)
                ->assertSee('aria-label="Move down"', false)
                ->assertSee('x-bind:disabled="index === 0"', false)
                ->assertSee('x-bind:disabled="index === lineItems.length - 1"', false)
                ->assertSee('x-data="SM.lineItemsEditor(false)"', false)
                ->assertSee(':key="lineItemKey(item)"', false);
        }
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_ISSUED]);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()
            ->assertDontSee('aria-label="Move up"', false)
            ->assertDontSee('aria-label="Move down"', false)
            ->assertSee('x-data="SM.lineItemsEditor(true)"', false);
    }
}
