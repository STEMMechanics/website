<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkshopAllocation
{
    public function context(Workshop $workshop): array
    {
        $invoice = Invoice::whereIn('id', Ticket::where('workshop_id', $workshop->id)->whereNotNull('invoice_id')->select('invoice_id'))->first()
            ?? new Invoice(['issue_date' => $workshop->starts_at]);
        $context = app(InvoiceAllocation::class)->context($invoice, null, null, $workshop);
        $context['total'] = max(0, $context['income']['net']);
        if (! ($context['budget']->manual ?? false)) {
            $context['targets'] = $context['suggestedTargets'];
        }

        return $context;
    }

    public function state(Workshop $workshop, ?object $budget = null, ?FinanceReportData $reportData = null): array
    {
        if (! $reportData && ! $budget) {
            $budget = DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first();
        }
        $tickets = ($reportData ? $workshop->loadMissing('tickets')->tickets : $workshop->tickets()->get())->sortBy('id');
        $ids = $tickets->pluck('invoice_id')->filter()->unique()->all();
        $invoices = $reportData ? $reportData->allocationInvoices->only($ids)->sortBy('id') : Invoice::with(['lines', 'tickets', 'taxAdjustments', 'allocations.customerPayment.refundOf'])->whereIn('id', $ids)->orderBy('id')->get();
        $parts = app(InvoiceAllocationParts::class);
        $events = $parts->events($ids, $workshop->id, $reportData);
        $pending = 0;
        $unresolved = false;
        foreach ($invoices as $invoice) {
            $unresolved = $unresolved || isset($parts->weights($invoice)['unresolved']);
            $received = app(FinancePlanner::class)->income([$invoice->id], null, $reportData)['gross'];
            $due = $invoice->status === Invoice::STATUS_CANCELLED ? 0 : (int) round($invoice->dueAmount() * 100);
            if ($invoice->status === Invoice::STATUS_DRAFT || ($invoice->status !== Invoice::STATUS_WRITTEN_OFF && $received < $due) || $received > $due) {
                $pending++;
            }

        }
        $pending += $tickets->filter(fn ($ticket) => ! $ticket->invoice_id && in_array($ticket->status, [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT], true))->count();
        $source = [
            'workshop' => [$workshop->starts_at?->toIso8601String(), $workshop->ends_at?->toIso8601String(), $workshop->status, $workshop->pricing_version_id, $workshop->hosted_for_organisation_id, $workshop->max_tickets],
            'tickets' => $tickets->map(fn ($ticket) => [$ticket->id, $ticket->status, $ticket->invoice_id, $ticket->invoice_line_id, $ticket->attended_at?->toIso8601String()])->all(),
            'invoices' => $invoices->map(fn ($invoice) => [$invoice->id, $invoice->status, $invoice->total_amount, $invoice->gst_amount, $invoice->lines->toArray(), $invoice->taxAdjustments->toArray()])->all(),
            'events' => $events,
        ];
        $hash = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
        $ready = $workshop->ends_at && $workshop->ends_at->lte(now()) && $pending === 0 && ! $unresolved;
        $current = $budget && $budget->finalised_at && hash_equals((string) $budget->source_hash, $hash);
        $status = $current ? 'Finalised' : ($budget?->finalised_at ? 'Allocation needs review' : ($ready ? 'Ready to finalise' : ($pending ? $pending.' payment outcomes outstanding' : ($unresolved ? 'Invoice breakdown needs review' : 'Estimated'))));

        return compact('hash', 'ready', 'current', 'status', 'pending', 'unresolved');
    }

    public function isCurrent(object $budget, ?FinanceReportData $reportData = null): bool
    {
        if (! $budget->workshop_id) {
            return true;
        }
        if (! ($budget->finalised_at ?? null)) {
            return false;
        }

        return app(RequestMemo::class)->remember('workshop-allocation-current:'.$budget->id, function () use ($budget, $reportData) {
            $workshop = $reportData?->workshops->get($budget->workshop_id) ?? Workshop::find($budget->workshop_id);

            return $workshop && $this->state($workshop, $budget, $reportData)['current'];
        });
    }

    public function attention(): array
    {
        return app(RequestMemo::class)->remember('workshop-allocation-attention', function () {
            $from = DB::table('finance_settings')->where('id', 1)->value('opening_date') ?? FinancePlanner::HISTORY_START;
            $rows = [];
            $workshops = Workshop::with('tickets')->where('ends_at', '<=', now())->whereDate('starts_at', '>=', $from)->whereHas('tickets')->orderBy('ends_at')->get();
            $budgets = DB::table('finance_budgets')->whereIn('workshop_id', $workshops->pluck('id'))->get()->keyBy('workshop_id');
            $reportData = new FinanceReportData(collect(), $workshops->values()->map(fn ($workshop, $index) => $budgets->get($workshop->id) ?? (object) ['id' => -$index - 1, 'workshop_id' => $workshop->id, 'pricing_version_id' => $workshop->pricing_version_id]));
            foreach ($workshops as $workshop) {
                $state = $this->state($workshop, $budgets->get($workshop->id), $reportData);
                if (($state['ready'] && ! $state['current']) || $state['status'] === 'Allocation needs review') {
                    $rows[] = ['workshop' => $workshop, 'status' => $state['status']];
                }
            }

            return $rows;
        });
    }

    public function finalise(Workshop $workshop, array $data, string $userId): void
    {
        DB::transaction(function () use ($workshop, $data, $userId) {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $workshop = Workshop::whereKey($workshop->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($workshop);
            if (! $state['ready'] || ! hash_equals($state['hash'], $data['source_hash'])) {
                throw ValidationException::withMessages(['allocation' => 'Workshop or payment details changed, or outcomes remain outstanding. Refresh and review before finalising.']);
            }
            $context = $this->context($workshop);
            $budget = $context['budget'];
            if (($budget ? hash('sha256', json_encode((array) $budget)) : '') !== (string) ($data['revision'] ?? '')) {
                throw ValidationException::withMessages(['allocation' => 'This allocation changed. Refresh before finalising.']);
            }
            $planner = app(FinancePlanner::class);
            $manual = (bool) ($data['override'] ?? false);
            $targets = $manual ? array_map($planner->cents(...), $data['targets'] ?? []) : $context['suggestedTargets'];
            if (array_diff(array_keys($targets), $context['categories']->pluck('id')->all())) {
                throw ValidationException::withMessages(['targets' => 'Choose valid cost centres.']);
            }
            $snapshot = ['targets' => $targets, 'assumptions' => $context['assumptions'], 'source_hash' => $state['hash'], 'income' => $context['income'], 'changed_by' => $userId, 'finalised_at' => now()->toIso8601String()];
            if (! $budget) {
                $planner->apply(['token' => (string) Str::uuid(), 'rows' => [['workshop_id' => $workshop->id, 'name' => $workshop->title, 'date' => $context['date'], 'invoice_ids' => $context['ids'], 'assumptions' => $context['assumptions'], 'version_id' => $context['version']->id, 'targets' => $targets, 'manual' => $manual, 'warning' => null]]], $userId, [0]);
                $budget = DB::table('finance_budgets')->where('workshop_id', $workshop->id)->first();
            }
            DB::table('finance_budget_revisions')->insert(['budget_id' => $budget->id, 'before' => json_encode((array) $budget), 'after' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('finance_budgets')->where('id', $budget->id)->update(['targets' => json_encode($targets), 'assumptions' => json_encode($context['assumptions']), 'manual' => $manual, 'finalised_at' => now(), 'finalised_by' => $userId, 'source_hash' => $state['hash'], 'updated_at' => now()]);
            foreach ($context['ids'] as $id) {
                DB::table('finance_budget_invoices')->updateOrInsert(['budget_id' => $budget->id, 'invoice_id' => $id]);
            }
        });
    }
}
