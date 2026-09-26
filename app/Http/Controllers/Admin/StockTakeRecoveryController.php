<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\StockTakeRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StockTakeRecoveryController extends Controller
{
    public function inspect(int $store, int $facility, int $variant, StockTakeRecovery $service): JsonResponse
    {
        return response()->json($service->inspect($store, $facility, $variant))->header('Cache-Control', 'no-store');
    }

    public function reviewHistorical(Request $request, int $store, int $facility, int $variant,
        StockTakeRecovery $service): JsonResponse
    {
        $input = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        return response()->json($service->reviewHistorical($request->user(), $store, $facility,
            $variant, $input['order_ids'], $input['reason']))->header('Cache-Control', 'no-store');
    }
}
