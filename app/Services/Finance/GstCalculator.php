<?php

namespace App\Services\Finance;

use App\Models\Payment;

class GstCalculator
{
    public function paymentGstAmount(Payment $payment): float
    {
        $baseGst = $this->paymentBaseGstAmount($payment);

        if ($payment->isRefund() && $baseGst <= 0.0001) {
            $original = $payment->refundOf;
            if ($original instanceof Payment) {
                $originalAmount = abs(round((float) $original->total_amount, 2));
                $refundAmount = abs(round((float) $payment->total_amount, 2));
                if ($originalAmount > 0.0001 && $refundAmount > 0.0001) {
                    $ratio = max(0.0, min(1.0, $refundAmount / $originalAmount));
                    $baseGst = round($this->paymentBaseGstAmount($original) * $ratio, 2);
                }
            }
        }

        if ($payment->isRefund()) {
            return -abs($baseGst);
        }

        return abs($baseGst);
    }

    private function paymentBaseGstAmount(Payment $payment): float
    {
        $storedGst = round((float) $payment->gst_amount, 2);
        if (abs($storedGst) > 0.0001) {
            return abs($storedGst);
        }

        $calculatedGst = 0.0;
        foreach ($payment->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if (! $invoice) {
                continue;
            }

            $allocatedAmount = (float) $allocation->allocated_amount;
            $invoiceTotal = (float) $invoice->total_amount;
            $invoiceGst = (float) $invoice->gst_amount;

            if ($allocatedAmount <= 0 || $invoiceTotal <= 0 || $invoiceGst <= 0) {
                continue;
            }

            $ratio = max(0.0, min(1.0, $allocatedAmount / $invoiceTotal));
            $calculatedGst += $invoiceGst * $ratio;
        }

        return abs(round($calculatedGst, 2));
    }
}
