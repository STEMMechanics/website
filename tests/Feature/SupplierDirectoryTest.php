<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::query()->create(['user_id' => $user->id, 'slug' => 'admin']);

        return $user;
    }

    public function test_supplier_has_a_single_default_and_its_own_expense_table(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.supplier.store'), ['name' => 'Workshop Supplies', 'category_id' => 2])->assertSessionHasNoErrors();
        $supplier = Supplier::query()->firstOrFail();
        $this->assertSame([2 => 100], $supplier->splits);
        $expense = Expense::factory()->create(['supplier' => ' WORKSHOP SUPPLIES ', 'description' => 'Microbit materials', 'total_amount' => 110, 'gst_amount' => 10]);
        Expense::factory()->create(['supplier' => 'Another supplier', 'description' => 'Unrelated expense']);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame([2 => 10000], app(FinancePlanner::class)->expenseSplits($expense));
        $this->get(route('admin.supplier.index'))->assertOk()->assertSee('Workshop Supplies')->assertSee('data-dynamic-list', false)->assertSee('Sort by Expenses')->assertSee('View expenses')->assertSee('data-record-editor', false);
        $this->get(route('admin.supplier.show', $supplier))->assertOk()->assertSee('Microbit materials')->assertDontSee('Unrelated expense')->assertSee('Default cost centre:')->assertDontSee('· 100%')->assertSee('Rows per page');
        $this->get(route('admin.supplier.edit', $supplier))->assertOk()->assertSee('Default cost centre')->assertDontSee('percentage split');
        $this->get(route('admin.expense.edit', $expense))->assertOk()->assertSee('Cost centre allocation');
        $this->get(route('admin.expense.index', ['supplier_id' => $supplier->id]))->assertOk()->assertSee('Microbit materials')->assertDontSee('Unrelated expense')->assertSee('Supplier account');
        $this->putJson(route('admin.supplier.update', $supplier), ['name' => 'Workshop Supplies', 'category_id' => 1])->assertOk()->assertJsonPath('message', 'Supplier saved.');
        $this->get(route('admin.supplier.edit', $supplier))->assertDontSee('100% of each expense, excluding recorded GST');

    }

    public function test_supplier_without_expenses_and_old_finance_bookmark(): void
    {
        $this->actingAs($this->admin())->post(route('admin.supplier.store'), ['name' => 'New venue', 'category_id' => 1])->assertSessionHasNoErrors();
        $supplier = Supplier::query()->firstOrFail();
        $this->get(route('admin.supplier.show', $supplier))->assertOk()->assertSee('New venue');
        $this->get(route('admin.finance.index', ['tab' => 'suppliers']))->assertRedirect(route('admin.supplier.index'));
        $this->get(route('admin.finance.index'))->assertRedirect(route('admin.cost-centre.index'));
        $this->get(route('admin.cost-centre.index'))->assertOk()->assertDontSee('Suppliers &amp; expenses', false)->assertDontSee('Supplier defaults')->assertDontSee('Upcoming commitments');
        $this->get(route('admin.expense.create'))->assertOk()->assertSee('New venue');
    }

    public function test_updating_default_preserves_explicit_allocations_and_supplier_history(): void
    {
        $this->actingAs($this->admin());
        $expense = Expense::factory()->create(['supplier' => 'Old Name', 'total_amount' => 55, 'gst_amount' => 5]);
        $supplier = Supplier::query()->findOrFail($expense->supplier_id);
        $this->post(route('admin.expense.allocation', $expense), ['splits' => [1 => 50]])->assertSessionHasNoErrors();
        $this->put(route('admin.supplier.update', $supplier), ['name' => 'New Name', 'category_id' => 2])->assertSessionHasNoErrors();
        $expense->update(['description' => 'Corrected description']);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame([1 => 5000], app(FinancePlanner::class)->expenseSplits($expense));
        $this->get(route('admin.supplier.show', $supplier))->assertOk()->assertSee('Corrected description');
        $this->assertDatabaseCount('finance_supplier_rules', 1);
    }

    public function test_directory_sorting_filtering_and_pagination_stay_scoped(): void
    {
        $this->actingAs($this->admin());
        Expense::factory()->count(12)->create(['supplier' => 'Alpha', 'description' => 'Matching row', 'total_amount' => 10]);
        Expense::factory()->create(['supplier' => 'Beta', 'description' => 'Other row', 'total_amount' => 1000]);
        foreach (['name', 'cost_centre', 'expenses_count', 'expenses_total_amount'] as $sort) {
            $this->get(route('admin.supplier.index', ['list_sort' => $sort, 'list_direction' => 'desc']))->assertOk();
        }
        $this->get(route('admin.supplier.index', ['search' => 'Alpha']))->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
        $supplier = Supplier::query()->where('name', 'Alpha')->firstOrFail();
        $supplier->update(['category_id' => 2]);
        $this->get(route('admin.supplier.index', ['cost_centre_id' => 2]))->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
        $response = $this->get(route('admin.supplier.show', [$supplier, 'per_page' => 10, 'page' => 2]));
        $response->assertOk()->assertDontSee('Other row')->assertViewHas('expenses', fn ($rows) => $rows->total() === 12 && $rows->count() === 2);
        $this->get(route('admin.supplier.show', [$supplier, 'list_description' => 'Other']))->assertOk()->assertDontSee('Matching row')->assertDontSee('Other row');
    }

    public function test_invalid_defaults_and_non_admin_access_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.supplier.store'), ['name' => 'No default'])->assertSessionHasErrors('category_id');
        $this->post(route('admin.supplier.store'), ['name' => 'Owner', 'category_id' => 6])->assertSessionHasErrors('category_id');
        $this->post(route('admin.supplier.store'), ['name' => ['malformed'], 'category_id' => 2])->assertSessionHasErrors('name');
        $this->post(route('admin.supplier.store'), ['name' => 'Unique', 'category_id' => 2])->assertSessionHasNoErrors();
        $this->post(route('admin.supplier.store'), ['name' => ' UNIQUE ', 'category_id' => 1])->assertSessionHasErrors('supplier');
        $this->get(route('admin.supplier.create'))->assertOk()->assertSee('This supplier already exists.');
        $this->actingAs(User::factory()->create())->get(route('admin.supplier.index'))->assertForbidden();
        $this->post(route('admin.supplier.store'), ['name' => 'Denied', 'category_id' => 2])->assertForbidden();
    }

    public function test_migration_links_historical_expenses_and_keeps_legacy_splits(): void
    {
        $expense = Expense::factory()->create(['supplier' => 'Historical Vendor']);
        $id = $expense->supplier_id;
        DB::table('finance_supplier_rules')->where('id', $id)->update(['mode' => 'split', 'splits' => json_encode([1 => 25, 2 => 75])]);
        $migration = require database_path('migrations/2026_09_06_230000_add_supplier_directory.php');
        $migration->down();
        $migration->up();
        $this->assertSame($id, $expense->fresh()->supplier_id);
        $supplier = Supplier::query()->findOrFail($id);
        $this->assertNull($supplier->category_id);
        $this->assertSame([1 => 25, 2 => 75], $supplier->splits);
        $this->actingAs($this->admin())->get(route('admin.supplier.edit', $supplier))->assertOk()->assertSee('existing split default');
    }
}
