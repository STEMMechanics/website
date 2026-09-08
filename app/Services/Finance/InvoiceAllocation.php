<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceAllocation
{
    /** Save inside the caller's finance-settings transaction lock. */
    public function saveManual(Invoice $invoice, array $context, array $targets, string $userId): void
    {
        $planner = app(FinancePlanner::class);
        $budget = $context['budget'];
        if ($budget) {
            DB::table('finance_budget_revisions')->insert(['budget_id' => $budget->id, 'before' => json_encode(['assumptions' => $planner->decode($budget->assumptions), 'targets' => $context['targets']]), 'after' => json_encode(['assumptions' => $context['assumptions'], 'targets' => $targets, 'changed_by' => $userId]), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('finance_budgets')->where('id', $budget->id)->update(['targets' => json_encode($targets), 'assumptions' => json_encode($context['assumptions']), 'manual' => true, 'updated_at' => now()]);
        } else {
            $row = ['workshop_id' => $context['workshopId'], 'name' => $context['workshop']->title ?? 'Invoice '.$invoice->invoice_number, 'date' => $context['date'], 'invoice_ids' => $context['ids'], 'assumptions' => $context['assumptions'], 'version_id' => $context['version']->id, 'targets' => $targets, 'warning' => null, 'manual' => true];
            $planner->apply(['token' => (string) Str::uuid(), 'rows' => [$row]], $userId, [0]);
        }
    }

    public function context(Invoice $invoice, ?int $versionId = null, ?array $supplied = null, ?Workshop $workshop = null): array
    {
        $planner = app(FinancePlanner::class);
        $parts = app(InvoiceAllocationParts::class);
        $budget = $workshop
            ? DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first()
            : DB::table('finance_budgets')->whereNull('workshop_id')->whereIn('id', DB::table('finance_budget_invoices')->where('invoice_id', $invoice->id)->select('budget_id'))->first();
        $workshopId = $workshop?->id;
        $ids = $workshop ? Ticket::where('workshop_id', $workshop->id)->whereNotNull('invoice_id')->pluck('invoice_id')->unique()->all() : [$invoice->id];
        $warning = ! $workshop && $parts->total($invoice) <= 0 && $invoice->tickets()->exists() ? 'Allocation managed by workshop.' : null;
        $date = $invoice->issue_date?->toDateString() ?? today()->toDateString();
        if ($workshop) {
            $date = $workshop->starts_at->toDateString();
        }
        $version = PricingVersion::forDate($date, $budget ? (int) $budget->pricing_version_id : ($versionId ?? $workshop?->pricing_version_id));
        $assumptions = $budget ? $planner->decode($budget->assumptions) : ['participants' => $workshop ? Ticket::where('workshop_id', $workshopId)->whereIn('status', [Ticket::STATUS_PAID, Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT])->count() : 0, 'hours' => $workshop ? max(0, $workshop->starts_at->diffInMinutes($workshop->ends_at)) / 60 : 0, 'travel_minutes' => 0, 'venue_supplied' => false];
        if ($supplied !== null && $workshop) {
            $assumptions['supplied_categories'] = $supplied;
        }
        $rules = $planner->decode($version->rules);
        $suggestedTargets = [];
        $automaticWarning = null;
        if ($workshop) {
            $assumptions['pricing_participants'] ??= min($workshop->max_tickets ?: PHP_INT_MAX, (int) ($planner->decode($version->prices)['pricing_participants'] ?? 10));
            $assumptions = array_merge($assumptions, ['participants' => Ticket::where('workshop_id', $workshopId)->whereIn('status', Ticket::activePurchasedStatuses())->count(), 'hours' => max(0, $workshop->starts_at->diffInMinutes($workshop->ends_at)) / 60, 'venue_supplied' => (bool) ($assumptions['venue_supplied'] ?? false)]);
            $suggestedTargets = $planner->targets($rules, $assumptions);
        } else {
            $assumptions = ['source' => 'invoice_lines', 'lines' => []];
            foreach (WorkshopLine::allocationLines($invoice->lines()->get()->filter(fn ($line) => $parts->lineKey($line, $invoice->loadMissing('tickets')) === 'invoice')->toArray()) as $lineData) {
                $line = (object) $lineData;
                if ($line->kind === 'workshop') {
                    $details = $line->details_json['workshop'] ?? null;
                    if (! is_array($details) || empty($details['hours']) || empty($details['seats'])) {
                        $automaticWarning = 'Add hours and seats to each workshop line before applying pricing defaults. Existing combined lines need an explicit breakdown.';

                        continue;
                    }
                    $inputs = ['participants' => (int) $details['seats'], 'hours' => (float) $details['hours'], 'venue_supplied' => (bool) ($details['venue_supplied'] ?? true), 'travel_minutes' => 0, 'supplied_categories' => $details['supplied_categories'] ?? [], 'gst_applicable' => (float) $line->tax_rate > 0];
                    $assumptions['lines'][] = ['line_number' => $line->line_number] + $inputs;
                    foreach ($planner->targets(array_filter($rules, fn ($rule) => $rule['basis'] !== 'travel'), $inputs) as $id => $amount) {
                        $suggestedTargets[$id] = ($suggestedTargets[$id] ?? 0) + $amount;
                    }
                } elseif ($line->kind === 'travel') {
                    $units = $line->details_json['travel']['billable_units'] ?? null;
                    if ($units === null) {
                        $automaticWarning = 'Set the billable 15-minute units on travel lines before applying pricing defaults.';

                        continue;
                    }
                    $assumptions['lines'][] = ['kind' => 'travel', 'units' => (int) $units, 'gst_applicable' => (float) $line->tax_rate > 0, 'supplied_categories' => $line->details_json['travel']['supplied_categories'] ?? []];
                    foreach ($rules as $rule) {
                        if ($rule['basis'] === 'travel') {
                            $id = $rule['category_id'];
                            $suggestedTargets[$id] = ($suggestedTargets[$id] ?? 0) + (int) round((! empty($rule['suppliable']) && ($line->details_json['travel']['supplied_categories'][$id] ?? false) ? 0 : $units) * $rule['rate_cents']);
                        }
                    }
                }
            }
        }
        $targets = $budget ? $planner->decode($budget->targets) : $suggestedTargets;

        $income = $parts->income($ids, $workshopId);
        $funding = $planner->funding($targets, $income['net'], ($budget->manual ?? false) ? [] : $planner->rounding($version, $assumptions));
        $total = Invoice::with(['lines', 'tickets'])->whereIn('id', $ids)->get()->sum(fn ($item) => $parts->total($item, $workshopId));

        $editorTargets = $targets;
        $rounding = ($budget->manual ?? false) ? [] : $planner->rounding($version, $assumptions);
        $roundingAmount = min(max(0, $total - array_sum($targets)), (int) ($rounding['limit'] ?? 0));
        if (! empty($rounding['category_id']) && $roundingAmount > 0) {
            $id = $rounding['category_id'];
            $editorTargets[$id] = ($editorTargets[$id] ?? 0) + $roundingAmount;
        }
        $categories = DB::table('finance_categories')->where(fn ($query) => $query->where('active', true)->orWhereIn('id', array_keys($editorTargets)))->orderBy('priority')->get();

        return compact('budget', 'categories', 'targets', 'income', 'funding', 'total', 'ids', 'workshopId', 'workshop', 'version', 'assumptions', 'date', 'warning', 'suggestedTargets', 'automaticWarning', 'editorTargets', 'roundingAmount');
    }

    public function sync(Invoice $invoice, ?string $userId, bool $force = false, ?int $versionId = null, ?array $supplied = null): void
    {
        DB::transaction(function () use ($invoice, $userId, $force, $versionId, $supplied): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $context = $this->context($invoice, $versionId, $supplied);
            $budget = $context['budget'];
            $userId ??= $context['version']->created_by;
            if (! $userId) {
                return;
            }
            if ($context['warning'] || $context['automaticWarning'] || (! $context['suggestedTargets'] && ! $budget) || ($budget && $budget->manual && ! $force)) {
                return;
            }
            $planner = app(FinancePlanner::class);
            if ($budget) {
                if ($planner->decode($budget->targets) !== $context['suggestedTargets'] || $planner->decode($budget->assumptions) !== $context['assumptions'] || $budget->manual) {
                    DB::table('finance_budget_revisions')->insert(['budget_id' => $budget->id, 'before' => json_encode(['assumptions' => $planner->decode($budget->assumptions), 'targets' => $planner->decode($budget->targets)]), 'after' => json_encode(['assumptions' => $context['assumptions'], 'targets' => $context['suggestedTargets'], 'changed_by' => $userId]), 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('finance_budgets')->where('id', $budget->id)->update(['targets' => json_encode($context['suggestedTargets']), 'assumptions' => json_encode($context['assumptions']), 'manual' => false, 'updated_at' => now()]);
                }
            } else {
                $row = ['workshop_id' => $context['workshopId'], 'name' => $context['workshop']->title ?? 'Invoice '.$invoice->invoice_number, 'date' => $context['date'], 'invoice_ids' => $context['ids'], 'assumptions' => $context['assumptions'], 'version_id' => $context['version']->id, 'targets' => $context['suggestedTargets'], 'warning' => null, 'manual' => false];
                $planner->apply(['token' => (string) Str::uuid(), 'rows' => [$row]], $userId, [0]);
            }
        });
    }
}
