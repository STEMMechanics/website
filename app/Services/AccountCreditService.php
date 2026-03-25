<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountCreditService
{
    public function availableCreditForUser(?User $user): float
    {
        if (! $user instanceof User) {
            return 0.0;
        }

        $available = 0.0;
        foreach ($this->creditPaymentsForUser($user)->get() as $payment) {
            if (! $payment instanceof Payment) {
                continue;
            }

            $available = round($available + $this->availableCreditForPayment($payment), 2);
        }

        return $available;
    }

    public function applyCreditToInvoice(Invoice $invoice, ?User $user, float $requestedAmount): float
    {
        if (! $user instanceof User || $requestedAmount <= 0.0001) {
            return 0.0;
        }

        $remaining = round(max(0, $requestedAmount), 2);
        $applied = 0.0;

        /** @var Collection<int, Payment> $creditPayments */
        $creditPayments = $this->creditPaymentsForUser($user)
            ->lockForUpdate()
            ->get();

        foreach ($creditPayments as $creditPayment) {
            if ($remaining <= 0.0001) {
                break;
            }

            $available = $this->availableCreditForPayment($creditPayment);
            if ($available <= 0.0001) {
                continue;
            }

            $allocationAmount = round(min($available, $remaining), 2);
            if ($allocationAmount <= 0.0001) {
                continue;
            }

            $creditPayment->allocations()->create([
                'invoice_id' => $invoice->id,
                'allocated_amount' => $allocationAmount,
            ]);

            $applied = round($applied + $allocationAmount, 2);
            $remaining = round($remaining - $allocationAmount, 2);
        }

        return $applied;
    }

    private function creditPaymentsForUser(User $user): Builder
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->whereNull('refund_of_payment_id')
            ->where('kind', Payment::KIND_PAYMENT)
            ->withSum('allocations as allocated_amount_sum', 'allocated_amount')
            ->withSum('refunds as refunded_amount_sum', 'total_amount')
            ->orderBy('received_on')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function availableCreditForPayment(Payment $payment): float
    {
        $total = (float) $payment->total_amount;
        $allocated = (float) ($payment->allocated_amount_sum ?? 0);
        $refunded = (float) ($payment->refunded_amount_sum ?? 0);

        return max(0, round($total - $allocated - $refunded, 2));
    }
}
