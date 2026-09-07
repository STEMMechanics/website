<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinanceAttention
{
    public function overdue(Builder $query): void
    {
        $query->where('total_amount', '>', 0)->whereDate('due_date', '<', today())
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE]);
    }

    public function unallocatedInvoices(Builder $query): void
    {
        $query->where('total_amount', '>', 0)->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);
        app(InvoiceAllocationFilters::class)->apply($query, ['allocation_state' => 'not_allocated']);
    }

    public function unallocatedExpenses(Builder $query): void
    {
        $suppliers = Supplier::query()->get(['id', 'supplier', 'splits'])->filter(fn ($supplier) => array_sum($supplier->splits ?? []) == 100);
        $manual = DB::table('finance_expense_splits')->selectRaw('1')->whereColumn('expense_id', 'expenses.id');
        $query->whereRaw('ROUND(expenses.total_amount * 100) - ROUND(expenses.gst_amount * 100) > 0')
            ->where(function ($missing) use ($suppliers, $manual): void {
                $missing->where(function ($noOverride) use ($suppliers, $manual): void {
                    $noOverride->whereNotExists(clone $manual)->where(function ($noDefault) use ($suppliers): void {
                        $noDefault->whereNotIn('supplier_id', $suppliers->pluck('id'))->orWhere(function ($legacy) use ($suppliers): void {
                            $legacy->whereNull('supplier_id')->whereNotIn(DB::raw('LOWER(TRIM(supplier))'), $suppliers->pluck('supplier'));
                        });
                    });
                })->orWhere(function ($invalidOverride) use ($manual): void {
                    $invalidOverride->whereExists(clone $manual)->whereRaw('(SELECT SUM(cents) FROM finance_expense_splits WHERE expense_id = expenses.id) <> ROUND(expenses.total_amount * 100) - ROUND(expenses.gst_amount * 100)');
                });
            });
    }

    public function counts(): array
    {
        if (request()->attributes->has('finance_attention_counts')) {
            return request()->attributes->get('finance_attention_counts');
        }
        $overdue = Invoice::query();
        $this->overdue($overdue);
        $unallocated = Invoice::query();
        $this->unallocatedInvoices($unallocated);
        $expenses = Expense::query();
        $this->unallocatedExpenses($expenses);
        $invoices = Invoice::query()->where(fn ($query) => $query
            ->where(fn ($part) => $this->overdue($part))
            ->orWhere(fn ($part) => $this->unallocatedInvoices($part)));
        $counts = ['overdue' => $overdue->count(), 'unallocated_invoices' => $unallocated->count(), 'invoices' => $invoices->count(), 'expenses' => $expenses->count()];
        request()->attributes->set('finance_attention_counts', $counts);
        return $counts;
    }
}
