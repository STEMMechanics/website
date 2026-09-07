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

    private function categories(): array
    {
        return DB::table('finance_categories')->where('kind', 'cost')->where('active', true)->pluck('id')->all();
    }

    private function apply(string $kind, array $ids, array $data = [])
    {
        return $this->postJson(route('admin.allocation-overrides.apply', ['kind' => $kind]), ['ids' => $ids] + $data);
    }

    private function override(array $percentages = []): array
    {
        return ['allocation_override' => 1, 'percentages' => $percentages ?: [$this->categories()[0] => 100]];
    }

    public function test_bulk_editor_uses_shared_form_and_percentage_fields_without_preview(): void
    {
        $this->admin();
        $expense = Expense::factory()->create();
        $response = $this->postJson(route('admin.allocation-overrides.edit', ['kind' => 'expenses']), ['ids' => [$expense->id]])->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('data-bulk-save', $html);
        $this->assertStringContainsString('Override allocations', $html);
        $this->assertStringContainsString('name="supplier"', $html);
        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('name="paid_on"', $html);
        $this->assertStringContainsString('Allocate remaining percentage', $html);
        $this->assertStringNotContainsString('Preview', $html);
        $this->assertDatabaseCount('finance_expense_splits', 0);
    }

    public function test_shared_values_are_prefilled_and_differing_values_show_mixed_without_enable_checkboxes(): void
    {
        $this->admin();
        $a = Expense::factory()->create(['supplier' => 'Shared Supplier', 'description' => 'First description', 'paid_on' => '2026-09-01']);
        $b = Expense::factory()->create(['supplier' => 'Shared Supplier', 'description' => 'Second description', 'paid_on' => '2026-09-02']);
        $html = $this->postJson(route('admin.allocation-overrides.edit', ['kind' => 'expenses']), ['ids' => [$a->id, $b->id]])->assertOk()->json('html');
        $this->assertStringContainsString('value="Shared Supplier"', $html);
        $this->assertStringContainsString('placeholder="Mixed"', $html);
        $this->assertStringNotContainsString('Change supplier', $html);
        $this->assertStringNotContainsString('Change description', $html);
        $this->assertStringContainsString('Override allocations', $html);
    }

    public function test_percentages_apply_to_each_current_net_total_with_exact_rounding(): void
    {
        $this->admin();
        [$one, $two] = $this->categories();
        $a = Expense::factory()->create(['total_amount' => 110.01, 'gst_amount' => 10]);
        $b = Expense::factory()->create(['total_amount' => 220, 'gst_amount' => 20]);
        $this->postJson(route('admin.allocation-overrides.edit', ['kind' => 'expenses']), ['ids' => [$a->id, $b->id]])->assertOk();
        $b->update(['total_amount' => 221]);
        $this->apply('expenses', [$a->id, $b->id], $this->override([$one => '33.33', $two => '66.67']))->assertOk();
        $this->assertDatabaseHas('finance_expense_splits', ['expense_id' => $a->id, 'category_id' => $one, 'cents' => 3333]);
        $this->assertSame(10001, (int) DB::table('finance_expense_splits')->where('expense_id', $a->id)->sum('cents'));
        $this->assertSame(20100, (int) DB::table('finance_expense_splits')->where('expense_id', $b->id)->sum('cents'));
        $this->assertSame('110.01', $a->fresh()->total_amount);
    }

    public function test_only_checked_expense_fields_change_and_supplier_linking_is_retained(): void
    {
        $this->admin();
        $a = Expense::factory()->create(['description' => 'Keep A', 'paid_on' => '2026-09-01']);
        $b = Expense::factory()->create(['description' => 'Keep B', 'paid_on' => '2026-09-02']);
        $this->apply('expenses', [$a->id, $b->id], ['change_supplier' => 1, 'supplier' => 'New supplier', 'description' => 'Ignore unchecked value', 'paid_on' => '2026-10-01'])->assertOk();
        $this->assertSame('Keep A', $a->fresh()->description);
        $this->assertSame('Keep B', $b->fresh()->description);
        $this->assertSame('2026-09-01', $a->fresh()->paid_on->toDateString());
        $this->assertSame('New supplier', $a->fresh()->supplier);
        $this->assertSame($a->fresh()->supplier_id, $b->fresh()->supplier_id);
        $this->assertDatabaseHas('finance_supplier_rules', ['id' => $a->fresh()->supplier_id, 'name' => 'New supplier']);
        $this->apply('expenses', [$a->id, $b->id], ['change_description' => 1, 'description' => 'Shared description', 'change_paid_on' => 1, 'paid_on' => null])->assertOk();
        $this->assertNull($a->fresh()->paid_on);
        $this->assertSame('Shared description', $b->fresh()->description);
    }

    public function test_unchecked_allocations_are_preserved_and_override_replaces_them(): void
    {
        $this->admin();
        [$one, $two] = $this->categories();
        $expense = Expense::factory()->create(['total_amount' => 100, 'gst_amount' => 0]);
        $this->apply('expenses', [$expense->id], $this->override([$one => 100]))->assertOk();
        $this->apply('expenses', [$expense->id], ['change_description' => 1, 'description' => 'Changed', 'percentages' => [$two => 12]])->assertOk();
        $this->assertDatabaseHas('finance_expense_splits', ['expense_id' => $expense->id, 'category_id' => $one, 'cents' => 10000]);
        $this->apply('expenses', [$expense->id], $this->override([$two => 100]))->assertOk();
        $this->assertDatabaseCount('finance_expense_splits', 1);
        $this->assertDatabaseHas('finance_expense_splits', ['expense_id' => $expense->id, 'category_id' => $two, 'cents' => 10000]);
    }

    public function test_invoice_replacement_keeps_revision_history(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $this->apply('invoices', [$invoice->id], $this->override())->assertOk();
        $this->apply('invoices', [$invoice->id], $this->override())->assertOk();
        $this->assertDatabaseHas('finance_budgets', ['manual' => true]);
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertDatabaseCount('finance_budget_revisions', 1);
    }

    public function test_shared_invoice_allocations_require_complete_selection_and_apply_once(): void
    {
        $this->admin();
        $a = Invoice::factory()->create();
        $b = Invoice::factory()->create();
        $ticket = Ticket::factory()->create(['invoice_id' => $a->id]);
        Ticket::factory()->create(['invoice_id' => $b->id, 'workshop_id' => $ticket->workshop_id]);
        $this->apply('invoices', [$a->id], $this->override())->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->apply('invoices', [$a->id, $b->id], $this->override())->assertOk();
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertDatabaseCount('finance_budget_invoices', 2);
    }

    public function test_invalid_record_rolls_back_all_metadata_and_allocations(): void
    {
        $this->admin();
        $a = Expense::factory()->create(['total_amount' => 100, 'gst_amount' => 0, 'description' => 'Original']);
        $b = Expense::factory()->create(['total_amount' => 0, 'gst_amount' => 0]);
        $this->apply('expenses', [$a->id, $b->id], $this->override() + ['change_description' => 1, 'description' => 'Changed'])->assertUnprocessable();
        $this->assertSame('Original', $a->fresh()->description);
        $this->assertDatabaseCount('finance_expense_splits', 0);
    }

    public function test_invalid_percentages_categories_fields_empty_changes_and_permissions_are_rejected(): void
    {
        $this->admin();
        $expense = Expense::factory()->create();
        $category = $this->categories()[0];
        $this->apply('expenses', [$expense->id], $this->override([$category => 99.99]))->assertUnprocessable();
        $this->apply('expenses', [$expense->id], $this->override([$category => 100.001]))->assertUnprocessable();
        DB::table('finance_categories')->where('id', $category)->update(['active' => false]);
        $this->apply('expenses', [$expense->id], $this->override([$category => 100]))->assertUnprocessable();
        $this->apply('expenses', [$expense->id], ['change_supplier' => 1, 'supplier' => ''])->assertUnprocessable();
        $this->apply('expenses', [$expense->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->apply('expenses', [$expense->id], ['change_description' => 1, 'description' => 'No'])->assertForbidden();
        $this->postJson(route('admin.allocation-overrides.edit', ['kind' => 'expenses']), ['ids' => [$expense->id]])->assertForbidden();
    }
}
