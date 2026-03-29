<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Services\Classroom\ClassroomStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly ClassroomStateService $stateService
    ) {}

    public function show(Request $request, ClassSession $classSession): View
    {
        $this->authorize('view', $classSession);

        $state = $this->stateService->stateFor($request->user(), $classSession);

        return view('classrooms.show', [
            'state' => $state,
            'livekitUrl' => config('livekit.url'),
            'tokenEndpoint' => route('livekit.token'),
            'helpRequestStateEndpoint' => route('class.help-requests.index', $classSession),
            'helpRequestStoreEndpoint' => route('class.help-requests.store', $classSession),
            'helpRequestApprovePattern' => route('class.help-requests.approve', ['classSession' => $classSession, 'helpRequest' => '__REQUEST__']),
            'helpRequestRevokePattern' => route('class.help-requests.revoke', ['classSession' => $classSession, 'helpRequest' => '__REQUEST__']),
            'broadcastStartEndpoint' => route('class.broadcast.start', $classSession),
            'broadcastEndEndpoint' => route('class.broadcast.end', $classSession),
            'clientErrorEndpoint' => route('class.client-error.store', $classSession),
        ]);
    }

    public function startBroadcast(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('manage', $classSession);

        $classSession->forceFill([
            'live_broadcast_started_at' => now(),
            'live_broadcast_ended_at' => null,
            'live_broadcast_started_by_user_id' => (string) $request->user()->id,
            'live_broadcast_ended_by_user_id' => null,
        ])->save();

        $state = $this->stateService->stateFor($request->user(), $classSession);

        return response()->json([
            'message' => 'Livestream started.',
            'state' => $state,
        ]);
    }

    public function endBroadcast(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('manage', $classSession);

        $classSession->forceFill([
            'live_broadcast_ended_at' => now(),
            'live_broadcast_ended_by_user_id' => (string) $request->user()->id,
        ])->save();

        $state = $this->stateService->stateFor($request->user(), $classSession);

        return response()->json([
            'message' => 'Livestream ended.',
            'state' => $state,
        ]);
    }
}
