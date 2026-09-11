<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancePlanningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::query()->create(['user_id' => $user->id, 'slug' => 'admin']);

        return $user;
    }

    private function invoice(float $amount = 110): Invoice
    {
        $invoice = Invoice::factory()->create(['total_amount' => $amount, 'gst_amount' => round($amount / 11, 2), 'issue_date' => '2026-09-01']);
        $payment = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'total_amount' => $amount, 'gst_amount' => round($amount / 11, 2), 'received_on' => '2026-09-02']);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => $amount]);

        return $invoice;
    }

    private function preview(Invoice $invoice): array
    {
        return app(FinancePlanner::class)->preview(['version_id' => 1, 'invoice_ids' => [$invoice->id], 'participants' => 4, 'hours' => 2, 'travel_minutes' => 45, 'venue_supplied' => false]);
    }

    public function test_finance_site_settings_validate_dates_and_control_available_drawings(): void
    {
        $this->actingAs($this->admin());
        $buffer = \App\Models\SiteOption::where('name', 'finance.cash-buffer')->firstOrFail();
        $anchor = \App\Models\SiteOption::where('name', 'finance.fortnight-start')->firstOrFail();
        $this->putJson(route('admin.site_option.update', $buffer), ['value' => '-1'])->assertUnprocessable();
        $this->putJson(route('admin.site_option.update', $anchor), ['value' => 'invalid'])->assertUnprocessable();
        $this->putJson(route('admin.site_option.update', $anchor), ['value' => '2026-09-13'])->assertOk();
        $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-14']))->assertOk()->assertViewHas('fortnight', fn ($date) => $date->toDateString() === '2026-09-13');
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 10000]);
        $before = app(FinancePlanner::class)->cash()['available'];
        $this->putJson(route('admin.site_option.update', $buffer), ['value' => '10.50'])->assertOk();
        $this->assertSame($before - 1050, app(FinancePlanner::class)->cash()['available']);
    }

    public function test_all_finance_sections_render_and_non_admin_is_denied(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get(route('admin.finance.index'))->assertRedirect(route('admin.cost-centre.index'));
        $this->get(route('admin.finance.index', ['tab' => 'pricing']))->assertRedirect(route('admin.cost-centre.allocations'));
        $this->get(route('admin.finance.index', ['tab' => 'setup']))->assertRedirect(route('admin.site_option.index', ['search' => 'finance.']));
        $this->actingAs(User::factory()->create())->get(route('admin.finance.index'))->assertForbidden();
        $this->post(route('admin.finance.drawing'), ['amount' => 1, 'token' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_pricing_does_not_calculate_unrelated_finance_reports(): void
    {
        $this->partialMock(FinancePlanner::class, function ($mock): void {
            $mock->shouldNotReceive('gst');
            $mock->shouldNotReceive('cash');
            $mock->shouldNotReceive('earned');
        });
        $this->actingAs($this->admin())->get(route('admin.finance.index', ['tab' => 'pricing']))->assertRedirect(route('admin.cost-centre.allocations'));
    }

    public function test_preview_is_read_only_and_apply_is_idempotent_with_reversal_history(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $user = $this->admin();
        $invoice = $this->invoice();
        $planner = app(FinancePlanner::class);
        $preview = $this->preview($invoice);
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->assertSame(10000, $preview['rows'][0]['income']['net']);
        // Venue 90 + consumables 20 + vehicle 19 + insurance 17 + operations 10 + time 135 + equipment 7.50.
        $this->assertSame(29850, array_sum($preview['rows'][0]['targets']));
        $id = $planner->apply($preview, $user->id, [0]);
        $this->assertSame($id, $planner->apply($preview, $user->id, [0]));
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertSame('110.00', $invoice->fresh()->total_amount);
        $this->assertNotNull($this->preview($invoice)['rows'][0]['warning']);
        $planner->reverse($id);
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->assertNotNull(DB::table('finance_batches')->where('id', $id)->value('reversed_at'));
    }

    public function test_partial_payment_shared_across_invoices_and_refund_are_proportioned(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $first = $this->invoice();
        $payment = Payment::query()->first();
        $second = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        InvoicePaymentAllocation::query()->where('payment_id', $payment->id)->update(['allocated_amount' => 55]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $second->id, 'allocated_amount' => 55]);
        Payment::factory()->create(['kind' => 'refund', 'payment_method' => 'cash', 'refund_of_payment_id' => $payment->id, 'total_amount' => 22, 'gst_amount' => 2, 'received_on' => '2026-09-03']);
        $planner = app(FinancePlanner::class);
        $this->assertSame(['gross' => 4400, 'gst' => 400, 'net' => 4000], $planner->income([$first->id]));
        $this->assertSame(8000, $planner->income([$first->id, $second->id])['net']);
    }

    public function test_category_priority_preserves_shortfall_and_surplus(): void
    {
        $planner = app(FinancePlanner::class);
        $result = $planner->funding([1 => 5000, 2 => 2000, 6 => 6000], 6000);
        $this->assertSame(5000, $result['categories'][1]);
        $this->assertSame(1000, $result['categories'][2]);
        $this->assertSame(0, $result['categories'][6]);
        $this->assertSame(7000, $result['shortfall']);
        $this->assertSame(2000, $planner->funding([1 => 5000], 7000)['surplus']);
    }

    public function test_supplier_defaults_round_to_exact_expense_and_manual_splits_win(): void
    {
        $user = $this->admin();
        $planner = app(FinancePlanner::class);
        $expense = Expense::factory()->create(['supplier' => 'Mixed Supplier', 'total_amount' => 11, 'gst_amount' => 1]);
        $this->actingAs($user);
        DB::table('finance_supplier_rules')->where('id', $expense->supplier_id)->update(['mode' => 'split', 'splits' => json_encode([1 => 33.33, 2 => 66.67])]);
        $this->assertSame([1 => 333, 2 => 667], $planner->expenseSplits($expense));
        $this->post(route('admin.expense.allocation', $expense), ['splits' => [1 => 10]])->assertSessionHasNoErrors();
        $this->assertSame([1 => 1000], $planner->expenseSplits($expense));
        $this->post(route('admin.expense.allocation', $expense), ['splits' => [1 => 11]])->assertSessionHasErrors('splits');
    }

    public function test_historical_allocations_do_not_recreate_available_cash(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $user = $this->admin();
        $invoice = $this->invoice();
        $planner = app(FinancePlanner::class);
        $planner->apply($this->preview($invoice), $user->id, [0]);
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-04', 'opening_cash_cents' => 5000, 'opening_gst_cents' => 1000]);
        $cash = $planner->cash();
        $this->assertSame(5000, $cash['cash']);
        $this->assertSame(4000, $cash['available']);
        $this->assertSame(0, array_sum($cash['reserves']));
    }

    public function test_drawings_respect_cash_gst_buffer_and_remuneration_reserve(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $user = $this->admin();
        $this->actingAs($user)->post(route('admin.finance.time'), ['date' => '2026-09-05', 'activity' => 'Preparation', 'minutes' => 120, 'rate' => 60])->assertSessionHasNoErrors();
        $this->post(route('admin.finance.drawing'), ['amount' => 10, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 10000, 'opening_gst_cents' => 2000, 'buffer_cents' => 1000]);
        \App\Models\SiteOption::updateOrCreate(['name' => 'finance.cash-buffer'], ['value' => '10.00']);
        $this->post(route('admin.finance.drawing'), ['amount' => 80, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 7000]);
        $this->post(route('admin.finance.drawing'), ['amount' => 80, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $token = (string) Str::uuid();
        $this->post(route('admin.finance.drawing'), ['amount' => 70, 'token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('admin.timesheet.index', ['tab' => 'drawings']));
        $this->post(route('admin.finance.drawing'), ['amount' => 70, 'token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('admin.timesheet.index', ['tab' => 'drawings']));
        $this->assertDatabaseCount('finance_drawings', 1);
        $this->assertSame(0, app(FinancePlanner::class)->cash()['available']);
        $id = DB::table('finance_drawings')->value('id');
        $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'paid', 'paid_on' => '2026-09-06', 'reference' => 'Bank 123'])->assertSessionHasNoErrors();
        $this->assertSame(3000, app(FinancePlanner::class)->cash()['cash']);
        $this->assertSame(0, app(FinancePlanner::class)->cash()['reserves'][6]);
        $this->assertSame(12000, app(FinancePlanner::class)->earned($user->id));
        $other = $this->admin();
        $this->actingAs($other)->post(route('admin.finance.drawingStatus', $id), ['status' => 'cancelled'])->assertNotFound();
    }

    public function test_gst_settlements_reduce_cash_and_liability_only_once(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $user = $this->admin();
        $planner = app(FinancePlanner::class);
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 10000, 'opening_gst_cents' => 2000]);
        $data = ['period' => '2026-08', 'paid_on' => '2026-09-02', 'amount' => 20, 'reference' => 'ATO'];
        $this->actingAs($user)->post(route('admin.finance.settlement'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.finance.settlement'), $data)->assertSessionHasErrors('period');
        $this->assertSame(8000, $planner->cash()['cash']);
        $this->assertSame(0, $planner->cash()['gst']);
    }

    public function test_workshop_ticket_allocations_wait_for_explicit_finalisation(): void
    {
        $admin = $this->admin();
        $invoice = $this->invoice();
        Ticket::factory()->create(['invoice_id' => $invoice->id]);
        DB::table('finance_settings')->where('id', 1)->update(['auto_budget' => true]);
        $this->assertSame(0, app(FinancePlanner::class)->automate());
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->assertSame(10000, app(FinancePlanner::class)->cash()['unallocated_income']);
    }

    public function test_bulk_preview_routes_are_removed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.finance.preview'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.finance.apply'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.finance.reverse'));
    }

    public function test_pending_bank_transfers_do_not_fund_budgets_or_drawings(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $invoice = $this->invoice();
        Payment::query()->update(['payment_method' => 'bank_transfer', 'cleared_at' => null]);
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01']);
        $planner = app(FinancePlanner::class);
        $this->assertSame(0, $planner->income([$invoice->id])['net']);
        $this->assertSame(0, $planner->cash()['cash']);
        $this->assertSame(0, $planner->gst('2026-09-01', '2026-09-06')['net']);
    }

    public function test_legacy_commitments_do_not_reserve_cash_or_limit_transfers(): void
    {
        $admin = $this->admin();
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 20000]);
        DB::table('finance_categories')->where('id', 1)->update(['opening_cents' => 5000]);
        DB::table('finance_commitments')->insert(['category_id' => 1, 'description' => 'Legacy booking', 'due_on' => '2026-09-07', 'cents' => 7000, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $planner = app(FinancePlanner::class);
        $this->assertSame(15000, $planner->cash()['available']);
        $planner->transfer(['from_category_id' => 1, 'category_id' => 2, 'amount' => 50, 'reason' => 'Move balance'], $admin->id);
        $this->assertSame(0, $planner->cash()['reserves'][1]);
        $this->assertSame(5000, $planner->cash()['reserves'][2]);
        $this->actingAs($admin)->get(route('admin.cost-centre.index'))->assertOk()->assertDontSee('Committed');
        $this->get(route('admin.finance.index', ['tab' => 'commitments']))->assertRedirect(route('admin.finance.index'));
        $this->post('/admin/finance/commitments', [])->assertStatus(405);
    }

    public function test_pricing_versions_preserve_old_rates_and_reject_invalid_categories(): void
    {
        $admin = $this->admin();
        $data = ['name' => 'Older rates', 'effective_from' => '2025-07-01', 'rules' => [['category_id' => 1, 'basis' => 'workshop', 'rate' => 20]], 'public' => [10, 20, 30, 40], 'organisation' => [8, 16, 24, 32]];
        $this->actingAs($admin)->post(route('admin.finance.pricing'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('finance_pricing_versions', 3);
        $this->assertSame(4500, app(FinancePlanner::class)->decode(DB::table('finance_pricing_versions')->where('id', 1)->value('rules'))[0]['rate_cents']);
        $data['rules'][0]['category_id'] = 9999;
        $this->post(route('admin.finance.pricing'), $data)->assertSessionHasErrors('rules.0.category_id');
    }

    public function test_unallocated_income_is_not_available_for_drawings_and_fund_transfers_are_audited(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $admin = $this->admin();
        $this->invoice();
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => '2026-09-01', 'opening_cash_cents' => 5000]);
        $planner = app(FinancePlanner::class);
        $this->assertSame(10000, $planner->cash()['unallocated_income']);
        $this->assertSame(5000, $planner->cash()['available']);
        $this->actingAs($admin)->post(route('admin.finance.transfer'), ['category_id' => 1, 'amount' => 30, 'reason' => 'Cover venue shortfall'])->assertSessionHasNoErrors();
        $this->assertSame(2000, $planner->cash()['available']);
        $this->assertDatabaseHas('finance_fund_transfers', ['created_by' => $admin->id, 'reason' => 'Cover venue shortfall', 'cents' => 3000]);
        $this->post(route('admin.finance.transfer'), ['from_category_id' => 1, 'category_id' => 2, 'amount' => 40, 'reason' => 'Too much'])->assertSessionHasErrors('amount');
    }

    public function test_rounding_never_creates_money_and_stale_expense_splits_are_flagged(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 6));
        $admin = $this->admin();
        $planner = app(FinancePlanner::class);
        $expense = Expense::factory()->create(['supplier' => 'Tiny amounts', 'total_amount' => 0.02, 'gst_amount' => 0]);
        $this->actingAs($admin);
        DB::table('finance_supplier_rules')->where('id', $expense->supplier_id)->update(['mode' => 'split', 'splits' => json_encode([1 => 25, 2 => 25, 3 => 25, 4 => 25])]);
        $this->assertSame(2, array_sum($planner->expenseSplits($expense)));
        $this->assertGreaterThanOrEqual(0, min($planner->expenseSplits($expense)));
        $this->post(route('admin.expense.allocation', $expense), ['splits' => [1 => 0.02]])->assertSessionHasNoErrors();
        $expense->update(['total_amount' => 0.03]);
        $this->assertSame([], $planner->expenseSplits($expense));
        $first = $this->invoice(0.02);
        $payment = Payment::query()->first();
        $second = Invoice::factory()->create(['total_amount' => 0.01, 'gst_amount' => 0]);
        InvoicePaymentAllocation::query()->where('payment_id', $payment->id)->update(['allocated_amount' => 0.01]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $second->id, 'allocated_amount' => 0.01]);
        Payment::factory()->create(['kind' => 'refund', 'payment_method' => 'cash', 'refund_of_payment_id' => $payment->id, 'total_amount' => 0.01, 'gst_amount' => 0, 'received_on' => '2026-09-03']);
        $this->assertSame(1, $planner->income([$first->id])['net'] + $planner->income([$second->id])['net']);
    }

    public function test_flat_venue_rate_covers_each_hour_and_replaces_unused_legacy_rules(): void
    {
        $planner = app(FinancePlanner::class);
        $latest = DB::table('finance_pricing_versions')->orderByDesc('id')->first();
        $rules = $planner->decode($latest->rules);
        $venue = array_values(array_filter($rules, fn ($rule) => $rule['basis'] === 'venue_hour'));
        $this->assertSame(4500, $venue[0]['rate_cents']);
        $this->assertArrayNotHasKey('extra_cents', $venue[0]);
        $inputs = ['participants' => 0, 'hours' => 1, 'travel_minutes' => 0, 'venue_supplied' => false];
        foreach ([1, 2, 3, 4, 1.5] as $hours) {
            $inputs['hours'] = $hours;
            $this->assertSame((int) ($hours * 4500), $planner->targets($venue, $inputs)[1]);
        }
        $inputs['venue_supplied'] = true;
        $this->assertSame(0, $planner->targets($venue, $inputs)[1]);
        $old = $planner->decode(DB::table('finance_pricing_versions')->where('id', 1)->value('rules'));
        $inputs['venue_supplied'] = false;
        $inputs['hours'] = 3;
        $this->assertSame(13500, $planner->targets($old, $inputs)[1]);
        $this->actingAs($this->admin())->get(route('admin.cost-centre.allocations', ['tab' => 'editor']))->assertOk()->assertSee('Per delivery hour')->assertSee('Suppliable')->assertDontSee('Additional venue hour')->assertDontSee('Venue: first hour');
    }
}
