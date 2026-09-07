<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceAllocationController extends Controller
{
    public function edit(Request $request, Invoice $invoice, InvoiceAllocation $allocations): View
    {
        $data = $request->validate(['version_id' => 'nullable|integer|exists:finance_pricing_versions,id']);

        $retained = $allocations->context($invoice)['version']->id;
        \App\Services\Finance\PricingVersion::assertSelectable(isset($data['version_id']) ? (int) $data['version_id'] : null, (int) $retained);

        return view('admin.invoice.allocation', ['invoice' => $invoice, 'allocation' => $allocations->context($invoice, isset($data['version_id']) ? (int) $data['version_id'] : null)]);
    }

    public function store(Request $request, Invoice $invoice, InvoiceAllocation $allocations, FinancePlanner $planner): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['version_id' => 'nullable|integer|exists:finance_pricing_versions,id', 'budget_id' => 'nullable|integer', 'targets' => 'required_unless:use_defaults,1|array|min:1', 'targets.*' => 'required|numeric|min:0|max:10000000']);
        $request->validate(['line_details' => 'nullable|array', 'line_details.*' => 'array:workshop_hours,workshop_seats,venue_supplied,travel_units,supplied_categories', 'use_defaults' => 'nullable|boolean', 'supplied_categories' => 'nullable|array|max:100', 'supplied_categories.*' => 'boolean']);
        DB::transaction(function () use ($request, $invoice, $allocations, $planner, $data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $retained = $allocations->context($invoice)['version']->id;
            \App\Services\Finance\PricingVersion::assertSelectable(isset($data['version_id']) ? (int) $data['version_id'] : null, (int) $retained);
            foreach ($request->input('line_details', []) as $id => $details) {
                $line = $invoice->lines()->whereIn('kind', ['workshop', 'travel'])->whereKey($id)->firstOrFail();
                $item = \App\Services\Finance\WorkshopLine::normalize($line->toArray());
                $item = \App\Services\Finance\WorkshopLine::normalize(array_merge($item, $details));
                if (abs((float) $item['quantity'] - (float) $line->quantity) > 0.001) {
                    throw ValidationException::withMessages(['line_details' => 'Hours × seats (or billable travel units) must match the existing quantity. Change the line in the invoice editor if its billed quantity needs correcting.']);
                }
                $line->update(['details_json' => $item['details_json'] ?? []]);
            }
            $context = $allocations->context($invoice->fresh(), isset($data['version_id']) ? (int) $data['version_id'] : null, $request->has('supplied_categories') ? array_map(fn ($value) => (bool) $value, $request->input('supplied_categories')) : null);
            if ($context['warning']) {
                throw ValidationException::withMessages(['targets' => $context['warning']]);
            }
            $budget = $context['budget'];
            if ($budget && isset($data['version_id']) && (int) $data['version_id'] !== (int) $budget->pricing_version_id) {
                throw ValidationException::withMessages(['version_id' => 'This allocation keeps its original version. Choose versions when allocating records for the first time.']);
            }
            if ((string) ($data['budget_id'] ?? '') !== (string) ($budget->id ?? '')) {
                throw ValidationException::withMessages(['targets' => 'The allocation changed. Close and reopen the editor before saving.']);
            }
            if ($request->boolean('use_defaults')) {
                if ($context['automaticWarning'] || ! $context['suggestedTargets']) {
                    throw ValidationException::withMessages(['targets' => $context['automaticWarning'] ?? 'Add a workshop or travel breakdown before using pricing defaults.']);
                }
                $allocations->sync($invoice->fresh(), $request->user()->id, true, isset($data['version_id']) ? (int) $data['version_id'] : null, $request->has('supplied_categories') ? array_map(fn ($value) => (bool) $value, $request->input('supplied_categories')) : null);

                return;
            }
            $targets = array_filter(array_map($planner->cents(...), $data['targets']));
            if (! $targets || array_diff(array_keys($targets), $context['categories']->pluck('id')->all())) {
                throw ValidationException::withMessages(['targets' => 'Allocate a positive amount to valid cost centres.']);
            }
            if ($budget) {
                DB::table('finance_budget_revisions')->insert(['budget_id' => $budget->id, 'before' => json_encode(['assumptions' => $planner->decode($budget->assumptions), 'targets' => $context['targets']]), 'after' => json_encode(['assumptions' => $context['assumptions'], 'targets' => $targets, 'changed_by' => $request->user()->id]), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('finance_budgets')->where('id', $budget->id)->update(['targets' => json_encode($targets), 'assumptions' => json_encode($context['assumptions']), 'manual' => true, 'updated_at' => now()]);
            } else {
                $row = ['workshop_id' => $context['workshopId'], 'name' => $context['workshop']->title ?? 'Invoice '.$invoice->invoice_number, 'date' => $context['date'], 'invoice_ids' => $context['ids'], 'assumptions' => $context['assumptions'], 'version_id' => $context['version']->id, 'targets' => $targets, 'warning' => null, 'manual' => true];
                $planner->apply(['token' => (string) Str::uuid(), 'rows' => [$row]], $request->user()->id, [0]);
            }
        });
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cost centre allocation saved.', 'target' => 'invoice-cost-centres', 'html' => view($request->boolean('inline') ? 'admin.invoice.allocation-form' : 'admin.invoice.allocation-summary', ['inline' => true, 'invoice' => $invoice, 'allocation' => $allocations->context($invoice)])->render()]);
        }

        return redirect()->route('admin.invoice.edit', $invoice)->with('message', 'Cost centre allocation saved.')->with('message-type', 'success');
    }
}
