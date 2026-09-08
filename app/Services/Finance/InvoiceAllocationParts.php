<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Ticket;

/** Partition invoice cash once, using stable line ownership and cumulative rounding. */
class InvoiceAllocationParts
{
    public function key(?string $workshopId): string
    {
        return $workshopId === null ? 'invoice' : 'workshop:'.$workshopId;
    }

    public function lineKey(InvoiceLine $line, Invoice $invoice): string
    {
        $tickets = $invoice->tickets->where('invoice_line_id', $line->id);
        $workshop = $line->details_json['workshop_id'] ?? ($tickets->pluck('workshop_id')->unique()->count() === 1 ? $tickets->first()->workshop_id : null);
        if ($line->kind === 'ticket' || $tickets->isNotEmpty()) {
            $workshop ??= $invoice->tickets->pluck('workshop_id')->unique()->count() === 1 ? $invoice->tickets->first()->workshop_id : null;

            return $workshop ? $this->key((string) $workshop) : 'unresolved';
        }

        return 'invoice';
    }

    public function weights(Invoice $invoice): array
    {
        $invoice->loadMissing('lines', 'tickets');
        $planner = app(FinancePlanner::class);
        $weights = [];
        foreach ($invoice->lines as $line) {
            $key = $this->lineKey($line, $invoice);
            $weights[$key] = ($weights[$key] ?? 0) + max(0, $planner->cents($line->line_total_ex_tax));
        }
        // Historical ticket invoices may predate invoice lines.
        if (! array_sum($weights)) {
            $ids = $invoice->tickets->pluck('workshop_id')->unique();
            $key = $ids->count() === 1 ? $this->key((string) $ids->first()) : ($ids->isEmpty() ? 'invoice' : 'unresolved');
            $weights = [$key => max(0, $planner->cents($invoice->total_amount) - $planner->cents($invoice->gst_amount))];
        }
        ksort($weights);

        return $weights;
    }

    public function portion(int $amount, array $weights, string $key): int
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return 0;
        }
        $before = 0;
        foreach ($weights as $part => $weight) {
            $after = $before + $weight;
            if ($part === $key) {
                return (int) round($amount * $after / $total) - (int) round($amount * $before / $total);
            }
            $before = $after;
        }

        return 0;
    }

    public function total(Invoice $invoice, ?string $workshopId = null): int
    {
        $planner = app(FinancePlanner::class);

        return $this->portion($planner->cents($invoice->total_amount) - $planner->cents($invoice->gst_amount), $this->weights($invoice), $this->key($workshopId));
    }

    public function events(array $ids, ?string $workshopId, ?FinanceReportData $data = null): array
    {
        $planner = app(FinancePlanner::class);
        $invoices = $data?->allocationInvoices->only($ids) ?? Invoice::with(['lines', 'tickets'])->whereIn('id', $ids)->get();
        $refunds = $data ? $data->payments->flatMap->refunds->keyBy('id') : Payment::with('allocations.taxAdjustment.lines')->where('kind', Payment::KIND_REFUND)
            ->whereHas('allocations', fn ($query) => $query->whereIn('invoice_id', $ids)->whereNotNull('tax_adjustment_id'))->get()->keyBy('id');
        $events = [];
        foreach ($invoices as $invoice) {
            $weights = $this->weights($invoice);
            foreach ($planner->incomeEvents([$invoice->id], $data) as $event) {
                $eventWeights = $weights;
                $refund = $refunds->get($event['id']);
                if ($refund) {
                    $adjusted = [];
                    foreach ($refund->allocations->where('invoice_id', $invoice->id) as $allocation) {
                        foreach ($allocation->taxAdjustment->lines ?? [] as $adjustmentLine) {
                            $line = $invoice->lines->firstWhere('id', $adjustmentLine->invoice_line_id);
                            if (! $line) {
                                $adjusted = [];
                                break 2;
                            }
                            $key = $this->lineKey($line, $invoice);
                            $adjusted[$key] = ($adjusted[$key] ?? 0) + abs($planner->cents($adjustmentLine->line_total_ex_tax));
                        }
                    }
                    if (array_sum($adjusted)) {
                        ksort($adjusted);
                        $eventWeights = $adjusted;
                    }
                }
                $net = $this->portion($event['gross'] - $event['gst'], $eventWeights, $this->key($workshopId));
                $tax = $this->portion($event['gst'], $eventWeights, $this->key($workshopId));
                $lineNet = [];
                if ($invoice->lines->contains(fn ($line) => ! empty($line->product_allocation_snapshot))) {
                    $lineWeights = $invoice->lines->mapWithKeys(fn ($line) => [$invoice->id.':'.$line->line_number => max(0, $planner->cents($line->line_total_ex_tax))])->all();
                    if ($refund) {
                        $adjustedLines = [];
                        foreach ($refund->allocations->where('invoice_id', $invoice->id) as $allocation) {
                            foreach ($allocation->taxAdjustment->lines ?? [] as $adjustmentLine) {
                                $line = $invoice->lines->firstWhere('id', $adjustmentLine->invoice_line_id);
                                if (! $line) {
                                    $adjustedLines = [];
                                    break 2;
                                }
                                $key = $invoice->id.':'.$line->line_number;
                                $adjustedLines[$key] = ($adjustedLines[$key] ?? 0) + abs($planner->cents($adjustmentLine->line_total_ex_tax));
                            }
                        }
                        if (array_sum($adjustedLines)) {
                            $lineWeights = $adjustedLines;
                        }
                    }
                    $lineNet = app(ProductAllocation::class)->split(abs($event['gross'] - $event['gst']), $lineWeights);
                    if ($event['gross'] - $event['gst'] < 0) {
                        $lineNet = array_map(fn ($amount) => -$amount, $lineNet);
                    }
                }
                $events[] = array_merge($event, ['id' => $event['id'].'-'.$invoice->id, 'gross' => $net + $tax, 'gst' => $tax, 'line_net' => $lineNet]);
            }
        }

        return collect($events)->sortBy([['date', 'asc'], ['id', 'asc']])->values()->all();
    }

    public function income(array $ids, ?string $workshopId, ?string $from = null, ?FinanceReportData $data = null): array
    {
        $events = collect($this->events($ids, $workshopId, $data))->filter(fn ($event) => ! $from || substr($event['date'], 0, 10) >= $from);
        $gross = (int) $events->sum('gross');
        $gst = (int) $events->sum('gst');

        return ['gross' => $gross, 'gst' => $gst, 'net' => $gross - $gst];
    }
}
