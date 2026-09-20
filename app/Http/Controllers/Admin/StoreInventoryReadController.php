<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\OverviewReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class StoreInventoryReadController extends Controller
{
    public function overview(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['platform' => ['nullable', Rule::in(['shopee', 'lazada'])], 'sku' => ['nullable', 'string', 'max:100']]);

        return response()->json($reader->read($filters))->header('Cache-Control', 'no-store');
    }

    public function stores(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['platform' => ['nullable', Rule::in(['shopee', 'lazada'])]]);

        return response()->json(['data' => $reader->stores($filters)])->header('Cache-Control', 'no-store');
    }

    public function history(Request $request, OverviewReader $reader): JsonResponse
    {
        $filters = $request->validate(['kind' => ['nullable', 'string', 'max:32'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($reader->history($filters))->header('Cache-Control', 'no-store');
    }

    public function operation(string $operation, OverviewReader $reader): JsonResponse
    {
        return response()->json($reader->operation($operation))->header('Cache-Control', 'no-store');
    }
}
