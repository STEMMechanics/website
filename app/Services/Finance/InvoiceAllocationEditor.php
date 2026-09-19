<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceAllocationEditor
{
    public function save(Invoice $invoice, array $input, string $userId): void
    {
        $allocations = app(InvoiceAllocation::class);
        $planner = app(FinancePlanner::class);
        $data = Validator::make($input, ['version_id' => 'nullable|integer|exists:finance_pricing_versions,id', 'budget_id' => 'nullable|integer', 'targets' => 'required_unless:use_defaults,1|array|min:1', 'targets.*' => 'required|numeric|min:0|max:10000000'])->validate();
        Validator::make($input, ['line_details' => 'nullable|array', 'line_details.*' => 'array:workshop_hours,workshop_seats,venue_supplied,travel_units,supplied_categories', 'use_defaults' => 'nullable|boolean', 'supplied_categories' => 'nullable|array|max:100', 'supplied_categories.*' => 'boolean'])->validate();
        DB::transaction(function () use ($input, $invoice, $allocations, $planner, $data, $userId): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $retained = $allocations->context($invoice)['version']->id;
            PricingVersion::assertSelectable(isset($data['version_id']) ? (int) $data['version_id'] : null, (int) $retained);
            foreach (($input['line_details'] ?? []) as $id => $details) {
                $line = $invoice->lines()->whereIn('kind', ['workshop', 'travel'])->whereKey($id)->firstOrFail();
                $item = WorkshopLine::normalize($line->toArray());
                $item = WorkshopLine::normalize(array_merge($item, $details));
                if (abs((float) $item['quantity'] - (float) $line->quantity) > 0.001) {
                    throw ValidationException::withMessages(['line_details' => 'Hours × seats (or billable travel units) must match the existing quantity. Change the line in the invoice editor if its billed quantity needs correcting.']);
                }
                $line->update(['details_json' => $item['details_json'] ?? []]);
            }
            $context = $allocations->context($invoice->fresh(), isset($data['version_id']) ? (int) $data['version_id'] : null, array_key_exists('supplied_categories', $input) ? array_map(fn ($value) => (bool) $value, $input['supplied_categories']) : null);
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
            if ((bool) ($input['use_defaults'] ?? false)) {
                if ($context['automaticWarning'] || ! $context['suggestedTargets']) {
                    throw ValidationException::withMessages(['targets' => $context['automaticWarning'] ?? 'Add a workshop or travel breakdown before using pricing defaults.']);
                }
                $allocations->sync($invoice->fresh(), $userId, true, isset($data['version_id']) ? (int) $data['version_id'] : null, array_key_exists('supplied_categories', $input) ? array_map(fn ($value) => (bool) $value, $input['supplied_categories']) : null);

                return;
            }
            $targets = array_filter(array_map($planner->cents(...), $data['targets']));
            if (! $targets || array_diff(array_keys($targets), $context['categories']->pluck('id')->all())) {
                throw ValidationException::withMessages(['targets' => 'Allocate a positive amount to valid cost centres.']);
            }
            $allocations->saveManual($invoice, $context, $targets, $userId);
        });
    }
}
