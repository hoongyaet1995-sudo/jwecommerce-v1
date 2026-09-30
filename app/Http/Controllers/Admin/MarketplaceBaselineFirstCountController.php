<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\MarketplaceStockLocationNotApplicable;
use App\Services\Inventory\StoreManagement\MarketplaceBaselineFirstCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MarketplaceBaselineFirstCountController extends Controller
{
    public function observe(Request $request, MarketplaceBaselineFirstCount $service): JsonResponse
    {
        $data = $request->validate([
            'store_key' => ['required', 'string', 'max:32'],
            'facility_id' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['required', 'integer', 'min:1'],
        ]);
        try {
            $result = $service->observe($request->user(), $data['store_key'],
                $data['facility_id'] ?? null, $data['variant_id']);
        } catch (MarketplaceStockLocationNotApplicable $error) {
            throw ValidationException::withMessages(['stock' => $error->getMessage()]);
        }
        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function commit(Request $request, MarketplaceBaselineFirstCount $service): JsonResponse
    {
        $data = $request->validate([
            'store_key' => ['required', 'string', 'max:32'],
            'facility_id' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['required', 'integer', 'min:1'],
            'source' => ['required', Rule::in(['marketplace', 'manual'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'expected_provider_quantity' => ['nullable', 'integer', 'min:0'],
            'operation_key' => ['required', 'string', 'max:120'],
            'allow_provider_overwrite' => ['sometimes', 'boolean'],
        ]);
        return response()->json($service->commit($request->user(), $data['store_key'],
            $data['facility_id'] ?? null, $data['variant_id'], $data['source'],
            $data['quantity'], $data['expected_provider_quantity'] ?? null,
            $data['operation_key'], $data['allow_provider_overwrite'] ?? false))
            ->header('Cache-Control', 'no-store');
    }
}
