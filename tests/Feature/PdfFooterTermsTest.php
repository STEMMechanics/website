<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\SiteOption;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfFooterTermsTest extends TestCase
{
    use RefreshDatabase;

    private function documents(): array
    {
        $invoice = Invoice::factory()->for(User::factory())->create();
        $quote = Quote::factory()->for($invoice->user)->create();
        $adjustment = TaxAdjustment::factory()->for($invoice)->create();

        return [
            'invoice' => ['view' => 'pdf.invoice', 'data' => ['invoice' => $invoice, 'itemPages' => [[]], 'adjustments' => collect()]],
            'quote' => ['view' => 'pdf.quote', 'data' => ['quote' => $quote, 'itemPages' => [[]]]],
            'adjustment' => ['view' => 'pdf.tax-adjustment', 'data' => ['invoice' => $invoice, 'adjustment' => $adjustment, 'itemPages' => [[]]]],
        ];
    }

    public function test_missing_document_overrides_use_the_current_shared_terms(): void
    {
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Current shared payment terms.']);

        foreach ($this->documents() as $document) {
            $html = view($document['view'], $document['data'])->render();
            $this->assertStringContainsString('<span class="heading">Terms</span> Current shared payment terms.', $html);
            $this->assertStringNotContainsString(SiteOption::defaultValue('document.footer.terms'), $html);
        }
    }

    public function test_each_document_type_uses_only_its_own_terms_override(): void
    {
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared fallback terms.']);
        $terms = ['invoice' => 'Invoice payment terms.', 'quote' => 'Quote acceptance terms.', 'adjustment' => 'Adjustment-specific terms.'];
        foreach ($terms as $type => $value) {
            SiteOption::query()->create(['name' => 'document.footer.terms.'.$type, 'value' => $value]);
        }

        foreach ($this->documents() as $type => $document) {
            $html = view($document['view'], $document['data'])->render();
            $this->assertStringContainsString('<span class="heading">Terms</span> '.$terms[$type], $html);
            $this->assertStringNotContainsString('Shared fallback terms.', $html);
            foreach (array_diff_key($terms, [$type => true]) as $otherTerms) {
                $this->assertStringNotContainsString($otherTerms, $html);
            }
        }
    }

    public function test_blank_empty_and_whitespace_overrides_hide_terms_for_all_document_types(): void
    {
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared fallback terms.']);
        SiteOption::query()->create(['name' => 'document.footer.payment', 'value' => 'Other footer content remains visible.']);

        foreach ($this->documents() as $type => $document) {
            foreach (['', " \t\r\n ", null] as $value) {
                SiteOption::query()->updateOrCreate(['name' => 'document.footer.terms.'.$type], ['value' => $value]);
                $html = view($document['view'], $document['data'])->render();
                $this->assertStringNotContainsString('<span class="heading">Terms</span>', $html);
                $this->assertStringNotContainsString('Shared fallback terms.', $html);
                $this->assertStringContainsString('Other footer content remains visible.', $html);
            }
        }
    }

    public function test_a_blank_adjustment_override_saved_through_the_admin_form_does_not_affect_invoices_or_quotes(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        SiteOption::query()->create(['name' => 'document.footer.terms', 'value' => 'Shared fallback terms.']);

        $this->postJson(route('admin.site_option.store'), ['name' => 'document.footer.terms.adjustment', 'value' => ''])
            ->assertOk()->assertJsonPath('success', true);
        $override = SiteOption::query()->where('name', 'document.footer.terms.adjustment')->firstOrFail();
        $this->assertNull($override->value);

        foreach ($this->documents() as $type => $document) {
            $html = view($document['view'], $document['data'])->render();
            if ($type === 'adjustment') {
                $this->assertStringNotContainsString('<span class="heading">Terms</span>', $html);
                $this->assertStringNotContainsString('Shared fallback terms.', $html);
            } else {
                $this->assertStringContainsString('<span class="heading">Terms</span> Shared fallback terms.', $html);
            }
        }

        $this->putJson(route('admin.site_option.update', $override), ['value' => 'Temporary adjustment terms.'])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson(route('admin.site_option.update', $override), ['value' => ''])
            ->assertOk()->assertJsonPath('success', true);
        $adjustmentDocument = $this->documents()['adjustment'];
        $html = view($adjustmentDocument['view'], $adjustmentDocument['data'])->render();
        $this->assertStringNotContainsString('<span class="heading">Terms</span>', $html);
        $this->assertStringNotContainsString('Shared fallback terms.', $html);
    }

    public function test_quote_terms_can_differ_from_invoice_terms_while_adjustments_hide_terms(): void
    {
        foreach (SiteOption::defaultDefinitions() as $name => $definition) {
            if (str_starts_with($name, 'document.')) {
                SiteOption::query()->create(['name' => $name, 'value' => $definition['value']]);
            }
        }
        SiteOption::query()->create(['name' => 'document.footer.terms.quote', 'value' => 'This quote is valid until the expiry date shown.']);
        SiteOption::query()->create(['name' => 'document.footer.terms.adjustment', 'value' => null]);

        foreach ($this->documents() as $type => $document) {
            $html = view($document['view'], $document['data'])->render();
            if ($type === 'adjustment') {
                $this->assertStringNotContainsString('<span class="heading">Terms</span>', $html);
            } elseif ($type === 'quote') {
                $this->assertStringContainsString('This quote is valid until the expiry date shown.', $html);
                $this->assertStringNotContainsString(SiteOption::defaultValue('document.footer.terms'), $html);
            } else {
                $this->assertStringContainsString(SiteOption::defaultValue('document.footer.terms'), $html);
            }

            if ($previewDir = getenv('FOOTER_TERMS_PDF_PREVIEW_DIR')) {
                Pdf::loadView($document['view'], $document['data'])->save($previewDir.'/'.$type.'.pdf');
            }
        }
    }
}
