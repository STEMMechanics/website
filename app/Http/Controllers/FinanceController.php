<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Workshop;
use App\Services\Finance\FinancePlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate(['tab' => ['nullable', Rule::in(['overview', 'budgets', 'pricing', 'suppliers', 'time', 'drawings', 'gst', 'setup'])], 'q' => 'nullable|string|max:100', 'per_page' => ['nullable', Rule::in([10, 25, 50, 100])], 'month' => 'nullable|date_format:Y-m', 'fortnight' => 'nullable|date_format:Y-m-d', 'sort' => ['nullable', Rule::in(['date', 'name'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $tab = $data['tab'] ?? 'overview';
        $perPage = (int) ($data['per_page'] ?? 25);
        $categories = DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get();
        $versions = DB::table('finance_pricing_versions')->orderByDesc('effective_from')->orderByDesc('id')->get();
        $cash = in_array($tab, ['overview', 'drawings', 'setup'], true) ? $planner->cash() : ['settings' => DB::table('finance_settings')->where('id', 1)->first()];
        $budgets = DB::table('finance_budgets')->when($data['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->orderBy($data['sort'] ?? 'date', $data['direction'] ?? 'desc')->paginate($perPage)->withQueryString();
        $reports = $tab === 'budgets' ? $budgets->getCollection()->map(fn ($budget) => $planner->budgetReport($budget)) : collect();
        $expenses = Expense::query()->when($data['q'] ?? null, fn ($q, $term) => $q->where('supplier', 'like', '%'.$term.'%'))->orderByDesc('paid_on')->paginate($perPage)->withQueryString();
        $suppliers = DB::table('finance_supplier_rules')->orderBy('supplier')->get();
        $user = $request->user()->id;
        $earned = $planner->earned($user);
        $paid = (int) DB::table('finance_drawings')->where('user_id', $user)->where('status', 'paid')->sum('cents');
        $pending = (int) DB::table('finance_drawings')->where('user_id', $user)->where('status', 'pending')->sum('cents');
        $anchor = Carbon::parse($cash['settings']->fortnight_anchor)->startOfDay();
        $days = (int) floor($anchor->diffInDays(now()->startOfDay()) / 14) * 14;
        $fortnight = isset($data['fortnight']) ? Carbon::parse($data['fortnight']) : $anchor->copy()->addDays($days);
        $timeEntries = DB::table('finance_time_entries')->where('user_id', $user)->whereBetween('date', [$fortnight->toDateString(), $fortnight->copy()->addDays(13)->toDateString()])->orderByDesc('date')->get();
        $drawings = DB::table('finance_drawings')->where('user_id', $user)->orderByDesc('id')->paginate($perPage)->withQueryString();
        $month = Carbon::parse(($data['month'] ?? now()->format('Y-m')).'-01');
        $gst = $planner->gst($month->toDateString(), $month->copy()->endOfMonth()->toDateString());
        $settlements = DB::table('finance_gst_settlements')->orderByDesc('period')->paginate($perPage)->withQueryString();
        $batches = DB::table('finance_batches')->orderByDesc('id')->limit(30)->get();
        $workshops = Workshop::query()->orderByDesc('starts_at')->limit(500)->get(['id', 'title', 'starts_at']);
        $preview = $request->session()->get('finance.preview');

        return view('admin.finance.index', compact('tab', 'categories', 'versions', 'cash', 'budgets', 'reports', 'expenses', 'suppliers', 'earned', 'paid', 'pending', 'fortnight', 'timeEntries', 'drawings', 'month', 'gst', 'settlements', 'batches', 'workshops', 'preview', 'planner'));
    }

    public function preview(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate([
            'version_id' => 'required|integer|exists:finance_pricing_versions,id', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'invoice_numbers' => 'nullable|string|max:2000', 'participants' => 'nullable|integer|min:0|max:10000', 'hours' => 'nullable|numeric|min:0|max:1000',
            'travel_minutes' => 'nullable|integer|min:0|max:10000', 'venue_supplied' => 'nullable|boolean',
        ]);
        $data = array_filter($data, fn ($value) => $value !== null && $value !== '');
        if (! empty($data['invoice_numbers'])) {
            $numbers = array_values(array_unique(preg_split('/[\s,]+/', trim($data['invoice_numbers'])) ?: []));
            $data['invoice_ids'] = DB::table('invoices')->whereIn('invoice_number', $numbers)->pluck('id')->all();
            if (count($data['invoice_ids']) !== count($numbers)) {
                throw ValidationException::withMessages(['invoice_numbers' => 'One or more invoice numbers could not be found.']);
            }
            if (! isset($data['participants'], $data['hours'])) {
                throw ValidationException::withMessages(['participants' => 'For invoice-only allocation, supply participants and duration for each invoice.']);
            }
            $data += ['travel_minutes' => 0, 'venue_supplied' => false];
        }
        $request->session()->put('finance.preview', $planner->preview($data));

        return $this->back('budgets');
    }

    public function apply(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'selected' => 'required|array|min:1|max:200', 'selected.*' => 'integer|min:0', 'targets' => 'nullable|array', 'targets.*' => 'array', 'targets.*.*' => 'numeric|min:0|max:10000000']);
        $preview = $request->session()->get('finance.preview');
        if (! is_array($preview) || $preview['token'] !== $data['token'] || Carbon::parse($preview['created_at'])->lt(now()->subHours(2))) {
            throw ValidationException::withMessages(['preview' => 'This preview has expired. Generate a new preview.']);
        }
        $planner->apply($preview, $request->user()->id, $data['selected'], $data['targets'] ?? []);
        $request->session()->forget('finance.preview');

        return $this->back('budgets', 'Allocations applied.');
    }

    public function reverse(Request $request, FinancePlanner $planner, int $batch): RedirectResponse
    {
        $planner->reverse($batch);

        return $this->back('budgets', 'Batch reversed. Its original preview remains in the history.');
    }

    public function category(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:finance_categories,id', 'name' => 'required|string|max:100', 'priority' => 'required|integer|min:1|max:1000', 'active' => 'required|boolean']);
        DB::transaction(function () use ($data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            if (! empty($data['id'])) {
                DB::table('finance_categories')->where('id', $data['id'])->update(['name' => $data['name'], 'priority' => $data['priority'], 'active' => $data['active'], 'updated_at' => now()]);
            } else {
                DB::table('finance_categories')->insert(['name' => $data['name'], 'priority' => $data['priority'], 'active' => $data['active'], 'kind' => 'cost', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return $this->back('pricing', 'Category saved.');
    }

    public function pricing(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate([
            'travel_price' => 'nullable|numeric|min:0|max:100000', 'travel_free_minutes' => 'nullable|integer|min:0|max:10000', 'name' => 'required|string|max:100', 'effective_from' => 'required|date_format:Y-m-d', 'rules' => 'required|array|min:1|max:100',
            'rules.*.category_id' => 'required|integer|exists:finance_categories,id', 'rules.*.basis' => ['required', Rule::in(['workshop', 'participant', 'hour', 'venue', 'travel'])],
            'rules.*.rate' => 'required|numeric|min:0|max:100000', 'rules.*.extra' => 'nullable|numeric|min:0|max:100000',
            'public' => 'required|array|size:4', 'public.*' => 'required|numeric|min:0|max:100000', 'organisation' => 'required|array|size:4', 'organisation.*' => 'required|numeric|min:0|max:100000',
        ]);
        $rules = array_map(fn ($row) => ['category_id' => (int) $row['category_id'], 'basis' => $row['basis'], 'rate_cents' => $planner->cents($row['rate']), 'extra_cents' => $planner->cents($row['extra'] ?? 0)], $data['rules']);
        DB::table('finance_pricing_versions')->insert(['name' => $data['name'], 'effective_from' => $data['effective_from'], 'rules' => json_encode($rules), 'prices' => json_encode(['public' => array_map($planner->cents(...), $data['public']), 'organisation' => array_map($planner->cents(...), $data['organisation']), 'travel_cents' => $planner->cents($data['travel_price'] ?? 34), 'travel_free_minutes' => (int) ($data['travel_free_minutes'] ?? 30)]), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return $this->back('pricing', 'New pricing version saved. Existing budgets keep their original targets.');
    }

    public function supplier(Request $request): RedirectResponse
    {
        $data = $request->validate(['supplier' => 'required|string|max:255', 'mode' => ['required', Rule::in(['single', 'default', 'split'])], 'splits' => 'required|array|min:1', 'splits.*' => 'numeric|min:0|max:100']);
        $splits = array_filter($data['splits'], fn ($v) => (float) $v > 0);
        if (abs(array_sum($splits) - 100) > 0.0001 || ($data['mode'] !== 'split' && count($splits) !== 1) || DB::table('finance_categories')->whereIn('id', array_keys($splits))->where('kind', 'cost')->count() !== count($splits)) {
            throw ValidationException::withMessages(['splits' => 'Choose cost categories totalling 100%. Single/default suppliers need one category.']);
        }
        DB::table('finance_supplier_rules')->updateOrInsert(['supplier' => mb_strtolower(trim($data['supplier']))], ['mode' => $data['mode'], 'splits' => json_encode($splits), 'created_at' => now(), 'updated_at' => now()]);

        return $this->back('suppliers', 'Supplier defaults saved. Explicit expense splits are preserved.');
    }

    public function expense(Request $request, FinancePlanner $planner, Expense $expense): RedirectResponse
    {
        $data = $request->validate(['splits' => 'required|array', 'splits.*' => 'numeric|min:0|max:10000000', 'budget_id' => 'nullable|integer|exists:finance_budgets,id', 'override' => 'sometimes|accepted']);
        $splits = array_filter(array_map($planner->cents(...), $data['splits']));
        $rule = DB::table('finance_supplier_rules')->where('supplier', mb_strtolower(trim((string) $expense->supplier)))->first();
        if ($rule && $rule->mode === 'single' && ! $request->boolean('override')) {
            throw ValidationException::withMessages(['override' => 'Confirm the exception to this supplier’s single category rule.']);
        }
        if (array_sum($splits) !== $planner->cents($expense->total_amount) - $planner->cents($expense->gst_amount) || DB::table('finance_categories')->whereIn('id', array_keys($splits))->where('kind', 'cost')->count() !== count($splits)) {
            throw ValidationException::withMessages(['splits' => 'Expense splits must equal the expense excluding recorded GST and use cost categories.']);
        }
        DB::transaction(function () use ($expense, $splits, $data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('finance_expense_splits')->where('expense_id', $expense->id)->delete();
            foreach ($splits as $id => $cents) {
                DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => $id, 'budget_id' => $data['budget_id'] ?? null, 'cents' => $cents, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return $this->back('suppliers', 'Expense allocation saved.');
    }

    public function transfer(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['from_category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('kind', 'cost')], 'category_id' => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'cost')], 'budget_id' => 'nullable|integer|exists:finance_budgets,id', 'amount' => 'required|numeric|min:0.01|max:10000000', 'reason' => 'required|string|max:255']);
        $planner->transfer($data, $request->user()->id);

        return $this->back('overview', 'Funds transferred. Workshop revenue and its shortfall remain unchanged.');
    }

    public function commitment(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'cost')], 'description' => 'required|string|max:255', 'due_on' => 'required|date_format:Y-m-d', 'amount' => 'required|numeric|min:0.01|max:10000000']);
        DB::transaction(function () use ($data, $planner): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('finance_commitments')->insert(['category_id' => $data['category_id'], 'description' => $data['description'], 'due_on' => $data['due_on'], 'cents' => $planner->cents($data['amount']), 'created_at' => now(), 'updated_at' => now()]);
        });

        return $this->back('suppliers', 'Commitment reserved.');
    }

    public function closeCommitment(Request $request, int $commitment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['paid', 'cancelled'])], 'expense_id' => 'required_if:status,paid|nullable|integer|exists:expenses,id']);
        DB::transaction(function () use ($data, $commitment): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            if ($data['status'] === 'paid' && ! Expense::query()->whereKey($data['expense_id'])->whereNotNull('paid_on')->whereDate('paid_on', '<=', today())->exists()) {
                throw ValidationException::withMessages(['expense_id' => 'Choose an expense that has been paid.']);
            }
            DB::table('finance_commitments')->where('id', $commitment)->where('status', 'open')->update(['status' => $data['status'], 'expense_id' => $data['expense_id'] ?? null, 'updated_at' => now()]);
        });

        return $this->back('suppliers', 'Commitment updated. The linked expense records the cash payment.');
    }

    public function time(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['id' => 'nullable|integer', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'activity' => ['required', Rule::in(['Delivery', 'Preparation', 'Pack down', 'Travel', 'Administration', 'Development'])], 'minutes' => 'required|integer|min:1|max:1440', 'rate' => 'required|numeric|min:0|max:10000', 'workshop_id' => 'nullable|string|exists:workshops,id', 'notes' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($request, $data, $planner): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $query = DB::table('finance_time_entries')->where('user_id', $request->user()->id);
            if (! empty($data['id'])) {
                abort_unless((clone $query)->where('id', $data['id'])->exists(), 404);
            }
            $minutes = (int) (clone $query)->where('date', $data['date'])->when($data['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->sum('minutes');
            if ($minutes + $data['minutes'] > 1440) {
                throw ValidationException::withMessages(['minutes' => 'A day cannot contain more than 24 hours of time.']);
            }
            $values = ['user_id' => $request->user()->id, 'date' => $data['date'], 'activity' => $data['activity'], 'minutes' => $data['minutes'], 'rate_cents' => $planner->cents($data['rate']), 'workshop_id' => $data['workshop_id'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_at' => now()];
            if (! empty($data['id'])) {
                (clone $query)->where('id', $data['id'])->update($values);
            } else {
                DB::table('finance_time_entries')->insert($values + ['created_at' => now()]);
            }
            $committed = (int) DB::table('finance_drawings')->where('user_id', $request->user()->id)->whereIn('status', ['pending', 'paid'])->sum('cents');
            if ($planner->earned($request->user()->id) < $committed) {
                throw ValidationException::withMessages(['rate' => 'This would reduce your time target below drawings already prepared or paid. Cancel pending drawings first.']);
            }
        });

        return $this->back('time', 'Time recorded.');
    }

    public function drawing(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'token' => 'required|uuid']);
        $planner->prepareDrawing($request->user()->id, $planner->cents($data['amount']), $data['token']);

        return $this->back('drawings', 'Drawing prepared. Make the bank transfer, then record its completion here.');
    }

    public function drawingStatus(Request $request, FinancePlanner $planner, int $drawing): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['paid', 'cancelled'])], 'paid_on' => 'required_if:status,paid|nullable|date_format:Y-m-d|before_or_equal:today', 'reference' => 'required_if:status,paid|nullable|string|max:255']);
        DB::transaction(function () use ($request, $data, $drawing): void {
            $settings = DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $row = DB::table('finance_drawings')->where('id', $drawing)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            if ($row->status !== 'pending') {
                return;
            }
            if ($data['status'] === 'paid' && ($data['paid_on'] < $settings->opening_date || $data['paid_on'] < substr($row->created_at, 0, 10))) {
                throw ValidationException::withMessages(['paid_on' => 'The transfer date must be on or after the drawing was prepared and the opening balance.']);
            }
            DB::table('finance_drawings')->where('id', $drawing)->update(['status' => $data['status'], 'paid_on' => $data['status'] === 'paid' ? $data['paid_on'] : null, 'reference' => $data['reference'] ?? null, 'updated_at' => now()]);
        });

        return $this->back('drawings', 'Drawing updated.');
    }

    public function settlement(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['period' => 'required|date_format:Y-m|unique:finance_gst_settlements,period', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required|numeric|between:-10000000,10000000', 'reference' => 'required|string|max:255']);
        if (DB::table('finance_gst_settlements')->where('period', $data['period'].'-01')->exists()) {
            throw ValidationException::withMessages(['period' => 'This month already has a settlement.']);
        }
        DB::table('finance_gst_settlements')->insert(['period' => $data['period'].'-01', 'paid_on' => $data['paid_on'], 'cents' => $planner->cents($data['amount']), 'reference' => $data['reference'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return $this->back('gst', 'GST settlement recorded.');
    }

    public function settings(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['opening_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'opening_cash' => 'required|numeric|between:-10000000,10000000', 'opening_gst' => 'required|numeric|between:-10000000,10000000', 'buffer' => 'required|numeric|min:0|max:10000000', 'fortnight_anchor' => 'required|date_format:Y-m-d', 'auto_budget' => 'nullable|boolean', 'reserves' => 'required|array', 'reserves.*' => 'numeric|min:0|max:10000000', 'confirmed' => 'required|accepted']);
        DB::transaction(function () use ($request, $data, $planner): void {
            $existing = DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $drawing = DB::table('finance_drawings')->whereIn('status', ['pending', 'paid'])->exists();
            if ($drawing && ($existing->opening_date !== $data['opening_date'] || $existing->opening_cash_cents !== $planner->cents($data['opening_cash']) || $existing->opening_gst_cents !== $planner->cents($data['opening_gst']))) {
                throw ValidationException::withMessages(['opening_date' => 'Opening balances are locked once drawings exist.']);
            }
            DB::table('finance_settings')->where('id', 1)->update(['opening_date' => $data['opening_date'], 'opening_cash_cents' => $planner->cents($data['opening_cash']), 'opening_gst_cents' => $planner->cents($data['opening_gst']), 'buffer_cents' => $planner->cents($data['buffer']), 'fortnight_anchor' => $data['fortnight_anchor'], 'auto_budget' => $request->boolean('auto_budget'), 'updated_at' => now()]);
            foreach ($data['reserves'] as $id => $amount) {
                DB::table('finance_categories')->where('id', $id)->update(['opening_cents' => $planner->cents($amount)]);
            }
            // Attribute the supplied initial template so opted-in automation has an audit actor.
            DB::table('finance_pricing_versions')->whereNull('created_by')->update(['created_by' => $request->user()->id]);
        });

        return $this->back('setup', 'Finance settings saved.');
    }

    private function back(string $tab, ?string $message = null): RedirectResponse
    {
        return redirect()->route('admin.finance.index', ['tab' => $tab])->with('success', $message);
    }
}
