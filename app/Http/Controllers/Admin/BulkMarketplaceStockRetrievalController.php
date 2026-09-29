<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\BulkMarketplaceStockRetrieval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BulkMarketplaceStockRetrievalController extends Controller
{
    public function start(Request $request, BulkMarketplaceStockRetrieval $service): JsonResponse
    {
        $data = $request->validate(['variant_ids' => ['required', 'array', 'min:1', 'max:250'],
            'variant_ids.*' => ['required', 'integer', 'min:1', 'distinct']]);
        return response()->json($service->start($request->user(), $data['variant_ids']), 202)
            ->header('Cache-Control', 'no-store');
    }

    public function latest(Request $request, BulkMarketplaceStockRetrieval $service): JsonResponse
    {
        return response()->json($service->latest($request->user()))->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $run, BulkMarketplaceStockRetrieval $service): JsonResponse
    {
        return response()->json($service->status($request->user(), $run))->header('Cache-Control', 'no-store');
    }

    public function resume(Request $request, string $run, BulkMarketplaceStockRetrieval $service): JsonResponse
    {
        return response()->json($service->resume($request->user(), $run))->header('Cache-Control', 'no-store');
    }
}
