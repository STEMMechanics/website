<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SiteOption;
use App\Models\TaxAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfFooterQuestionsPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_footer_questions_placeholder_uses_invoice(): void
    {
        $html = $this->renderPdfFooterForDocument(
            'pdf.invoice',
            [
                'invoice' => Invoice::factory()->for(User::factory())->create(),
                'itemPages' => [[]],
                'adjustments' => collect(),
                'publicPayUrl' => null,
            ],
            'invoice',
        );

        $this->assertStringContainsString('If you have any questions about this invoice, please feel free to contact us.', $html);
    }

    public function test_quote_pdf_footer_questions_placeholder_uses_quote(): void
    {
        $html = $this->renderPdfFooterForDocument(
            'pdf.quote',
            [
                'quote' => Quote::factory()->for(User::factory())->create(),
                'itemPages' => [[]],
            ],
            'quote',
        );

        $this->assertStringContainsString('If you have any questions about this quote, please feel free to contact us.', $html);
    }

    public function test_tax_adjustment_pdf_footer_questions_placeholder_uses_tax_adjustment(): void
    {
        $invoice = Invoice::factory()->for(User::factory())->create();
        $adjustment = TaxAdjustment::factory()->for($invoice)->create();

        $html = $this->renderPdfFooterForDocument(
            'pdf.tax-adjustment',
            [
                'adjustment' => $adjustment->loadMissing('invoice.user'),
                'invoice' => $adjustment->invoice,
                'itemPages' => [[]],
            ],
            'tax adjustment',
        );

        $this->assertStringContainsString('If you have any questions about this tax adjustment, please feel free to contact us.', $html);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderPdfFooterForDocument(string $view, array $data, string $documentType): string
    {
        SiteOption::query()->updateOrCreate(
            ['name' => 'document.footer.questions'],
            ['value' => 'If you have any questions about this {document}, please feel free to contact us.'],
        );

        return view($view, array_merge($data, ['documentType' => $documentType]))->render();
    }
}
