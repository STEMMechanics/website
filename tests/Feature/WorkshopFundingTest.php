<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceLine, InvoicePaymentAllocation, Payment, Quote, Ticket, User, UserGroup, Workshop};
use App\Services\Finance\{FinancePlanner, FinanceReportData, InvoiceAllocation, InvoiceAllocationParts, WorkshopAllocation, WorkshopFunding, WorkshopLine};
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkshopFundingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function workshop(): Workshop
    {
        $ticket = Ticket::factory()->create();
        $workshop = $ticket->workshop->replicate();
        $ticket->delete();
        $workshop->fill(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour(), 'max_tickets' => 20, 'price' => 'Free']);
        $workshop->save();
        return $workshop;
    }

    private function line(Workshop $workshop, string $basis = 'manual'): array
    {
        return ['kind' => 'workshop', 'description' => $workshop->title, 'workshop_hours' => 2, 'workshop_seats' => 20,
            'quantity' => 40, 'unit_price' => 10, 'gst_applicable' => true,
            'details_json' => ['workshop' => ['linked_workshop_id' => $workshop->id, 'allocation_basis' => $basis, 'allocation_seats' => 12]]];
    }

    private function createInvoice(array $lines): Invoice
    {
        $number = 'COUNCIL-'.Invoice::count();
        $this->post(route('admin.invoice.store'), ['invoice_number' => $number, 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode($lines)])
            ->assertSessionHasNoErrors()->assertRedirect();
        return Invoice::where('invoice_number', $number)->firstOrFail();
    }

    private function pay(Invoice $invoice, float $amount): void
    {
        $payment = Payment::factory()->create(['total_amount' => $amount, 'gst_amount' => round($amount / 11, 2), 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => $amount]);
    }

    public function test_four_free_workshops_share_council_receipts_without_double_allocation(): void
    {
        $user = $this->admin();
        $workshops = collect(range(1, 4))->map(fn () => $this->workshop());
        $invoice = $this->createInvoice($workshops->map(fn ($workshop) => $this->line($workshop))->push(['kind' => 'custom', 'description' => 'Travel', 'quantity' => 1, 'unit_price' => 100, 'gst_applicable' => true])->all());
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $this->pay($invoice, 935); // Half of $1,870: $200 net per workshop, $50 other income.
        $parts = app(InvoiceAllocationParts::class);
        $this->assertSame(5000, $parts->income([$invoice->id], null)['net']);
        foreach ($workshops as $workshop) {
            $context = app(WorkshopAllocation::class)->context($workshop);
            $this->assertSame(20000, $context['income']['net']);
            $this->assertSame(20000, $context['awaitingFunding']);
            $this->assertSame(20, $context['assumptions']['participants']);
            $this->assertTrue(app(WorkshopAllocation::class)->state($workshop)['ready']);
            $this->get(route('admin.workshop.allocation.edit', $workshop))->assertOk()->assertDontSee('awaiting payment');
        }
        $this->assertSame([], app(InvoiceAllocation::class)->context($invoice)['suggestedTargets']);
        $this->pay($invoice, 935);
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        foreach ($workshops as $workshop) {
            $service = app(WorkshopAllocation::class);
            $this->assertTrue($service->state($workshop)['ready']);
            $service->finalise($workshop, ['source_hash' => $service->state($workshop)['hash']], $user->id);
        }
        $data = new FinanceReportData(collect(), DB::table('finance_budgets')->get());
        foreach ($data->budgets as $budget) {
            $this->assertSame([$invoice->id], $data->invoiceIds($budget));
            $this->assertSame(40000, $parts->income($data->invoiceIds($budget), $budget->workshop_id, null, $data)['net']);
            $this->assertTrue(app(WorkshopAllocation::class)->isCurrent($budget, $data));
        }
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Allocation sections')->assertSee('Workshop funding');
    }

    public function test_participant_bases_change_costs_without_changing_billing_and_invalidate_finalisation(): void
    {
        $user = $this->admin();
        $workshop = $this->workshop();
        $invoice = $this->createInvoice([$this->line($workshop, 'capacity')]);
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        $this->pay($invoice, 440);
        $service = app(WorkshopAllocation::class);
        $this->assertSame(20, $service->context($workshop)['assumptions']['participants']);
        $line = $invoice->lines->first();
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(), 'workshop_funding' => [$line->id => ['linked_workshop_id' => $workshop->id, 'allocation_basis' => 'tickets', 'allocation_seats' => 12]]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        Ticket::factory()->count(3)->create(['workshop_id' => $workshop->id, 'status' => Ticket::STATUS_PAID, 'invoice_id' => null]);
        $this->assertSame(3, $service->context($workshop)['assumptions']['participants']);
        $service->finalise($workshop, ['source_hash' => $service->state($workshop)['hash']], $user->id);
        Ticket::factory()->create(['workshop_id' => $workshop->id, 'status' => Ticket::STATUS_PAID, 'invoice_id' => null]);
        $this->assertFalse($service->state($workshop)['current']);
        $this->assertSame(4, $service->context($workshop)['assumptions']['participants']);
        $this->assertSame('440.00', $invoice->fresh()->total_amount);
        $this->assertSame('40.00', $line->fresh()->quantity);
    }

    public function test_existing_issued_invoice_can_be_linked_and_unlinked_without_changing_price(): void
    {
        $this->admin();
        $workshop = $this->workshop();
        $manual = $this->line($workshop);
        $manual['details_json'] = [];
        $invoice = $this->createInvoice([$manual]);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $line = $invoice->lines->first();
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(), 'workshop_funding' => [$line->id => ['linked_workshop_id' => $workshop->id, 'allocation_basis' => 'manual', 'allocation_seats' => 12]]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame($workshop->id, $line->fresh()->details_json['workshop']['linked_workshop_id']);
        $this->assertSame([], app(InvoiceAllocation::class)->context($invoice->fresh())['targets']);
        $this->assertSame('440.00', $invoice->fresh()->total_amount);
        $payload['workshop_funding'][$line->id]['linked_workshop_id'] = '';
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('linked_workshop_id', $line->fresh()->details_json['workshop']);
        $this->assertNotEmpty(app(InvoiceAllocation::class)->context($invoice->fresh())['targets']);
    }

    public function test_quote_conversion_retains_link_and_allocation_basis(): void
    {
        $user = $this->admin();
        $workshop = $this->workshop();
        $line = WorkshopLine::normalize($this->line($workshop, 'capacity'));
        $quote = Quote::factory()->create(['user_id' => $user->id, 'line_items' => [$line], 'subtotal_amount' => 400, 'gst_amount' => 40, 'total_amount' => 440]);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame($workshop->id, $invoice->lines->first()->details_json['workshop']['linked_workshop_id']);
        $this->assertSame(20, app(WorkshopAllocation::class)->context($workshop)['assumptions']['participants']);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Seats options');
    }

    public function test_draft_funding_invoice_can_save_linked_workshop_plans_without_receiving_cash(): void
    {
        $this->admin();
        $workshop = $this->workshop();
        $workshop->update(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
        $lines = [$this->line($workshop)];
        $invoice = $this->createInvoice($lines);
        $service = app(WorkshopAllocation::class);
        $state = $service->state($workshop);
        $this->assertTrue($state['ready']);
        $this->assertSame(0, $state['pending']);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee("inspectionReason('invoice')", false)->assertSee('data-allocation-ready="1"', false);
        $lines[0]['workshop_seats'] = 8;
        $this->put(route('admin.invoice.update', $invoice), [
            'invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(),
            'line_items_json' => json_encode($lines),
            'workshop_allocations' => [$workshop->id => ['source_hash' => $state['hash'], 'override' => 1, 'targets' => [1 => '123.45']]],
        ])->assertSessionHasNoErrors();
        $context = $service->context($workshop);
        $this->assertSame(8, $context['assumptions']['participants']);
        $this->assertSame([1 => 12345], $context['targets']);
        $this->assertTrue($service->state($workshop)['current']);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status);
        $this->assertSame(0, array_sum(app(FinancePlanner::class)->budgetReport($context['budget'])['funding']['categories']));
        $currentHash = $service->state($workshop)['hash'];
        $oldLineId = $invoice->lines()->first()->id;
        $this->travel(1)->minutes();
        $this->put(route('admin.invoice.update', $invoice), [
            'invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(),
            'line_items_json' => json_encode($lines), 'notes' => 'Unrelated invoice note',
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($oldLineId, $invoice->lines()->first()->id);
        $this->assertSame($currentHash, $service->state($workshop)['hash']);
        $this->assertTrue($service->state($workshop)['current']);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $this->assertTrue($service->state($workshop)['current']);
        $linked = $invoice->lines()->first();
        $details = $linked->details_json;
        $details['workshop']['allocation_seats'] = 7;
        $linked->update(['details_json' => $details]);
        $this->assertFalse($service->state($workshop)['current']);
    }

    public function test_tabs_follow_invoice_rows_and_seat_changes_save_with_the_plan(): void
    {
        $this->admin();
        $first = $this->workshop();
        $second = $this->workshop();
        $second->update(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $invoice = $this->createInvoice([$this->line($first), $this->line($second)]);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $this->assertSame([$first->id, $second->id], app(\App\Services\Finance\InvoiceAllocationWorkspace::class)->workshops($invoice)->pluck('id')->all());
        $line = $invoice->lines()->first();
        $hash = app(WorkshopAllocation::class)->state($first)['hash'];
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(),
            'workshop_funding' => [$line->id => ['linked_workshop_id' => $first->id, 'allocation_basis' => 'manual', 'allocation_seats' => 8]],
            'workshop_allocations' => [$first->id => ['source_hash' => $hash, 'override' => 0]],
        ];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $context = app(WorkshopAllocation::class)->context($first);
        $this->assertSame(8, $context['assumptions']['participants']);
        $this->assertSame($context['suggestedTargets'], $context['targets']);
        $this->assertTrue(app(WorkshopAllocation::class)->state($first)['current']);
        $this->assertSame('20', (string) $line->fresh()->details_json['workshop']['seats']);
        $payload['workshop_funding'][$line->id]['allocation_seats'] = 4;
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasErrors();
        $this->assertSame(8, app(WorkshopAllocation::class)->context($first)['assumptions']['participants']);
    }

    public function test_unpaid_funding_plan_can_be_finalised_from_invoice_and_receipts_do_not_require_review(): void
    {
        $this->admin();
        $workshop = $this->workshop();
        $invoice = $this->createInvoice([$this->line($workshop)]);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $service = app(WorkshopAllocation::class);
        $hash = $service->state($workshop)['hash'];
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(), 'workshop_allocations' => [$workshop->id => ['source_hash' => $hash, 'override' => 1, 'targets' => [1 => '300.00']]]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $budget = DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first();
        $this->assertNotNull($budget->finalised_at);
        $unallocated = Invoice::query()->whereKey($invoice->id);
        app(\App\Services\Finance\InvoiceAllocationFilters::class)->apply($unallocated, ['allocation_state' => 'not_allocated']);
        $this->assertFalse($unallocated->exists());
        $this->assertTrue($service->state($workshop)['current']);
        $this->assertSame(0, array_sum(app(FinancePlanner::class)->budgetReport($budget)['funding']['categories']));
        $this->pay($invoice, 220);
        $this->assertSame($hash, $service->state($workshop)['hash']);
        $this->assertTrue($service->state($workshop)['current']);
        $this->assertSame(20000, array_sum(app(FinancePlanner::class)->budgetReport($budget)['funding']['categories']));
        $this->pay($invoice, 220);
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        $this->assertTrue($service->state($workshop)['current']);
        $this->assertSame(30000, array_sum(app(FinancePlanner::class)->budgetReport($budget)['funding']['categories']));
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Combined allocation (ex GST)')->assertSee('Invoice items')->assertSee('data-workshop-allocation', false)->assertSee('Use defaults')->assertDontSee('<x-ui.input-control', false)->assertDontSee('@js(', false);
    }

    public function test_invalid_workshop_tab_rolls_back_invoice_and_other_workshop_plans(): void
    {
        $this->admin();
        $first = $this->workshop();
        $second = $this->workshop();
        $invoice = $this->createInvoice([$this->line($first), $this->line($second)]);
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $service = app(WorkshopAllocation::class);
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(), 'notes' => 'Must roll back', 'workshop_allocations' => [
            $first->id => ['source_hash' => $service->state($first)['hash'], 'override' => 1, 'targets' => [1 => '50.00']],
            $second->id => ['source_hash' => $service->state($second)['hash'], 'override' => 1, 'targets' => [1 => '-1.00']],
        ]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasErrors('workshop_allocations.'.$second->id.'.targets.1');
        $this->assertNull($invoice->fresh()->notes);
        $this->assertDatabaseCount('finance_budgets', 0);
        $other = $this->workshop();
        $payload['workshop_allocations'] = [$other->id => ['source_hash' => $service->state($other)['hash']]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasErrors('workshop_allocations');
    }

    public function test_grouped_council_delivery_splits_receipts_by_billed_seat_hours(): void
    {
        $this->admin();
        $workshops = collect(range(1, 4))->map(fn () => $this->workshop());
        $rows = $workshops->map(fn ($workshop, $index) => array_merge($this->line($workshop, 'capacity'), ['workshop_hours' => $index % 2 ? 2 : 1]))->all();
        $group = ['kind' => 'multi_workshop', 'description' => 'Julia Creek workshops', 'workshops' => $rows, 'quantity' => 120, 'unit_price' => 6.25, 'gst_applicable' => true, 'details_json' => ['inclusive_unit_price' => 6.875, 'multi_workshop' => ['quantity_basis' => 'seat_hours']]];
        $invoice = $this->createInvoice([$group]);
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        $this->assertSame('825.00', $invoice->total_amount);
        $this->pay($invoice, 825);
        foreach ($workshops as $index => $workshop) {
            $context = app(WorkshopAllocation::class)->context($workshop);
            $this->assertSame($index % 2 ? 25000 : 12500, $context['income']['net']);
            $this->assertSame(20, $context['assumptions']['participants']);
        }
        $this->assertSame(0, app(InvoiceAllocationParts::class)->income([$invoice->id], null)['net']);
        $this->assertSame([], app(InvoiceAllocation::class)->context($invoice)['suggestedTargets']);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('Funding is split using');
        $line = $invoice->lines->first();
        $payload = ['invoice_number' => $invoice->invoice_number, 'issue_date' => today()->toDateString(), 'workshop_funding' => [$line->id => ['rows' => [0 => ['linked_workshop_id' => '', 'allocation_basis' => 'manual', 'allocation_seats' => 20]]]]];
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame('825.00', $invoice->fresh()->total_amount);
        $this->assertSame('120.00', $line->fresh()->quantity);
        $this->assertSame(12500, app(InvoiceAllocationParts::class)->income([$invoice->id], null)['net']);
        $this->assertNotEmpty(app(InvoiceAllocation::class)->context($invoice->fresh())['suggestedTargets']);
        $this->assertSame([], app(WorkshopFunding::class)->invoiceIds($workshops[0]->id));
    }

    public function test_refunds_are_partitioned_and_cancelled_funding_is_not_awaiting_payment(): void
    {
        $this->admin();
        $workshop = $this->workshop();
        $invoice = $this->createInvoice([$this->line($workshop)]);
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        $this->pay($invoice, 440);
        $payment = $invoice->allocations()->first()->customerPayment;
        $refund = Payment::factory()->create(['kind' => Payment::KIND_REFUND, 'refund_of_payment_id' => $payment->id, 'total_amount' => 440, 'gst_amount' => 40, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $refund->id, 'invoice_id' => $invoice->id, 'allocated_amount' => -440]);
        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        $context = app(WorkshopAllocation::class)->context($workshop);
        $this->assertSame(0, $context['income']['net']);
        $this->assertSame(0, $context['awaitingFunding']);
        $this->assertSame(0, app(InvoiceAllocationParts::class)->income([$invoice->id], null)['net']);
    }

    public function test_unknown_workshop_and_duplicate_funding_lines_are_rejected_atomically(): void
    {
        $this->admin();
        $workshop = $this->workshop();
        $line = $this->line($workshop);
        $line['details_json']['workshop']['linked_workshop_id'] = 'missing';
        $payload = ['invoice_number' => 'BAD-LINK', 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode([$line])];
        $this->post(route('admin.invoice.store'), $payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
        $payload['line_items_json'] = json_encode([$this->line($workshop), $this->line($workshop)]);
        $this->post(route('admin.invoice.store'), $payload)->assertSessionHasErrors('workshop_funding');
        $this->assertDatabaseCount('invoices', 0);
    }
}
