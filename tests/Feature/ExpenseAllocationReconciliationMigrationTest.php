<?php

namespace Tests\Feature;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExpenseAllocationReconciliationMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function expense(array $amounts, string $total = '12.15', string $gst = '1.10'): Expense
    {
        $expense = Expense::factory()->create(['total_amount' => $total, 'gst_amount' => $gst]);
        foreach ($amounts as $category => $cents) {
            DB::table('finance_expense_splits')->insert([
                'expense_id' => $expense->id, 'category_id' => $category, 'cents' => $cents,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $expense;
    }

    public function test_gross_allocations_are_reconciled_with_exact_rounding_and_stable_rows(): void
    {
        $single = $this->expense([5 => 1215]);
        $multiple = $this->expense([1 => 405, 2 => 405, 5 => 405]);
        $before = DB::table('finance_expense_splits')->orderBy('id')->get();
        $migration = require database_path('migrations/2026_09_14_000000_reconcile_expense_allocations_excluding_gst.php');
        $migration->up();
        $this->assertSame(1105, (int) DB::table('finance_expense_splits')->where('expense_id', $single->id)->sum('cents'));
        $this->assertSame([368, 369, 368], DB::table('finance_expense_splits')->where('expense_id', $multiple->id)->orderBy('category_id')->pluck('cents')->all());
        $after = DB::table('finance_expense_splits')->orderBy('id')->get();
        foreach ($before as $index => $row) {
            foreach (['id', 'expense_id', 'category_id', 'budget_id', 'created_at'] as $field) {
                $this->assertSame($row->$field, $after[$index]->$field);
            }
        }
        $migration->up();
        $migration->down();
        $this->assertEquals($after, DB::table('finance_expense_splits')->orderBy('id')->get());
    }

    public function test_valid_and_ambiguous_allocations_are_unchanged_and_ambiguities_are_logged(): void
    {
        $this->expense([5 => 1105]);
        $this->expense([5 => 1215], gst: '0.00');
        $this->expense([]);
        $ambiguous = $this->expense([5 => 1000]);
        $invalid = $this->expense([5 => 1215], gst: '20.00');
        $before = DB::table('finance_expense_splits')->orderBy('id')->get();
        Log::spy();
        $migration = require database_path('migrations/2026_09_14_000000_reconcile_expense_allocations_excluding_gst.php');
        $migration->up();
        $this->assertEquals($before, DB::table('finance_expense_splits')->orderBy('id')->get());
        foreach ([$ambiguous, $invalid] as $expense) {
            Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['expense_id'] === $expense->id)->once();
        }
    }
}
