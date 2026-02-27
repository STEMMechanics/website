<?php

namespace App\Http\Controllers;

use App\Models\SquareWebhookEvent;
use App\Services\SquareApiService;
use App\Services\SquareWebhookSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquareWebhookController extends Controller
{
    public function handle(
        Request $request,
        SquareApiService $squareApi,
        SquareWebhookSyncService $syncService
    ): JsonResponse
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

        $event = SquareWebhookEvent::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payment_id' => null,
            'payload' => $payload,
            'processed_at' => now(),
        ]);
        $syncService->syncPayload($payload, $event);

        return response()->json(['ok' => true]);
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
