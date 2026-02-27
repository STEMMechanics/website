<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SquareWebhookEvent;
use Illuminate\Support\Carbon;

class SquareWebhookSyncService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{payment: Payment|null, created_payment: bool, event_updated: bool}
     */
    public function syncPayload(array $payload, ?SquareWebhookEvent $event = null): array
    {
        $eventType = trim((string) ($payload['type'] ?? $event?->event_type ?? ''));
        $eventId = trim((string) ($payload['event_id'] ?? $event?->event_id ?? ''));
        $payment = data_get($payload, 'data.object.payment');
        $refund = data_get($payload, 'data.object.refund');
        $squarePaymentId = (string) ($payment['id'] ?? $refund['payment_id'] ?? '');
        $referenceId = (string) ($payment['reference_id'] ?? $refund['payment_id'] ?? '');

        $customerPayment = null;
        $createdPayment = false;

        if ($squarePaymentId !== '') {
            $customerPayment = Payment::query()
                ->where('square_payment_id', $squarePaymentId)
                ->first();
        }

        if (! $customerPayment && str_starts_with($referenceId, 'payment:')) {
            $id = (int) substr($referenceId, strlen('payment:'));
            if ($id > 0) {
                $customerPayment = Payment::query()->find($id);
            }
        }

        if (! $customerPayment && is_array($payment) && $this->isSquarePosPayment($payment)) {
            [$customerPayment, $createdPayment] = $this->createUnallocatedSquarePosPayment($payment);
        }

        if ($customerPayment instanceof Payment) {
            $this->applyWebhookToPayment($customerPayment, $eventType, $eventId, $payment, $refund, $payload);
        }

        $eventUpdated = false;
        if ($event instanceof SquareWebhookEvent) {
            $newPaymentId = $customerPayment?->id;
            $eventPaymentId = $event->payment_id !== null ? (int) $event->payment_id : null;
            if ($newPaymentId !== null && $eventPaymentId !== $newPaymentId) {
                $event->payment_id = $newPaymentId;
                $eventUpdated = true;
            }
            if ($event->processed_at === null) {
                $event->processed_at = now();
                $eventUpdated = true;
            }
            if ($eventUpdated) {
                $event->save();
            }
        }

        return [
            'payment' => $customerPayment,
            'created_payment' => $createdPayment,
            'event_updated' => $eventUpdated,
        ];
    }

    private function applyWebhookToPayment(
        Payment $customerPayment,
        string $eventType,
        string $eventId,
        mixed $payment,
        mixed $refund,
        array $payload
    ): void {
        if (is_array($payment)) {
            $incomingAmountCents = (int) ($payment['amount_money']['amount'] ?? $customerPayment->square_paid_money_amount ?? 0);
            $incomingAmount = round(max(0, $incomingAmountCents) / 100, 2);

            $customerPayment->gateway_provider = 'square';
            $customerPayment->gateway_status = (string) ($payment['status'] ?? $customerPayment->gateway_status);
            $customerPayment->square_payment_id = (string) ($payment['id'] ?? $customerPayment->square_payment_id);
            $customerPayment->square_order_id = (string) ($payment['order_id'] ?? $customerPayment->square_order_id);
            $customerPayment->square_location_id = (string) ($payment['location_id'] ?? $customerPayment->square_location_id);
            $customerPayment->square_receipt_url = (string) ($payment['receipt_url'] ?? $customerPayment->square_receipt_url);
            $customerPayment->square_paid_money_amount = $incomingAmountCents;
            $customerPayment->gateway_reference_id = (string) ($payment['reference_id'] ?? $customerPayment->gateway_reference_id);
            $customerPayment->square_card_brand = (string) ($payment['card_details']['card']['card_brand'] ?? $customerPayment->square_card_brand);
            $customerPayment->square_card_last4 = (string) ($payment['card_details']['card']['last_4'] ?? $customerPayment->square_card_last4);
            $customerPayment->square_gateway_created_at = $this->squareDateTime($payment['created_at'] ?? null) ?? $customerPayment->square_gateway_created_at;
            $customerPayment->square_gateway_updated_at = $this->squareDateTime($payment['updated_at'] ?? null) ?? $customerPayment->square_gateway_updated_at;
            if ($customerPayment->isAutoImportedSquarePos() && $incomingAmount > 0) {
                $customerPayment->total_amount = $incomingAmount;
                if (! $customerPayment->allocations()->where('allocated_amount', '>', 0)->exists()) {
                    $customerPayment->gst_amount = $this->estimateInclusiveGst($incomingAmount);
                }
            }
        }

        if (is_array($refund)) {
            $customerPayment->gateway_provider = 'square';
            $refundAmount = (int) ($refund['amount_money']['amount'] ?? 0);
            $paidAmount = (int) ($customerPayment->square_paid_money_amount ?? 0);
            $recordedRefundedCents = (int) round(((float) $customerPayment->refunds()->sum('total_amount')) * 100);
            if ($recordedRefundedCents > 0) {
                $customerPayment->square_refunded_money_amount = min($paidAmount, max(0, $recordedRefundedCents));
            } else {
                $currentRefunded = (int) ($customerPayment->square_refunded_money_amount ?? 0);
                $customerPayment->square_refunded_money_amount = min($paidAmount, max(0, $currentRefunded + $refundAmount));
            }
            $customerPayment->square_gateway_created_at = $this->squareDateTime($refund['created_at'] ?? null) ?? $customerPayment->square_gateway_created_at;
            $customerPayment->square_gateway_updated_at = $this->squareDateTime($refund['updated_at'] ?? null) ?? $customerPayment->square_gateway_updated_at;
        }

        $customerPayment->markSquareWebhook($eventType, $eventId, $payload);
        $customerPayment->save();
    }

    private function isSquarePosPayment(array $payment): bool
    {
        $squareProduct = strtoupper(trim((string) ($payment['application_details']['square_product'] ?? '')));
        if ($squareProduct === 'SQUARE_POS') {
            return true;
        }

        $deviceName = trim((string) ($payment['device_details']['device_name'] ?? ''));

        return $deviceName !== '';
    }

    /**
     * @return array{0: Payment, 1: bool}
     */
    private function createUnallocatedSquarePosPayment(array $payment): array
    {
        $existingBySquareId = Payment::query()
            ->where('square_payment_id', (string) ($payment['id'] ?? ''))
            ->first();

        if ($existingBySquareId instanceof Payment) {
            return [$existingBySquareId, false];
        }

        $amountCents = (int) ($payment['amount_money']['amount'] ?? $payment['total_money']['amount'] ?? 0);
        $amount = round(max(0, $amountCents) / 100, 2);
        $capturedAt = $payment['card_details']['card_payment_timeline']['captured_at'] ?? null;
        $authorizedAt = $payment['card_details']['card_payment_timeline']['authorized_at'] ?? null;
        $createdAt = $payment['created_at'] ?? null;
        $receivedOn = $this->squareDateTime($capturedAt)
            ?? $this->squareDateTime($authorizedAt)
            ?? $this->squareDateTime($createdAt)
            ?? now();
        $deviceName = trim((string) ($payment['device_details']['device_name'] ?? ''));
        $receiptNumber = trim((string) ($payment['receipt_number'] ?? ''));
        $sourceType = trim((string) ($payment['source_type'] ?? ''));

        $paymentRecord = new Payment();
        $paymentRecord->kind = Payment::KIND_PAYMENT;
        $paymentRecord->user_id = null;
        $paymentRecord->created_by = null;
        $paymentRecord->received_on = $receivedOn;
        $paymentRecord->payment_method = Payment::PAYMENT_METHOD_EFTPOS;
        $paymentRecord->reference = trim(implode(' | ', array_filter([
            'Square POS',
            $receiptNumber !== '' ? 'Receipt '.$receiptNumber : null,
            $sourceType !== '' ? 'Source '.$sourceType : null,
        ])));
        $paymentRecord->total_amount = $amount;
        $paymentRecord->gst_amount = $this->estimateInclusiveGst($amount);
        $paymentRecord->notes = trim(implode("\n", array_filter([
            'Auto-created from Square POS webhook.',
            $deviceName !== '' ? 'Device: '.$deviceName : null,
        ])));
        $paymentRecord->gateway_provider = 'square';
        $paymentRecord->gateway_status = (string) ($payment['status'] ?? '');
        $paymentRecord->gateway_reference_id = (string) ($payment['reference_id'] ?? '');
        $paymentRecord->square_payment_id = (string) ($payment['id'] ?? '');
        $paymentRecord->square_order_id = (string) ($payment['order_id'] ?? '');
        $paymentRecord->square_location_id = (string) ($payment['location_id'] ?? '');
        $paymentRecord->square_receipt_url = (string) ($payment['receipt_url'] ?? '');
        $paymentRecord->square_card_brand = (string) ($payment['card_details']['card']['card_brand'] ?? '');
        $paymentRecord->square_card_last4 = (string) ($payment['card_details']['card']['last_4'] ?? '');
        $paymentRecord->square_paid_money_amount = max(0, $amountCents);
        $paymentRecord->square_refunded_money_amount = (int) ($payment['refunded_money']['amount'] ?? 0);
        $paymentRecord->square_gateway_created_at = $this->squareDateTime($payment['created_at'] ?? null);
        $paymentRecord->square_gateway_updated_at = $this->squareDateTime($payment['updated_at'] ?? null);
        $paymentRecord->save();

        return [$paymentRecord, true];
    }

    private function estimateInclusiveGst(float $totalInc): float
    {
        if ($totalInc <= 0) {
            return 0.0;
        }

        return round($totalInc / 11, 2);
    }

    private function squareDateTime(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
