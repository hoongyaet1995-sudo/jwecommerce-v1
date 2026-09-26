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
            'order_ids' => ['required_without:lines', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'lines' => ['required_without:order_ids', 'array', 'min:1', 'max:100'],
            'lines.*.order_id' => ['required', 'integer', 'min:1'],
            'lines.*.line_key' => ['required', 'string', 'max:100'],
            'lines.*.decision' => ['sometimes', 'in:historical,risk_accepted'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        return response()->json($service->reviewHistorical($request->user(), $store, $facility,
            $variant, $input['lines'] ?? array_map(fn ($id) => ['order_id' => $id], $input['order_ids']), $input['reason']))->header('Cache-Control', 'no-store');
    }
}
