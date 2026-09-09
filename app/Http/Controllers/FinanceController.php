<?php

namespace App\Http\Controllers;

use App\Services\Finance\FinancePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, FinancePlanner $planner): View|RedirectResponse
    {
        if ($request->query('tab') === 'commitments') {
            return redirect()->route('admin.finance.index');
        }
        if ($request->query('tab') === 'budgets') {
            return redirect()->route('admin.cost-centre.allocations', ['tab' => 'allocations'] + $request->only(['q', 'sort', 'direction', 'page', 'per_page']));
        }
        if ($request->query('tab') === 'gst') {
            return redirect()->route('admin.cost-centre.gst', $request->only('month'));
        }
        if ($request->query('tab') === 'drawings') {
            return redirect()->route('admin.timesheet.index', ['tab' => 'drawings'] + $request->only(['page', 'per_page']));
        }
        if ($request->query('tab') === 'time') {
            return redirect()->route('admin.timesheet.index', $request->only('fortnight'));
        }
        if ($request->query('tab') === 'suppliers') {
            return redirect()->route('admin.supplier.index');
        }
        if ($request->query('tab') === 'pricing') { return redirect()->route('admin.cost-centre.allocations'); }
        if ($request->query('tab') === 'setup') { return redirect()->route('admin.site_option.index', ['search' => 'finance.']); }
        return redirect()->route('admin.cost-centre.index');
    }

    public function category(Request $request): RedirectResponse|JsonResponse
    {
        return app(CostCentreController::class)->store($request);
    }

    public function pricing(Request $request, FinancePlanner $planner): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'travel_rounding_step' => ['nullable', Rule::in([0, 10, 50, 100, 500])], 'rounding_step' => ['nullable', Rule::in([0, 10, 50, 100, 500])], 'rounding_category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('active', true)], 'pricing_participants' => 'nullable|integer|min:1|max:10000', 'edit_id' => 'nullable|integer|exists:finance_pricing_versions,id', 'make_default' => 'nullable|boolean', 'return_to' => 'nullable|in:versions', 'travel_price' => 'nullable|numeric|min:0|max:100000', 'travel_free_minutes' => 'nullable|integer|min:0|max:10000', 'name' => 'required|string|max:100', 'effective_from' => 'sometimes|date_format:Y-m-d', 'rules' => 'required|array|min:1|max:100',
            'rules.*.category_id' => ['required', 'integer', Rule::exists('finance_categories', 'id')->where('active', true)], 'rules.*.basis' => ['required', Rule::in(['workshop', 'participant', 'hour', 'venue_hour', 'travel'])],
            'rules.*.suppliable' => 'nullable|boolean', 'rules.*.venue_default' => 'nullable|boolean', 'rules.*.rate' => 'required|numeric|min:0|max:100000',
            'public' => 'sometimes|array|size:4', 'public.*' => 'required|numeric|min:0|max:100000', 'organisation' => 'sometimes|array|size:4', 'organisation.*' => 'required|numeric|min:0|max:100000',
        ]);
        $rules = array_map(fn ($row) => ['category_id' => (int) $row['category_id'], 'basis' => $row['basis'], 'rate_cents' => $planner->cents($row['rate']), 'suppliable' => (bool) ($row['suppliable'] ?? false), 'venue_default' => (bool) ($row['venue_default'] ?? false)], $data['rules']);
        DB::transaction(function () use ($request, $data, $rules, $planner): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $ids = array_unique(array_column($rules, 'category_id'));
            if (DB::table('finance_categories')->whereIn('id', $ids)->where('active', true)->count() !== count($ids)) {
                throw ValidationException::withMessages(['rules' => 'Choose active cost centres for the allocation plan.']);
            }
            $wasDefault = false;
            $archived = false;
            if (! empty($data['edit_id'])) {
                $previous = DB::table('finance_pricing_versions')->where('id', $data['edit_id'])->where('is_snapshot', false)->first();
                if (! $previous) {
                    throw ValidationException::withMessages(['edit_id' => 'This plan has been updated. Reopen the editor and try again.']);
                }
                $archived = (bool) $previous->archived;
                if ($archived && $request->boolean('make_default')) {
                    throw ValidationException::withMessages(['make_default' => 'Restore this plan before setting it as default.']);
                }
                $wasDefault = (int) DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id') === $previous->id;
                // Existing allocations retain this immutable revision, including automatic recalculations.
                DB::table('finance_pricing_versions')->where('id', $previous->id)->update(['is_snapshot' => true, 'updated_at' => now()]);
            }
            $versionId = DB::table('finance_pricing_versions')->insertGetId(['archived' => $archived, 'name' => $data['name'], 'effective_from' => $data['effective_from'] ?? today()->toDateString(), 'rules' => json_encode($rules), 'prices' => json_encode(['travel_rounding_step' => (int) ($data['travel_rounding_step'] ?? 0), 'rounding_step' => (int) ($data['rounding_step'] ?? 0), 'rounding_category_id' => isset($data['rounding_category_id']) ? (int) $data['rounding_category_id'] : null, 'pricing_participants' => (int) ($data['pricing_participants'] ?? 10), 'public' => array_map($planner->cents(...), $data['public'] ?? []), 'organisation' => array_map($planner->cents(...), $data['organisation'] ?? []), 'travel_cents' => (int) round(array_sum(array_column(array_filter($rules, fn ($rule) => $rule['basis'] === 'travel'), 'rate_cents')) * 1.1), 'travel_free_minutes' => (int) ($data['travel_free_minutes'] ?? 30)]), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            if ($request->boolean('make_default') || $wasDefault) {
                DB::table('finance_settings')->where('id', 1)->update(['default_pricing_version_id' => $versionId, 'updated_at' => now()]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Allocation plan saved. Existing allocations are unchanged.']);
        }
        if (($data['return_to'] ?? '') === 'versions') {
            return redirect()->route('admin.cost-centre.allocations', ['tab' => 'versions'])->with('success', 'Allocation plan saved. Existing allocations are unchanged.');
        }

        return $this->back('pricing', 'Allocation plan saved. Existing allocations are unchanged.');
    }

    public function transfer(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['from_category_id' => ['nullable', Rule::exists('finance_categories', 'id')->where('kind', 'cost')], 'category_id' => ['required', Rule::exists('finance_categories', 'id')->where('kind', 'cost')], 'budget_id' => 'nullable|integer|exists:finance_budgets,id', 'amount' => 'required|numeric|min:0.01|max:10000000', 'reason' => 'required|string|max:255']);
        $planner->transfer($data, $request->user()->id);

        return $this->back('overview', 'Funds transferred. Workshop revenue and its shortfall remain unchanged.');
    }

    public function time(Request $request, FinancePlanner $planner): RedirectResponse|JsonResponse
    {
        return app(TimesheetController::class)->store($request, $planner);
    }

    public function drawing(Request $request, FinancePlanner $planner): RedirectResponse
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'token' => 'required|uuid', 'purpose' => ['nullable', Rule::in(['time', 'contribution'])]]);
        $planner->prepareDrawing($request->user()->id, $planner->cents($data['amount']), $data['token'], $data['purpose'] ?? 'time');

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

    public function settlement(Request $request, FinancePlanner $planner): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['settlement_id' => 'nullable|integer|exists:finance_gst_settlements,id', 'period' => 'required|date_format:Y-m', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required|numeric|between:-10000000,10000000', 'reference' => 'nullable|string|max:255']);
        DB::transaction(function () use ($data, $planner, $request) {
            $existing = DB::table('finance_gst_settlements')->where('period', $data['period'].'-01')->lockForUpdate()->first();
            if (!empty($data['settlement_id'])) {
                if (!$existing || (int) $existing->id !== (int) $data['settlement_id']) {
                    throw ValidationException::withMessages(['period' => 'The settlement month cannot be changed.']);
                }
            } elseif ($existing) {
                throw ValidationException::withMessages(['period' => 'This month already has a settlement.']);
            }
            $values = ['paid_on' => $data['paid_on'], 'cents' => $planner->cents($data['amount']), 'reference' => $data['reference'] ?? '', 'updated_at' => now()];
            if ($existing) {
                DB::table('finance_gst_settlements')->where('id', $existing->id)->update($values);
            } else {
                DB::table('finance_gst_settlements')->insert($values + ['period' => $data['period'].'-01', 'created_by' => $request->user()->id, 'created_at' => now()]);
            }
        });
        if ($request->expectsJson()) return response()->json(['message' => 'GST settlement saved.']);

        return redirect()->route('admin.cost-centre.gst', ['month' => $data['period']])->with('message', 'GST settlement recorded.')->with('message-type', 'success');
    }

    private function back(string $tab, ?string $message = null): RedirectResponse
    {
        if ($tab === 'drawings') {
            return redirect()->route('admin.timesheet.index', ['tab' => 'drawings'])->with('success', $message);
        }

        return ($tab === 'budgets' ? redirect()->route('admin.cost-centre.allocations', ['tab' => 'allocations']) : redirect()->route('admin.finance.index', ['tab' => $tab]))->with('success', $message);
    }
}
