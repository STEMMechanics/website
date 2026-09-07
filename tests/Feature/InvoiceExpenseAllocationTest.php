<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceExpenseAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        return $user;
    }

    public function test_invoice_allocation_can_be_created_and_overridden_without_changing_invoice(): void
    {
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $this->actingAs($this->admin())->get(route('admin.invoice.allocation.edit', $invoice))->assertOk()->assertSee('data-record-form', false);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['targets' => [1 => '60.00', 2 => '40.00']])->assertOk()->assertJsonPath('target', 'invoice-cost-centres');
        $budget = DB::table('finance_budgets')->first();
        $this->assertTrue((bool) $budget->manual);
        $this->assertSame('110.00', $invoice->fresh()->total_amount);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['budget_id' => $budget->id, 'targets' => [1 => '90.00', 2 => '10.00']])->assertOk();
        $this->assertDatabaseCount('finance_budget_revisions', 1);
        $this->assertSame(9000, json_decode(DB::table('finance_budgets')->first()->targets, true)[1]);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['targets' => [1 => 100]])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson(route('admin.invoice.allocation.store', $invoice), ['targets' => [1 => 100]])->assertForbidden();
    }

    public function test_inline_allocation_editor_saves_and_returns_the_editor_without_changing_invoice(): void
    {
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $this->actingAs($this->admin())->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee('data-allocation-inline', false);
        $this->get(route('admin.invoice.allocation.edit', [$invoice, 'inline' => 1]))->assertOk()->assertSee('data-allocation-load', false);
        $response = $this->postJson(route('admin.invoice.allocation.store', $invoice), ['inline' => 1, 'targets' => [1 => '100.00']])->assertOk();
        $this->assertStringContainsString('data-allocation-inline', $response->json('html'));
        $this->assertSame('110.00', $invoice->fresh()->total_amount);
    }

    public function test_ticket_invoices_share_one_workshop_allocation(): void
    {
        $workshop = Workshop::findOrFail(Ticket::factory()->create()->workshop_id);
        $invoices = Invoice::factory()->count(2)->create();
        foreach ($invoices as $invoice) {
            Ticket::factory()->create(['workshop_id' => $workshop->id, 'invoice_id' => $invoice->id]);
        }
        $this->actingAs($this->admin())->postJson(route('admin.invoice.allocation.store', $invoices[0]), ['targets' => [1 => 100]])->assertOk();
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertDatabaseCount('finance_budget_invoices', 2);
        $this->get(route('admin.invoice.allocation.edit', $invoices[1]))->assertOk()->assertSee('2');
        $this->postJson(route('admin.invoice.allocation.store', $invoices[1]), ['targets' => [1 => 100]])->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 1);
    }

    public function test_expense_tally_can_find_the_supplier_autocomplete_for_default_allocations(): void
    {
        $expense = Expense::factory()->create(['supplier' => 'Operational Supplier', 'total_amount' => 29.99, 'gst_amount' => 2.73]);
        DB::table('finance_supplier_rules')->where('id', $expense->supplier_id)->update(['splits' => json_encode([5 => 100]), 'category_id' => 5]);
        $response = $this->actingAs($this->admin())->get(route('admin.expense.edit', $expense))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $inputs = $xpath->query('//input[@id="expense-supplier" and @name="supplier"]');
        $this->assertCount(1, $inputs);
        $section = $xpath->query('//section[contains(@x-data, "allocationTally")]')->item(0);
        $this->assertStringContainsString('expense-supplier', $section->getAttribute('x-data'));
        $this->assertSame([5 => 2726], app(\App\Services\Finance\FinancePlanner::class)->expenseSplits($expense));
    }

    public function test_expense_save_is_atomic_and_requires_exact_net_allocation(): void
    {
        $expense = Expense::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $data = ['supplier' => $expense->supplier, 'description' => 'Changed description', 'invoice_id' => 'EXP-TEST', 'total_amount' => 220, 'gst_amount' => 20, 'allocation_editor' => 1, 'allocation_override' => 1, 'splits' => [1 => 190]];
        $this->actingAs($this->admin())->put(route('admin.expense.update', $expense), $data)->assertSessionHasErrors('splits');
        $this->assertSame('110.00', $expense->fresh()->total_amount);
        $this->assertDatabaseCount('finance_expense_splits', 0);
        $data['splits'] = [1 => '150.00', 2 => '50.00'];
        $this->put(route('admin.expense.update', $expense), $data)->assertSessionHasNoErrors();
        $this->assertSame('220.00', $expense->fresh()->total_amount);
        $this->assertSame(20000, (int) DB::table('finance_expense_splits')->sum('cents'));
        $invoice = Invoice::factory()->create();
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['targets' => [1 => 100]])->assertOk();
        $budgetId = DB::table('finance_budgets')->value('id');
        DB::table('finance_expense_splits')->where('expense_id', $expense->id)->update(['budget_id' => $budgetId]);
        $this->put(route('admin.expense.update', $expense), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('finance_expense_splits')->where('expense_id', $expense->id)->where('budget_id', $budgetId)->count());
        $data['allocation_override'] = 0;
        $this->put(route('admin.expense.update', $expense), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('finance_expense_splits', 0);
    }
}
