<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardDataService;
use Illuminate\Http\JsonResponse;

class DashboardDataController extends Controller
{
    public function __invoke(DashboardDataService $dashboard): JsonResponse
    {
        return response()->json($dashboard->data());
    }
}
