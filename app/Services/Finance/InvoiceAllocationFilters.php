<?php

namespace App\Services\Finance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvoiceAllocationFilters
{
    public function apply(Builder $query, array $data): void
    {
        if (! empty($data['customer'])) {
            $search = trim($data['customer']);
            $query->where(function ($customers) use ($search) {
                $customers->where('billing_name', 'like', '%'.$search.'%')->orWhere('billing_email', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($users) use ($search) {
                        foreach (preg_split('/\s+/', $search) as $word) {
                            $users->where(fn ($names) => $names->where('firstname', 'like', '%'.$word.'%')->orWhere('surname', 'like', '%'.$word.'%')->orWhere('email', 'like', '%'.$word.'%'));
                        }
                    });
            });
        }
        if (! empty($data['line_types'])) {
            $types = $data['line_types'];
            if (in_array('custom', $types, true)) {
                $types[] = 'generic';
            }
            $query->where(function ($invoices) use ($types) {
                $invoices->whereHas('lines', fn ($lines) => $lines->whereIn('kind', $types));
                if (in_array('ticket', $types, true)) {
                    $invoices->orWhereHas('tickets');
                }
            });
        }
        if (! empty($data['payment_from']) || ! empty($data['payment_to'])) {
            $query->whereHas('allocations', function ($allocations) use ($data) {
                $allocations->where('allocated_amount', '>', 0)->whereHas('customerPayment', function ($payments) use ($data) {
                    $payments->where('kind', 'payment')->where(fn ($status) => $status->whereNull('gateway_status')->orWhereIn('gateway_status', ['COMPLETED', 'APPROVED']));
                    if (! empty($data['payment_from'])) {
                        $payments->whereDate('received_on', '>=', $data['payment_from']);
                    }
                    if (! empty($data['payment_to'])) {
                        $payments->whereDate('received_on', '<=', $data['payment_to']);
                    }
                });
            });
        }
        if (! empty($data['allocation_state']) || ! empty($data['allocation_plan'])) {
            if (($data['allocation_state'] ?? '') === 'not_allocated' && ! empty($data['allocation_plan'])) {
                $query->whereRaw('1 = 0');

                return;
            }
            $budgets = DB::table('finance_budgets')->whereNull('workshop_id')->select('id');
            if (($data['allocation_state'] ?? '') === 'not_allocated') {
                $query->where(fn ($part) => $part->whereDoesntHave('tickets')->orWhereHas('lines', fn ($lines) => $lines->where('kind', '!=', 'ticket')->where('line_total_ex_tax', '>', 0)));
            }
            if (($data['allocation_state'] ?? '') === 'manual') {
                $budgets->where('manual', true);
            }
            if (($data['allocation_state'] ?? '') === 'automatic') {
                $budgets->where('manual', false);
            }
            if (! empty($data['allocation_plan'])) {
                $budgets->where('pricing_version_id', $data['allocation_plan']);
            }
            $match = function ($invoices) use ($budgets) {
                $invoices->whereIn('invoices.id', DB::table('finance_budget_invoices')->whereIn('budget_id', clone $budgets)->select('invoice_id'));
            };
            if (($data['allocation_state'] ?? '') === 'not_allocated') {
                $query->whereNot($match);
            } else {
                $query->where($match);
            }
        }
    }
}
