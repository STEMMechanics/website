<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardActions;
use App\Services\DashboardSnapshot;
use App\Services\Finance\WorkshopAllocation;
use App\Services\WeeklyWorkplanPdfService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request, WeeklyWorkplanService $workplans, AdminDashboardActions $actions): View
    {
        return view('admin.dashboard.index', app(DashboardSnapshot::class)->get((string) $request->query('period', 'overview')) + [
            'workplan' => $workplans->build(),
            'allocationTasks' => app(WorkshopAllocation::class)->attention(),
            'actionCards' => $actions->build((string) $request->user()->getAuthIdentifier()),
        ]);
    }

    public function actions(Request $request, AdminDashboardActions $actions): JsonResponse
    {
        return response()->json(['actions' => $actions->build((string) $request->user()->getAuthIdentifier())])
            ->header('Cache-Control', 'no-store, private');
    }

    public function dismissAction(Request $request, AdminDashboardActions $actions): JsonResponse
    {
        $validated = $request->validate([
            'action_key' => ['required', 'string', 'regex:/^bas:\\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        abort_unless($actions->dismissAction((string) $request->user()->getAuthIdentifier(), $validated['action_key']), 404);

        return response()->json(['success' => true]);
    }

    public function viewWorkplan(WeeklyWorkplanService $workplans, WeeklyWorkplanPdfService $pdfs): StreamedResponse
    {
        $workplan = $workplans->build();
        $binary = $pdfs->render($workplan);

        return response()->stream(fn () => print ($binary), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdfs->filename($workplan).'"',
        ]);
    }
}
