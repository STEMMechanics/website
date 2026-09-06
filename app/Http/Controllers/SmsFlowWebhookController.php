<?php

namespace App\Http\Controllers;

use App\Services\SmsFlowInboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsFlowWebhookController extends Controller
{
    public function handle(Request $request, SmsFlowInboundService $smsFlowInboundService): JsonResponse
    {
        $secret = (string) config('services.smsflow.webhook_secret', '');
        $provided = $request->bearerToken() ?? $request->query('webhook_secret', '');
        if (strlen($secret) < 32 || $secret === (string) config('services.smsflow.api_key') || ! is_string($provided) || ! hash_equals($secret, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        $rawBody = (string) $request->getContent();
        $decodedBody = json_decode($rawBody, true);

        if (is_array($decodedBody)) {
            try {
                $inboundSms = $smsFlowInboundService->storeIncomingPayload($decodedBody, [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('SMSFlow inbound payload could not be stored.', [
                    'exception' => $exception::class,
                ]);
                throw $exception;
            }
        } else {
            $inboundSms = null;
        }

        Log::info('SMSFlow webhook received.', [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'topic' => is_array($decodedBody) ? ($decodedBody['topic'] ?? null) : null,
            'inbound_sms_id' => $inboundSms?->id,
            'matched_sent_sms_id' => $inboundSms?->sent_sms_id,
        ]);

        return response()->json(['ok' => true]);
    }
}
