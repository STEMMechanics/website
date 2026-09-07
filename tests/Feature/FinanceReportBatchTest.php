<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\FinanceReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceReportBatchTest extends TestCase
{
    use RefreshDatabase;

    private function addBudget(User $user): void
    {
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $payment = Payment::factory()->create(['payment_method' => 'cash', 'total_amount' => 110, 'gst_amount' => 0, 'received_on' => now()->subDay()]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 110]);
        Payment::factory()->create(['kind' => 'refund', 'refund_of_payment_id' => $payment->id, 'payment_method' => 'cash', 'total_amount' => 11, 'gst_amount' => 0, 'received_on' => now()->subHour()]);
        $planner = app(FinancePlanner::class);
        $preview = $planner->preview(['version_id' => 1, 'invoice_ids' => [$invoice->id], 'participants' => 4, 'hours' => 2, 'travel_minutes' => 0, 'venue_supplied' => false]);
        $planner->apply($preview, $user->id, [0]);
    }

    private function reportQueryCount(): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(FinancePlanner::class)->cash();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_report_query_count_does_not_grow_per_expense_or_budget_and_results_match_live_calculations(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create(['supplier' => 'Batch supplier', 'total_amount' => 110, 'gst_amount' => 10, 'paid_on' => today()]);
        Supplier::findOrFail($expense->supplier_id)->update(['splits' => [1 => 100]]);
        $this->addBudget($user);
        $before = $this->reportQueryCount();
        Expense::factory()->count(20)->create(['supplier' => 'Batch supplier', 'total_amount' => 110, 'gst_amount' => 10, 'paid_on' => today()]);
        foreach (range(1, 5) as $_) {
            $this->addBudget($user);
        }
        $after = $this->reportQueryCount();
        $this->assertLessThanOrEqual($before, $after);
        DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => 2, 'cents' => 10000]);
        $expenses = Expense::all();
        $data = new FinanceReportData($expenses, DB::table('finance_budgets')->get());
        $planner = app(FinancePlanner::class);
        foreach ($expenses as $record) {
            $this->assertSame($planner->expenseSplits($record), $planner->expenseSplits($record, $data));
        }
        foreach ($data->budgets as $budget) {
            $ids = $data->invoiceIds($budget);
            $this->assertSame($planner->budgetInvoiceIds($budget), $ids);
            $this->assertSame($planner->incomeEvents($ids), $planner->incomeEvents($ids, $data));
        }
    }
}
