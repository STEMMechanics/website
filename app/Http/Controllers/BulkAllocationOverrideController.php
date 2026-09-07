<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Finance\ExpenseAllocation;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkAllocationOverrideController extends Controller
{
    public function edit(Request $request, string $kind): JsonResponse
    {
        $ids = $this->ids($request, $kind);
        $categories = DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->orderBy('priority')->get();

        $common = $mixed = [];
        if ($kind === 'expenses') {
            $expenses = Expense::whereIn('id', $ids)->get();
            foreach (['supplier', 'description', 'paid_on'] as $field) {
                $values = $expenses->map(fn (Expense $expense) => $field === 'paid_on' ? ($expense->paid_on?->format('Y-m-d') ?? '') : (string) $expense->$field)->uniqueStrict();
                $mixed[$field] = $values->count() > 1;
                $common[$field] = $mixed[$field] ? '' : $values->first();
            }
        }

        return response()->json(['html' => view('admin.finance.bulk-overrides', compact('kind', 'ids', 'categories', 'common', 'mixed'))->render()]);
    }

    public function apply(Request $request, string $kind): JsonResponse
    {
        $ids = $this->ids($request, $kind);
        $request->validate(['allocation_override' => 'nullable|boolean', 'change_supplier' => 'nullable|boolean', 'change_description' => 'nullable|boolean', 'change_paid_on' => 'nullable|boolean']);
        $changes = [];
        if ($kind === 'expenses') {
            foreach (['supplier' => 'required|string|max:255', 'description' => 'required|string|max:255', 'paid_on' => 'nullable|date_format:Y-m-d'] as $field => $rule) {
                if ($request->boolean('change_'.$field)) {
                    $changes[$field] = $request->validate([$field => $rule])[$field] ?? null;
                }
            }
        }
        $weights = [];
        if ($request->boolean('allocation_override')) {
            $data = $request->validate(['percentages' => 'required|array|min:1|max:100', 'percentages.*' => 'required|numeric|min:0|max:100|decimal:0,2']);
            $weights = array_filter(array_map(fn ($value) => (int) round((float) $value * 100), $data['percentages']));
            if (array_sum($weights) !== 10000) {
                throw ValidationException::withMessages(['percentages' => 'Cost centre percentages must total exactly 100%.']);
            }
            ksort($weights, SORT_NUMERIC);
        }
        if (! $changes && ! $weights) {
            throw ValidationException::withMessages(['changes' => 'Choose at least one field to change or enable allocation overrides.']);
        }
        DB::transaction(function () use ($request, $kind, $ids, $changes, $weights): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $records = ($kind === 'expenses' ? Expense::query() : Invoice::query())->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages(['ids' => 'A selected record was removed. Reopen the bulk editor.']);
            }
            if ($weights && DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->whereIn('id', array_keys($weights))->count() !== count($weights)) {
                throw ValidationException::withMessages(['percentages' => 'Choose active cost centres.']);
            }
            $planner = app(FinancePlanner::class);
            $seen = [];
            foreach ($records as $record) {
                if ($record instanceof Expense && $changes) {
                    // Use model saves to retain supplier-directory linking.
                    $record->fill($changes)->save();
                }
                if (! $weights) {
                    continue;
                }
                if ($record instanceof Expense) {
                    $net = $planner->cents($record->total_amount) - $planner->cents($record->gst_amount);
                    $targets = $this->split($net, $weights);
                    $save = Request::create('/', 'POST', ['allocation_override' => 1, 'splits' => array_map(fn ($cents) => number_format($cents / 100, 2, '.', ''), $targets)]);
                    app(ExpenseAllocation::class)->save($save, $record, true);
                } else {
                    $service = app(InvoiceAllocation::class);
                    $context = $service->context($record);
                    $linked = $context['ids'];
                    sort($linked, SORT_NUMERIC);
                    $group = implode(',', $linked);
                    if (isset($seen[$group])) {
                        continue;
                    }
                    $seen[$group] = true;
                    if ($context['warning'] || array_diff($linked, $ids)) {
                        throw ValidationException::withMessages(['allocation_override' => $context['warning'] ?: 'Select every invoice in a shared allocation before changing it.']);
                    }
                    if (Invoice::whereIn('id', $linked)->where(fn ($query) => $query->where('status', Invoice::STATUS_CANCELLED)->orWhere('total_amount', '<', 0))->exists()) {
                        throw ValidationException::withMessages(['allocation_override' => 'Cancelled invoices and credit documents need individual review.']);
                    }
                    $service->saveManual($record, $context, $this->split($context['total'], $weights), $request->user()->id);
                }
            }
        });

        return response()->json(['message' => 'Updated '.count($ids).' '.$kind.'.']);
    }

    private function ids(Request $request, string $kind): array
    {
        return $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => "required|integer|distinct|exists:{$kind},id"])['ids'];
    }

    private function split(int $net, array $weights): array
    {
        if ($net <= 0) {
            throw ValidationException::withMessages(['percentages' => 'Zero or negative totals need individual review. No records were changed.']);
        }
        $targets = [];
        $weight = $assigned = 0;
        foreach ($weights as $id => $percent) {
            $weight += $percent;
            $next = (int) round($net * $weight / 10000);
            $targets[$id] = $next - $assigned;
            $assigned = $next;
        }

        return array_filter($targets);
    }
}
