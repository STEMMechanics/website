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
                    'message' => $exception->getMessage(),
                    'payload_json' => $decodedBody,
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
            'headers' => $request->headers->all(),
            'payload_text' => $rawBody,
            'payload_json' => is_array($decodedBody) ? $decodedBody : null,
            'inbound_sms_id' => $inboundSms?->id,
            'matched_sent_sms_id' => $inboundSms?->sent_sms_id,
        ]);

        return response()->json(['ok' => true]);
    }
}
