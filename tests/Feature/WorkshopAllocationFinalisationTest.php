<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\TaxAdjustment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinanceAttention;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\InvoiceAllocationParts;
use App\Services\Finance\WorkshopAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkshopAllocationFinalisationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $mixed = false): array
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        $invoice = Invoice::factory()->create(['status' => 'paid', 'total_amount' => $mixed ? 165 : 110, 'gst_amount' => $mixed ? 15 : 10, 'subtotal_amount' => $mixed ? 150 : 100, 'issue_date' => today(), 'created_by' => $user->id]);
        $ticket = Ticket::factory()->create(['invoice_id' => $invoice->id]);
        $workshop = $ticket->workshop;
        $workshop->update(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'ticket', 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110, 'details_json' => ['workshop_id' => $workshop->id]]);
        $ticket->update(['invoice_line_id' => $line->id]);
        if ($mixed) {
            InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'product', 'line_number' => 2, 'line_total_ex_tax' => 50, 'tax_amount' => 5, 'line_total_inc_tax' => 55]);
        }
        $payment = Payment::factory()->create(['total_amount' => $invoice->total_amount, 'gst_amount' => $invoice->gst_amount, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()->subMinutes(30)]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'allocated_amount' => $invoice->total_amount]);

        return compact('user', 'invoice', 'ticket', 'workshop', 'payment');
    }

    private function finalise(array $fixture): void
    {
        $service = app(WorkshopAllocation::class);
        $this->post(route('admin.workshop.allocation.store', $fixture['workshop']), ['source_hash' => $service->state($fixture['workshop'])['hash'], 'override' => 1, 'targets' => [1 => 100]])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_hosted_workshop_defaults_to_nothing_supplied_and_preserves_explicit_choices(): void
    {
        $f = $this->fixture();
        $f['workshop']->update(['hosted_for_organisation_id' => \App\Models\Organisation::factory()->create()->id, 'pricing_version_id' => 1]);
        DB::table('finance_pricing_versions')->where('id', 1)->update(['rules' => json_encode([
            ['category_id' => 1, 'basis' => 'flat', 'rate_cents' => 3000, 'suppliable' => true, 'venue_default' => true],
            ['category_id' => 2, 'basis' => 'flat', 'rate_cents' => 500, 'suppliable' => true],
            ['category_id' => 3, 'basis' => 'flat', 'rate_cents' => 1000],
        ])]);
        $service = app(WorkshopAllocation::class);
        $this->assertSame(3000, $service->context($f['workshop'])['suggestedTargets'][1]);
        $this->assertSame(500, $service->context($f['workshop'])['suggestedTargets'][2]);
        $this->assertFalse($service->context($f['workshop'])['assumptions']['venue_supplied']);
        $this->get(route('admin.workshop.allocation.edit', $f['workshop']))->assertOk()->assertSee('Venue hire supplied')->assertSee('Supplied items');
        $this->post(route('admin.workshop.allocation.store', $f['workshop']), [
            'source_hash' => $service->state($f['workshop'])['hash'],
            'supplied_categories' => [1 => '0', 2 => '1'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $context = $service->context($f['workshop']);
        $this->assertSame(3000, $context['targets'][1]);
        $this->assertSame(0, $context['targets'][2]);
        $this->assertSame(1000, $context['targets'][3]);
        $this->assertFalse($context['assumptions']['supplied_categories'][1]);
        $this->assertTrue($context['assumptions']['supplied_categories'][2]);
        $this->assertFalse((bool) $context['budget']->manual);
        $this->post(route('admin.workshop.allocation.store', $f['workshop']), [
            'source_hash' => $service->state($f['workshop'])['hash'],
            'revision' => hash('sha256', json_encode((array) $context['budget'])),
            'supplied_categories' => [1 => '1', 2 => '0'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $context = $service->context($f['workshop']);
        $this->assertSame(0, $context['targets'][1]);
        $this->assertSame(500, $context['targets'][2]);
        $this->assertDatabaseCount('finance_budget_revisions', 2);
    }

    public function test_supplied_items_reject_unknown_categories_and_invalid_values(): void
    {
        $f = $this->fixture();
        $base = ['source_hash' => app(WorkshopAllocation::class)->state($f['workshop'])['hash']];
        foreach ([[999999 => '1'], [1 => 'invalid']] as $supplied) {
            $this->postJson(route('admin.workshop.allocation.store', $f['workshop']), $base + ['supplied_categories' => $supplied])->assertUnprocessable();
        }
        $this->assertDatabaseCount('finance_budgets', 0);
    }

    private function cancelAndRefund(array $f): void
    {
        $f['workshop']->update(['status' => 'cancelled']);
        $f['invoice']->update(['status' => Invoice::STATUS_CANCELLED]);
        $refund = Payment::factory()->create(['kind' => Payment::KIND_REFUND, 'refund_of_payment_id' => $f['payment']->id, 'total_amount' => 110, 'gst_amount' => 10, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $f['invoice']->id, 'payment_id' => $refund->id, 'allocated_amount' => -110]);
    }

    public function test_settled_cancelled_workshop_without_an_allocation_needs_no_review(): void
    {
        $f = $this->fixture();
        $this->cancelAndRefund($f);
        $service = app(WorkshopAllocation::class);
        $state = $service->state($f['workshop']);
        $this->assertSame('No allocation required', $state['status']);
        $this->assertFalse($state['ready']);
        $this->assertEmpty($service->attention());
        $this->get(route('admin.workshop.allocation.edit', $f['workshop']))->assertOk()->assertSee('No allocation required')->assertDontSee('Finalise allocation');
        $this->postJson(route('admin.workshop.allocation.store', $f['workshop']), ['source_hash' => $state['hash']])->assertUnprocessable();
    }

    public function test_cancelled_workshop_without_receipts_is_exempt_only_after_payment_outcomes_settle(): void
    {
        $f = $this->fixture();
        $f['workshop']->update(['status' => 'cancelled']);
        $f['payment']->update(['payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER, 'cleared_at' => null]);
        $service = app(WorkshopAllocation::class);
        $this->assertNotSame('No allocation required', $service->state($f['workshop'])['status']);
        $f['invoice']->update(['status' => Invoice::STATUS_CANCELLED]);
        $this->assertSame('No allocation required', $service->state($f['workshop'])['status']);
    }

    public function test_cancelled_workshop_with_retained_income_or_previous_allocation_still_needs_review(): void
    {
        $f = $this->fixture();
        $f['workshop']->update(['status' => 'cancelled']);
        $service = app(WorkshopAllocation::class);
        $this->assertTrue($service->state($f['workshop'])['ready']);
        $this->assertCount(1, $service->attention());
        $this->finalise($f);
        $this->cancelAndRefund($f);
        $this->assertSame('Allocation needs review', $service->state($f['workshop'])['status']);
        $this->assertCount(1, $service->attention());
    }

    public function test_dashboard_review_task_and_workshop_tabs_follow_finalisation(): void
    {
        $f = $this->fixture();
        $url = route('admin.workshop.allocation.edit', $f['workshop']);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('allocationTasks', fn ($tasks) => count($tasks) === 1)->assertSee($url);
        foreach (['edit', 'attendance', 'files', 'photos', 'allocation.edit'] as $page) {
            $response = $this->get(route('admin.workshop.'.$page, $f['workshop']))->assertOk()->assertSee($url);
            if ($page === 'edit') {
                $response->assertSee('Allocation ready for review')->assertDontSee('>Review allocation</a>', false);
            } else {
                $response->assertDontSee('aria-label="Workshop allocation review"', false);
            }
        }
        $this->finalise($f);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('allocationTasks', fn ($tasks) => count($tasks) === 0);
        $f['ticket']->update(['attended_at' => now()]);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('allocationTasks', fn ($tasks) => count($tasks) === 1);
    }

    public function test_workshop_finalises_once_and_later_changes_require_review(): void
    {
        $f = $this->fixture();
        $service = app(WorkshopAllocation::class);
        $this->assertSame('Ready for review', $service->state($f['workshop'])['status']);
        $this->get(route('admin.workshop.allocation.edit', $f['workshop']))->assertOk()->assertSee('Received excluding GST')->assertSee('Finalise allocation')->assertDontSee('name="outcomes_reviewed"', false);
        $this->get(route('admin.invoice.allocation.edit', $f['invoice']))->assertOk()->assertSee('Ticket allocation managed by workshop')->assertDontSee('Save allocation');
        $this->postJson(route('admin.invoice.allocation.store', $f['invoice']), ['targets' => [1 => 100]])->assertUnprocessable();
        $this->assertSame(0, app(FinanceAttention::class)->counts()['unallocated_invoices']);
        $this->finalise($f);
        $budget = DB::table('finance_budgets')->first();
        $this->assertTrue($service->isCurrent($budget));
        $this->assertSame(10000, app(FinancePlanner::class)->budgetReport($budget)['funding']['categories'][1]);
        $f['ticket']->update(['attended_at' => now()]);
        $this->assertSame('Allocation needs review', $service->state($f['workshop'])['status']);
        $this->assertFalse($service->isCurrent($budget));
        $this->assertSame(0, app(FinancePlanner::class)->budgetReport($budget)['funding']['categories'][1]);
        $this->assertSame($budget->targets, DB::table('finance_budgets')->value('targets'));
        $this->assertDatabaseCount('finance_budget_revisions', 1);
    }

    public function test_mixed_invoice_cash_is_split_and_pending_workshop_money_stays_reserved(): void
    {
        $f = $this->fixture(true);
        $parts = app(InvoiceAllocationParts::class);
        $this->assertSame(5000, $parts->income([$f['invoice']->id], null)['net']);
        $this->assertSame(10000, $parts->income([$f['invoice']->id], $f['workshop']->id)['net']);
        $context = app(InvoiceAllocation::class)->context($f['invoice']);
        $this->assertSame(5000, $context['total']);
        $this->postJson(route('admin.invoice.allocation.store', $f['invoice']), ['targets' => [1 => 50]])->assertOk();
        $this->assertSame(10000, app(FinancePlanner::class)->cash()['unallocated_income']);
        $this->finalise($f);
        $this->assertDatabaseCount('finance_budgets', 2);
        $this->assertDatabaseCount('finance_budget_invoices', 2);
        $this->assertSame(0, app(FinancePlanner::class)->cash()['unallocated_income']);
        $this->assertSame(15000, array_sum(app(FinancePlanner::class)->cash()['reserves']));
    }

    public function test_future_unpaid_and_stale_workshops_cannot_be_finalised(): void
    {
        $f = $this->fixture();
        $service = app(WorkshopAllocation::class);
        $hash = $service->state($f['workshop'])['hash'];
        $f['workshop']->update(['ends_at' => now()->addDay()]);
        $this->postJson(route('admin.workshop.allocation.store', $f['workshop']), ['source_hash' => $hash])->assertUnprocessable();
        $f['workshop']->update(['ends_at' => now()->subHour()]);
        $f['payment']->update(['payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER, 'cleared_at' => null]);
        $this->assertFalse($service->state($f['workshop'])['ready']);
        $this->postJson(route('admin.workshop.allocation.store', $f['workshop']), ['source_hash' => $hash])->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
    }

    public function test_partial_receipts_and_refunds_conserve_each_cent(): void
    {
        $f = $this->fixture(true);
        $f['payment']->update(['total_amount' => 82.5, 'gst_amount' => 7.5]);
        $f['payment']->allocations()->update(['allocated_amount' => 82.5]);
        $refund = Payment::factory()->create(['kind' => Payment::KIND_REFUND, 'refund_of_payment_id' => $f['payment']->id, 'total_amount' => 16.5, 'gst_amount' => 1.5, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $f['invoice']->id, 'payment_id' => $refund->id, 'allocated_amount' => -16.5]);
        $parts = app(InvoiceAllocationParts::class);
        $this->assertSame(2000, $parts->income([$f['invoice']->id], null)['net']);
        $this->assertSame(4000, $parts->income([$f['invoice']->id], $f['workshop']->id)['net']);
        foreach (range(1, 100) as $cents) {
            $this->assertSame($cents, $parts->portion($cents, ['a' => 2, 'b' => 1], 'a') + $parts->portion($cents, ['a' => 2, 'b' => 1], 'b'));
        }
    }

    public function test_ticket_refund_only_reduces_workshop_funding_and_preserves_product_income(): void
    {
        $f = $this->fixture(true);
        $adjustment = TaxAdjustment::create(['invoice_id' => $f['invoice']->id, 'adjustment_number' => 'ADJ-SCOPED', 'issue_date' => today(), 'subtotal_amount' => -100, 'gst_amount' => -10, 'total_amount' => -110]);
        $adjustment->lines()->create(['invoice_line_id' => $f['ticket']->invoice_line_id, 'line_number' => 1, 'description' => 'Ticket refund', 'quantity' => 1, 'unit_price_ex_tax' => -100, 'tax_rate' => 0.1, 'line_total_ex_tax' => -100, 'tax_amount' => -10, 'line_total_inc_tax' => -110]);
        $refund = Payment::factory()->create(['kind' => Payment::KIND_REFUND, 'refund_of_payment_id' => $f['payment']->id, 'total_amount' => 110, 'gst_amount' => 10, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $f['invoice']->id, 'tax_adjustment_id' => $adjustment->id, 'payment_id' => $refund->id, 'allocated_amount' => -110]);
        $parts = app(InvoiceAllocationParts::class);
        $this->assertSame(5000, $parts->income([$f['invoice']->id], null)['net']);
        $this->assertSame(0, $parts->income([$f['invoice']->id], $f['workshop']->id)['net']);
        $this->assertTrue(app(WorkshopAllocation::class)->state($f['workshop'])['ready']);
    }

    public function test_multiple_workshops_share_invoice_cash_without_duplicate_funding(): void
    {
        $f = $this->fixture(true);
        $workshop = $f['workshop']->replicate();
        $workshop->title = 'Second workshop';
        $workshop->save();
        $line = $f['invoice']->lines()->where('kind', 'product')->first();
        $line->update(['kind' => 'ticket', 'details_json' => ['workshop_id' => $workshop->id]]);
        Ticket::factory()->create(['workshop_id' => $workshop->id, 'invoice_id' => $f['invoice']->id, 'invoice_line_id' => $line->id]);
        $this->finalise($f);
        $service = app(WorkshopAllocation::class);
        $service->finalise($workshop, ['source_hash' => $service->state($workshop)['hash'], 'override' => true, 'targets' => [1 => 50]], $f['user']->id);
        $this->assertDatabaseCount('finance_budgets', 2);
        $this->assertSame(15000, array_sum(app(FinancePlanner::class)->cash()['reserves']));
        $this->assertSame(0, app(InvoiceAllocation::class)->context($f['invoice'])['total']);
    }

    public function test_written_off_outcome_can_be_finalised_and_refinalisation_keeps_revisions(): void
    {
        $f = $this->fixture();
        $f['payment']->update(['total_amount' => 55, 'gst_amount' => 5]);
        $f['payment']->allocations()->update(['allocated_amount' => 55]);
        $f['invoice']->update(['status' => Invoice::STATUS_WRITTEN_OFF]);
        $this->finalise($f);
        $this->assertSame(5000, app(FinancePlanner::class)->budgetReport(DB::table('finance_budgets')->first())['funding']['categories'][1]);
        $f['ticket']->update(['attended_at' => now()]);
        $budget = DB::table('finance_budgets')->first();
        $service = app(WorkshopAllocation::class);
        $this->post(route('admin.workshop.allocation.store', $f['workshop']), ['source_hash' => $service->state($f['workshop'])['hash'], 'revision' => hash('sha256', json_encode((array) $budget)), 'override' => 1, 'targets' => [1 => 50]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('finance_budget_revisions', 2);
        $this->assertTrue($service->isCurrent(DB::table('finance_budgets')->first()));
        $this->actingAs(User::factory()->create())->get(route('admin.workshop.allocation.edit', $f['workshop']))->assertForbidden();
        $this->post(route('admin.workshop.allocation.store', $f['workshop']), [])->assertForbidden();
    }
    public function test_review_notice_filters_the_workshop_list_and_supports_clearing(): void
    {
        $f = $this->fixture();
        $other = $f['workshop']->replicate();
        $other->title = 'Unrelated workshop';
        $other->save();
        $url = route('admin.workshop.index', ['view' => 'list', 'allocation_state' => 'needs_review']);
        $this->get(route('admin.workshop.index'))->assertOk()->assertSee($url)
            ->assertViewHas('workshops', fn ($rows) => $rows->total() === 2);
        $this->get($url)->assertOk()->assertSee('Workshop allocation: Ready for review')->assertSee('Clear filters')
            ->assertViewHas('workshops', fn ($rows) => $rows->pluck('id')->all() === [$f['workshop']->id]);
        $this->getJson($url.'&select_listing=1')->assertOk()->assertJsonPath('names', [$f['workshop']->id]);
        $this->get($url.'&search=Unrelated')->assertOk()->assertViewHas('workshops', fn ($rows) => $rows->isEmpty());
        $this->get(route('admin.workshop.allocations'))->assertRedirect($url);
        $this->getJson(route('admin.workshop.index', ['allocation_state' => 'invalid']))->assertUnprocessable();
        $this->finalise($f);
        $this->get($url)->assertOk()->assertViewHas('workshops', fn ($rows) => $rows->isEmpty());
        $this->get(route('admin.workshop.index'))->assertOk()->assertViewHas('workshops', fn ($rows) => $rows->total() === 2);
    }

}
