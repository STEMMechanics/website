<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\GstCalculator;
use App\Services\SiteListControls;
use App\Support\ListPageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CostCentreController extends Controller
{
    public function index(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:255', 'state' => ['nullable', Rule::in(['all', 'active', 'archived'])],
            'list_sort' => ['nullable', Rule::in(['name', 'priority', 'balance'])],
            'list_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $cash = $planner->cash();
        $centres = DB::table('finance_categories')->get()->map(function ($centre) use ($cash) {
            $centre->balance = $cash['reserves'][$centre->id] ?? 0;

            return $centre;
        });
        $centres->push((object) ['id' => 'gst', 'name' => 'GST', 'kind' => 'gst', 'active' => true, 'priority' => 0, 'balance' => $cash['gst']]);
        $counts = ['all' => $centres->count(), 'active' => $centres->where('active', true)->count(), 'archived' => $centres->where('active', false)->count()];
        $state = $data['state'] ?? 'all';
        $centres = $centres->filter(fn ($centre) => ($state === 'all' || (bool) $centre->active === ($state === 'active')) && (! isset($data['search']) || str_contains(mb_strtolower($centre->name), mb_strtolower($data['search']))));
        $centres = $centres->sortBy($data['list_sort'] ?? 'priority', SORT_REGULAR, ($data['list_direction'] ?? 'asc') === 'desc')->values();
        $centres = $this->paginate($centres);

        return view('admin.cost-centre.index', compact('centres', 'counts', 'state', 'cash'));
    }

    public function allocations(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate(['tab' => ['nullable', Rule::in(['allocations', 'versions', 'editor'])], 'edit_id' => 'nullable|integer|exists:finance_pricing_versions,id', 'template_id' => 'nullable|integer|exists:finance_pricing_versions,id', 'q' => 'nullable|string|max:100', 'sort' => ['nullable', Rule::in(['date', 'name'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $categories = DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get();
        $versions = DB::table('finance_pricing_versions')->where('is_snapshot', false)->orderByDesc('effective_from')->orderByDesc('id')->get();
        $defaultVersionId = DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id');
        $selectedVersionId = \App\Services\Finance\PricingVersion::forDate(today()->toDateString())->id;
        $templateVersion = isset($data['template_id']) ? $versions->firstWhere('id', $data['template_id']) : $versions->firstWhere('id', $selectedVersionId);
        $editing = isset($data['edit_id']);
        if ($editing) {
            $templateVersion = $versions->firstWhere('id', $data['edit_id']);
            abort_unless($templateVersion !== null, 404);
        }
        if (($data['tab'] ?? '') === 'editor') {
            return view('admin.cost-centre.version-editor', compact('categories', 'versions', 'defaultVersionId', 'templateVersion', 'planner', 'editing'));
        }
        $versions = DB::table('finance_pricing_versions')->where('is_snapshot', false)
            ->when(! empty($data['q']), fn ($query) => $query->where('name', 'like', '%'.$data['q'].'%'))
            ->orderBy('archived')
            ->orderBy(($data['sort'] ?? 'name') === 'date' ? 'created_at' : 'name', $data['direction'] ?? 'desc')
            ->orderBy('id')->paginate(\App\Support\ListPageSize::resolve(25))->withQueryString();
        $usedVersionIds = DB::table('finance_budgets')->pluck('pricing_version_id')
            ->merge(DB::table('workshops')->whereNotNull('pricing_version_id')->pluck('pricing_version_id'))->unique()->all();
        return view('admin.cost-centre.versions', compact('categories', 'versions', 'defaultVersionId', 'templateVersion', 'planner', 'usedVersionIds'));
    }

    public function archiveVersion(Request $request, int $version): RedirectResponse
    {
        $data = $request->validate(['archived' => 'required|boolean']);
        DB::transaction(function () use ($version, $data): void {
            $settings = DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('finance_pricing_versions')->where('id', $version)->where('is_snapshot', false)->lockForUpdate()->firstOrFail();
            if ($data['archived'] && (int) $settings->default_pricing_version_id === $version) {
                throw ValidationException::withMessages(['version_id' => 'Set another plan as default before archiving this plan.']);
            }
            DB::table('finance_pricing_versions')->where('id', $version)->update(['archived' => (bool) $data['archived'], 'updated_at' => now()]);
        });

        return redirect()->route('admin.cost-centre.allocations')->with('success', $data['archived'] ? 'Allocation plan archived.' : 'Allocation plan restored.');
    }

    public function destroyVersion(int $version): RedirectResponse
    {
        DB::transaction(function () use ($version): void {
            $settings = DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('finance_pricing_versions')->where('id', $version)->where('is_snapshot', false)->lockForUpdate()->firstOrFail();
            if ((int) $settings->default_pricing_version_id === $version) {
                throw ValidationException::withMessages(['version_id' => 'Set another allocation plan as default before deleting this plan.']);
            }
            if (DB::table('finance_budgets')->where('pricing_version_id', $version)->exists()
                || DB::table('workshops')->where('pricing_version_id', $version)->exists()) {
                throw ValidationException::withMessages(['version_id' => 'This allocation plan is used by a workshop or saved allocation and cannot be deleted.']);
            }
            DB::table('finance_pricing_versions')->where('id', $version)->delete();
        });

        return redirect()->route('admin.cost-centre.allocations')->with('success', 'Allocation plan deleted.');
    }

    public function defaultVersion(Request $request): RedirectResponse
    {
        $data = $request->validate(['version_id' => 'required|integer|exists:finance_pricing_versions,id']);
        DB::transaction(function () use ($data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $version = DB::table('finance_pricing_versions')->where('id', $data['version_id'])->where('is_snapshot', false)->firstOrFail();
            if ($version->archived) {
                throw ValidationException::withMessages(['version_id' => 'Restore this plan before setting it as default.']);
            }
            $ids = collect(json_decode($version->rules, true))->filter(fn ($rule) => $rule['rate_cents'] > 0)->pluck('category_id')->unique();
            $roundingCategory = json_decode($version->prices, true)['rounding_category_id'] ?? null;
            if ($roundingCategory) { $ids->push($roundingCategory); }
            if (DB::table('finance_categories')->whereIn('id', $ids)->where('active', false)->exists()) {
                throw ValidationException::withMessages(['version_id' => 'This allocation plan uses archived cost centres. Edit it before making it the default.']);
            }
            DB::table('finance_settings')->where('id', 1)->update(['default_pricing_version_id' => $data['version_id'], 'updated_at' => now()]);
        });

        return redirect()->route('admin.cost-centre.allocations', ['tab' => 'versions'])->with('success', 'Default allocation plan updated. Existing allocations are unchanged.');
    }

    public function editor(Request $request): View
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:finance_categories,id']);
        $centre = isset($data['id']) ? DB::table('finance_categories')->where('id', $data['id'])->first() : null;

        return view('admin.cost-centre.edit', compact('centre'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:finance_categories,id', 'name' => 'required|string|max:100', 'priority' => 'required|integer|min:1|max:1000', 'active' => 'required|boolean']);
        DB::transaction(function () use ($data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $centre = isset($data['id']) ? DB::table('finance_categories')->where('id', $data['id'])->first() : null;
            if ($centre && ! $data['active']) {
                if ($centre->kind !== 'cost') {
                    throw ValidationException::withMessages(['active' => 'This system cost centre cannot be archived.']);
                }
                $versions = collect([\App\Services\Finance\PricingVersion::forDate(today()->toDateString())]);
                foreach ($versions as $version) {
                    if ((json_decode($version->prices, true)['rounding_category_id'] ?? null) == $centre->id) {
                        throw ValidationException::withMessages(['active' => 'Choose another rounding cost centre in the default allocation plan first.']);
                    }
                    foreach (json_decode($version->rules, true) as $rule) {
                        if ((int) $rule['category_id'] === (int) $centre->id && (int) $rule['rate_cents'] > 0) {
                            throw ValidationException::withMessages(['active' => 'This cost centre is used by the default allocation plan. Remove it from that plan before archiving it.']);
                        }
                    }
                }
                $supplierUses = DB::table('finance_supplier_rules')->get()->contains(fn ($rule) => (float) (json_decode($rule->splits, true)[$centre->id] ?? 0) > 0);
                if ($supplierUses) {
                    throw ValidationException::withMessages(['active' => 'Reassign supplier defaults before archiving this cost centre.']);
                }
            }
            $name = trim($data['name']);
            if ($name === '' || mb_strtolower($name) === 'gst' || DB::table('finance_categories')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($centre, fn ($q) => $q->where('id', '!=', $centre->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Choose a unique cost centre name. GST is reserved for the system tax account.']);
            }
            $values = ['name' => $name, 'priority' => $data['priority'], 'active' => $data['active'], 'updated_at' => now()];
            if ($centre) {
                DB::table('finance_categories')->where('id', $centre->id)->update($values);
            } else {
                DB::table('finance_categories')->insert($values + ['kind' => 'cost', 'created_at' => now()]);
            }
        });

        return $request->expectsJson() ? response()->json(['message' => 'Cost centre saved.']) : redirect()->route('admin.cost-centre.index')->with('message', 'Cost centre saved.')->with('message-type', 'success');
    }

    public function show(Request $request, FinancePlanner $planner, string $centre): View
    {
        $category = DB::table('finance_categories')->where('id', $centre)->first();
        abort_unless($category !== null, 404);
        if (!$request->filled('list_sort')) {
            $request->query->set('list_sort', 'date');
            $request->query->set('list_direction', 'desc');
        }
        $records = $this->paginate(app(SiteListControls::class)->applyCollection($planner->costCentreLedger($category)));
        return view('admin.cost-centre.show', compact('category', 'records'));
    }

    public function transferEditor(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate(['from' => ['nullable', Rule::in(DB::table('finance_categories')->where('kind', 'cost')->pluck('id')->push('remuneration')->all())]]);
        $from = $data['from'] ?? null;
        $categories = DB::table('finance_categories')->where('kind', 'cost')->orderBy('name')->get();

        $remunerationAvailable = $planner->remunerationTransferAvailable();

        return view('admin.cost-centre.transfer', compact('categories', 'from', 'remunerationAvailable'));
    }

    public function transfer(Request $request, FinancePlanner $planner): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['from_category_id' => ['nullable', Rule::in(DB::table('finance_categories')->where('kind', 'cost')->pluck('id')->push('remuneration')->all())], 'token' => 'required_if:from_category_id,remuneration|nullable|uuid', 'category_id' => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'cost')->where('active', true)], 'amount' => 'required|numeric|min:0.01|max:10000000', 'reason' => 'required|string|max:255']);
        $planner->transfer($data, $request->user()->id);

        return $request->expectsJson() ? response()->json(['message' => 'Funds transferred. Transaction history is unchanged.']) : redirect()->route('admin.cost-centre.index')->with('message', 'Funds transferred.')->with('message-type', 'success');
    }

    public function gst(Request $request, FinancePlanner $planner): View
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m', 'tab' => ['nullable', Rule::in(['summary', 'income', 'expenses'])]]);
        $tab = $data['tab'] ?? 'summary';
        $month = Carbon::parse(($data['month'] ?? now()->format('Y-m')).'-01');
        if ($request->header('X-SM-Fragment') === 'record') {
            $settlement = DB::table('finance_gst_settlements')->where('period', $month->toDateString())->first();
            return view('admin.cost-centre.gst-settlement-form', compact('month', 'settlement'));
        }
        $historyStart = $month->copy()->subMonths(11);
        $totals = $planner->gstMonths(($tab === 'summary' ? $historyStart : $month)->toDateString(), $month->copy()->endOfMonth()->toDateString());
        $gst = $totals[$month->format('Y-m')] ?? ['sales' => 0, 'credits' => 0, 'net' => 0];
        $cash = $planner->cash();
        $settlements = $tab === 'summary' ? DB::table('finance_gst_settlements')->whereBetween('period', [$historyStart->toDateString(), $month->toDateString()])->get()->keyBy('period') : collect();
        $history = collect(range(0, 11))->map(function ($offset) use ($month, $totals, $settlements) {
            $period = $month->copy()->subMonths($offset);

            return ['month' => $period, 'settlement' => $settlements->get($period->toDateString())] + ($totals[$period->format('Y-m')] ?? ['sales' => 0, 'credits' => 0, 'net' => 0]);
        });

        $rows = collect();
        if ($tab === 'expenses') {
            foreach (Expense::whereBetween('paid_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->where('gst_amount', '!=', 0)->get() as $expense) {
                $rows->push(['date' => $expense->paid_on?->toDateString(), 'description' => $expense->supplier.' · '.$expense->description, 'amount' => -$planner->cents($expense->gst_amount), 'links' => [['label' => 'View expense', 'url' => route('admin.expense.edit', $expense)]]]);
            }
        } elseif ($tab === 'income') {
            foreach (Payment::with(['allocations.invoice', 'refundOf.allocations.invoice'])->whereBetween('received_on', [$month->toDateString().' 00:00:00', $month->copy()->endOfMonth()->toDateString().' 23:59:59'])->whereIn('kind', Payment::KINDS)->get() as $payment) {
                if (! $planner->received($payment)) {
                    continue;
                }
                $amount = $planner->cents(app(GstCalculator::class)->paymentGstAmount($payment));
                if (! $amount) {
                    continue;
                }
                $allocations = $payment->isRefund() && $payment->allocations->isEmpty() ? ($payment->refundOf->allocations ?? collect()) : $payment->allocations;
                $links = $allocations->filter(fn ($allocation) => $allocation->invoice !== null)->map(fn ($allocation) => ['label' => 'Invoice '.$allocation->invoice->invoice_number, 'url' => route('admin.invoice.edit', $allocation->invoice)])->unique('url')->values()->all();
                $rows->push(['date' => $payment->received_on?->toDateString(), 'description' => $payment->isRefund() ? 'Customer refund' : 'Payment received', 'amount' => $amount, 'links' => $links]);
            }
        }
        $rows = $rows->map(fn ($row) => $row + ['amount_display' => $row['amount'] / 100])->sortByDesc('date');
        $records = $this->paginate(app(SiteListControls::class)->applyCollection($rows));

        return view('admin.cost-centre.gst', compact('month', 'gst', 'cash', 'history', 'historyStart', 'tab', 'records'));
    }

    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $perPage = ListPageSize::resolve(25);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => request()->url()]))->withQueryString();
    }
}
