<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomClientErrorRequest;
use App\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClassroomClientErrorController extends Controller
{
    public function store(ClassroomClientErrorRequest $request, ClassSession $classSession): JsonResponse
    {
        $this->authorize('view', $classSession);

        $validated = $request->validated();

        Log::warning('Classroom client error', [
            'class_session_id' => (string) $classSession->id,
            'class_session_slug' => (string) $classSession->slug,
            'user_id' => (string) ($request->user()?->id ?? ''),
            'username' => (string) ($request->user()?->username ?? ''),
            'message' => (string) ($validated['message'] ?? ''),
            'source' => (string) ($validated['source'] ?? ''),
            'stack' => (string) ($validated['stack'] ?? ''),
            'context' => $validated['context'] ?? [],
            'user_agent' => (string) $request->userAgent(),
            'url' => (string) $request->fullUrl(),
        ]);

        return response()->json(['ok' => true]);
    }
}
