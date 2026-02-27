<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SquareIgnoredPayment;
use App\Models\SquareWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SquareWebhookSyncService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{payment: Payment|null, created_payment: bool, event_updated: bool, ignored: bool}
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
        $ignored = false;

        if ($squarePaymentId !== '' && $this->isIgnoredSquarePaymentId($squarePaymentId)) {
            $ignored = true;
        }

        if (! $ignored && $squarePaymentId !== '') {
            $customerPayment = Payment::query()
                ->where('square_integration_meta->square_payment_id', $squarePaymentId)
                ->first();
        }

        if (! $ignored && ! $customerPayment && str_starts_with($referenceId, 'payment:')) {
            $id = (int) substr($referenceId, strlen('payment:'));
            if ($id > 0) {
                $customerPayment = Payment::query()->find($id);
            }
        }

        if (! $ignored && ! $customerPayment && is_array($payment) && $this->isSquarePosPayment($payment)) {
            [$customerPayment, $createdPayment] = $this->createUnallocatedSquarePosPayment($payment);
        }

        if (! $ignored && $customerPayment instanceof Payment) {
            $this->applyWebhookToPayment($customerPayment, $eventType, $eventId, $payment, $refund, $payload);
        }

        $eventUpdated = false;
        if ($event instanceof SquareWebhookEvent) {
            $newPaymentId = $customerPayment?->id;
            $eventPaymentId = $event->payment_id !== null ? (int) $event->payment_id : null;
            if ($ignored && $eventPaymentId !== null) {
                $event->payment_id = null;
                $eventUpdated = true;
            } elseif ($newPaymentId !== null && $eventPaymentId !== $newPaymentId) {
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
            'ignored' => $ignored,
        ];
    }

    private function isIgnoredSquarePaymentId(string $squarePaymentId): bool
    {
        static $cache = [];
        if (array_key_exists($squarePaymentId, $cache)) {
            return $cache[$squarePaymentId];
        }

        $cache[$squarePaymentId] = SquareIgnoredPayment::query()
            ->where('square_payment_id', $squarePaymentId)
            ->exists();

        return $cache[$squarePaymentId];
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
            if ($this->supportsSquareIntegrationMetaColumn()) {
                $customerPayment->square_integration_meta = $this->buildSquareIntegrationMeta($payment, $customerPayment->square_integration_meta);
            }
            if ($customerPayment->isAutoImportedSquarePos() && $incomingAmount > 0) {
                $customerPayment->total_amount = $incomingAmount;
                if (! $customerPayment->allocations()->where('allocated_amount', '>', 0)->exists()) {
                    $customerPayment->gst_amount = $this->estimateInclusiveGst($incomingAmount);
                }
                if ($this->isLegacyAutoNote((string) ($customerPayment->notes ?? ''))) {
                    $customerPayment->notes = null;
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
            ->where('square_integration_meta->square_payment_id', (string) ($payment['id'] ?? ''))
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
        $paymentRecord->total_amount = $amount;
        $paymentRecord->gst_amount = $this->estimateInclusiveGst($amount);
        $paymentRecord->notes = null;
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
        if ($this->supportsSquareIntegrationMetaColumn()) {
            $paymentRecord->square_integration_meta = $this->buildSquareIntegrationMeta($payment, null);
        }
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

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function buildSquareIntegrationMeta(array $payment, ?array $existing): array
    {
        $meta = is_array($existing) ? $existing : [];

        $set = static function (array &$target, string $key, mixed $value): void {
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === null || $value === '') {
                return;
            }
            $target[$key] = $value;
        };

        $set($meta, 'square_product', data_get($payment, 'application_details.square_product'));
        $set($meta, 'source_type', data_get($payment, 'source_type'));
        $set($meta, 'entry_method', data_get($payment, 'card_details.entry_method'));
        $set($meta, 'receipt_number', data_get($payment, 'receipt_number'));
        $set($meta, 'device_name', data_get($payment, 'device_details.device_name'));
        $set($meta, 'device_id', data_get($payment, 'device_details.device_id'));
        $set($meta, 'device_installation_id', data_get($payment, 'device_details.device_installation_id'));
        $set($meta, 'statement_description', data_get($payment, 'card_details.statement_description'));
        $set($meta, 'application_name', data_get($payment, 'card_details.application_name'));
        $set($meta, 'verification_method', data_get($payment, 'card_details.verification_method'));

        return $meta;
    }

    private function isLegacyAutoNote(string $notes): bool
    {
        $trimmed = trim($notes);
        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, 'Auto-created from Square POS webhook.');
    }

    private function supportsSquareIntegrationMetaColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('payments', 'square_integration_meta');
        }

        return $hasColumn;
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
