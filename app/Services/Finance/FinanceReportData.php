<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** A fresh batch of dependencies for one report calculation, never a shared cache. */
class FinanceReportData
{
    public Collection $splits;

    public Collection $suppliers;

    public Collection $versions;

    public Collection $payments;

    public Collection $invoices;

    public Collection $allocationInvoices;

    public Collection $workshops;

    private array $invoiceIds = [];

    private array $workshopInvoiceIds = [];

    private array $paymentsByInvoice = [];

    public Collection $suppliersByName;

    public function __construct(Collection $expenses, public Collection $budgets)
    {
        $this->workshops = \App\Models\Workshop::with('tickets')->whereIn('id', $budgets->pluck('workshop_id')->filter())->get()->keyBy('id');
        $this->splits = DB::table('finance_expense_splits')->whereIn('expense_id', $expenses->pluck('id'))->get()->groupBy('expense_id');
        $this->suppliers = DB::table('finance_supplier_rules')->where(function ($query) use ($expenses) {
            $query->whereIn('id', $expenses->pluck('supplier_id')->filter())
                ->orWhereIn('supplier', $expenses->whereNull('supplier_id')->map(fn ($expense) => mb_strtolower(trim((string) $expense->supplier))));
        })->get();
        $this->suppliersByName = $this->suppliers->keyBy('supplier');
        $this->suppliers = $this->suppliers->keyBy('id');
        $this->versions = DB::table('finance_pricing_versions')->whereIn('id', $budgets->pluck('pricing_version_id'))->get()->keyBy('id');
        $links = DB::table('finance_budget_invoices')->get();
        $candidates = Ticket::whereIn('workshop_id', $budgets->pluck('workshop_id')->filter())->whereNotNull('invoice_id')->get(['workshop_id', 'invoice_id']);
        $linksByBudget = $links->groupBy('budget_id');
        $this->workshopInvoiceIds = $candidates->groupBy('workshop_id')->map(fn ($rows) => $rows->pluck('invoice_id')->unique()->values()->all())->all();
        foreach (app(WorkshopFunding::class)->linkedLines($this->workshops->keys()->all()) as $line) {
            $workshopId = $line->details_json['workshop']['linked_workshop_id'];
            $this->workshopInvoiceIds[$workshopId] = array_values(array_unique([...($this->workshopInvoiceIds[$workshopId] ?? []), $line->invoice_id]));
        }
        foreach ($budgets as $budget) {
            $ids = $budget->workshop_id
                ? $this->workshopInvoiceIds($budget->workshop_id)
                : $linksByBudget->get($budget->id, collect())->pluck('invoice_id')->all();
            $this->invoiceIds[$budget->id] = $ids;
        }
        $allIds = collect($this->invoiceIds)->flatten()->unique();
        $this->allocationInvoices = \App\Models\Invoice::with(['lines', 'tickets', 'taxAdjustments'])->whereIn('id', $allIds)->get()->keyBy('id');
        $this->invoices = DB::table('invoices')->whereIn('id', $allIds)->get(['id', 'invoice_number'])->keyBy('id');
        $this->payments = Payment::with(['allocations.invoice', 'refunds.allocations.invoice', 'refunds.allocations.taxAdjustment.lines'])
            ->where('kind', Payment::KIND_PAYMENT)->whereHas('allocations', fn ($query) => $query->whereIn('invoice_id', $allIds))->get();
        foreach ($this->payments as $payment) {
            foreach ($payment->allocations as $allocation) {
                $this->paymentsByInvoice[$allocation->invoice_id][$payment->id] = $payment;
            }
            foreach ($payment->refunds as $refund) {
                $refund->setRelation('refundOf', $payment);
            }
        }
    }

    public function workshopInvoiceIds(string $workshopId): array
    {
        return $this->workshopInvoiceIds[$workshopId] ?? [];
    }

    public function invoiceIds(object $budget): array
    {
        return $this->invoiceIds[$budget->id] ?? [];
    }

    public function paymentsFor(array $ids): Collection
    {
        $payments = [];
        foreach ($ids as $id) {
            $payments += $this->paymentsByInvoice[$id] ?? [];
        }

        return collect($payments)->values();
    }
}
