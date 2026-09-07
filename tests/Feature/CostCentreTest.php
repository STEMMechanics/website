<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CostCentreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);

        return $user;
    }

    public function test_zero_opening_counts_recorded_cash_and_expenses_without_setup(): void
    {
        $this->actingAs($this->admin());
        $expense = Expense::factory()->create(['supplier' => 'Hosting', 'total_amount' => 110, 'gst_amount' => 10, 'paid_on' => '2025-03-01']);
        Supplier::findOrFail($expense->supplier_id)->update(['category_id' => 5, 'splits' => [5 => 100]]);
        Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'gateway_status' => null, 'total_amount' => 220, 'gst_amount' => 20, 'received_on' => '2025-03-01']);
        Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'gateway_status' => 'FAILED', 'total_amount' => 330, 'gst_amount' => 30, 'received_on' => '2025-03-01']);
        $cash = app(FinancePlanner::class)->cash();
        $this->assertNull($cash['settings']->opening_date);
        $this->assertSame(11000, $cash['cash']);
        $this->assertSame(1000, $cash['gst']);
        $this->assertSame(-10000, $cash['reserves'][5]);
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertDontSee('Not set up')->assertDontSee('Set your opening balance');
        $this->get(route('admin.cost-centre.show', 5))->assertOk()->assertSee('100.00')->assertDontSee('Not set up');
        $this->get(route('admin.cost-centre.allocations'))->assertOk()->assertSee('Allocation Plans')->assertDontSee('Preview allocations');
        $this->get(route('admin.finance.index', ['tab' => 'budgets']))->assertRedirect(route('admin.cost-centre.allocations', ['tab' => 'allocations']));
    }

    public function test_directory_balances_popups_archive_and_system_protection(): void
    {
        $this->actingAs($this->admin());
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 100000, 'opening_gst_cents' => 10000]);
        DB::table('finance_categories')->where('id', 1)->update(['opening_cents' => 50000]);
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertSee('500.00')->assertSee('GST')->assertSee('System')->assertSee('data-record-editor', false)->assertSee('Rows per page');
        $this->postJson(route('admin.cost-centre.store'), ['name' => 'Training', 'priority' => 80, 'active' => 1])->assertOk();
        $id = DB::table('finance_categories')->where('name', 'Training')->value('id');
        $this->get(route('admin.cost-centre.edit', ['id' => $id]))->assertOk();
        $this->postJson(route('admin.cost-centre.store'), ['id' => $id, 'name' => 'Training', 'priority' => 80, 'active' => 0])->assertOk();
        $this->get(route('admin.cost-centre.index', ['state' => 'archived']))->assertOk()->assertSee('Training')->assertDontSee('Venue hire');
        $this->postJson(route('admin.cost-centre.store'), ['id' => 6, 'name' => 'Wages', 'priority' => 60, 'active' => 0])->assertUnprocessable();
        $this->postJson(route('admin.finance.category'), ['id' => 6, 'name' => 'Wages', 'priority' => 60, 'active' => 0])->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.store'), ['name' => 'GST', 'priority' => 1, 'active' => 1])->assertUnprocessable();
        $this->get(route('admin.cost-centre.show', 99999))->assertNotFound();
        $this->get(route('admin.finance.index', ['tab' => 'gst']))->assertRedirect(route('admin.cost-centre.gst'));
        $this->get(route('admin.finance.index', ['tab' => 'pricing']))->assertDontSee('Categories and funding order')->assertDontSee('New category');
        $this->get(route('admin.finance.index'))->assertDontSee('Category reserves')->assertDontSee('Move money between funds');
    }

    public function test_related_expenses_and_invoice_funding_use_real_allocations(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $expense = Expense::factory()->create(['supplier' => 'Parts vendor', 'description' => 'Electronics parts', 'total_amount' => 110, 'gst_amount' => 10, 'paid_on' => '2026-09-02']);
        Supplier::where('id', $expense->supplier_id)->update(['category_id' => 2, 'splits' => [2 => 100]]);
        Expense::factory()->create(['supplier' => 'Other vendor', 'description' => 'Unrelated purchase']);
        $this->get(route('admin.cost-centre.show', 2))->assertOk()->assertSee('Electronics parts')->assertSee('100.00')->assertDontSee('Unrelated purchase');
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10, 'issue_date' => '2026-09-01']);
        $payment = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => 110, 'gst_amount' => 10, 'received_on' => '2026-09-02']);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 110]);
        $planner = app(FinancePlanner::class);
        $preview = $planner->preview(['version_id' => 1, 'invoice_ids' => [$invoice->id], 'participants' => 4, 'hours' => 2, 'travel_minutes' => 0, 'venue_supplied' => false]);
        $planner->apply($preview, $user->id, [0]);
        $response = $this->get(route('admin.cost-centre.show', ['centre' => 1, 'tab' => 'allocations']))->assertOk()->assertSee($invoice->invoice_number)->assertSee('Running balance');
        $this->assertSame(9000, $response->viewData('records')->items()[0]['amount']);
        $this->get(route('admin.cost-centre.gst', ['month' => '2026-09', 'tab' => 'income']))->assertOk()->assertSee($invoice->invoice_number);
        $this->get(route('admin.cost-centre.gst', ['month' => '2026-09', 'tab' => 'expenses']))->assertOk()->assertSee('Electronics parts');
        $this->get(route('admin.cost-centre.gst'))->assertOk()->assertSee('Record a GST settlement');
        $filtered = $this->get(route('admin.cost-centre.show', ['centre' => 2, 'list_amount_display_max' => -101]))->assertOk();
        $this->assertSame(0, $filtered->viewData('records')->total());
        $this->get(route('admin.cost-centre.show', ['centre' => 2, 'list_date_min' => '2026-09-02', 'list_sort' => 'amount_display']))->assertOk()->assertSee('Electronics parts');

    }

    public function test_ledger_balances_survive_type_filters_and_refunds(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $payment = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => 55, 'gst_amount' => 5, 'received_on' => '2026-09-01']);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 55]);
        $second = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => 55, 'gst_amount' => 5, 'received_on' => '2026-09-02']);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $second->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 55]);
        Payment::factory()->create(['kind' => 'refund', 'payment_method' => 'cash', 'refund_of_payment_id' => $payment->id, 'total_amount' => 22, 'gst_amount' => 2, 'received_on' => '2026-09-04']);
        $planner = app(FinancePlanner::class);
        $preview = $planner->preview(['version_id' => 1, 'invoice_ids' => [$invoice->id], 'participants' => 4, 'hours' => 2, 'travel_minutes' => 0, 'venue_supplied' => false]);
        $planner->apply($preview, $user->id, [0]);
        $expense = Expense::factory()->create(['supplier' => 'Ledger vendor', 'total_amount' => 11, 'gst_amount' => 1, 'paid_on' => '2026-09-03']);
        Supplier::findOrFail($expense->supplier_id)->update(['category_id' => 1, 'splits' => [1 => 100]]);
        $response = $this->get(route('admin.cost-centre.show', 1))->assertOk()->assertDontSee('Records include all history.');
        $rows = $response->viewData('records')->items();
        $this->assertSame(['refund', 'expense', 'invoice', 'invoice'], array_column($rows, 'type'));
        $this->assertSame([7000, 8000, 9000, 5000], array_column($rows, 'balance'));
        $this->assertSame($planner->cash()['reserves'][1], $rows[0]['balance']);
        $filtered = $this->get(route('admin.cost-centre.show', ['centre' => 1, 'list_type' => 'expense']))->assertOk()->viewData('records');
        $this->assertSame(1, $filtered->total());
        $this->assertSame(8000, $filtered->items()[0]['balance']);
    }

    public function test_transfers_preserve_history_and_reject_protected_or_unfunded_moves(): void
    {
        $this->actingAs($this->admin());
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 10000]);
        DB::table('finance_categories')->where('id', 1)->update(['opening_cents' => 5000]);
        $data = ['from_category_id' => 1, 'category_id' => 2, 'amount' => 20, 'reason' => 'Cover consumables'];
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertOk();
        $cash = app(FinancePlanner::class)->cash();
        $this->assertSame(3000, $cash['reserves'][1]);
        $this->assertSame(2000, $cash['reserves'][2]);
        $this->get(route('admin.cost-centre.show', ['centre' => 1, 'tab' => 'transfers']))->assertOk()->assertSee('Cover consumables');
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['amount' => 100]))->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['from_category_id' => 6]))->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['category_id' => 6]))->assertUnprocessable();
        $this->get(route('admin.cost-centre.transfer.edit', ['from' => 1]))->assertOk();
        $this->assertDatabaseCount('finance_fund_transfers', 1);
    }

    public function test_archiving_blocks_live_dependencies_and_non_admin_access(): void
    {
        $this->actingAs($this->admin());
        $this->postJson(route('admin.cost-centre.store'), ['id' => 1, 'name' => 'Venue hire', 'priority' => 10, 'active' => 0])->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.store'), ['name' => 'Supplier reserve', 'priority' => 80, 'active' => 1])->assertOk();
        $id = DB::table('finance_categories')->where('name', 'Supplier reserve')->value('id');
        Supplier::create(['name' => 'Vendor', 'supplier' => 'vendor', 'category_id' => $id, 'splits' => [$id => 100], 'mode' => 'default']);
        $this->postJson(route('admin.cost-centre.store'), ['id' => $id, 'name' => 'Supplier reserve', 'priority' => 80, 'active' => 0])->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        foreach (['admin.cost-centre.index', 'admin.cost-centre.edit', 'admin.cost-centre.gst', 'admin.cost-centre.transfer.edit'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->postJson(route('admin.cost-centre.store'), ['name' => 'Bad', 'priority' => 80, 'active' => 1])->assertForbidden();
        $this->postJson(route('admin.cost-centre.transfer'), ['category_id' => 1, 'amount' => 1, 'reason' => 'Bad'])->assertForbidden();
    }
}
