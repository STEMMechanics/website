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

    private array $invoiceIds = [];

    private array $paymentsByInvoice = [];

    public Collection $suppliersByName;

    public function __construct(Collection $expenses, public Collection $budgets)
    {
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
        $workshops = Ticket::whereIn('invoice_id', $candidates->pluck('invoice_id')->unique())->get(['workshop_id', 'invoice_id'])->groupBy('invoice_id');
        $linksByBudget = $links->groupBy('budget_id');
        $linksByInvoice = $links->keyBy('invoice_id');
        $candidatesByWorkshop = $candidates->groupBy('workshop_id');
        foreach ($budgets as $budget) {
            $ids = $linksByBudget->get($budget->id, collect())->pluck('invoice_id')->all();
            foreach ($candidatesByWorkshop->get($budget->workshop_id, collect())->pluck('invoice_id')->unique() as $id) {
                $linked = $linksByInvoice->get($id);
                $shared = $workshops->get($id, collect())->contains(fn ($ticket) => $ticket->workshop_id !== null && $ticket->workshop_id != $budget->workshop_id);
                if (! in_array($id, $ids) && (! $linked || $linked->budget_id == $budget->id) && ! $shared) {
                    $ids[] = $id;
                }
            }
            $this->invoiceIds[$budget->id] = $ids;
        }
        $allIds = collect($this->invoiceIds)->flatten()->unique();
        $this->invoices = DB::table('invoices')->whereIn('id', $allIds)->get(['id', 'invoice_number'])->keyBy('id');
        $this->payments = Payment::with(['allocations.invoice', 'refunds.allocations.invoice'])
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
