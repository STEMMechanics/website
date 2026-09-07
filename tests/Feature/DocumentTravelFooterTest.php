<?php
namespace Tests\Feature;

use App\Models\Quote;
use App\Services\Finance\DocumentTravelFooter;
use Tests\TestCase;

class DocumentTravelFooterTest extends TestCase
{
    public function test_hourly_and_legacy_rates_include_rounding_and_overrides(): void
    {
        $quote = new Quote;
        $quote->line_items = [['kind' => 'travel', 'unit_price' => 136.36, 'details_json' => ['inclusive_unit_price' => 150, 'travel' => ['quantity_basis' => 'hours', 'billable_units' => 9]]]];
        $this->assertSame('$34.09 ex GST', DocumentTravelFooter::render('{travel_rate_ex_gst} ex GST', $quote));
        $quote->line_items = [['kind' => 'travel', 'unit_price' => 34, 'details_json' => ['travel' => ['billable_units' => 9]]]];
        $this->assertSame('$34.00', DocumentTravelFooter::render('{travel_rate_ex_gst}', $quote));
        $quote->line_items = [['kind' => 'travel', 'unit_price' => 160]];
        $this->assertSame('$40.00', DocumentTravelFooter::render('{travel_rate_ex_gst}', $quote));
    }

    public function test_unknown_and_mixed_rates_do_not_claim_a_default_price(): void
    {
        $quote = new Quote;
        $quote->line_items = [];
        $this->assertSame('Travel charges are as itemised or otherwise agreed.', DocumentTravelFooter::render('{travel_rate_ex_gst}', $quote));
        $quote->line_items = [['kind' => 'travel', 'unit_price' => 100], ['kind' => 'travel', 'unit_price' => 200]];
        $this->assertSame('Travel charges are as itemised or otherwise agreed.', DocumentTravelFooter::render('{travel_rate_ex_gst}', $quote));
        $this->assertSame('Existing wording', DocumentTravelFooter::render('Existing wording', $quote));
    }
}
