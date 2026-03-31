<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\ForumTopic;
use App\Services\Classroom\ClassroomStateService;
use App\Services\Classroom\ClassroomBroadcastLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly ClassroomStateService $stateService,
        private readonly ClassroomBroadcastLifecycleService $broadcastLifecycleService
    ) {}

    public function show(Request $request, ClassSession $classSession): View
    {
        $this->authorize('view', $classSession);

        $state = $this->stateService->stateFor($request->user(), $classSession);
        $forumUnreadCount = 0;
        $forumCategoryId = (string) ($classSession->forum_category_id ?? '');
        if ($forumCategoryId !== '' && $request->user()) {
            $forumUnreadCountByCategoryId = ForumTopic::unreadCountMapForUser($request->user());
            $forumUnreadCount = (int) ($forumUnreadCountByCategoryId[$forumCategoryId] ?? 0);
        }

        return view('classrooms.show', [
            'state' => $state,
            'forumUnreadCount' => $forumUnreadCount,
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

        $this->broadcastLifecycleService->startBroadcast($classSession, $request->user());

        $state = $this->stateService->stateFor($request->user(), $classSession);

        return response()->json([
            'message' => 'Livestream started.',
            'state' => $state,
        ]);
    }

    public function endBroadcast(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('manage', $classSession);

        $this->broadcastLifecycleService->endBroadcast($classSession, $request->user());

        $state = $this->stateService->stateFor($request->user(), $classSession);

        return response()->json([
            'message' => 'Livestream ended.',
            'state' => $state,
        ]);
    }
}
