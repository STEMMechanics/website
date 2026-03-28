<?php

namespace App\Http\Controllers;

use App\Http\Requests\LiveKitTokenRequest;
use App\Models\ClassSession;
use App\Services\LiveKit\LiveKitTokenService;
use Illuminate\Http\JsonResponse;

class LiveKitTokenController extends Controller
{
    public function __construct(
        private readonly LiveKitTokenService $tokenService
    ) {}

    public function store(LiveKitTokenRequest $request): JsonResponse
    {
        $classSession = $request->classSession();
        $this->authorize('join', $classSession);

        $payload = $this->tokenService->create($request->user(), $classSession);

        return response()->json($payload);
    }
}
