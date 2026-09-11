<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancePlanner
{
    // A null opening date means the complete recorded history, starting from zero.
    // This is the earliest date supported by MySQL's date/time columns.
    public const HISTORY_START = '1000-01-01';

    public function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function decode(mixed $json): array
    {
        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function targets(array $rules, array $inputs): array
    {
        $targets = [];
        foreach ($rules as $rule) {
            $units = match ($rule['basis']) {
                'participant' => $inputs['participants'],
                'hour' => $inputs['hours'],
                'travel' => (int) ceil(max(0, $inputs['travel_minutes'] - ($inputs['travel_free_minutes'] ?? 30)) / 15),
                'venue_hour' => ($inputs['supplied_categories'][$rule['category_id']] ?? $inputs['venue_supplied']) ? 0 : $inputs['hours'],
                default => 1,
            };
            if (! empty($rule['suppliable']) && ($inputs['supplied_categories'][$rule['category_id']] ?? (! empty($rule['venue_default']) && ($inputs['venue_supplied'] ?? false)))) {
                $units = 0;
            }
            $cents = (int) round($rule['rate_cents'] * $units);
            $id = $rule['category_id'];
            $targets[$id] = ($targets[$id] ?? 0) + $cents;
        }

        return $targets;
    }

    /** Build a dry run only. No financial records are changed. */
    public function preview(array $input): array
    {
        $version = DB::table('finance_pricing_versions')->where('id', $input['version_id'])->first();
        abort_unless($version !== null, 404);
        $rules = $this->decode($version->rules);
        $rows = [];
        if (! empty($input['invoice_ids'])) {
            foreach (Invoice::query()->whereIn('id', $input['invoice_ids'])->get() as $invoice) {
                if (! isset($input['participants'], $input['hours'])) {
                    $context = app(InvoiceAllocation::class)->context($invoice, (int) $version->id);
                    if ($context['workshopId'] && collect($rows)->contains('workshop_id', $context['workshopId'])) {
                        continue;
                    }
                    $warning = $context['budget'] ? 'Already allocated — existing allocations will be preserved.' : ($context['warning'] ?? $context['automaticWarning']);
                    if (! $context['suggestedTargets'] && ! $warning) {
                        $warning = 'Enter participants and duration overrides, or add a workshop breakdown to this invoice.';
                    }
                    $rows[] = ['workshop_id' => $context['workshopId'], 'name' => $context['workshop']->title ?? 'Invoice '.$invoice->invoice_number, 'date' => $context['date'], 'invoice_ids' => $context['ids'], 'assumptions' => $context['assumptions'], 'version_id' => $version->id, 'targets' => $context['suggestedTargets'], 'warning' => $warning, 'income' => $context['income'], 'suggested_price_cents' => null, 'structured' => true];
                    continue;
                }
                $assumptions = ['participants' => (int) $input['participants'], 'hours' => (float) $input['hours'], 'travel_minutes' => (int) $input['travel_minutes'], 'venue_supplied' => (bool) $input['venue_supplied']];
                $rows[] = $this->previewRow(null, 'Invoice '.$invoice->invoice_number, $invoice->issue_date?->toDateString() ?? now()->toDateString(), [$invoice->id], $assumptions, $version, $rules);
            }
        } else {
            $workshops = Workshop::query()->whereDate('starts_at', '>=', $input['from'])->whereDate('starts_at', '<=', $input['to'])->orderBy('starts_at')->limit(201)->get();
            if ($workshops->count() > 200) {
                throw ValidationException::withMessages(['from' => 'Choose a smaller range (up to 200 workshops per preview).']);
            }
            foreach ($workshops as $workshop) {
                $tickets = Ticket::query()->where('workshop_id', $workshop->id)->get();
                $invoiceIds = $tickets->pluck('invoice_id')->filter()->merge(DB::table('invoice_lines')->where('source_type', $workshop->getMorphClass())->where('source_id', $workshop->id)->pluck('invoice_id'))->unique()->values()->all();
                $assumptions = [
                    'participants' => (int) ($input['participants'] ?? $tickets->whereIn('status', Ticket::activePurchasedStatuses())->count()),
                    'pricing_participants' => min($workshop->max_tickets ?: PHP_INT_MAX, (int) ($this->decode($version->prices)['pricing_participants'] ?? 10)),
                    'hours' => (float) ($input['hours'] ?? $workshop->teachingHours()),
                    'travel_minutes' => (int) ($input['travel_minutes'] ?? 0),
                    'venue_supplied' => isset($input['venue_supplied']) ? (bool) $input['venue_supplied'] : (bool) $workshop->hosted_for_organisation_id,
                ];
                $rows[] = $this->previewRow($workshop->id, $workshop->title, $workshop->starts_at->toDateString(), $invoiceIds, $assumptions, $version, $rules);
            }
        }
        // A multi-workshop invoice needs an explicit split; never count its receipts twice.
        $counts = array_count_values(array_merge(...array_map(fn ($row) => $row['invoice_ids'], $rows)));
        foreach ($rows as &$row) {
            if (collect($row['invoice_ids'])->contains(fn ($id) => ($counts[$id] ?? 0) > 1)) {
                $row['warning'] = 'Shared invoice: allocate this invoice separately once, with a combined cost breakdown.';
            }
        }
        unset($row);
        foreach ($rows as &$row) {
            $row['manual'] = empty($row['structured']) && (! empty($input['invoice_ids']) || count(array_intersect(['participants', 'hours', 'travel_minutes', 'venue_supplied'], array_keys($input))) > 0);
        }
        unset($row);

        return ['token' => (string) Str::uuid(), 'rows' => $rows, 'created_at' => now()->toIso8601String()];
    }

    private function previewRow(?string $workshopId, string $name, string $date, array $invoiceIds, array $inputs, object $version, array $rules): array
    {
        $warning = null;
        if (($workshopId && DB::table('finance_budgets')->where('workshop_id', $workshopId)->exists()) || DB::table('finance_budget_invoices')->whereIn('invoice_id', $invoiceIds)->exists()) {
            $warning = 'Already allocated — existing allocations will be preserved.';
        } elseif (! $invoiceIds) {
            $warning = 'No linked invoices. Link the workshop on its invoice or allocate the invoice separately.';
        } elseif ($workshopId && Ticket::query()->whereIn('invoice_id', $invoiceIds)->where('workshop_id', '!=', $workshopId)->exists()) {
            $warning = 'Shared invoice: allocate this invoice separately once, with a combined cost breakdown.';
        }
        $prices = $this->decode($version->prices);
        $inputs['travel_free_minutes'] = $prices['travel_free_minutes'] ?? 30;
        $targets = $this->targets($rules, $inputs);
        $hourIndex = (int) ceil($inputs['hours']) - 1;
        $price = $prices[$inputs['venue_supplied'] ? 'organisation' : 'public'][$hourIndex] ?? null;
        $travelUnits = (int) ceil(max(0, $inputs['travel_minutes'] - ($prices['travel_free_minutes'] ?? 30)) / 15);
        // New versions derive the workshop charge from costs; keep historical price tables intact.
        $workshopCosts = $this->targets($rules, array_replace($inputs, ['travel_minutes' => 0]));
        $workshopPrice = $price === null ? (int) round(array_sum($workshopCosts) * 1.1) : $price * $inputs['participants'];
        $travelRules = array_filter($rules, fn ($rule) => $rule['basis'] === 'travel');
        $travelPrice = $travelRules ? array_sum(array_column($travelRules, 'rate_cents')) * 1.1 : ($prices['travel_cents'] ?? 3400);
        $travelStep = (int) ($prices['travel_rounding_step'] ?? 0);
        $travelPrice = $travelStep > 0 ? ceil(($travelPrice - 0.000001) / $travelStep) * $travelStep : round($travelPrice);
        $suggested = $workshopPrice + (int) ($travelUnits * $travelPrice);

        return ['workshop_id' => $workshopId, 'name' => $name, 'date' => $date, 'invoice_ids' => $invoiceIds, 'assumptions' => $inputs, 'version_id' => $version->id, 'targets' => $targets, 'warning' => $warning, 'income' => $this->income($invoiceIds), 'suggested_price_cents' => $suggested];
    }

    public function apply(array $preview, string $userId, array $selected, array $overrides = []): int
    {
        return DB::transaction(function () use ($preview, $userId, $selected, $overrides): int {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $existing = DB::table('finance_batches')->where('token', $preview['token'])->first();
            if ($existing) {
                return $existing->id;
            }
            $rows = [];
            foreach ($preview['rows'] as $key => $row) {
                if (! in_array((string) $key, array_map('strval', $selected), true) || $row['warning']) {
                    continue;
                }
                if (($row['workshop_id'] && DB::table('finance_budgets')->where('workshop_id', $row['workshop_id'])->exists()) || (! $row['workshop_id'] && DB::table('finance_budget_invoices')->whereIn('invoice_id', $row['invoice_ids'])->whereIn('budget_id', DB::table('finance_budgets')->whereNull('workshop_id')->select('id'))->exists())) {
                    throw ValidationException::withMessages(['preview' => 'These records have changed. Generate a new preview.']);
                }
                // Refresh receipts before committing; receipt amounts are never a frozen cash balance.
                $row['income'] = app(InvoiceAllocationParts::class)->income($row['invoice_ids'], $row['workshop_id']);
                $originalTargets = $row['targets'];
                if (isset($overrides[$key])) {
                    foreach ($overrides[$key] as $category => $amount) {
                        if (array_key_exists($category, $row['targets'])) {
                            $row['targets'][$category] = $this->cents($amount);
                        }
                    }
                }
                $row['manual'] = ($row['manual'] ?? false) || $row['targets'] !== $originalTargets;
                $rows[] = $row;
            }
            if (! $rows) {
                throw ValidationException::withMessages(['preview' => 'Select at least one eligible record.']);
            }
            $batch = DB::table('finance_batches')->insertGetId(['token' => $preview['token'], 'created_by' => $userId, 'snapshot' => json_encode($rows), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($rows as $row) {
                $id = DB::table('finance_budgets')->insertGetId(['workshop_id' => $row['workshop_id'], 'batch_id' => $batch, 'pricing_version_id' => $row['version_id'], 'name' => $row['name'], 'date' => $row['date'], 'assumptions' => json_encode($row['assumptions']), 'targets' => json_encode($row['targets']), 'manual' => $row['manual'] ?? true, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($row['invoice_ids'] as $invoiceId) {
                    DB::table('finance_budget_invoices')->insert(['budget_id' => $id, 'invoice_id' => $invoiceId]);
                }
            }

            return $batch;
        });
    }

    public function reverse(int $batch): void
    {
        DB::transaction(function () use ($batch): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $record = DB::table('finance_batches')->where('id', $batch)->lockForUpdate()->first();
            abort_unless($record !== null, 404);
            if ($record->reversed_at) {
                return;
            }
            $ids = DB::table('finance_budgets')->where('batch_id', $batch)->pluck('id');
            if (DB::table('finance_expense_splits')->whereIn('budget_id', $ids)->exists() || DB::table('finance_fund_transfers')->whereIn('budget_id', $ids)->exists()) {
                throw ValidationException::withMessages(['batch' => 'This batch has expense or fund-transfer links and cannot be reversed. Its history must be retained.']);
            }
            DB::table('finance_budgets')->whereIn('id', $ids)->delete();
            DB::table('finance_batches')->where('id', $batch)->update(['reversed_at' => now()]);
        });
    }

    public function received(Payment $payment): bool
    {
        return ! $payment->isPendingBankTransfer()
            && ! in_array($payment->payment_method, [Payment::PAYMENT_METHOD_CREDIT, Payment::PAYMENT_METHOD_ACCOUNT_TERMS], true)
            && ! in_array(strtoupper((string) $payment->gateway_status), ['PENDING', 'FAILED', 'CANCELED', 'CANCELLED', 'REJECTED', 'APPROVED'], true);
    }

    /** Cumulative rounding conserves every cent across invoices, including one-cent receipts. */
    private function share(int $amount, Payment $source, array $invoiceIds): int
    {
        $total = abs($this->cents($source->total_amount));
        if ($total === 0) {
            return 0;
        }
        $cumulative = 0;
        $share = 0;
        foreach ($source->allocations->sortBy('id') as $allocation) {
            $before = (int) round($amount * min($total, $cumulative) / $total);
            $cumulative += abs($this->cents($allocation->allocated_amount));
            $after = (int) round($amount * min($total, $cumulative) / $total);
            if (in_array($allocation->invoice_id, $invoiceIds)) {
                $share += $after - $before;
            }
        }

        return $share;
    }

    /** Actual received cash, proportioned by invoice payment allocation. Refunds follow the original payment. */
    public function income(array $invoiceIds, ?string $from = null, ?FinanceReportData $data = null): array
    {
        $events = collect($this->incomeEvents($invoiceIds, $data))->filter(fn ($event) => !$from || substr($event['date'], 0, 10) >= $from);
        $gross = $events->sum('gross');
        $gst = $events->sum('gst');
        return ['gross' => $gross, 'gst' => $gst, 'net' => $gross - $gst];
    }

    public function incomeEvents(array $invoiceIds, ?FinanceReportData $data = null): array
    {
        $events = [];
        $payments = $data?->paymentsFor($invoiceIds) ?? Payment::query()->with(['allocations.invoice', 'refunds.allocations.invoice'])->where('kind', Payment::KIND_PAYMENT)->whereHas('allocations', fn ($q) => $q->whereIn('invoice_id', $invoiceIds))->get();
        foreach ($payments as $payment) {
            $total = $this->cents($payment->total_amount);
            if ($total <= 0 || ! $this->received($payment)) {
                continue;
            }
            $tax = $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
            if ($payment->received_on && $payment->received_on->lte(now())) {
                $events[] = ['id' => $payment->id, 'date' => $payment->received_on->format('Y-m-d H:i:s'), 'type' => 'invoice', 'gross' => $this->share($total, $payment, $invoiceIds), 'gst' => $this->share($tax, $payment, $invoiceIds)];
            }
            foreach ($payment->refunds as $refund) {
                $refund->setRelation('refundOf', $payment);
                if (! $this->received($refund) || ! $refund->received_on || $refund->received_on->gt(now())) {
                    continue;
                }
                $refundTotal = abs($this->cents($refund->total_amount));
                $refundSource = $refund->allocations->isNotEmpty() ? $refund : $payment;
                $refundTax = abs($this->cents(app(GstCalculator::class)->paymentGstAmount($refund)));
                if (! $refundTax) {
                    $refundTax = (int) round($tax * min(1, $refundTotal / $total));
                }
                $events[] = ['id' => $refund->id, 'date' => $refund->received_on->format('Y-m-d H:i:s'), 'type' => 'refund', 'gross' => -$this->share($refundTotal, $refundSource, $invoiceIds), 'gst' => -$this->share($refundTax, $refundSource, $invoiceIds)];
            }
        }

        return collect($events)->sortBy([['date', 'asc'], ['id', 'asc']])->values()->all();
    }

    public function rounding(object $version, array $assumptions): array
    {
        $prices = $this->decode($version->prices);
        $step = (int) ($prices['rounding_step'] ?? 0);
        $category = $prices['rounding_category_id'] ?? null;
        $limit = 0;
        if (($step > 0 || ($prices['travel_rounding_step'] ?? 0) > 0) && $category) {
            $rules = array_filter($this->decode($version->rules), fn ($rule) => $rule['basis'] !== 'travel');
            foreach ($assumptions['lines'] ?? [$assumptions] as $inputs) {
                if (($inputs['kind'] ?? '') === 'travel') {
                    $travelStep = (int) ($prices['travel_rounding_step'] ?? 0);
                    $multiplier = ($inputs['gst_applicable'] ?? true) ? 1.1 : 1;
                    $travelRules = array_filter($this->decode($version->rules), fn ($rule) => $rule['basis'] === 'travel');
                    $raw = $travelRules ? array_sum(array_column($travelRules, 'rate_cents')) * $multiplier : (float) ($prices['travel_cents'] ?? 3400);
                    foreach ($this->decode($version->rules) as $rule) {
                        if ($rule['basis'] === 'travel' && ($rule['suppliable'] ?? false) && ($inputs['supplied_categories'][$rule['category_id']] ?? false)) {
                            $raw -= $rule['rate_cents'] * $multiplier;
                        }
                    }
                    $raw = max(0, $raw);
                    if ($travelStep > 0) {
                        $rounded = ceil(($raw - 0.000001) / $travelStep) * $travelStep;
                        $limit += max(0, (int) round(($rounded - $raw) * $inputs['units'] / $multiplier));
                    }
                    continue;
                }
                if ($step <= 0) { continue; }
                $units = (int) ($inputs['participants'] ?? 0) * (isset($assumptions['lines']) ? (float) ($inputs['hours'] ?? 0) : 1);
                if ($units <= 0) { continue; }
                $pricingInputs = $inputs;
                $pricingUnits = $units;
                if (! isset($assumptions['lines'])) {
                    $pricingInputs['participants'] = max(1, (int) ($inputs['pricing_participants'] ?? $prices['pricing_participants'] ?? 10));
                    $pricingUnits = $pricingInputs['participants'];
                }
                $cost = array_sum($this->targets($rules, $pricingInputs + ['travel_minutes' => 0, 'venue_supplied' => false]));
                $multiplier = ($inputs['gst_applicable'] ?? true) ? 1.1 : 1;
                $gross = (int) round(ceil(($cost * $multiplier / $pricingUnits - 0.000001) / $step) * $step * $units);
                $limit += max(0, (int) round($gross / $multiplier - $cost * $units / $pricingUnits));
            }
        }
        return ['category_id' => $category, 'limit' => $limit] + (empty($assumptions['product_lines']) ? [] : ['product_lines' => $assumptions['product_lines']]);
    }

    private function budgetRounding(object $budget, ?FinanceReportData $data = null): array
    {
        if ($budget->manual) { return []; }
        $version = $data ? $data->versions->get($budget->pricing_version_id) : DB::table('finance_pricing_versions')->where('id', $budget->pricing_version_id)->first();
        return $version ? $this->rounding($version, $this->decode($budget->assumptions)) : [];
    }

    public function funding(array $targets, int $net, array $rounding = [], ?\Illuminate\Support\Collection $categories = null): array
    {
        if (! empty($rounding['product_lines'])) {
            return app(ProductAllocation::class)->funding($targets, $net, $rounding, $categories);
        }
        $remaining = max(0, $net);
        $funded = [];
        foreach ($categories ?? DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get() as $category) {
            $amount = min($remaining, $targets[$category->id] ?? 0);
            $funded[$category->id] = $amount;
            $remaining -= $amount;
        }

        if (! empty($rounding['category_id'])) {
            $extra = min($remaining, $rounding['limit'] ?? 0);
            $funded[$rounding['category_id']] = ($funded[$rounding['category_id']] ?? 0) + $extra;
            $remaining -= $extra;
        }
        return ['categories' => $funded, 'surplus' => $remaining, 'shortfall' => max(0, array_sum($targets) - $net)];
    }

    public function budgetInvoiceIds(object $budget): array
    {
        return $budget->workshop_id
            ? Ticket::where('workshop_id', $budget->workshop_id)->whereNotNull('invoice_id')->pluck('invoice_id')->unique()->all()
            : DB::table('finance_budget_invoices')->where('budget_id', $budget->id)->pluck('invoice_id')->all();
    }

    public function costCentreLedger(object $category): \Illuminate\Support\Collection
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        $from = $settings->opening_date ?? self::HISTORY_START;
        $rows = collect();
        $fundingCategories = DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get();
        $add = function ($key, $date, $type, $description, $amount, $links = []) use ($rows) {
            if ($amount) $rows->push(compact('key', 'date', 'type', 'description', 'amount', 'links'));
        };
        $add('opening', $from.' 00:00:00', 'opening', 'Opening balance', (int) $category->opening_cents);
        $expenses = Expense::whereBetween('paid_on', [$from, today()->toDateString()])->get(['id', 'supplier_id', 'supplier', 'description', 'paid_on', 'total_amount', 'gst_amount']);
        $data = new FinanceReportData($expenses, DB::table('finance_budgets')->get());
        foreach ($expenses as $expense) {
            $add('expense-'.$expense->id, $expense->paid_on->format('Y-m-d H:i:s'), 'expense', $expense->supplier.' · '.$expense->description, -($this->expenseSplits($expense, $data)[$category->id] ?? 0), [['label' => 'View expense', 'url' => route('admin.expense.edit', $expense)]]);
        }
        foreach ($data->budgets as $budget) {
            if ($budget->workshop_id && ! app(WorkshopAllocation::class)->isCurrent($budget, $data)) continue;
            $ids = $data->invoiceIds($budget);
            $targets = $this->decode($budget->targets);
            $rounding = $this->budgetRounding($budget, $data);
            if (!($targets[$category->id] ?? 0) && ($rounding['category_id'] ?? null) != $category->id) continue;
            $links = $data->invoices->only($ids)->map(fn ($invoice) => ['label' => 'Invoice '.$invoice->invoice_number, 'url' => route('admin.invoice.edit', $invoice->id)])->values()->all();
            $net = $previous = 0;
            foreach (app(InvoiceAllocationParts::class)->events($ids, $budget->workshop_id, $data) as $event) {
                $net += $event['gross'] - $event['gst'];
                foreach ($event['line_net'] ?? [] as $key => $amount) {
                    $rounding['received_lines'][$key] = ($rounding['received_lines'][$key] ?? 0) + $amount;
                }
                $funded = $this->funding($targets, $net, $rounding, $fundingCategories)['categories'][$category->id] ?? 0;
                if (substr($event['date'], 0, 10) >= $from) {
                    $add('payment-'.$event['id'].'-'.$budget->id, $event['date'], $event['type'], $budget->name.' · '.($event['type'] === 'refund' ? 'Refund' : 'Payment received'), $funded - $previous, $links);
                }
                $previous = $funded;
            }
        }
        $names = DB::table('finance_categories')->pluck('name', 'id');
        foreach (DB::table('finance_fund_transfers')->where(fn ($q) => $q->where('category_id', $category->id)->orWhere('from_category_id', $category->id))->get() as $transfer) {
            $add('transfer-'.$transfer->id, $transfer->created_at, 'transfer', ($transfer->remuneration_user_id ? 'Remuneration forgone by '.(\App\Models\User::find($transfer->remuneration_user_id)?->getName() ?? $transfer->remuneration_user_id) : ($names[$transfer->from_category_id] ?? 'Available business cash')).' → '.($names[$transfer->category_id] ?? 'Available business cash').($transfer->reason ? ' · '.$transfer->reason : ''), $transfer->category_id == $category->id ? $transfer->cents : -$transfer->cents);
        }
        if ($category->kind === 'owner') {
            foreach (DB::table('finance_drawings')->where('purpose', 'time')->where('status', 'paid')->whereBetween('paid_on', [$from, today()->toDateString()])->get() as $drawing) {
                $add('drawing-'.$drawing->id, substr($drawing->paid_on, 0, 10).' 00:00:00', 'drawing', $drawing->reference ?: 'Owner drawing', -$drawing->cents);
            }
        }
        foreach (DB::table('finance_owner_contributions')->whereBetween('date', [$from, today()->toDateString()])->get() as $contribution) {
            $add('contribution-'.$contribution->id, $contribution->date.' 00:00:00', 'contribution', $contribution->reference, $this->decode($contribution->splits)[$category->id] ?? 0, [['label' => 'Owner contributions', 'url' => route('admin.timesheet.index', ['tab' => 'contributions'])]]);
        }
        $balance = 0;
        return $rows->sortBy([['date', 'asc'], ['key', 'asc']])->map(function ($row) use (&$balance) {
            $balance += $row['amount'];
            return $row + ['balance' => $balance, 'amount_display' => $row['amount'] / 100];
        })->reverse()->values();
    }

    public function budgetReport(object $budget): array
    {
        $invoiceIds = $this->budgetInvoiceIds($budget);
        $income = app(InvoiceAllocationParts::class)->income($invoiceIds, $budget->workshop_id);
        $targets = $this->decode($budget->targets);

        $rounding = $this->budgetRounding($budget);
        if (! empty($rounding['product_lines'])) {
            $rounding['received_lines'] = app(ProductAllocation::class)->balances(app(InvoiceAllocationParts::class)->events($invoiceIds, $budget->workshop_id));
        }

        return ['budget' => $budget, 'targets' => $targets, 'income' => $income, 'funding' => $this->funding($targets, $budget->workshop_id && ! app(WorkshopAllocation::class)->isCurrent($budget) ? 0 : $income['net'], $rounding), 'spent' => DB::table('finance_expense_splits')->join('expenses', 'expenses.id', '=', 'finance_expense_splits.expense_id')->where('budget_id', $budget->id)->whereDate('paid_on', '<=', today())->selectRaw('category_id, sum(cents) as total')->groupBy('category_id')->pluck('total', 'category_id')->all(), 'support' => DB::table('finance_fund_transfers')->where('budget_id', $budget->id)->whereNotNull('category_id')->selectRaw('category_id, sum(cents) as total')->groupBy('category_id')->pluck('total', 'category_id')->all()];
    }

    /** An explicit split wins; supplier rules are fallback defaults, never applied twice. */
    public function expenseSplits(Expense $expense, ?FinanceReportData $data = null): array
    {
        $manual = $data ? $data->splits->get($expense->id, collect()) : DB::table('finance_expense_splits')->where('expense_id', $expense->id)->get();
        if ($manual->isNotEmpty()) {
            if ((int) $manual->sum('cents') !== $this->cents($expense->total_amount) - $this->cents($expense->gst_amount)) {
                return [];
            }

            return $manual->mapWithKeys(fn ($row) => [$row->category_id => (int) $row->cents])->all();
        }
        $rule = $data
            ? ($expense->supplier_id ? $data->suppliers->get($expense->supplier_id) : $data->suppliersByName->get(mb_strtolower(trim((string) $expense->supplier))))
            : ($expense->supplier_id
                ? DB::table('finance_supplier_rules')->where('id', $expense->supplier_id)->first()
                : DB::table('finance_supplier_rules')->where('supplier', mb_strtolower(trim((string) $expense->supplier)))->first());
        if (! $rule) {
            return [];
        }
        $net = max(0, $this->cents($expense->total_amount) - $this->cents($expense->gst_amount));
        $splits = $this->decode($rule->splits);
        $result = [];
        $percentSoFar = 0;
        $allocated = 0;
        foreach ($splits as $id => $percent) {
            $percentSoFar += $percent;
            $next = (int) round($net * $percentSoFar / 100);
            $result[$id] = $next - $allocated;
            $allocated = $next;
        }

        return $result;
    }

    public function gst(string $from, string $to): array
    {
        $months = $this->gstMonths($from, $to);
        $sales = array_sum(array_column($months, 'sales'));
        $credits = array_sum(array_column($months, 'credits'));

        return ['sales' => $sales, 'credits' => $credits, 'net' => $sales - $credits];
    }

    /** @return array<string, array{sales: int, credits: int, net: int}> */
    public function gstMonths(string $from, string $to): array
    {
        $months = [];
        Payment::query()->with(['allocations.invoice', 'refundOf.allocations.invoice'])
            ->whereBetween('received_on', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereIn('kind', Payment::KINDS)->chunkById(500, function ($payments) use (&$months) {
                foreach ($payments as $payment) {
                    if (! $this->received($payment)) {
                        continue;
                    }
                    $key = $payment->received_on->format('Y-m');
                    $months[$key] ??= ['sales' => 0, 'credits' => 0, 'net' => 0];
                    $months[$key]['sales'] += $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
                }
            });
        foreach (Expense::query()->whereBetween('paid_on', [$from, $to])->select(['id', 'paid_on', 'gst_amount'])->lazyById(500) as $expense) {
            $key = $expense->paid_on->format('Y-m');
            $months[$key] ??= ['sales' => 0, 'credits' => 0, 'net' => 0];
            $months[$key]['credits'] += $this->cents($expense->gst_amount);
        }
        foreach ($months as &$month) {
            $month['net'] = $month['sales'] - $month['credits'];
        }

        return $months;
    }

    public function cash(?int $excludingDrawing = null): array
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        $cash = (int) $settings->opening_cash_cents;
        $gst = (int) $settings->opening_gst_cents;
        $categories = DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get();
        $reserves = $categories->mapWithKeys(fn ($c) => [$c->id => (int) $c->opening_cents])->all();
        $uncategorised = 0;
        $receivedNet = 0;
        $allocatedNet = 0;
        $from = $settings->opening_date ?? self::HISTORY_START;
        $today = now()->toDateString();
        foreach (Payment::query()->with(['allocations.invoice', 'refundOf.allocations.invoice'])->whereBetween('received_on', [$from.' 00:00:00', now()])->whereIn('kind', Payment::KINDS)->get() as $payment) {
            if (! $this->received($payment)) {
                continue;
            }
            $signed = ($payment->kind === Payment::KIND_REFUND ? -1 : 1) * abs($this->cents($payment->total_amount));
            $cash += $signed;
            $receivedNet += $signed - $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
        }
        $expenses = Expense::query()->whereBetween('paid_on', [$from, $today])->get(['id', 'supplier_id', 'supplier', 'total_amount', 'gst_amount']);
        $data = new FinanceReportData($expenses, DB::table('finance_budgets')->get());
        foreach ($expenses as $expense) {
            $cash -= $this->cents($expense->total_amount);
            $splits = $this->expenseSplits($expense, $data);
            $uncategorised += max(0, $this->cents($expense->total_amount) - $this->cents($expense->gst_amount) - array_sum($splits));
            foreach ($splits as $id => $amount) {
                $reserves[$id] = ($reserves[$id] ?? 0) - $amount;
            }
        }
        foreach ($data->budgets as $budget) {
            if ($budget->workshop_id && ! app(WorkshopAllocation::class)->isCurrent($budget, $data)) continue;
            $ids = $data->invoiceIds($budget);
            $targets = $this->decode($budget->targets);
            $all = app(InvoiceAllocationParts::class)->income($ids, $budget->workshop_id, null, $data);
            $live = $settings->opening_date ? app(InvoiceAllocationParts::class)->income($ids, $budget->workshop_id, $from, $data) : $all;
            $allocatedNet += $live['net'];
            $rounding = $this->budgetRounding($budget, $data);
            $events = empty($rounding['product_lines']) ? [] : app(InvoiceAllocationParts::class)->events($ids, $budget->workshop_id, $data);
            $beforeLines = app(ProductAllocation::class)->balances(array_filter($events, fn ($event) => substr($event['date'], 0, 10) < $from));
            $afterLines = app(ProductAllocation::class)->balances($events);
            $before = $this->funding($targets, $all['net'] - $live['net'], $rounding + ['received_lines' => $beforeLines], $categories);
            $after = $this->funding($targets, $all['net'], $rounding + ['received_lines' => $afterLines], $categories);
            foreach ($after['categories'] as $id => $amount) {
                $reserves[$id] = ($reserves[$id] ?? 0) + $amount - ($before['categories'][$id] ?? 0);
            }
        }
        $gst += $this->gst($from, $today)['net'];
        $settled = (int) DB::table('finance_gst_settlements')->whereBetween('paid_on', [$from, $today])->sum('cents');
        $gst -= $settled;
        $cash -= $settled;
        $drawn = (int) DB::table('finance_drawings')->where('status', 'paid')->whereBetween('paid_on', [$from, $today])->sum('cents');
        $cash -= $drawn;
        $ownerCategory = $categories->firstWhere('kind', 'owner');
        if ($ownerCategory) {
            $reserves[$ownerCategory->id] -= (int) DB::table('finance_drawings')->where('purpose', 'time')->where('status', 'paid')->whereBetween('paid_on', [$from, $today])->sum('cents');
        }
        foreach (DB::table('finance_fund_transfers')->get() as $transfer) {
            if ($transfer->category_id !== null) {
                $reserves[$transfer->category_id] = ($reserves[$transfer->category_id] ?? 0) + $transfer->cents;
            }
            if ($transfer->from_category_id) {
                $reserves[$transfer->from_category_id] = ($reserves[$transfer->from_category_id] ?? 0) - $transfer->cents;
            }
        }
        foreach (DB::table('finance_owner_contributions')->whereBetween('date', [$from, $today])->get() as $contribution) {
            $cash += $contribution->cents;
            foreach ($this->decode($contribution->splits) as $id => $amount) $reserves[$id] = ($reserves[$id] ?? 0) + $amount;
        }
        $pendingDrawings = DB::table('finance_drawings')->where('status', 'pending')->when($excludingDrawing, fn ($q) => $q->where('id', '!=', $excludingDrawing))->get();
        $pending = (int) $pendingDrawings->sum('cents');
        $ownerReserve = (int) ($reserves[$ownerCategory->id ?? null] ?? 0);
        $remunerationReserveAvailable = max(0, $ownerReserve - (int) $pendingDrawings->where('purpose', 'time')->sum('cents'));
        $protected = 0;
        foreach ($categories as $category) {
            if ($category->kind !== 'owner') {
                $protected += max(0, $reserves[$category->id] ?? 0);
            }
        }

        $unallocatedIncome = max(0, $receivedNet - $allocatedNet);
        $protected += $unallocatedIncome;

        // Pending remuneration commits both cash and its reserve; subtract it only once.
        $drawingCash = max(0, $cash - max(0, $gst) - $protected - $this->cents(\App\Models\SiteOption::value('finance.cash-buffer', '0')) - $pending);

        return ['unallocated_income' => $unallocatedIncome, 'settings' => $settings, 'cash' => $cash, 'gst' => $gst, 'reserves' => $reserves, 'uncategorised' => $uncategorised, 'protected' => $protected, 'pending' => $pending,
            'owner_reserve' => $ownerReserve, 'remuneration_reserve_available' => $remunerationReserveAvailable,
            'drawing_cash' => $drawingCash, 'available' => max(0, $drawingCash - $remunerationReserveAvailable)];
    }

    public function transfer(array $data, string $user): void
    {
        DB::transaction(function () use ($data, $user): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $destination = ($data['category_id'] ?? null) === 'cash' ? null : ($data['category_id'] ?? null);
            if (($data['category_id'] ?? null) !== 'cash' && ! DB::table('finance_categories')->where('id', $destination)->where('kind', 'cost')->where('active', true)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'Choose an active cost centre.']);
            }
            $remuneration = ($data['from_category_id'] ?? null) === 'remuneration';
            if ($remuneration && $destination === null) {
                throw ValidationException::withMessages(['category_id' => 'Forgone remuneration must fund a cost centre.']);
            }
            if ($destination === null && ! empty($data['budget_id'])) {
                throw ValidationException::withMessages(['budget_id' => 'A transfer to business cash cannot fund a workshop budget.']);
            }
            if ($remuneration && DB::table('finance_fund_transfers')->where('token', $data['token'] ?? null)->where('remuneration_user_id', $user)->exists()) {
                return;
            }
            if ($remuneration && empty($data['token'])) {
                throw ValidationException::withMessages(['token' => 'Reopen the transfer form and try again.']);
            }
            if (! $remuneration && ! empty($data['from_category_id']) && ! DB::table('finance_categories')->where('id', $data['from_category_id'])->where('kind', 'cost')->exists()) {
                throw ValidationException::withMessages(['from_category_id' => 'System funds cannot be transferred.']);
            }
            $cash = $this->cash();
            $amount = $this->cents($data['amount']);
            $from = $remuneration ? DB::table('finance_categories')->where('kind', 'owner')->value('id') : ($data['from_category_id'] ?? null);
            $available = $from ? ($cash['reserves'][$from] ?? 0) : $cash['available'];
            if ($remuneration) {
                $available = $this->remunerationTransferAvailable($cash);
            }
            if ($amount <= 0 || $amount > $available || $from == $destination) {
                throw ValidationException::withMessages(['amount' => $remuneration
                    ? 'This exceeds the owner remuneration fund balance available to transfer. Pending remuneration drawings are reserved.'
                    : 'Choose different funds and an amount covered by the source’s balance.']);
            }
            DB::table('finance_fund_transfers')->insert(['remuneration_user_id' => $remuneration ? $user : null, 'token' => $remuneration ? $data['token'] : null, 'from_category_id' => $from, 'category_id' => $destination, 'budget_id' => $data['budget_id'] ?? null, 'cents' => $amount, 'reason' => $data['reason'] ?? '', 'created_by' => $user, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function remunerationTransferAvailable(?array $cash = null): int
    {
        $ownerId = DB::table('finance_categories')->where('kind', 'owner')->value('id');
        $cash ??= $this->cash();
        $pending = (int) DB::table('finance_drawings')->where('purpose', 'time')->where('status', 'pending')->sum('cents');

        return max(0, ($cash['reserves'][$ownerId] ?? 0) - $pending);
    }

    public function remunerationForgone(string $user): int
    {
        return (int) DB::table('finance_fund_transfers')->where('remuneration_user_id', $user)->sum('cents');
    }

    /** Historical unpaid earnings; reserve availability controls drawings and transfers. */
    public function remunerationAvailable(string $user): int
    {
        return max(0, $this->earned($user) - $this->remunerationForgone($user)
            - (int) DB::table('finance_drawings')->where('user_id', $user)->where('purpose', 'time')->whereIn('status', ['pending', 'paid'])->sum('cents'));
    }

    public function earned(string $user): int
    {
        return (int) DB::table('finance_time_entries')->where('user_id', $user)->get()->sum(fn ($row) => (int) round($row->minutes * $row->rate_cents / 60));
    }

    /** Reserve and liability balances share the same cash limits in forms and writes. */
    public function drawingTotals(string $user, ?array $cash = null, ?int $excludingDrawing = null): array
    {
        $cash ??= $this->cash($excludingDrawing);
        $contributed = (int) DB::table('finance_owner_contributions')->where('user_id', $user)->whereDate('date', '<=', today())->sum('cents');
        $drawings = DB::table('finance_drawings')->where('user_id', $user)->where('purpose', 'contribution')->when($excludingDrawing, fn ($q) => $q->where('id', '!=', $excludingDrawing))->get();
        $outstanding = max(0, $contributed - (int) $drawings->where('status', 'paid')->sum('cents'));
        $repayable = max(0, $outstanding - (int) $drawings->where('status', 'pending')->sum('cents'));

        return [
            'time' => ['outstanding' => max(0, $cash['owner_reserve']), 'available' => min($cash['remuneration_reserve_available'], $cash['drawing_cash'])],
            'contribution' => ['outstanding' => $outstanding, 'available' => min($repayable, $cash['available'], $cash['drawing_cash'])],
        ];
    }

    public function validateDrawing(string $user, int $cents, string $purpose, ?int $excludingDrawing = null): void
    {
        if (! in_array($purpose, ['time', 'contribution'], true)) {
            throw ValidationException::withMessages(['purpose' => 'Choose remuneration or return of contributions.']);
        }
        if ($cents <= 0 || $cents > $this->drawingTotals($user, excludingDrawing: $excludingDrawing)[$purpose]['available']) {
            throw ValidationException::withMessages(['amount' => 'This exceeds the reserve, outstanding contributions or available cash.']);
        }
    }

    public function prepareDrawing(string $user, int $cents, string $token, string $purpose = 'time'): void
    {
        DB::transaction(function () use ($user, $cents, $token, $purpose): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            if (DB::table('finance_drawings')->where('token', $token)->exists()) {
                return;
            }
            $this->validateDrawing($user, $cents, $purpose);
            DB::table('finance_drawings')->insert(['user_id' => $user, 'token' => $token, 'purpose' => $purpose, 'cents' => $cents, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function automate(): int
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        $excludedInvoices = [];
        foreach (DB::table('finance_batches')->whereNotNull('reversed_at')->get() as $batch) {
            foreach ($this->decode($batch->snapshot) as $row) {
                $excludedInvoices = array_merge($excludedInvoices, $row['invoice_ids']);
            }
        }
        $count = 0;
        foreach (Invoice::query()->whereNotIn('id', $excludedInvoices)->whereNotNull('created_by')->whereHas('lines', fn ($query) => $query->whereIn('kind', ['workshop', 'multi_workshop', 'travel']))->whereDate('issue_date', '>=', $settings->opening_date ?? self::HISTORY_START)->lazyById(100) as $invoice) {
            app(InvoiceAllocation::class)->sync($invoice, $invoice->created_by);
        }

        return $count;
    }
}
