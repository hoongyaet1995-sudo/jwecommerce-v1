<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardDataService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardDataService $dashboard): View
    {
        return view('admin.dashboard', $dashboard->legacyViewData());
    }
}
