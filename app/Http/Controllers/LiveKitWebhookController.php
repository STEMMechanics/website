<?php

namespace App\Http\Controllers;

use App\Services\LiveKit\LiveKitWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LiveKitWebhookController extends Controller
{
    public function __construct(
        private readonly LiveKitWebhookService $webhookService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->webhookService->receive(
                (string) $request->getContent(),
                $request->header('Authorization')
            );
            $this->webhookService->logEvent($event);

            return response()->json(['ok' => true]);
        } catch (Throwable $throwable) {
            return response()->json([
                'ok' => false,
                'message' => $throwable->getMessage(),
            ], 401);
        }
    }
}
