<?php

namespace Tests\Feature;

use App\Http\Controllers\QuoteController;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Location;
use App\Models\Media;
use App\Models\Quote;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Finance\InvoicePdfLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopPdfPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function rows(): array
    {
        return [
            ['description' => 'Manunda Minecraft', 'workshop_date' => '2027-02-17', 'workshop_hours' => 1, 'workshop_seats' => 10],
            ['description' => 'Smithfield STEM', 'workshop_date' => '2027-03-24', 'workshop_hours' => 1.5, 'workshop_seats' => 20],
            ['description' => 'Manunda Minecraft', 'workshop_date' => '2027-04-21', 'workshop_hours' => 1, 'workshop_seats' => 10],
        ];
    }

    private function line(array $rows, string $notes = ''): array
    {
        $quantity = array_sum(array_map(fn (array $row): float => $row['workshop_hours'] * $row['workshop_seats'], $rows));
        $gross = round($quantity * 14.5, 2);
        $net = round($gross / 1.1, 2);

        return [
            'kind' => 'multi_workshop', 'description' => 'Charged per hour, per seat', 'notes' => $notes,
            'quantity' => $quantity, 'unit_price' => 14.5 / 1.1, 'gst_applicable' => true,
            'line_total' => $net, 'tax_amount' => round($gross - $net, 2), 'line_total_inc_tax' => $gross,
            'details_json' => ['inclusive_unit_price' => 14.5, 'multi_workshop' => ['rows' => $rows, 'quantity_basis' => 'seat_hours']],
        ];
    }

    private function quote(array $items): Quote
    {
        $user = User::factory()->create([
            'firstname' => 'Sample', 'surname' => 'Customer', 'email' => 'sample-'.User::count().'@example.test',
            'billing_address' => '123 Sample Street', 'billing_city' => 'Cairns', 'billing_state' => 'QLD',
            'billing_postcode' => '4870', 'billing_country' => 'Australia',
        ]);

        return Quote::factory()->for($user)->create([
            'quote_number' => 'Q-'.(1000 + Quote::count()), 'quote_date' => '2026-10-12',
            'title' => 'H1 2027 - STEAM Workshops', 'description' => null, 'notes' => null,
            'line_items' => $items, 'subtotal_amount' => array_sum(array_column($items, 'line_total')),
            'gst_amount' => array_sum(array_column($items, 'tax_amount')),
            'total_amount' => array_sum(array_column($items, 'line_total_inc_tax')),
        ]);
    }

    private function invoice(Quote $quote): Invoice
    {
        return Invoice::factory()->for($quote->user)->create([
            'invoice_number' => 'INV-'.(1000 + Invoice::count()), 'issue_date' => '2026-10-12', 'due_date' => '2026-11-09',
            'subtotal_amount' => $quote->subtotal_amount, 'gst_amount' => $quote->gst_amount,
            'total_amount' => $quote->total_amount, 'purchase_order_number' => null, 'notes' => null,
        ]);
    }

    public function test_manual_workshop_details_appear_with_empty_generic_and_custom_notes(): void
    {
        foreach (['', 'Charged per hour, per seat', 'Bring your own laptop'] as $notes) {
            $line = $this->line($this->rows(), $notes);
            $quote = $this->quote([$line]);
            $invoice = $this->invoice($quote);
            $views = [
                view('pdf.quote', ['quote' => $quote])->render(),
                view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [[$line]], 'adjustments' => collect()])->render(),
            ];
            foreach ($views as $html) {
                $this->assertStringContainsString('Workshop Delivery', $html);
                $this->assertStringContainsString('17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)', $html);
                $this->assertStringContainsString('24/03/2027 - Smithfield STEM - (1.5 hrs × 20 seats)', $html);
                $this->assertStringContainsString('21/04/2027 - Manunda Minecraft - (1 hr × 10 seats)', $html);
                $this->assertStringContainsString('>50</td>', $html);
                $this->assertStringContainsString('$ 14.50', $html);
                $this->assertStringContainsString('$ 725.00', $html);
                if ($notes !== '') {
                    $this->assertStringContainsString($notes, $html);
                }
            }
            $this->assertSame($notes, $quote->fresh()->line_items[0]['notes']);
        }
    }

    public function test_linking_workshops_does_not_change_quote_or_invoice_presentation(): void
    {
        $rows = $this->rows();
        $manual = $this->line($rows, 'Bring your own laptop');
        $quote = $this->quote([$manual]);
        $invoice = $this->invoice($quote);
        foreach (SiteOption::defaultDefinitions() as $name => $definition) {
            if (str_starts_with($name, 'document.')) {
                SiteOption::query()->updateOrCreate(['name' => $name], ['value' => $definition['value']]);
            }
        }
        $manualQuoteHtml = view('pdf.quote', ['quote' => $quote])->render();
        $manualInvoiceHtml = view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [[$manual]], 'adjustments' => collect()])->render();
        Location::factory()->create();
        $media = Media::factory()->create(['user_id' => $quote->user_id]);
        foreach ($rows as &$row) {
            $workshop = Workshop::factory()->create(['title' => $row['description'], 'user_id' => $quote->user_id, 'hero_media_name' => $media->name]);
            $row['details_json']['workshop']['linked_workshop_id'] = $workshop->id;
        }
        unset($row);
        $linked = $this->line($rows, $manual['notes']);
        $quote->line_items = [$linked];

        $this->assertSame($manualQuoteHtml, view('pdf.quote', ['quote' => $quote])->render());
        $this->assertSame($manualInvoiceHtml, view('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [[$linked]], 'adjustments' => collect()])->render());

        if ($previewDir = getenv('WORKSHOP_PDF_PREVIEW_DIR')) {
            Pdf::loadView('pdf.quote', ['quote' => $quote])->save($previewDir.'/linked-workshops.pdf');
            $quote->line_items = [$manual];
            Pdf::loadView('pdf.quote', ['quote' => $quote])->save($previewDir.'/manual-workshops.pdf');
            Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'itemPages' => [[$manual]], 'adjustments' => collect()])->save($previewDir.'/manual-workshops-invoice.pdf');
        }
    }

    public function test_invoice_pages_support_collections_of_saved_workshop_lines(): void
    {
        $line = $this->line($this->rows(), 'Bring your own laptop');
        $quote = $this->quote([$line]);
        $invoice = $this->invoice($quote);
        InvoiceLine::factory()->for($invoice)->create([
            'kind' => $line['kind'],
            'description' => $line['description'],
            'notes' => $line['notes'],
            'details_json' => $line['details_json'],
            'quantity' => $line['quantity'],
            'unit_price_ex_tax' => $line['unit_price'],
            'line_total_ex_tax' => $line['line_total'],
            'tax_amount' => $line['tax_amount'],
            'line_total_inc_tax' => $line['line_total_inc_tax'],
        ]);
        $lines = $invoice->lines;
        $viewData = ['invoice' => $invoice, 'adjustments' => collect()];
        $arrayHtml = view('pdf.invoice', $viewData + ['itemPages' => [$lines->toArray()]])->render();
        $collectionHtml = view('pdf.invoice', $viewData + ['itemPages' => [$lines]])->render();

        $this->assertSame($arrayHtml, $collectionHtml);
        $this->assertStringContainsString('17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)', $collectionHtml);
        $this->assertStringContainsString('Bring your own laptop', $collectionHtml);
    }

    public function test_existing_schedule_notes_do_not_duplicate_workshops_or_hide_other_dates(): void
    {
        $notes = "Ask about Manunda Minecraft materials.\n- 17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)";
        $quote = $this->quote([$this->line($this->rows(), $notes)]);
        $html = view('pdf.quote', ['quote' => $quote])->render();

        $this->assertSame(1, substr_count($html, '17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)'));
        $this->assertSame(1, substr_count($html, '21/04/2027 - Manunda Minecraft - (1 hr × 10 seats)'));
        $this->assertStringContainsString('Ask about Manunda Minecraft materials.', $html);
    }

    public function test_manual_and_linked_rows_are_displayed_together_and_custom_names_are_escaped(): void
    {
        $rows = $this->rows();
        $rows[1]['description'] = 'STEM <build> & play';
        $quote = $this->quote([$this->line($rows)]);
        Location::factory()->create();
        $media = Media::factory()->create(['user_id' => $quote->user_id]);
        $workshop = Workshop::factory()->create(['title' => $rows[0]['description'], 'user_id' => $quote->user_id, 'hero_media_name' => $media->name]);
        $rows[0]['details_json']['workshop']['linked_workshop_id'] = $workshop->id;
        $quote->line_items = [$this->line($rows)];
        $html = view('pdf.quote', ['quote' => $quote])->render();

        $this->assertStringContainsString('17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)', $html);
        $this->assertStringContainsString('24/03/2027 - STEM &lt;build&gt; &amp; play - (1.5 hrs × 20 seats)', $html);
        $this->assertStringContainsString('21/04/2027 - Manunda Minecraft - (1 hr × 10 seats)', $html);
        $this->assertStringNotContainsString('STEM <build>', $html);
    }

    public function test_single_manual_workshops_use_saved_metadata_and_support_missing_dates(): void
    {
        $line = ['kind' => 'workshop', 'description' => 'Manunda Minecraft', 'notes' => '', 'workshop_hours' => 1, 'workshop_seats' => 10];
        $prepared = InvoicePdfLines::withWorkshopDetails([$line]);
        $this->assertSame('- Manunda Minecraft - (1 hr × 10 seats)', InvoicePdfLines::notesForPdf($prepared[0]));

        unset($line['workshop_hours'], $line['workshop_seats']);
        $line['details_json']['workshop'] = ['hours' => 1, 'seats' => 10, 'date' => '2027-02-17'];
        $prepared = InvoicePdfLines::withWorkshopDetails([$line]);
        $this->assertSame('- 17/02/2027 - Manunda Minecraft - (1 hr × 10 seats)', InvoicePdfLines::notesForPdf($prepared[0]));
    }

    public function test_quote_pagination_counts_workshop_details_even_when_notes_are_empty(): void
    {
        $line = $this->line($this->rows());
        $quote = $this->quote(array_fill(0, 9, $line));
        $method = new \ReflectionMethod(QuoteController::class, 'paginateLineItemsForPdf');
        $pages = $method->invoke(app(QuoteController::class), $quote);

        $this->assertGreaterThan(1, count($pages));
        $this->assertCount(9, array_merge(...$pages));
        foreach (array_merge(...$pages) as $item) {
            $this->assertCount(3, $item['workshop_details']);
            $this->assertSame('', $item['notes']);
            $this->assertSame($line['line_total_inc_tax'], (float) $item['line_total_inc_tax']);
        }
    }
}
