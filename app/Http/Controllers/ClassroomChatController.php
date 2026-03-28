<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomChatStoreRequest;
use App\Models\ClassSession;
use App\Services\Classroom\ClassroomChatService;
use App\Services\Classroom\ClassroomStateService;
use Illuminate\Http\JsonResponse;

class ClassroomChatController extends Controller
{
    public function __construct(
        private readonly ClassroomStateService $stateService,
        private readonly ClassroomChatService $chatService
    ) {}

    public function store(ClassroomChatStoreRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('view', $classSession);
        abort_unless((bool) $classSession->live_chat_enabled, 404);

        $chatMessage = $this->chatService->store(
            $request->user(),
            $classSession,
            (string) $request->validated()['message']
        );

        return response()->json([
            'message' => 'Chat message sent.',
            'chatMessage' => $chatMessage,
            'state' => $this->stateService->stateFor($request->user(), $classSession),
        ]);
    }
}
