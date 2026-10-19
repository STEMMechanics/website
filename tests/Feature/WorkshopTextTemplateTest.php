<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\WorkshopLine;
use App\Services\QuoteWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class WorkshopTextTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    private function line(string $kind = 'workshop'): array
    {
        $line = [
            'kind' => $kind, 'description' => 'Charged per hour, per seat',
            'notes' => '- {date} - {workshop} - ({duration} x {seats})',
            'workshop_hours' => 1, 'workshop_seats' => 10, 'workshop_date' => '2027-02-17',
            'quantity' => 10, 'unit_price_inc_tax' => 14.5, 'unit_price' => 14.5 / 1.1,
            'gst_applicable' => true, 'details_json' => [
                'workshop_notes_mode' => 'template', 'inclusive_unit_price' => 14.5,
                'workshop' => ['name' => 'Library Minecraft'],
            ],
        ];
        if ($kind === 'multi_workshop') {
            $line['workshops'] = [
                ['description' => 'Library STEM', 'workshop_hours' => 1.5, 'workshop_seats' => 20, 'workshop_date' => '2027-03-24'],
                ['description' => 'Library Robotics', 'workshop_hours' => 1, 'workshop_seats' => 10, 'workshop_date' => ''],
            ];
            unset($line['details_json']['workshop']);
            $line['details_json']['multi_workshop']['quantity_basis'] = 'seat_hours';
        }
        $line = WorkshopLine::normalize($line);
        $amounts = WorkshopLine::amounts($line, $line['quantity'], $line['unit_price'], 0.1);

        return $line + ['line_total' => $amounts['net'], 'tax_amount' => $amounts['tax'], 'line_total_inc_tax' => $amounts['gross']];
    }

    private function quote(array $items): Quote
    {
        return Quote::factory()->create([
            'user_id' => auth()->id(), 'quote_date' => '2026-10-20', 'title' => 'Workshop delivery',
            'line_items' => $items, 'subtotal_amount' => array_sum(array_column($items, 'line_total')),
            'gst_amount' => array_sum(array_column($items, 'tax_amount')),
            'total_amount' => array_sum(array_column($items, 'line_total_inc_tax')),
        ]);
    }

    private function saveQuote(Quote $quote, array $line): void
    {
        $this->put(route('admin.quote.update', $quote), [
            'quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-10-20', 'line_items_json' => json_encode([$line]),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quote->refresh();
    }

    public function test_workshop_names_descriptions_and_templates_survive_saving_and_invoice_conversion(): void
    {
        $line = $this->line();
        $quote = $this->quote([$line]);
        $this->saveQuote($quote, $line);
        $saved = $quote->line_items[0];
        $this->assertSame('Charged per hour, per seat', $saved['description']);
        $this->assertSame('Library Minecraft', $saved['details_json']['workshop']['name']);
        $this->assertSame($line['notes'], $saved['notes']);
        $this->assertSame('145.00', $quote->total_amount);

        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame($saved['notes'], $invoice->lines[0]->notes);
        $this->assertSame($saved['details_json']['workshop'], $invoice->lines[0]->details_json['workshop']);
        $this->assertSame('145.00', $invoice->total_amount);
        foreach ([route('account.quote.show', $quote), route('account.invoice.show', $invoice)] as $url) {
            $this->get($url)->assertOk()->assertSee('17/02/2027 - Library Minecraft - (1 hr x 10 seats)')
                ->assertDontSee('{workshop}')->assertDontSee('{duration}');
        }
    }

    public function test_custom_and_empty_workshop_text_is_authoritative_for_single_and_multi_workshops(): void
    {
        foreach (['workshop', 'multi_workshop'] as $kind) {
            foreach (['Only the agreed workshop.', ''] as $text) {
                $line = array_replace($this->line($kind), ['description' => '', 'notes' => $text]);
                $quote = $this->quote([$line]);
                $this->saveQuote($quote, $line);
                $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
                $invoice->update(['status' => Invoice::STATUS_DRAFT]);
                $this->put(route('admin.invoice.update', $invoice), [
                    'invoice_number' => $invoice->invoice_number, 'user_id' => $invoice->user_id,
                    'issue_date' => '2026-10-20', 'line_items_json' => json_encode([$line + ['id' => $invoice->lines[0]->id]]),
                ])->assertSessionHasNoErrors()->assertRedirect();
                $invoice->refresh();

                $this->assertSame('', $quote->line_items[0]['description']);
                $this->assertSame($text, $quote->line_items[0]['notes']);
                $this->assertSame($text, $invoice->lines[0]->notes);
                foreach ([
                    view('pdf.quote', ['quote' => $quote])->render(),
                    view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [$invoice->lines], 'adjustments' => collect()])->render(),
                ] as $html) {
                    $this->assertStringNotContainsString('Library Minecraft', $html);
                    $this->assertStringNotContainsString('Library STEM', $html);
                    $this->assertStringNotContainsString('Charged per hour, per seat', $html);
                    if ($text !== '') {
                        $this->assertSame(1, substr_count($html, $text));
                    }
                }
            }
        }
    }

    public function test_placeholders_expand_into_bullets_with_one_description_and_no_duplicate_notes(): void
    {
        $items = [$this->line(), $this->line('multi_workshop')];
        $items[1]['notes'] = "Bring your own laptop.\n".$items[1]['notes'];
        $quote = $this->quote($items);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        foreach ([
            view('pdf.quote', ['quote' => $quote])->render(),
            view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [$invoice->lines], 'adjustments' => collect()])->render(),
        ] as $html) {
            $this->assertSame(2, substr_count($html, 'Charged per hour, per seat'));
            $this->assertSame(1, substr_count($html, '<li>17/02/2027 - Library Minecraft - (1 hr x 10 seats)</li>'));
            $this->assertSame(1, substr_count($html, '<li>24/03/2027 - Library STEM - (1.5 hrs x 20 seats)</li>'));
            $this->assertSame(1, substr_count($html, '<li>Library Robotics - (1 hr x 10 seats)</li>'));
            $this->assertSame(1, substr_count($html, 'Bring your own laptop.'));
            $this->assertStringNotContainsString('{workshop}', $html);
            $this->assertStringContainsString('$ 13.1818', $html);
            $this->assertStringNotContainsString('$ 13.181818', $html);
        }

        if ($previewDir = getenv('WORKSHOP_TEXT_PREVIEW_DIR')) {
            foreach (SiteOption::defaultDefinitions() as $name => $definition) {
                if (str_starts_with($name, 'document.') || str_starts_with($name, 'payments.bank-')) {
                    SiteOption::query()->updateOrCreate(['name' => $name], ['value' => $definition['value']]);
                }
            }
            Pdf::loadView('pdf.quote', ['quote' => $quote])->save($previewDir.'/workshop-text-quote.pdf');
            Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [$invoice->lines], 'adjustments' => collect()])->save($previewDir.'/workshop-text-invoice.pdf');
            file_put_contents($previewDir.'/editor.html', Blade::render('<x-finance.line-item-details />'));
            file_put_contents($previewDir.'/items.json', json_encode($items));
        }
    }

    public function test_adjustments_copy_resolved_workshop_text_without_unresolved_placeholders(): void
    {
        $quote = $this->quote([$this->line()]);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $this->get(route('admin.tax_adjustment.create', $invoice))->assertOk()
            ->assertSee('Library Minecraft')->assertDontSee('{workshop}');
        $this->post(route('admin.tax_adjustment.store', $invoice), [
            'refund_qty' => [$invoice->lines[0]->id => 1], 'reason' => 'One seat-hour credit',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $adjustment = $invoice->taxAdjustments()->with('lines')->firstOrFail();
        $this->assertSame('- 17/02/2027 - Library Minecraft - (1 hr x 10 seats)', $adjustment->lines[0]->notes);
    }

    public function test_an_empty_description_and_text_do_not_discard_a_zero_seat_workshop(): void
    {
        $line = $this->line();
        $line['description'] = $line['notes'] = '';
        $line['workshop_seats'] = 0;
        $quote = $this->quote([]);
        $this->saveQuote($quote, $line);
        $this->assertCount(1, $quote->line_items);
        $this->assertSame(0.0, (float) $quote->line_items[0]['quantity']);
        $this->assertSame('Library Minecraft', $quote->line_items[0]['details_json']['workshop']['name']);
        $this->assertSame('0.00', $quote->total_amount);
        $this->post(route('admin.invoice.store'), [
            'invoice_number' => 'ZERO-WORKSHOP-TEXT', 'issue_date' => '2026-10-20', 'line_items_json' => json_encode([$line]),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = Invoice::where('invoice_number', 'ZERO-WORKSHOP-TEXT')->firstOrFail();
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('0.00', $invoice->total_amount);
    }
}
