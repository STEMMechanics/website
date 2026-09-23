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
    public function context(Workshop $workshop, ?array $supplied = null): array
    {
        $invoice = Invoice::whereIn('id', app(WorkshopFunding::class)->invoiceIds($workshop->id))->first()
            ?? new Invoice(['issue_date' => $workshop->starts_at]);
        $context = app(InvoiceAllocation::class)->context($invoice, null, $supplied, $workshop);
        if ($supplied !== null) {
            $rules = json_decode($context['version']->rules, true, 512, JSON_THROW_ON_ERROR);
            $suppliable = collect($rules)->filter(fn ($rule) => ($rule['suppliable'] ?? false) || $rule['basis'] === 'venue_hour')->pluck('category_id')->all();
            if (array_diff(array_keys($supplied), $suppliable)) {
                throw ValidationException::withMessages(['supplied_categories' => 'Choose supplied items from this allocation plan.']);
            }
        }
        $context['fundingLines'] = app(WorkshopFunding::class)->lines($workshop->id);
        $context['invoicedFunding'] = Invoice::with(['lines', 'tickets'])->whereIn('id', $context['ids'])->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_WRITTEN_OFF])->get()->sum(fn ($invoice) => app(InvoiceAllocationParts::class)->total($invoice, $workshop->id));
        $context['awaitingFunding'] = max(0, $context['invoicedFunding'] - $context['income']['net']);
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
        $ids = $reportData ? $reportData->workshopInvoiceIds($workshop->id) : app(WorkshopFunding::class)->invoiceIds($workshop->id);
        $invoices = $reportData ? $reportData->allocationInvoices->only($ids)->sortBy('id') : Invoice::with(['lines', 'tickets', 'taxAdjustments', 'allocations.customerPayment.refundOf'])->whereIn('id', $ids)->orderBy('id')->get();
        $parts = app(InvoiceAllocationParts::class);
        $events = $parts->events($ids, $workshop->id, $reportData);
        $fundingInvoiceIds = $invoices->filter(fn ($invoice) => $invoice->lines->contains(fn ($line) => app(WorkshopFunding::class)->entries($line)->contains(fn ($entry) => ($entry->details_json['workshop']['linked_workshop_id'] ?? null) === $workshop->id)))->pluck('id')->all();
        $hasFundingPlan = $invoices->contains(fn ($invoice) => in_array($invoice->id, $fundingInvoiceIds, true) && ! in_array($invoice->status, [Invoice::STATUS_CANCELLED, Invoice::STATUS_WRITTEN_OFF], true));
        $pending = 0;
        $unresolved = false;
        foreach ($invoices as $invoice) {
            $unresolved = $unresolved || isset($parts->weights($invoice)['unresolved']);
            $received = app(FinancePlanner::class)->income([$invoice->id], null, $reportData)['gross'];
            $due = $invoice->status === Invoice::STATUS_CANCELLED ? 0 : (int) round($invoice->dueAmount() * 100);
            if (in_array($invoice->id, $fundingInvoiceIds, true) && ! in_array($invoice->status, [Invoice::STATUS_CANCELLED, Invoice::STATUS_WRITTEN_OFF], true)) continue;
            if ($invoice->status === Invoice::STATUS_DRAFT || ($invoice->status !== Invoice::STATUS_WRITTEN_OFF && $received < $due) || $received > $due) {
                $pending++;
            }

        }
        $pending += $tickets->filter(fn ($ticket) => ! $ticket->invoice_id && in_array($ticket->status, [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT], true))->count();
        $source = [
            'workshop' => [$workshop->starts_at?->toIso8601String(), $workshop->ends_at?->toIso8601String(), $workshop->status, $workshop->pricing_version_id, $workshop->hosted_for_organisation_id, $workshop->max_tickets],
            'tickets' => $tickets->map(fn ($ticket) => [$ticket->id, $ticket->status, $ticket->invoice_id, $ticket->invoice_line_id, $ticket->attended_at?->toIso8601String()])->all(),
            'invoices' => $invoices->map(fn ($invoice) => [$invoice->id, in_array($invoice->id, $fundingInvoiceIds, true) && in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED, Invoice::STATUS_SENT, Invoice::STATUS_PAID, Invoice::STATUS_OVERDUE], true) ? 'issued' : $invoice->status, $invoice->total_amount, $invoice->gst_amount, in_array($invoice->id, $fundingInvoiceIds, true) ? $invoice->lines->map(fn ($line) => $line->only(['line_number', 'kind', 'details_json', 'quantity', 'unit_price_ex_tax', 'tax_rate', 'line_total_ex_tax', 'tax_amount', 'line_total_inc_tax', 'source_type', 'source_id']))->all() : $invoice->lines->toArray(), $invoice->taxAdjustments->toArray()])->all(),
            // Funding receipts/refunds change available cash, not the approved cost plan.
            'events' => array_values(array_filter($events, fn ($event) => ! collect($fundingInvoiceIds)->contains(fn ($id) => str_ends_with((string) $event['id'], '-'.$id)))),
        ];
        if ($workshop->isCourse()) {
            $source['sessions'] = $workshop->effectiveScheduleEntries();
            $source['session_attendance'] = DB::table('workshop_session_attendance')
                ->where('workshop_id', $workshop->id)
                ->orderBy('session_id')
                ->orderBy('ticket_id')
                ->get(['session_id', 'ticket_id', 'attended_at'])
                ->map(fn ($attendance): array => [
                    $attendance->session_id,
                    $attendance->ticket_id,
                    $attendance->attended_at,
                ])
                ->all();
        }
        $hash = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
        // Settled cancellations with no retained receipts need no initial allocation.
        $noAllocationRequired = $workshop->status === 'cancelled' && ! $budget?->finalised_at
            && $pending === 0 && ! $unresolved && array_sum(array_column($events, 'gross')) === 0;
        $ready = ! $noAllocationRequired && ($hasFundingPlan || ($workshop->effectiveEndsAt() && $workshop->effectiveEndsAt()->lte(now()))) && $pending === 0 && ! $unresolved;
        $current = $budget && $budget->finalised_at && hash_equals((string) $budget->source_hash, $hash);
        $status = match (true) {
            (bool) $current => 'Finalised',
            (bool) $budget?->finalised_at => 'Allocation needs review',
            $noAllocationRequired => 'No allocation required',
            (bool) $ready => 'Ready for review',
            $pending > 0 => $pending.' payment outcomes outstanding',
            $unresolved => 'Invoice breakdown needs review',
            default => 'Estimated',
        };

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
            $workshops = Workshop::with('tickets')->where(fn ($query) => $query->where('ends_at', '<=', now())->orWhere('format', 'course'))->whereDate('starts_at', '>=', $from)->where(fn ($query) => $query->whereHas('tickets')->orWhereIn('id', app(WorkshopFunding::class)->linkedLines()->pluck('details_json.workshop.linked_workshop_id')))->orderBy('ends_at')->get();
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
        $data = \Illuminate\Support\Facades\Validator::make($data, ['source_hash' => 'required|string|size:64', 'revision' => 'nullable|string', 'override' => 'nullable|boolean', 'supplied_categories' => 'sometimes|array|max:100', 'supplied_categories.*' => 'boolean', 'targets' => 'required_if:override,1|array|min:1', 'targets.*' => 'required|numeric|min:0|max:10000000'])->validate();
        DB::transaction(function () use ($workshop, $data, $userId) {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $workshop = Workshop::whereKey($workshop->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($workshop);
            if (! $state['ready'] || ! hash_equals($state['hash'], $data['source_hash'])) {
                throw ValidationException::withMessages(['allocation' => 'Workshop or payment details changed, or outcomes remain outstanding. Refresh and review before finalising.']);
            }
            $context = $this->context($workshop, isset($data['supplied_categories']) ? array_map(fn ($value) => (bool) $value, $data['supplied_categories']) : null);
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
