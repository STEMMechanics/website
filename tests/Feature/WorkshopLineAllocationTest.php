<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Quote;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\WorkshopLine;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkshopLineAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function line(int $hours, int $seats): array
    {
        return ['kind' => 'workshop', 'description' => 'Workshop delivery', 'workshop_hours' => $hours, 'workshop_seats' => $seats, 'venue_supplied' => false, 'quantity' => 999, 'unit_price' => 10, 'gst_applicable' => true];
    }

    public function test_multi_workshop_invoice_keeps_one_line_and_allocates_each_delivery(): void
    {
        $this->admin();
        $rows = [$this->line(1, 10), $this->line(2, 15)];
        $rows[0]['venue_supplied'] = true;
        $line = ['kind' => 'multi_workshop', 'description' => 'Term 1', 'workshops' => $rows, 'quantity' => 40, 'unit_price' => 12.5, 'gst_applicable' => true];
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'MULTI-TERM', 'issue_date' => '2026-09-07', 'line_items_json' => json_encode([$line])])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'MULTI-TERM')->firstOrFail();
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('40.00', $invoice->lines->first()->quantity);
        $this->assertSame('550.00', $invoice->total_amount);
        $this->assertCount(2, $invoice->lines->first()->details_json['multi_workshop']['rows']);
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertNull($context['automaticWarning']);
        $this->assertSame(9000, $context['targets'][1]);
        $this->assertSame(12500, $context['targets'][2]);
        $this->assertSame(18000, $context['targets'][6]);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Multi Workshop Delivery');
    }

    public function test_invoice_travel_hours_preserve_quarter_hour_cost_allocations(): void
    {
        $this->admin();
        $line = ['kind' => 'travel', 'description' => 'Travel', 'travel_hours' => 2.25, 'quantity' => 999, 'unit_price' => 136.36, 'gst_applicable' => true, 'details_json' => ['inclusive_unit_price' => 150]];
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'TRAVEL-HOURS', 'issue_date' => '2026-09-07', 'line_items_json' => json_encode([$line])])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'TRAVEL-HOURS')->firstOrFail();
        $this->assertSame('2.25', $invoice->lines->first()->quantity);
        $this->assertSame(9, $invoice->lines->first()->details_json['travel']['billable_units']);
        $this->assertSame('337.50', $invoice->total_amount);
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertSame(17100, $context['targets'][3]);
        $this->assertSame(13500, $context['targets'][6]);
    }

    public function test_same_billable_quantity_has_different_costs_and_manual_overrides_are_preserved(): void
    {
        $admin = $this->admin();
        foreach ([[1, 30, 4500, 15000, 6000], [2, 15, 9000, 7500, 12000]] as [$hours, $seats, $venue, $consumables, $owner]) {
            $this->post(route('admin.invoice.store'), ['invoice_number' => 'WORKSHOP-'.$hours, 'issue_date' => '2026-09-07', 'line_items_json' => json_encode([$this->line($hours, $seats)])])->assertSessionHasNoErrors();
            $invoice = Invoice::where('invoice_number', 'WORKSHOP-'.$hours)->firstOrFail();
            $this->assertSame('30.00', $invoice->lines->first()->quantity);
            $this->assertSame('330.00', $invoice->total_amount);
            $context = app(InvoiceAllocation::class)->context($invoice);
            $this->assertSame($venue, $context['targets'][1]);
            $this->assertSame($consumables, $context['targets'][2]);
            $this->assertSame($owner, $context['targets'][6]);
            $this->assertFalse((bool) $context['budget']->manual);
            $this->postJson(route('admin.invoice.allocation.store', $invoice), ['budget_id' => $context['budget']->id, 'targets' => [1 => 20]])->assertOk();
            app(InvoiceAllocation::class)->sync($invoice, $admin->id);
            $this->assertSame([1 => 2000], app(InvoiceAllocation::class)->context($invoice)['targets']);
            $this->postJson(route('admin.invoice.allocation.store', $invoice), ['budget_id' => $context['budget']->id, 'use_defaults' => 1])->assertOk();
            $this->assertSame($venue, app(InvoiceAllocation::class)->context($invoice)['targets'][1]);
        }
    }

    public function test_issued_legacy_line_can_gain_a_breakdown_without_changing_billing(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_ISSUED, 'total_amount' => 330, 'gst_amount' => 30]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'workshop', 'quantity' => 30, 'details_json' => []]);
        $this->assertNotNull(app(InvoiceAllocation::class)->context($invoice)['automaticWarning']);
        $data = ['use_defaults' => 1, 'line_details' => [$line->id => ['workshop_hours' => 2, 'workshop_seats' => 30, 'venue_supplied' => 1]]];
        $this->postJson(route('admin.invoice.allocation.store', $invoice), $data)->assertUnprocessable();
        $this->assertSame([], $line->fresh()->details_json);
        $data['line_details'][$line->id]['workshop_seats'] = 15;
        $this->postJson(route('admin.invoice.allocation.store', $invoice), $data)->assertOk();
        $this->assertSame('30.00', $line->fresh()->quantity);
        $this->assertSame('330.00', $invoice->fresh()->total_amount);
        $this->assertSame(0, app(InvoiceAllocation::class)->context($invoice)['targets'][1]);
    }

    public function test_ticket_workshop_costs_are_shared_and_include_completed_tickets(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['status' => Ticket::STATUS_DONE]);
        $workshop = $ticket->workshop;
        $workshop->update(['starts_at' => '2026-09-07 10:00:00', 'ends_at' => '2026-09-07 12:00:00']);
        $first = Invoice::factory()->create();
        $second = Invoice::factory()->create();
        $ticket->update(['invoice_id' => $first->id]);
        Ticket::factory()->create(['invoice_id' => $second->id, 'workshop_id' => $workshop->id]);
        app(InvoiceAllocation::class)->sync($first, $admin->id);
        app(InvoiceAllocation::class)->sync($second, $admin->id);
        $this->assertDatabaseCount('finance_budgets', 0);
        $context = app(\App\Services\Finance\WorkshopAllocation::class)->context($workshop);
        $this->assertSame(1000, $context['targets'][2]);
        $this->assertSame(12000, $context['targets'][6]);
    }

    public function test_ticket_allocations_use_the_workshop_plan_and_keep_it_when_the_default_changes(): void
    {
        $admin = $this->admin();
        $plan = (array) DB::table('finance_pricing_versions')->first();
        unset($plan['id']);
        $plan['name'] = 'Online workshops';
        $plan['rules'] = json_encode([['category_id' => 6, 'basis' => 'hour', 'rate_cents' => 6000]]);
        $planId = DB::table('finance_pricing_versions')->insertGetId($plan);
        $ticket = Ticket::factory()->create(['status' => Ticket::STATUS_PAID]);
        $ticket->workshop->update(['pricing_version_id' => $planId, 'starts_at' => '2026-09-07 10:00:00', 'ends_at' => '2026-09-07 12:00:00']);
        $invoice = Invoice::factory()->create();
        $ticket->update(['invoice_id' => $invoice->id]);
        $allocator = app(InvoiceAllocation::class);
        $context = app(\App\Services\Finance\WorkshopAllocation::class)->context($ticket->workshop);
        $this->assertSame($planId, $context['version']->id);
        $this->assertSame([6 => 12000], $context['targets']);
        $allocator->sync($invoice, $admin->id);
        $this->get(route('admin.workshop.edit', $ticket->workshop))->assertOk()->assertSee('Review workshop allocation')->assertSee('Online workshops');
        $this->assertDatabaseCount('finance_budgets', 0);
        DB::table('finance_settings')->where('id', 1)->update(['default_pricing_version_id' => 1]);
        $context = app(\App\Services\Finance\WorkshopAllocation::class)->context($ticket->workshop);
        $this->assertSame($planId, $context['version']->id);
        $this->assertSame([6 => 12000], $context['targets']);
    }

    public function test_multiple_workshops_and_travel_apply_their_own_units(): void
    {
        $admin = $this->admin();
        $lines = [$this->line(1, 30), $this->line(2, 15), ['kind' => 'travel', 'description' => 'Travel', 'travel_units' => 2, 'quantity' => 20, 'unit_price' => 34, 'gst_applicable' => true]];
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'MULTIPLE', 'issue_date' => '2026-09-07', 'line_items_json' => json_encode($lines)])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'MULTIPLE')->firstOrFail();
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertSame(13500, $context['targets'][1]);
        $this->assertSame(22500, $context['targets'][2]);
        $this->assertSame(3800, $context['targets'][3]);
        $this->assertSame(3400, $context['targets'][4]);
        $this->assertSame(21000, $context['targets'][6]);
        $this->assertSame('2.00', $invoice->lines()->where('kind', 'travel')->first()->quantity);
        $invoice->lines()->delete();
        app(InvoiceAllocation::class)->sync($invoice, $admin->id);
        $this->assertSame([], app(InvoiceAllocation::class)->context($invoice)['targets']);
    }

    public function test_incomplete_dimensions_are_rejected_before_an_invoice_is_saved(): void
    {
        $this->admin();
        $line = $this->line(2, 15);
        $line['workshop_seats'] = '';
        $this->postJson(route('admin.invoice.store'), ['invoice_number' => 'INVALID', 'issue_date' => '2026-09-07', 'line_items_json' => json_encode([$line])])->assertUnprocessable();
        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INVALID']);
    }

    public function test_inclusive_rounding_is_exact_and_only_funded_extra_reaches_the_selected_centre(): void
    {
        $this->admin();
        $this->postJson(route('admin.finance.pricing'), ['name' => 'Rounded tickets', 'make_default' => true,
            'rounding_step' => 50, 'rounding_category_id' => 2,
            'rules' => [['category_id' => 1, 'basis' => 'participant', 'rate' => 17.70]],
        ])->assertOk();
        $line = array_merge($this->line(1, 17), ['unit_price' => 17.73, 'details_json' => ['inclusive_unit_price' => 19.50]]);
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'ROUND-EXACT', 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode([$line])])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'ROUND-EXACT')->firstOrFail();
        $this->assertSame('331.50', $invoice->total_amount);
        $this->assertSame('301.36', $invoice->lines->first()->line_total_ex_tax);
        $this->assertSame('30.14', $invoice->lines->first()->tax_amount);
        $payment = \App\Models\Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => 331.50, 'gst_amount' => 30.14, 'received_on' => today()->toDateString()]);
        \App\Models\InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 331.50]);
        $planner = app(\App\Services\Finance\FinancePlanner::class);
        $context = app(InvoiceAllocation::class)->context($invoice);
        $rounding = $planner->rounding($context['version'], $context['assumptions']);
        $this->assertSame(46, $rounding['limit']);
        $this->assertSame(0, $planner->funding($context['targets'], 10000, $rounding)['categories'][2]);
        $this->assertSame(46, $planner->budgetReport($context['budget'])['funding']['categories'][2]);
        $this->assertSame(46, $planner->cash()['reserves'][2]);
        $this->get(route('admin.cost-centre.show', ['centre' => 2, 'tab' => 'allocations']))->assertOk()->assertSee('ROUND-EXACT');
        $quote = Quote::factory()->create(['status' => Quote::STATUS_OPEN]);
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN, 'quote_date' => today()->toDateString(), 'title' => 'Rounded price', 'line_items_json' => json_encode([$line])])->assertSessionHasNoErrors();
        $this->assertSame('331.50', $quote->fresh()->total_amount);
        $converted = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote->fresh());
        $this->assertSame('331.50', $converted->total_amount);
        $this->assertSame('331.50', $converted->lines->first()->line_total_inc_tax);
    }

    public function test_ticket_rounding_uses_the_pricing_attendance_and_does_not_capture_other_surplus(): void
    {
        $planner = app(\App\Services\Finance\FinancePlanner::class);
        $version = (object) ['prices' => json_encode(['rounding_step' => 100, 'rounding_category_id' => 2, 'pricing_participants' => 10]),
            'rules' => json_encode([['category_id' => 1, 'basis' => 'workshop', 'rate_cents' => 10000], ['category_id' => 2, 'basis' => 'participant', 'rate_cents' => 500]])];
        $inputs = ['participants' => 20, 'hours' => 1, 'travel_minutes' => 0, 'venue_supplied' => false];
        $rounding = $planner->rounding($version, $inputs);
        $this->assertSame(909, $rounding['limit']);
        $targets = $planner->targets(json_decode($version->rules, true), $inputs);
        $funding = $planner->funding($targets, 30909, $rounding);
        $this->assertSame(10909, $funding['categories'][2]);
        $this->assertSame(10000, $funding['surplus']);
        $this->assertSame(0, $planner->funding($targets, 9000, $rounding)['categories'][2]);
    }

    public function test_travel_rounding_extra_uses_its_own_increment(): void
    {
        $planner = app(\App\Services\Finance\FinancePlanner::class);
        $version = (object) ['prices' => json_encode(['rounding_step' => 0, 'travel_rounding_step' => 500, 'travel_cents' => 3400, 'rounding_category_id' => 2]), 'rules' => '[]'];
        $rounding = $planner->rounding($version, ['lines' => [['kind' => 'travel', 'units' => 2, 'gst_applicable' => true]]]);
        $this->assertSame(182, $rounding['limit']);
        $this->assertSame(182, $planner->funding([1 => 4000], 6364, $rounding)['categories'][2]);
        $this->assertSame(0, $planner->funding([1 => 4000], 3000, $rounding)['categories'][2]);
    }

    public function test_multi_workshop_quote_conversion_preserves_separate_terms_and_metadata(): void
    {
        $this->admin();
        $quote = Quote::factory()->create(['status' => Quote::STATUS_OPEN]);
        $group = ['kind' => 'multi_workshop', 'description' => 'Term 1', 'workshops' => [$this->line(1, 10), $this->line(2, 15)], 'unit_price' => 12.5, 'gst_applicable' => true];
        $second = array_replace($group, ['description' => 'Term 2']);
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN, 'quote_date' => '2026-09-07', 'title' => 'Terms', 'line_items_json' => json_encode([$group, $second])])->assertSessionHasNoErrors();
        $quote->refresh();
        $this->get(route('admin.quote.edit', $quote))->assertOk()->assertSee('Multi Workshop Delivery');
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('1100.00', $invoice->total_amount);
        $this->assertSame($quote->line_items[0]['details_json'], $invoice->lines[0]->details_json);
        $this->assertSame($quote->line_items[0]['notes'], $invoice->lines[0]->notes);
        $this->assertSame('Term 2', $invoice->lines[1]->description);
        $data = ['invoice' => $invoice, 'itemPages' => [$invoice->lines->toArray()], 'adjustments' => collect()];
        $this->assertStringContainsString('Term 2', view('pdf.invoice', $data)->render());
        if (getenv('MULTI_PDF_PREVIEW')) {
            \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', $data)->save('/tmp/multi-workshop-preview.pdf');
        }
    }

    public function test_quote_conversion_and_pdf_preserve_hours_and_seats(): void
    {
        $this->admin();
        $quote = Quote::factory()->create(['status' => Quote::STATUS_OPEN]);
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN, 'quote_date' => '2026-09-07', 'title' => 'Workshop test', 'line_items_json' => json_encode([$this->line(2, 15)])])->assertSessionHasNoErrors();
        $quote->refresh();
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame('30.00', $invoice->lines->first()->quantity);
        $this->assertSame('2 × 15', WorkshopLine::quantityLabel($invoice->lines->first()->toArray()));
        $invoiceData = ['invoice' => $invoice, 'itemPages' => [$invoice->lines->toArray()], 'adjustments' => collect()];
        $quoteData = ['quote' => $quote, 'itemPages' => [$quote->line_items]];
        $this->assertStringContainsString('HRS / QTY', view('pdf.invoice', $invoiceData)->render());
        $this->assertStringContainsString('>30</td>', view('pdf.invoice', $invoiceData)->render());
        $this->assertStringContainsString('2 × 15', view('pdf.quote', $quoteData)->render());
        if (getenv('WORKSHOP_PDF_PREVIEW')) {
            \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', $invoiceData)->save('/tmp/workshop-invoice-preview.pdf');
            \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quote', $quoteData)->save('/tmp/workshop-quote-preview.pdf');
        }
    }
}
