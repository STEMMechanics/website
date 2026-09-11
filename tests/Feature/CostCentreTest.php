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
use Illuminate\Support\Carbon;
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

    public function test_owner_contributions_are_a_conditional_system_liability_with_business_wide_history(): void
    {
        $this->actingAs($owner = $this->admin());
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertViewHas('centres', fn ($rows) => ! $rows->contains('id', 'contributions'));
        $otherOwner = User::factory()->create();
        foreach ([$owner->id => 900000, $otherOwner->id => 50000] as $userId => $cents) {
            DB::table('finance_owner_contributions')->insert([
                'user_id' => $userId, 'token' => (string) \Illuminate\Support\Str::uuid(), 'date' => today()->toDateString(),
                'cents' => $cents, 'reference' => 'Personal funding '.$userId, 'splits' => json_encode([5 => $cents]),
            ]);
        }
        $repaymentId = DB::table('finance_drawings')->insertGetId([
            'user_id' => $owner->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'purpose' => 'contribution',
            'status' => 'pending', 'cents' => 200000, 'reference' => 'Capital repayment', 'created_at' => now(),
        ]);
        foreach ([[], ['state' => 'active'], ['list_sort' => 'balance', 'list_direction' => 'desc']] as $filters) {
            $this->get(route('admin.cost-centre.index', $filters))->assertOk()
                ->assertSee('Owner contributions')->assertSee('-$9,500.00')
                ->assertSee(route('admin.cost-centre.contributions'), false)
                ->assertViewHas('centres', fn ($rows) => $rows[0]->id === 'cash' && $rows[0]->balance === app(FinancePlanner::class)->cash()['available'] && $rows[1]->id === 'contributions' && $rows[1]->priority === null && $rows[2]->id === 'gst');
        }
        $this->get(route('admin.cost-centre.index', ['state' => 'archived']))->assertOk()->assertViewHas('centres', fn ($rows) => ! $rows->contains('id', 'contributions'));
        $this->get(route('admin.cost-centre.contributions'))->assertOk()->assertSee($otherOwner->getName())->assertSee('Capital repayment')->assertViewHas('balance', -950000);
        DB::table('finance_drawings')->where('id', $repaymentId)->update(['status' => 'paid', 'paid_on' => today()->toDateString()]);
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertSee('-$7,500.00');
        $this->get(route('admin.cost-centre.contributions'))->assertOk()->assertViewHas('balance', -750000);
        DB::table('finance_drawings')->where('id', $repaymentId)->update(['cents' => 950000]);
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertViewHas('centres', fn ($rows) => ! $rows->contains('id', 'contributions'));
        $this->actingAs(User::factory()->create())->get(route('admin.cost-centre.contributions'))->assertForbidden();
    }

    public function test_remuneration_can_cover_a_deficit_without_a_cash_payment_and_cannot_be_drawn_again(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 50000]);
        DB::table('finance_categories')->where('id', 1)->update(['opening_cents' => -8000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 20000]);
        $this->post(route('admin.finance.time'), ['date' => today()->toDateString(), 'activity' => 'Preparation', 'minutes' => 120, 'rate' => 100])->assertSessionHasNoErrors();
        $this->post(route('admin.finance.drawing'), ['amount' => 100, 'token' => (string) \Illuminate\Support\Str::uuid()])->assertSessionHasNoErrors();
        $planner = app(FinancePlanner::class);
        $before = $planner->cash();
        $data = ['from_category_id' => 'remuneration', 'category_id' => 1, 'amount' => 80, 'reason' => 'Forgo pay to cover venue deficit', 'token' => (string) \Illuminate\Support\Str::uuid()];
        $this->get(route('admin.cost-centre.transfer.edit', ['from' => 'remuneration']))->assertOk()->assertSee('Owner remuneration')->assertSee('100.00');
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertOk();
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertOk();
        $this->assertDatabaseCount('finance_fund_transfers', 1);
        $this->assertDatabaseHas('finance_fund_transfers', ['remuneration_user_id' => $user->id, 'created_by' => $user->id, 'cents' => 8000, 'category_id' => 1]);
        $this->assertSame(8000, $planner->remunerationForgone($user->id));
        $this->assertSame(2000, $planner->remunerationAvailable($user->id));
        $this->assertSame(0, $planner->cash()['reserves'][1]);
        $this->assertSame(12000, $planner->cash()['reserves'][6]);
        $this->assertSame($before['cash'], $planner->cash()['cash']);
        $this->assertSame($before['gst'], $planner->cash()['gst']);
        $this->assertDatabaseCount('finance_drawings', 1);
        $this->get(route('admin.cost-centre.show', 1))->assertOk()->assertSee('Remuneration forgone by')->assertSee($data['reason']);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()->assertSee($data['reason'])->assertViewHas('drawingTotals', fn ($totals) => $totals['time']['outstanding'] === 12000 && $totals['time']['available'] === 2000);
        $this->post(route('admin.finance.drawing'), ['amount' => 21, 'token' => (string) \Illuminate\Support\Str::uuid()])->assertSessionHasErrors('amount');
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['amount' => 21, 'token' => (string) \Illuminate\Support\Str::uuid()]))->assertUnprocessable();
        $this->post(route('admin.finance.time'), ['id' => DB::table('finance_time_entries')->value('id'), 'date' => today()->toDateString(), 'activity' => 'Preparation', 'minutes' => 30, 'rate' => 100])->assertSessionHasErrors('rate');
        $this->assertSame(20000, $planner->earned($user->id));
        $this->actingAs($this->admin())->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['token' => (string) \Illuminate\Support\Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('finance_fund_transfers', 1);
    }

    public function test_remuneration_transfers_require_funding_a_token_and_an_active_cost_centre(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.finance.time'), ['date' => today()->toDateString(), 'activity' => 'Preparation', 'minutes' => 60, 'rate' => 100])->assertSessionHasNoErrors();
        $data = ['from_category_id' => 'remuneration', 'category_id' => 1, 'amount' => 1, 'reason' => 'Cover deficit', 'token' => (string) \Illuminate\Support\Str::uuid()];
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertUnprocessable();
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 10000]);
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['token' => null]))->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.transfer'), array_merge($data, ['category_id' => 6]))->assertUnprocessable();
        DB::table('finance_categories')->where('id', 1)->update(['active' => false]);
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('finance_fund_transfers', 0);
    }

    public function test_allocated_remuneration_can_be_transferred_without_timesheets(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 320550]);
        DB::table('finance_categories')->where('id', 2)->update(['opening_cents' => -493743]);
        $planner = app(FinancePlanner::class);
        $before = $planner->cash();
        $this->assertSame(0, $planner->earned($user->id));
        $this->get(route('admin.cost-centre.transfer.edit', ['from' => 'remuneration']))->assertOk()->assertSee('3,205.50 available to forgo');
        $data = ['from_category_id' => 'remuneration', 'category_id' => 2, 'amount' => 3205.50, 'reason' => 'Cover consumables', 'token' => (string) \Illuminate\Support\Str::uuid()];
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertOk();
        $this->postJson(route('admin.cost-centre.transfer'), $data)->assertOk();
        $this->assertDatabaseCount('finance_fund_transfers', 1);
        $this->assertSame(0, $planner->cash()['reserves'][6]);
        $this->assertSame(-173193, $planner->cash()['reserves'][2]);
        $this->assertSame($before['cash'], $planner->cash()['cash']);
        $this->assertSame($before['gst'], $planner->cash()['gst']);
        $this->assertSame(320550, $planner->remunerationForgone($user->id));
        $this->assertSame(0, $planner->remunerationTransferAvailable());
        $this->assertDatabaseCount('finance_drawings', 0);
        // Forgoing allocated funds must not prevent recording subsequent time.
        $this->post(route('admin.finance.time'), ['date' => today()->toDateString(), 'activity' => 'Preparation', 'minutes' => 60, 'rate' => 100])->assertSessionHasNoErrors();
        $this->assertSame(10000, $planner->earned($user->id));
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()->assertViewHas('drawingTotals', fn ($totals) => $totals['time']['outstanding'] === 0);
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

    public function test_gst_history_groups_received_cash_and_credits_by_month_and_retains_settlement_amounts(): void
    {
        $this->actingAs($this->admin());
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $payment = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => 110, 'gst_amount' => 10, 'received_on' => '2026-08-02']);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 110]);
        Payment::factory()->create(['kind' => 'refund', 'payment_method' => 'cash', 'refund_of_payment_id' => $payment->id, 'total_amount' => 22, 'gst_amount' => 2, 'received_on' => '2026-09-02']);
        Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'bank_transfer', 'gateway_status' => 'PENDING', 'total_amount' => 110, 'gst_amount' => 10, 'received_on' => '2026-08-03']);
        Expense::factory()->create(['total_amount' => 33, 'gst_amount' => 3, 'paid_on' => '2026-08-04']);
        Expense::factory()->create(['total_amount' => 22, 'gst_amount' => 2, 'paid_on' => '2026-09-04']);
        Expense::factory()->create(['total_amount' => 55, 'gst_amount' => 5, 'paid_on' => null]);
        DB::table('finance_gst_settlements')->insert(['period' => '2026-08-01', 'paid_on' => '2026-09-01', 'cents' => 699, 'reference' => 'myGov card receipt']);
        $response = $this->get(route('admin.cost-centre.gst', ['month' => '2026-09']))->assertOk()->assertSee('BAS / GST history')->assertSee('myGov card receipt');
        $history = $response->viewData('history');
        $this->assertCount(12, $history);
        $this->assertSame(-400, $history[0]['net']);
        $this->assertSame(1000, $history[1]['sales']);
        $this->assertSame(300, $history[1]['credits']);
        $this->assertSame(700, $history[1]['net']);
        $this->assertSame(699, (int) $history[1]['settlement']->cents);
        $this->assertSame(0, $history[2]['net']);
        $this->assertNull($history[2]['settlement']);
        $this->assertSame('2025-10', $history[11]['month']->format('Y-m'));
        $this->get(route('admin.cost-centre.gst', ['month' => '2025-09']))->assertOk()->assertViewHas('historyStart', fn ($date) => $date->format('Y-m') === '2024-10');
    }

    public function test_gst_settlement_reference_is_optional_and_returns_to_the_recorded_month(): void
    {
        $this->actingAs($this->admin());
        $this->travelTo(Carbon::parse('2026-09-08'));
        $data = ['period' => '2026-08', 'paid_on' => '2026-09-08', 'amount' => '-229'];
        $this->post(route('admin.finance.settlement'), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.cost-centre.gst', ['month' => '2026-08']));
        $this->assertDatabaseHas('finance_gst_settlements', ['period' => '2026-08-01', 'cents' => -22900, 'reference' => '']);
        $this->post(route('admin.finance.settlement'), $data)->assertSessionHasErrors('period');
        $this->get(route('admin.cost-centre.gst', ['month' => '2026-08']))->assertOk()->assertSee('Receipt / note (optional)')->assertSee('-$229.00');
        $this->travelBack();
    }
    public function test_gst_history_popup_loads_the_month_and_updates_its_existing_settlement(): void
    {
        $this->actingAs($this->admin());
        $this->travelTo(Carbon::parse('2026-09-08'));
        $url = route('admin.cost-centre.gst', ['month' => '2026-08']);
        $this->get($url)->assertOk()->assertSee('colspan="3"', false)->assertSee('Not recorded')->assertSee('Edit GST settlement');
        $this->get($url, ['X-SM-Fragment' => 'record'])->assertOk()->assertSee('data-record-form', false)->assertSee('value="2026-08"', false)->assertDontSee('settlement_id');
        $data = ['period' => '2026-08', 'paid_on' => '2026-09-08', 'amount' => 150, 'reference' => 'Receipt 123'];
        $this->postJson(route('admin.finance.settlement'), $data)->assertOk();
        $id = DB::table('finance_gst_settlements')->value('id');
        $this->get($url, ['X-SM-Fragment' => 'record'])->assertOk()->assertSee('value="150.00"', false)->assertSee('Receipt 123')->assertSee('Save settlement');
        $this->postJson(route('admin.finance.settlement'), array_replace($data, ['settlement_id' => $id, 'amount' => -20, 'reference' => 'Corrected']))->assertOk();
        $this->assertDatabaseCount('finance_gst_settlements', 1);
        $this->assertDatabaseHas('finance_gst_settlements', ['id' => $id, 'period' => '2026-08-01', 'cents' => -2000, 'reference' => 'Corrected']);
        $this->postJson(route('admin.finance.settlement'), array_replace($data, ['settlement_id' => $id, 'period' => '2026-07']))->assertUnprocessable();
        $this->travelBack();
    }

}
