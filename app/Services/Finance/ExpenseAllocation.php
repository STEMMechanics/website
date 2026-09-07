<?php

namespace App\Services\Finance;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseAllocation
{
    public function save(Request $request, Expense $expense, bool $standalone = false): void
    {
        if (! $standalone && ! $request->boolean('allocation_editor')) {
            return;
        }
        if (! $standalone && ! $request->boolean('allocation_override')) {
            DB::table('finance_expense_splits')->where('expense_id', $expense->id)->delete();

            return;
        }
        $data = $request->validate(['splits' => 'required|array', 'splits.*' => 'required|numeric|min:0|max:10000000', 'budget_id' => 'nullable|integer|exists:finance_budgets,id']);
        $planner = app(FinancePlanner::class);
        $splits = array_filter(array_map($planner->cents(...), $data['splits']));
        $rule = DB::table('finance_supplier_rules')->where('id', $expense->supplier_id)->first();
        if ($rule && $rule->mode === 'single' && ! $request->boolean('override') && ! $request->boolean('allocation_override')) {
            throw ValidationException::withMessages(['override' => 'Confirm the exception to this supplier’s single category rule.']);
        }
        $net = $planner->cents($expense->total_amount) - $planner->cents($expense->gst_amount);
        if ($net < 0 || array_sum($splits) !== $net || DB::table('finance_categories')->whereIn('id', array_keys($splits))->where('kind', 'cost')->count() !== count($splits)) {
            throw ValidationException::withMessages(['splits' => 'Allocated amounts must equal the expense total excluding GST.']);
        }
        $existingLinks = DB::table('finance_expense_splits')->where('expense_id', $expense->id)->pluck('budget_id', 'category_id');
        DB::table('finance_expense_splits')->where('expense_id', $expense->id)->delete();
        foreach ($splits as $id => $cents) {
            DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => $id, 'budget_id' => $request->exists('budget_id') ? ($data['budget_id'] ?? null) : ($existingLinks[$id] ?? null), 'cents' => $cents, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
