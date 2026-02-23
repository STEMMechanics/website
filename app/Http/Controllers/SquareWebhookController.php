<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SquareWebhookEvent;
use App\Services\SquareApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquareWebhookController extends Controller
{
    public function handle(Request $request, SquareApiService $squareApi): JsonResponse
    {
        if (! $squareApi->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Square disabled'], 503);
        }

        $signature = (string) ($request->header('x-square-hmacsha256-signature')
            ?? $request->header('x-square-signature')
            ?? '');
        $payloadText = (string) $request->getContent();
        $configuredWebhookUrl = trim((string) config('services.square.webhook_url'));
        $candidateUrls = $this->webhookUrlCandidates($request, $configuredWebhookUrl);

        $isValidSignature = false;
        foreach ($candidateUrls as $candidateUrl) {
            if ($squareApi->validateWebhookSignature($payloadText, $signature, $candidateUrl)) {
                $isValidSignature = true;
                break;
            }
        }

        if (! $isValidSignature) {
            Log::warning('Square webhook signature validation failed.', [
                'event_id' => (string) ($request->json('event_id') ?? ''),
                'type' => (string) ($request->json('type') ?? ''),
                'configured_webhook_url' => $configuredWebhookUrl,
                'request_full_url' => $request->fullUrl(),
                'candidate_urls' => $candidateUrls,
                'signature_header_prefix' => substr($signature, 0, 16),
                'payload_size' => strlen($payloadText),
            ]);

            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        $eventType = trim((string) ($payload['type'] ?? ''));

        if ($eventId === '') {
            return response()->json(['ok' => false, 'message' => 'Missing event_id'], 422);
        }

        $alreadyProcessed = SquareWebhookEvent::query()->where('event_id', $eventId)->exists();
        if ($alreadyProcessed) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $payment = data_get($payload, 'data.object.payment');
        $refund = data_get($payload, 'data.object.refund');
        $squarePaymentId = (string) ($payment['id'] ?? $refund['payment_id'] ?? '');
        $referenceId = (string) ($payment['reference_id'] ?? $refund['payment_id'] ?? '');

        $customerPayment = null;
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

        if ($customerPayment) {
            $this->applyWebhookToPayment($customerPayment, $eventType, $eventId, $payment, $refund, $payload);
        }

        SquareWebhookEvent::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payment_id' => $customerPayment?->id,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function applyWebhookToPayment(
        Payment $customerPayment,
        string $eventType,
        string $eventId,
        $payment,
        $refund,
        array $payload
    ): void {
        if (is_array($payment)) {
            $customerPayment->gateway_provider = 'square';
            $customerPayment->gateway_status = (string) ($payment['status'] ?? $customerPayment->gateway_status);
            $customerPayment->square_payment_id = (string) ($payment['id'] ?? $customerPayment->square_payment_id);
            $customerPayment->square_order_id = (string) ($payment['order_id'] ?? $customerPayment->square_order_id);
            $customerPayment->square_location_id = (string) ($payment['location_id'] ?? $customerPayment->square_location_id);
            $customerPayment->square_receipt_url = (string) ($payment['receipt_url'] ?? $customerPayment->square_receipt_url);
            $customerPayment->square_paid_money_amount = (int) ($payment['amount_money']['amount'] ?? $customerPayment->square_paid_money_amount ?? 0);
            $customerPayment->gateway_reference_id = (string) ($payment['reference_id'] ?? $customerPayment->gateway_reference_id);
            $customerPayment->square_card_brand = (string) ($payment['card_details']['card']['card_brand'] ?? $customerPayment->square_card_brand);
            $customerPayment->square_card_last4 = (string) ($payment['card_details']['card']['last_4'] ?? $customerPayment->square_card_last4);
            $customerPayment->square_gateway_created_at = $this->squareDateTime($payment['created_at'] ?? null) ?? $customerPayment->square_gateway_created_at;
            $customerPayment->square_gateway_updated_at = $this->squareDateTime($payment['updated_at'] ?? null) ?? $customerPayment->square_gateway_updated_at;
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

    private function squareDateTime($value): ?\Illuminate\Support\Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function webhookUrlCandidates(Request $request, string $configuredWebhookUrl): array
    {
        $candidates = [];

        $add = static function (array &$urls, string $url): void {
            $normalized = trim($url);
            if ($normalized === '') {
                return;
            }

            if (! in_array($normalized, $urls, true)) {
                $urls[] = $normalized;
            }
        };

        if ($configuredWebhookUrl !== '') {
            $add($candidates, $configuredWebhookUrl);
            $add($candidates, rtrim($configuredWebhookUrl, '/'));
            $add($candidates, rtrim($configuredWebhookUrl, '/').'/');
        }

        $runtimePath = $request->getPathInfo();
        $runtimeBase = rtrim((string) config('app.url'), '/');
        $runtimeHostUrl = $request->getSchemeAndHttpHost().$runtimePath;

        $add($candidates, $request->fullUrl());
        $add($candidates, $runtimeHostUrl);
        $add($candidates, rtrim((string) $request->fullUrl(), '/'));
        $add($candidates, $runtimeBase.$runtimePath);

        return $candidates;
    }
}
