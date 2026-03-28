<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Services\Classroom\ClassroomStateService;
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
            'clientErrorEndpoint' => route('class.client-error.store', $classSession),
        ]);
    }
}
