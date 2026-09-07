<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Finance\ExpenseAllocation;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkAllocationOverrideController extends Controller
{
    public function edit(Request $request, string $kind): View
    {
        $table = $kind === 'expenses' ? 'expenses' : 'invoices';
        $input = $request->validate([
            'ids' => 'required|array|min:1|max:200', 'ids.*' => "required|integer|distinct|exists:{$table},id",
            'mode' => 'nullable|in:single,percent', 'category' => 'nullable|integer',
            'percentages' => 'nullable|array|max:100', 'percentages.*' => 'nullable|numeric|min:0|max:100|decimal:0,2',
            'replace' => 'nullable|boolean', 'review' => 'nullable|boolean',
        ]);
        $categories = DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->orderBy('priority')->get();
        $preview = null;
        if ($request->boolean('review')) {
            $weights = ($input['mode'] ?? 'single') === 'single'
                ? [($input['category'] ?? '') => 10000]
                : array_filter(array_map(fn ($value) => (int) round((float) $value * 100), $input['percentages'] ?? []));
            if (array_sum($weights) !== 10000 || array_diff(array_keys($weights), $categories->pluck('id')->all())) {
                throw ValidationException::withMessages(['percentages' => 'Choose active cost centres and percentages totalling exactly 100%.']);
            }
            ksort($weights, SORT_NUMERIC);
            $input['weights'] = $weights;
            $preview = ['token' => (string) Str::uuid(), 'rows' => $this->rows($kind, $input)];
            Cache::put($this->key($request, $kind, $preview['token']), ['input' => $input, 'rows' => $preview['rows']], now()->addMinutes(30));
        }

        return view('admin.finance.bulk-overrides', compact('kind', 'input', 'categories', 'preview'));
    }

    public function apply(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'selected' => 'required|array|min:1|max:200', 'selected.*' => 'required|integer|distinct|min:0']);
        $key = $this->key($request, $kind, $data['token']);
        $cached = Cache::get($key);
        if (! $cached) {
            throw ValidationException::withMessages(['preview' => 'The preview expired or was already applied. Preview again.']);
        }
        $count = DB::transaction(function () use ($request, $kind, $data, $cached) {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            ($kind === 'expenses' ? Expense::query() : Invoice::query())->whereIn('id', $cached['input']['ids'])->lockForUpdate()->get();
            $fresh = $this->rows($kind, $cached['input']);
            foreach ($data['selected'] as $index) {
                $row = $cached['rows'][$index] ?? null;
                if (! $row || $row['warning'] || $row !== ($fresh[$index] ?? null)) {
                    throw ValidationException::withMessages(['preview' => 'A selected record or cost centre changed. Preview again before applying.']);
                }
            }
            foreach ($data['selected'] as $index) {
                $row = $fresh[$index];
                if ($kind === 'expenses') {
                    $save = Request::create('/', 'POST', ['allocation_override' => 1, 'splits' => array_map(fn ($cents) => number_format($cents / 100, 2, '.', ''), $row['targets'])]);
                    app(ExpenseAllocation::class)->save($save, Expense::findOrFail($row['id']), true);
                } else {
                    $invoice = Invoice::findOrFail($row['id']);
                    $service = app(InvoiceAllocation::class);
                    $service->saveManual($invoice, $service->context($invoice), $row['targets'], $request->user()->id);
                }
            }

            return count($data['selected']);
        });
        Cache::forget($key);

        return response()->json(['action' => $kind === 'invoices' ? 'invoice-allocation' : 'expense-allocation', 'message' => "Saved {$count} allocation overrides."]);
    }

    private function rows(string $kind, array $input): array
    {
        $planner = app(FinancePlanner::class);
        $categoriesValid = DB::table('finance_categories')->where('active', true)->where('kind', 'cost')->whereIn('id', array_keys($input['weights']))->count() === count($input['weights']);
        $records = ($kind === 'expenses' ? Expense::query() : Invoice::query())->whereIn('id', $input['ids'])->orderBy('id')->get();
        $rows = [];
        $seen = [];
        foreach ($records as $record) {
            $warning = $categoriesValid ? null : 'A cost centre is no longer active.';
            if ($kind === 'expenses') {
                $existing = DB::table('finance_expense_splits')->where('expense_id', $record->id)->orderBy('category_id')->get()->toArray();
                $net = $planner->cents($record->total_amount) - $planner->cents($record->gst_amount);
                $name = $record->supplier.' · expense #'.$record->id;
                $state = [$record->getAttributes(), $existing];
                $ids = [$record->id];
            } else {
                $context = app(InvoiceAllocation::class)->context($record);
                $ids = $context['ids'];
                sort($ids, SORT_NUMERIC);
                $group = implode(',', $ids);
                if (isset($seen[$group])) {
                    continue;
                }
                $seen[$group] = true;
                $existing = $context['budget'] !== null;
                $net = $context['total'];
                $name = $context['workshop']->title ?? 'Invoice '.$record->invoice_number;
                $warning ??= $context['warning'];
                if (array_diff($ids, $input['ids'])) {
                    $warning = 'Select every invoice in this shared allocation before changing it.';
                }
                if (Invoice::whereIn('id', $ids)->where(fn ($query) => $query->where('status', Invoice::STATUS_CANCELLED)->orWhere('total_amount', '<', 0))->exists()) {
                    $warning = 'Cancelled invoices and credit documents need individual review.';
                }
                $state = [$context['budget'], $context['targets'], $context['assumptions'], $context['version'], Invoice::whereIn('id', $ids)->orderBy('id')->get()->toArray()];
            }
            if ($existing && empty($input['replace'])) {
                $warning ??= 'Existing allocation preserved. Enable replacement to change it.';
            }
            if ($net <= 0) {
                $warning ??= 'Zero or negative totals need individual review.';
            }
            $targets = [];
            $weight = $assigned = 0;
            foreach ($input['weights'] as $id => $percent) {
                $weight += $percent;
                $next = (int) round(max(0, $net) * $weight / 10000);
                $targets[$id] = $next - $assigned;
                $assigned = $next;
            }
            $rows[] = ['id' => $record->id, 'ids' => $ids, 'name' => $name, 'net' => $net, 'targets' => array_filter($targets), 'warning' => $warning, 'fingerprint' => hash('sha256', json_encode($state))];
        }

        return $rows;
    }

    private function key(Request $request, string $kind, string $token): string
    {
        return 'allocation-override:'.$kind.':'.$request->user()->id.':'.$token;
    }
}
