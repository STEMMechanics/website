<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

class ExpenseDuplicateService
{
    /** @return Collection<int, Expense> */
    public function matches(?string $supplier, ?string $invoiceId, ?int $ignoreExpenseId = null): Collection
    {
        $invoiceId = Expense::normalizeInvoiceId($invoiceId);
        if ($invoiceId === '') {
            return new Collection;
        }

        $supplier = trim((string) $supplier);
        $supplierKey = mb_strtolower($supplier);
        $supplierId = $supplierKey === ''
            ? null
            : Supplier::query()->where('supplier', $supplierKey)->value('id');

        $query = Expense::query()
            ->where('invoice_id_normalized', $invoiceId)
            ->when($ignoreExpenseId !== null, fn ($builder) => $builder->where('id', '!=', $ignoreExpenseId));

        if ($supplierKey !== '') {
            $query->where(function ($builder) use ($supplierId, $supplierKey): void {
                if ($supplierId !== null) {
                    $builder->where('supplier_id', $supplierId)
                        ->orWhere(function ($legacy) use ($supplierKey): void {
                            $legacy->whereNull('supplier_id')
                                ->whereRaw('LOWER(TRIM(supplier)) = ?', [$supplierKey]);
                        });
                } else {
                    $builder->whereRaw('LOWER(TRIM(supplier)) = ?', [$supplierKey]);
                }
            });
        }

        return $query
            ->latest('paid_on')
            ->latest('id')
            ->get(['id', 'supplier', 'invoice_id', 'paid_on', 'total_amount', 'gst_amount']);
    }

    /** @return array<string, mixed> */
    public function payload(Expense $expense): array
    {
        return [
            'id' => (int) $expense->id,
            'supplier' => (string) $expense->supplier,
            'invoice_id' => (string) ($expense->invoice_id ?? ''),
            'paid_on' => $expense->paid_on?->toDateString(),
            'total_amount' => number_format((float) $expense->total_amount, 2, '.', ''),
            'gst_amount' => number_format((float) $expense->gst_amount, 2, '.', ''),
            'url' => route('admin.expense.edit', $expense),
        ];
    }
}
