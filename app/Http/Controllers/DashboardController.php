<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        if ($request->user()->isAdmin()) {
            return view('dashboard.admin', $dashboard->home());
        }

        return view('dashboard.employee', $dashboard->employee($request->user()->id));
    }
}
