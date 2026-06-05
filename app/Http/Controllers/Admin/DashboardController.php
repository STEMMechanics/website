<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboard): View
    {
        return view('admin.dashboard.index', $dashboard->build((string) $request->query('period', 'week')));
    }
}
