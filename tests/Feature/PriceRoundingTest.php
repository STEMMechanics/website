<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\LinePricing;
use App\Services\Finance\WorkshopLine;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PriceRoundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    private function line(): array
    {
        return [
            'kind' => 'workshop', 'description' => 'Charged per hour, per seat',
            'notes' => '- {date} - {workshop} - ({duration} x {seats})',
            'workshop_hours' => 1, 'workshop_seats' => 60, 'quantity' => 60,
            'unit_price_inc_tax' => 14.52, 'unit_price' => 13.20, 'gst_applicable' => true,
            'details_json' => [
                'workshop' => ['name' => 'Caravonica STEM'], 'workshop_notes_mode' => 'template',
                'inclusive_unit_price' => 14.52, 'price_rounding' => ['requested' => 14.50, 'applied' => 14.52],
            ],
        ];
    }

    public function test_clean_rate_survives_quote_saves_conversion_and_invoice_attendance_changes(): void
    {
        $line = $this->line();
        $quote = Quote::factory()->create(['user_id' => auth()->id(), 'status' => Quote::STATUS_OPEN]);
        foreach ([60 => [792.00, 79.20, 871.20], 35 => [462.00, 46.20, 508.20]] as $seats => [$net, $tax, $gross]) {
            $line['workshop_seats'] = $seats;
            $this->put(route('admin.quote.update', $quote), [
                'quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN,
                'quote_date' => '2026-10-20', 'line_items_json' => json_encode([$line]),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $quote->refresh();
            $this->assertSame($net, (float) $quote->subtotal_amount);
            $this->assertSame($tax, (float) $quote->gst_amount);
            $this->assertSame($gross, (float) $quote->total_amount);
            $this->assertSame('13.20', LinePricing::formatExclusiveUnit($quote->line_items[0]));
            $this->assertSame($line['details_json']['price_rounding'], $quote->line_items[0]['details_json']['price_rounding']);
        }
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame('508.20', $invoice->total_amount);
        $this->assertSame($line['details_json']['price_rounding'], $invoice->lines[0]->details_json['price_rounding']);
        $invoice->update(['status' => Invoice::STATUS_DRAFT]);
        $line['id'] = $invoice->lines[0]->id;
        $line['workshop_seats'] = 25;
        $this->put(route('admin.invoice.update', $invoice), [
            'invoice_number' => $invoice->invoice_number, 'user_id' => $invoice->user_id,
            'issue_date' => '2026-10-20', 'line_items_json' => json_encode([$line]),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $invoice->refresh();
        $this->assertSame('363.00', $invoice->total_amount);
        $this->assertSame('330.00', $invoice->subtotal_amount);
        $this->assertSame('33.00', $invoice->gst_amount);
        $this->assertSame('13.20', LinePricing::formatExclusiveUnit($invoice->lines[0]));

        if ($directory = getenv('PRICE_ROUNDING_PREVIEW_DIR')) {
            file_put_contents($directory.'/quote.html', $this->get(route('admin.quote.edit', $quote))->assertOk()->getContent());
            file_put_contents($directory.'/invoice.html', $this->get(route('admin.invoice.edit', $invoice))->assertOk()->getContent());
            $pendingWorkshop = $this->line();
            $pendingWorkshop['workshop_seats'] = 35;
            $pendingWorkshop['unit_price_inc_tax'] = $pendingWorkshop['details_json']['inclusive_unit_price'] = 14.50;
            unset($pendingWorkshop['details_json']['price_rounding']);
            $pendingTravel = [
                'kind' => 'travel', 'description' => 'Travel', 'travel_hours' => 0.25, 'unit_price_inc_tax' => 14.63,
                'unit_price' => 14.63 / 1.1, 'gst_applicable' => true, 'details_json' => ['inclusive_unit_price' => 14.63],
            ];
            $pendingItems = array_map(function ($line) {
                $line = WorkshopLine::normalize($line);
                $line['unit_price'] = $line['unit_price_inc_tax'] / 1.1;
                $amounts = WorkshopLine::amounts($line, $line['quantity'], $line['unit_price'], 0.1);

                return $line + ['line_total' => $amounts['net'], 'tax_amount' => $amounts['tax'], 'line_total_inc_tax' => $amounts['gross']];
            }, [$pendingWorkshop, $pendingTravel]);
            $pendingQuote = Quote::factory()->create([
                'user_id' => auth()->id(), 'title' => 'Rounding review', 'line_items' => $pendingItems,
                'subtotal_amount' => array_sum(array_column($pendingItems, 'line_total')),
                'gst_amount' => array_sum(array_column($pendingItems, 'tax_amount')),
                'total_amount' => array_sum(array_column($pendingItems, 'line_total_inc_tax')),
            ]);
            file_put_contents($directory.'/pending-quote.html', $this->get(route('admin.quote.edit', $pendingQuote))->assertOk()->getContent());
            $pendingInvoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($pendingQuote);
            $pendingInvoice->update(['status' => Invoice::STATUS_DRAFT]);
            file_put_contents($directory.'/pending-invoice.html', $this->get(route('admin.invoice.edit', $pendingInvoice))->assertOk()->getContent());
            $this->post(route('admin.finance.pricing'), [
                'name' => '2026 Workshop Plan', 'make_default' => true,
                'rules' => [
                    ['category_id' => 1, 'basis' => 'workshop', 'rate' => 15],
                    ['category_id' => 2, 'basis' => 'hour', 'rate' => 70],
                    ['category_id' => 3, 'basis' => 'participant', 'rate' => 4],
                    ['category_id' => 4, 'basis' => 'travel', 'rate' => 19],
                ],
                'rounding_step' => 0, 'travel_rounding_step' => 100, 'pricing_participants' => 10,
            ])->assertSessionHasNoErrors();
            $versionId = DB::table('finance_pricing_versions')->where('name', '2026 Workshop Plan')->value('id');
            $editorUrl = route('admin.cost-centre.allocations', ['tab' => 'editor', 'edit_id' => $versionId]);
            file_put_contents($directory.'/plan-list.html', $this->get(route('admin.cost-centre.allocations'))->assertOk()->getContent());
            file_put_contents($directory.'/plan-editor.html', $this->get($editorUrl)->assertOk()->getContent());
            file_put_contents($directory.'/reference-quote.html', $this->get(route('admin.quote.edit', $pendingQuote))->assertOk()->getContent());
        }
    }

    public function test_plan_pricing_basis_survives_conversion_while_allocations_use_actual_participants(): void
    {
        $this->post(route('admin.finance.pricing'), [
            'name' => 'Ten participant pricing', 'make_default' => true, 'pricing_participants' => 10,
            'rounding_step' => 50, 'rounding_category_id' => 2,
            'rules' => [
                ['category_id' => 1, 'basis' => 'workshop', 'rate' => 85],
                ['category_id' => 2, 'basis' => 'participant', 'rate' => 4],
            ],
        ])->assertSessionHasNoErrors();
        $single = $this->line();
        $single['workshop_seats'] = 15;
        $single['unit_price_inc_tax'] = 14.08;
        $single['unit_price'] = 12.80;
        $single['details_json']['inclusive_unit_price'] = 14.08;
        $single['details_json']['price_rounding'] = ['requested' => 14.00, 'applied' => 14.08];
        $single['details_json']['pricing_basis'] = ['participants' => 10];
        $multiple = array_replace($single, ['kind' => 'multi_workshop', 'quantity' => 25, 'workshops' => [
            ['description' => 'Morning', 'workshop_hours' => 1, 'workshop_seats' => 10],
            ['description' => 'Afternoon', 'workshop_hours' => 1, 'workshop_seats' => 15],
        ]]);
        $multiple['details_json']['multi_workshop']['quantity_basis'] = 'seat_hours';
        $quote = Quote::factory()->create(['user_id' => auth()->id(), 'status' => Quote::STATUS_OPEN]);
        $this->put(route('admin.quote.update', $quote), [
            'quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-10-20', 'line_items_json' => json_encode([$single, $multiple]),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $quote->refresh();
        $this->assertSame('563.20', $quote->total_amount);
        $this->get(route('admin.quote.edit', $quote))->assertOk()
            ->assertSeeInOrder(['data-price-rounding-review', 'Price rounding included in total:', 'name="quote_number"'], false)
            ->assertDontSee('data-price-rounding-total', false);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        foreach ($invoice->lines as $line) {
            $this->assertSame(['participants' => 10], $line->details_json['pricing_basis']);
        }
        $this->assertSame('563.20', $invoice->total_amount);
        $context = app(\App\Services\Finance\InvoiceAllocation::class)->context($invoice);
        $this->assertSame([15, 10, 15], array_column($context['assumptions']['lines'], 'participants'));
        $this->assertSame([10, 10, 10], array_column($context['assumptions']['lines'], 'pricing_participants'));
        $this->assertSame(41500, array_sum($context['suggestedTargets']));
        $rounding = app(\App\Services\Finance\FinancePlanner::class)->rounding($context['version'], $context['assumptions']);
        $this->assertSame(909, $rounding['limit']);
        $fractionalBasis = clone $context['version'];
        $rules = json_decode($fractionalBasis->rules, true);
        $rules[0]['rate_cents'] = 8501;
        $fractionalBasis->rules = json_encode($rules);
        $this->assertSame(339, app(\App\Services\Finance\FinancePlanner::class)->rounding($fractionalBasis, [
            'lines' => [$context['assumptions']['lines'][0]],
        ])['limit']);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()
            ->assertSeeInOrder(['data-price-rounding-review', 'Price rounding included in total:', 'name="invoice_number"'], false)
            ->assertDontSee('data-price-rounding-total', false);
    }

    public function test_fractional_and_group_quantities_keep_two_decimal_unit_rates_and_reproducible_totals(): void
    {
        $workshop = $this->line();
        $workshop['workshop_hours'] = 0.25;
        $workshop['workshop_seats'] = 1;
        $travel = array_replace($this->line(), ['kind' => 'travel', 'description' => 'Travel', 'travel_hours' => 0.25]);
        $group = array_replace($this->line(), ['kind' => 'multi_workshop', 'workshops' => [
            ['description' => 'STEM', 'workshop_hours' => 0.25, 'workshop_seats' => 1],
            ['description' => 'Robotics', 'workshop_hours' => 1, 'workshop_seats' => 10],
        ]]);
        $group['details_json']['multi_workshop']['quantity_basis'] = 'seat_hours';
        foreach ([$workshop, $travel, $group] as $index => $line) {
            $this->post(route('admin.invoice.store'), [
                'invoice_number' => 'CLEAN-PRICE-'.$index, 'issue_date' => '2026-10-20',
                'line_items_json' => json_encode([$line]),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $invoice = Invoice::where('invoice_number', 'CLEAN-PRICE-'.$index)->firstOrFail();
            $saved = $invoice->lines[0];
            $this->assertSame('13.20', LinePricing::formatExclusiveUnit($saved));
            $this->assertSame(round((float) $saved->quantity * 13.20, 2), (float) $invoice->subtotal_amount);
            $this->assertSame(round((float) $saved->quantity * 14.52, 2), (float) $invoice->total_amount);
            $this->assertSame(round((float) $invoice->subtotal_amount * 0.1, 2), (float) $invoice->gst_amount);
        }
    }
}
