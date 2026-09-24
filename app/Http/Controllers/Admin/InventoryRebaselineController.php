<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\RebaselineReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryRebaselineController extends Controller
{
    public function readiness(Request $request, RebaselineReadiness $readiness): JsonResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);

        return response()->json($readiness->inspect())->header('Cache-Control', 'no-store');
    }
}
