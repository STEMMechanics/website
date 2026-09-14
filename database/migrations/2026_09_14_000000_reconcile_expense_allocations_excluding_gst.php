<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expenses')->whereExists(function ($query) {
            $query->selectRaw('1')->from('finance_expense_splits')->whereColumn('expense_id', 'expenses.id');
        })->orderBy('id')->chunkById(200, function ($expenses) {
            foreach ($expenses as $expense) {
                DB::transaction(function () use ($expense) {
                    $expense = DB::table('expenses')->where('id', $expense->id)->lockForUpdate()->first();
                    if (! $expense) {
                        return;
                    }
                    $splits = DB::table('finance_expense_splits')->where('expense_id', $expense->id)->orderBy('category_id')->lockForUpdate()->get();
                    if ($splits->isEmpty()) {
                        return;
                    }
                    $gross = (int) round((float) $expense->total_amount * 100);
                    $gst = (int) round((float) $expense->gst_amount * 100);
                    $net = $gross - $gst;
                    $allocated = (int) $splits->sum('cents');
                    if ($allocated === $net && $net >= 0 && $splits->every(fn ($split) => $split->cents >= 0)) {
                        return;
                    }
                    if ($gross <= 0 || $gst <= 0 || $net < 0 || $allocated !== $gross || $splits->contains(fn ($split) => $split->cents < 0)) {
                        Log::warning('Expense allocation requires manual review after ex-GST reconciliation.', [
                            'expense_id' => $expense->id,
                            'net_cents' => $net,
                            'allocated_cents' => $allocated,
                        ]);

                        return;
                    }

                    // Cumulative rounding preserves proportions and allocates exactly the net total.
                    $grossSoFar = 0;
                    $netSoFar = 0;
                    foreach ($splits as $split) {
                        $grossSoFar += (int) $split->cents;
                        $next = (int) round($net * ($grossSoFar / $gross));
                        DB::table('finance_expense_splits')->where('id', $split->id)->update([
                            'cents' => $next - $netSoFar,
                            'updated_at' => now(),
                        ]);
                        $netSoFar = $next;
                    }
                    Log::info('Expense allocation reconciled to exclude GST.', [
                        'expense_id' => $expense->id,
                        'previous_cents' => $allocated,
                        'net_cents' => $net,
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        // Data corrections must not restore GST-inclusive allocations on rollback.
    }
};
