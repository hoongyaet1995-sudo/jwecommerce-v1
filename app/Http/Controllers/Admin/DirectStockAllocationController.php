<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\DirectStockAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DirectStockAllocationController extends Controller
{
    public function store(Request $request, DirectStockAllocation $service): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['variant_id', 'physical_count', 'allocations', 'reason', 'operation_key', '_token']) !== []) {
            throw ValidationException::withMessages(['inventory' => 'Unsupported fields.']);
        }
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'physical_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255'],
            'operation_key' => ['required', 'string', 'max:120'],
        ]);

        return response()->json($service->apply($request->user(), $data['variant_id'], $data['physical_count'],
            $data['allocations'], $data['reason'], $data['operation_key']))->header('Cache-Control', 'no-store');
    }
}
