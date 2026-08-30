<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Services\WeeklyWorkplanPdfService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboard, WeeklyWorkplanService $workplans): View
    {
        return view('admin.dashboard.index', $dashboard->build((string) $request->query('period', 'overview')) + [
            'workplan' => $workplans->build(),
        ]);
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
