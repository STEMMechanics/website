<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BulkAllocationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    private function preview(string $kind, array $ids, array $extra = []): array
    {
        return $this->get(route('admin.allocation-overrides.edit', ['kind' => $kind, 'ids' => $ids, 'mode' => 'single', 'category' => $this->categories()[0], 'review' => 1] + $extra))->assertOk()->viewData('preview');
    }

    private function categories(): array
    {
        return DB::table('finance_categories')->where('kind', 'cost')->where('active', true)->pluck('id')->all();
    }

    private function apply(string $kind, array $preview, array $selected = [0])
    {
        return $this->postJson(route('admin.allocation-overrides.apply', ['kind' => $kind]), ['token' => $preview['token'], 'selected' => $selected]);
    }

    public function test_expense_percentages_use_each_net_total_and_round_exactly(): void
    {
        $this->admin();
        [$one, $two] = $this->categories();
        $a = Expense::factory()->create(['total_amount' => 110.01, 'gst_amount' => 10]);
        $b = Expense::factory()->create(['total_amount' => 220, 'gst_amount' => 20]);
        $preview = $this->get(route('admin.allocation-overrides.edit', ['kind' => 'expenses', 'ids' => [$a->id, $b->id], 'mode' => 'percent', 'percentages' => [$one => '33.33', $two => '66.67'], 'review' => 1]))->assertOk()->viewData('preview');
        $this->assertDatabaseCount('finance_expense_splits', 0);
        $this->assertSame(10001, array_sum($preview['rows'][0]['targets']));
        $this->assertSame(20000, array_sum($preview['rows'][1]['targets']));
        $this->apply('expenses', $preview, [0, 1])->assertOk();
        $this->assertDatabaseHas('finance_expense_splits', ['expense_id' => $a->id, 'category_id' => $one, 'cents' => 3333]);
        $this->assertSame(20000, (int) DB::table('finance_expense_splits')->where('expense_id', $b->id)->sum('cents'));
        $this->assertSame('110.01', $a->fresh()->total_amount);
        $this->apply('expenses', $preview)->assertUnprocessable();
    }

    public function test_invoice_override_preserves_then_explicitly_replaces_with_revision(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $preview = $this->preview('invoices', [$invoice->id]);
        $this->apply('invoices', $preview)->assertOk();
        $this->assertDatabaseHas('finance_budgets', ['manual' => true]);
        $blocked = $this->preview('invoices', [$invoice->id]);
        $this->assertNotNull($blocked['rows'][0]['warning']);
        $this->apply('invoices', $blocked)->assertUnprocessable();
        $replace = $this->preview('invoices', [$invoice->id], ['replace' => 1]);
        $this->apply('invoices', $replace)->assertOk();
        $this->assertDatabaseCount('finance_budget_revisions', 1);
        $this->assertDatabaseCount('finance_budgets', 1);
    }

    public function test_shared_invoice_allocations_require_complete_selection_and_apply_once(): void
    {
        $this->admin();
        $a = Invoice::factory()->create();
        $b = Invoice::factory()->create();
        $ticket = Ticket::factory()->create(['invoice_id' => $a->id]);
        Ticket::factory()->create(['invoice_id' => $b->id, 'workshop_id' => $ticket->workshop_id]);
        $preview = $this->preview('invoices', [$a->id]);
        $this->assertStringContainsString('every invoice', $preview['rows'][0]['warning']);
        $this->apply('invoices', $preview)->assertUnprocessable();
        $preview = $this->preview('invoices', [$a->id, $b->id]);
        $this->assertCount(1, $preview['rows']);
        $this->apply('invoices', $preview)->assertOk();
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertDatabaseCount('finance_budget_invoices', 2);
    }

    public function test_stale_records_categories_tampering_and_other_users_are_rejected(): void
    {
        $this->admin();
        $expense = Expense::factory()->create(['total_amount' => 100, 'gst_amount' => 0]);
        $preview = $this->preview('expenses', [$expense->id]);
        $this->apply('expenses', $preview, [999])->assertUnprocessable();
        $expense->update(['total_amount' => 101]);
        $this->apply('expenses', $preview)->assertUnprocessable();
        $preview = $this->preview('expenses', [$expense->id]);
        DB::table('finance_categories')->where('id', $this->categories()[0])->update(['active' => false]);
        $this->apply('expenses', $preview)->assertUnprocessable();
        $this->admin();
        $this->apply('expenses', $preview)->assertUnprocessable();
        $this->assertDatabaseCount('finance_expense_splits', 0);
        $this->actingAs(User::factory()->create())->get(route('admin.allocation-overrides.edit', ['kind' => 'expenses', 'ids' => [$expense->id]]))->assertForbidden();
    }

    public function test_one_stale_selection_prevents_the_entire_batch_and_expense_replacement_is_explicit(): void
    {
        $this->admin();
        $a = Expense::factory()->create(['total_amount' => 100, 'gst_amount' => 0]);
        $b = Expense::factory()->create(['total_amount' => 200, 'gst_amount' => 0]);
        $preview = $this->preview('expenses', [$a->id, $b->id]);
        $b->update(['total_amount' => 201]);
        $this->apply('expenses', $preview, [0, 1])->assertUnprocessable();
        $this->assertDatabaseCount('finance_expense_splits', 0);
        $fresh = $this->preview('expenses', [$a->id]);
        $this->apply('expenses', $fresh)->assertOk();
        $blocked = $this->preview('expenses', [$a->id]);
        $this->assertNotNull($blocked['rows'][0]['warning']);
        $this->apply('expenses', $blocked)->assertUnprocessable();
        $replace = $this->preview('expenses', [$a->id], ['replace' => 1]);
        $this->apply('expenses', $replace)->assertOk();
        $this->assertDatabaseCount('finance_expense_splits', 1);
    }

    public function test_cancelled_invoices_and_nonpositive_expenses_require_individual_review(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_CANCELLED]);
        $preview = $this->preview('invoices', [$invoice->id]);
        $this->apply('invoices', $preview)->assertUnprocessable();
        $expense = Expense::factory()->create(['total_amount' => 0, 'gst_amount' => 0]);
        $preview = $this->preview('expenses', [$expense->id]);
        $this->apply('expenses', $preview)->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->assertDatabaseCount('finance_expense_splits', 0);
    }

    public function test_invoice_editors_render_executable_preview_links(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create();
        $this->get(route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => [$invoice->id]]))->assertOk()->assertDontSee('@js(', false);
        $this->get(route('admin.invoice.allocation.edit', $invoice))->assertOk()->assertDontSee('@js(', false);
    }

    public function test_invalid_percentage_totals_and_non_cost_centres_are_rejected(): void
    {
        $this->admin();
        $expense = Expense::factory()->create();
        $base = ['kind' => 'expenses', 'ids' => [$expense->id], 'review' => 1];
        $this->getJson(route('admin.allocation-overrides.edit', $base + ['mode' => 'percent', 'percentages' => [$this->categories()[0] => 99.99]]))->assertUnprocessable();
        $this->getJson(route('admin.allocation-overrides.edit', $base + ['mode' => 'single', 'category' => 999999]))->assertUnprocessable();
    }
}
