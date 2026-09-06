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
                'venue' => $inputs['venue_supplied'] ? 0 : 1,
                default => 1,
            };
            $cents = (int) round($rule['rate_cents'] * $units);
            if ($rule['basis'] === 'venue' && ! $inputs['venue_supplied']) {
                $cents += (int) round(max(0, $inputs['hours'] - 1) * ($rule['extra_cents'] ?? 0));
            }
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
                    'participants' => (int) ($input['participants'] ?? $tickets->whereIn('status', [Ticket::STATUS_PAID, Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT])->count()),
                    'hours' => (float) ($input['hours'] ?? max(0, $workshop->starts_at->diffInMinutes($workshop->ends_at)) / 60),
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
            $row['manual'] = ! empty($input['invoice_ids']) || count(array_intersect(['participants', 'hours', 'travel_minutes', 'venue_supplied'], array_keys($input))) > 0;
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
        $suggested = $price === null ? null : $price * $inputs['participants'] + (int) ceil(max(0, $inputs['travel_minutes'] - ($prices['travel_free_minutes'] ?? 30)) / 15) * ($prices['travel_cents'] ?? 3400);

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
                if (($row['workshop_id'] && DB::table('finance_budgets')->where('workshop_id', $row['workshop_id'])->exists()) || DB::table('finance_budget_invoices')->whereIn('invoice_id', $row['invoice_ids'])->exists()) {
                    throw ValidationException::withMessages(['preview' => 'These records have changed. Generate a new preview.']);
                }
                // Refresh receipts before committing; receipt amounts are never a frozen cash balance.
                $row['income'] = $this->income($row['invoice_ids']);
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

    private function received(Payment $payment): bool
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
    public function income(array $invoiceIds, ?string $from = null): array
    {
        $gross = $gst = 0;
        $payments = Payment::query()->with(['allocations.invoice', 'refunds.allocations.invoice'])->where('kind', Payment::KIND_PAYMENT)->whereHas('allocations', fn ($q) => $q->whereIn('invoice_id', $invoiceIds))->get();
        foreach ($payments as $payment) {
            $total = $this->cents($payment->total_amount);
            if ($total <= 0 || ! $this->received($payment)) {
                continue;
            }
            $tax = $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
            if ($payment->received_on && $payment->received_on->lte(now()) && (! $from || $payment->received_on->toDateString() >= $from)) {
                $gross += $this->share($total, $payment, $invoiceIds);
                $gst += $this->share($tax, $payment, $invoiceIds);
            }
            foreach ($payment->refunds as $refund) {
                if (! $this->received($refund) || ! $refund->received_on || $refund->received_on->gt(now()) || ($from && $refund->received_on->toDateString() < $from)) {
                    continue;
                }
                $refundTotal = abs($this->cents($refund->total_amount));
                $refundSource = $refund->allocations->isNotEmpty() ? $refund : $payment;
                $refundTax = abs($this->cents(app(GstCalculator::class)->paymentGstAmount($refund)));
                if (! $refundTax) {
                    $refundTax = (int) round($tax * min(1, $refundTotal / $total));
                }
                $gross -= $this->share($refundTotal, $refundSource, $invoiceIds);
                $gst -= $this->share($refundTax, $refundSource, $invoiceIds);
            }
        }

        return ['gross' => $gross, 'gst' => $gst, 'net' => $gross - $gst];
    }

    public function funding(array $targets, int $net): array
    {
        $remaining = max(0, $net);
        $funded = [];
        foreach (DB::table('finance_categories')->orderBy('priority')->orderBy('id')->get() as $category) {
            $amount = min($remaining, $targets[$category->id] ?? 0);
            $funded[$category->id] = $amount;
            $remaining -= $amount;
        }

        return ['categories' => $funded, 'surplus' => $remaining, 'shortfall' => max(0, array_sum($targets) - $net)];
    }

    public function budgetInvoiceIds(object $budget): array
    {
        $ids = DB::table('finance_budget_invoices')->where('budget_id', $budget->id)->pluck('invoice_id')->all();
        if (! $budget->workshop_id) {
            return $ids;
        }
        $candidates = Ticket::query()->where('workshop_id', $budget->workshop_id)->whereNotNull('invoice_id')->pluck('invoice_id')->unique();
        foreach ($candidates as $id) {
            if (! in_array($id, $ids) && ! DB::table('finance_budget_invoices')->where('invoice_id', $id)->where('budget_id', '!=', $budget->id)->exists() && ! Ticket::query()->where('invoice_id', $id)->where('workshop_id', '!=', $budget->workshop_id)->exists()) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function budgetReport(object $budget): array
    {
        $invoiceIds = $this->budgetInvoiceIds($budget);
        $income = $this->income($invoiceIds);
        $targets = $this->decode($budget->targets);

        return ['budget' => $budget, 'targets' => $targets, 'income' => $income, 'funding' => $this->funding($targets, $income['net']), 'spent' => DB::table('finance_expense_splits')->join('expenses', 'expenses.id', '=', 'finance_expense_splits.expense_id')->where('budget_id', $budget->id)->whereDate('paid_on', '<=', today())->selectRaw('category_id, sum(cents) as total')->groupBy('category_id')->pluck('total', 'category_id')->all(), 'support' => DB::table('finance_fund_transfers')->where('budget_id', $budget->id)->selectRaw('category_id, sum(cents) as total')->groupBy('category_id')->pluck('total', 'category_id')->all()];
    }

    /** An explicit split wins; supplier rules are fallback defaults, never applied twice. */
    public function expenseSplits(Expense $expense): array
    {
        $manual = DB::table('finance_expense_splits')->where('expense_id', $expense->id)->get();
        if ($manual->isNotEmpty()) {
            if ((int) $manual->sum('cents') !== $this->cents($expense->total_amount) - $this->cents($expense->gst_amount)) {
                return [];
            }

            return $manual->mapWithKeys(fn ($row) => [$row->category_id => (int) $row->cents])->all();
        }
        $rule = DB::table('finance_supplier_rules')->where('supplier', mb_strtolower(trim((string) $expense->supplier)))->first();
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
        $sales = 0;
        foreach (Payment::query()->with(['allocations.invoice', 'refundOf.allocations.invoice'])->whereBetween('received_on', [$from.' 00:00:00', $to.' 23:59:59'])->whereIn('kind', Payment::KINDS)->get() as $payment) {
            if (! $this->received($payment)) {
                continue;
            }
            $sales += $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
        }
        $credits = $this->cents(Expense::query()->whereBetween('paid_on', [$from, $to])->sum('gst_amount'));

        return ['sales' => $sales, 'credits' => $credits, 'net' => $sales - $credits];
    }

    public function cash(): array
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        $cash = (int) $settings->opening_cash_cents;
        $gst = (int) $settings->opening_gst_cents;
        $categories = DB::table('finance_categories')->orderBy('priority')->get();
        $reserves = $categories->mapWithKeys(fn ($c) => [$c->id => (int) $c->opening_cents])->all();
        $uncategorised = 0;
        $receivedNet = 0;
        $allocatedNet = 0;
        if ($settings->opening_date) {
            $from = $settings->opening_date;
            $today = now()->toDateString();
            foreach (Payment::query()->with(['allocations.invoice', 'refundOf.allocations.invoice'])->whereBetween('received_on', [$from.' 00:00:00', now()])->whereIn('kind', Payment::KINDS)->get() as $payment) {
                if (! $this->received($payment)) {
                    continue;
                }
                $signed = ($payment->kind === Payment::KIND_REFUND ? -1 : 1) * abs($this->cents($payment->total_amount));
                $cash += $signed;
                $receivedNet += $signed - $this->cents(app(GstCalculator::class)->paymentGstAmount($payment));
            }
            $expenses = Expense::query()->whereBetween('paid_on', [$from, $today])->get();
            foreach ($expenses as $expense) {
                $cash -= $this->cents($expense->total_amount);
                $splits = $this->expenseSplits($expense);
                $uncategorised += max(0, $this->cents($expense->total_amount) - $this->cents($expense->gst_amount) - array_sum($splits));
                foreach ($splits as $id => $amount) {
                    $reserves[$id] = ($reserves[$id] ?? 0) - $amount;
                }
            }
            foreach (DB::table('finance_budgets')->get() as $budget) {
                $ids = $this->budgetInvoiceIds($budget);
                $targets = $this->decode($budget->targets);
                $all = $this->income($ids);
                $live = $this->income($ids, $from);
                $allocatedNet += $live['net'];
                $before = $this->funding($targets, $all['net'] - $live['net']);
                $after = $this->funding($targets, $all['net']);
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
                $reserves[$ownerCategory->id] -= $drawn;
            }
        }
        foreach (DB::table('finance_fund_transfers')->get() as $transfer) {
            $reserves[$transfer->category_id] = ($reserves[$transfer->category_id] ?? 0) + $transfer->cents;
            if ($transfer->from_category_id) {
                $reserves[$transfer->from_category_id] = ($reserves[$transfer->from_category_id] ?? 0) - $transfer->cents;
            }
        }
        $pending = (int) DB::table('finance_drawings')->where('status', 'pending')->sum('cents');
        $protected = 0;
        foreach ($categories as $category) {
            if ($category->kind !== 'owner') {
                $commitment = (int) DB::table('finance_commitments')->where('category_id', $category->id)->where('status', 'open')->sum('cents');
                $protected += max(0, $reserves[$category->id] ?? 0, $commitment);
            }
        }

        $unallocatedIncome = max(0, $receivedNet - $allocatedNet);
        $protected += $unallocatedIncome;

        return ['unallocated_income' => $unallocatedIncome, 'settings' => $settings, 'cash' => $cash, 'gst' => $gst, 'reserves' => $reserves, 'uncategorised' => $uncategorised, 'protected' => $protected, 'pending' => $pending, 'available' => $settings->opening_date ? max(0, $cash - max(0, $gst) - $protected - $settings->buffer_cents - $pending) : 0];
    }

    public function transfer(array $data, string $user): void
    {
        DB::transaction(function () use ($data, $user): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $cash = $this->cash();
            $amount = $this->cents($data['amount']);
            $from = $data['from_category_id'] ?? null;
            $available = $from ? ($cash['reserves'][$from] ?? 0) - (int) DB::table('finance_commitments')->where('category_id', $from)->where('status', 'open')->sum('cents') : $cash['available'];
            if (! $cash['settings']->opening_date || $amount > $available || $from == $data['category_id']) {
                throw ValidationException::withMessages(['amount' => 'Choose different funds and an amount covered by the source’s uncommitted balance.']);
            }
            DB::table('finance_fund_transfers')->insert(['from_category_id' => $from, 'category_id' => $data['category_id'], 'budget_id' => $data['budget_id'] ?? null, 'cents' => $amount, 'reason' => $data['reason'], 'created_by' => $user, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function earned(string $user): int
    {
        return (int) DB::table('finance_time_entries')->where('user_id', $user)->get()->sum(fn ($row) => (int) round($row->minutes * $row->rate_cents / 60));
    }

    public function prepareDrawing(string $user, int $cents, string $token): void
    {
        DB::transaction(function () use ($user, $cents, $token): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            if (DB::table('finance_drawings')->where('token', $token)->exists()) {
                return;
            }
            $outstanding = $this->earned($user) - (int) DB::table('finance_drawings')->where('user_id', $user)->whereIn('status', ['pending', 'paid'])->sum('cents');
            if ($cents <= 0 || $cents > min($outstanding, $this->cash()['available'])) {
                throw ValidationException::withMessages(['amount' => 'This exceeds your undrawn time target or available cash. Reconcile cash and reserves first.']);
            }
            DB::table('finance_drawings')->insert(['user_id' => $user, 'token' => $token, 'cents' => $cents, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function automate(): int
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        if (! $settings->auto_budget || ! $settings->opening_date) {
            return 0;
        }
        foreach (DB::table('finance_budgets')->where('manual', false)->whereNotNull('workshop_id')->get() as $budget) {
            DB::transaction(function () use ($budget): void {
                DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
                $current = DB::table('finance_budgets')->where('id', $budget->id)->lockForUpdate()->first();
                if (! $current || $current->manual) {
                    return;
                }
                $workshop = Workshop::query()->find($current->workshop_id);
                if (! $workshop) {
                    return;
                }
                $assumptions = $this->decode($current->assumptions);
                $assumptions['participants'] = Ticket::query()->where('workshop_id', $workshop->id)->whereIn('status', [Ticket::STATUS_PAID, Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_XFER, Ticket::STATUS_ACCOUNT])->count();
                $assumptions['hours'] = max(0, $workshop->starts_at->diffInMinutes($workshop->ends_at)) / 60;
                $version = DB::table('finance_pricing_versions')->where('id', $current->pricing_version_id)->first();
                $targets = $this->targets($this->decode($version->rules), $assumptions);
                if ($targets !== $this->decode($current->targets)) {
                    DB::table('finance_budget_revisions')->insert(['budget_id' => $current->id, 'before' => json_encode(['assumptions' => $this->decode($current->assumptions), 'targets' => $this->decode($current->targets)]), 'after' => json_encode(['assumptions' => $assumptions, 'targets' => $targets]), 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('finance_budgets')->where('id', $current->id)->update(['assumptions' => json_encode($assumptions), 'targets' => json_encode($targets), 'updated_at' => now()]);
                }
                // New ticket invoices join this workshop budget; never steal an existing allocation.
                $ids = Ticket::query()->where('workshop_id', $workshop->id)->whereNotNull('invoice_id')->pluck('invoice_id')->unique();
                foreach ($ids as $invoiceId) {
                    if (! DB::table('finance_budget_invoices')->where('invoice_id', $invoiceId)->exists() && ! Ticket::query()->where('invoice_id', $invoiceId)->where('workshop_id', '!=', $workshop->id)->exists()) {
                        DB::table('finance_budget_invoices')->insert(['budget_id' => $current->id, 'invoice_id' => $invoiceId]);
                    }
                }
            });
        }
        $excluded = [];
        foreach (DB::table('finance_batches')->whereNotNull('reversed_at')->get() as $batch) {
            foreach ($this->decode($batch->snapshot) as $row) {
                if ($row['workshop_id']) {
                    $excluded[] = $row['workshop_id'];
                }
            }
        }
        $count = 0;
        foreach (Workshop::query()->whereNotIn('id', $excluded)->whereDate('starts_at', '>=', $settings->opening_date)->whereDate('starts_at', '<=', now()->addYear()->toDateString())->whereNotIn('id', DB::table('finance_budgets')->whereNotNull('workshop_id')->select('workshop_id'))->orderBy('starts_at')->limit(200)->get() as $workshop) {
            $version = DB::table('finance_pricing_versions')->where('effective_from', '<=', $workshop->starts_at->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->first();
            if (! $version) {
                continue;
            }
            $preview = $this->preview(['version_id' => $version->id, 'from' => $workshop->starts_at->toDateString(), 'to' => $workshop->starts_at->toDateString()]);
            $selected = [];
            foreach ($preview['rows'] as $key => $row) {
                if ($row['workshop_id'] === $workshop->id && ! $row['warning']) {
                    $selected[] = $key;
                }
            }
            if ($selected && $version->created_by) {
                $this->apply($preview, $version->created_by, $selected);
                $count++;
            }
        }

        return $count;
    }
}
