<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomChatStoreRequest;
use App\Models\ClassChatMessage;
use App\Models\ClassSession;
use App\Models\User;
use App\Services\Classroom\ClassroomChatService;
use App\Services\Classroom\ClassroomStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

        try {
            $chatMessage = $this->chatService->store(
                $request->user(),
                $classSession,
                (string) $request->validated()['message']
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Chat message sent.',
            'chatMessage' => $chatMessage,
            'state' => $this->stateService->stateFor($request->user(), $classSession),
        ]);
    }

    public function destroy(Request $request, ClassSession $classSession, ClassChatMessage $chatMessage): JsonResponse
    {
        $this->authorize('manage', $classSession);

        try {
            $this->chatService->deleteMessage($classSession, $chatMessage, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Chat message deleted.',
            'state' => $this->stateService->stateFor($request->user(), $classSession),
        ]);
    }

    public function clear(Request $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('manage', $classSession);

        $deletedCount = $this->chatService->clearMessages($classSession, $request->user());

        return response()->json([
            'message' => 'Chat cleared.',
            'deletedCount' => $deletedCount,
            'state' => $this->stateService->stateFor($request->user(), $classSession),
        ]);
    }

    public function updateParticipantState(Request $request, ClassSession $classSession, User $user): JsonResponse
    {
        $this->authorize('manage', $classSession);

        $disabled = filter_var($request->input('disabled', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $disabled = $disabled === null ? true : $disabled;

        try {
            $this->chatService->setParticipantChatDisabled($classSession, $user, $request->user(), $disabled);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $disabled ? 'Chat disabled for participant.' : 'Chat enabled for participant.',
            'state' => $this->stateService->stateFor($request->user(), $classSession),
        ]);
    }
}
