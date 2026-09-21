<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\AllocationReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class StoreInventoryAllocationController extends Controller
{
    public function store(Request $request, AllocationReviewService $service): JsonResponse
    {
        $this->allowOnly($request, ['variant_id', 'physical_count', 'allocations', 'reason', '_token']);
        $data = $request->validate(['variant_id' => ['required', 'integer', 'min:1'],
            'physical_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'allocations' => ['required', 'array', 'min:1'], 'allocations.*' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255']]);

        return response()->json($service->create($request->user(), $data), 201)->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, AllocationReviewService $service): JsonResponse
    {
        $this->allowOnly($request, ['operation_key', '_token']);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($service->confirm($request->user(), $review, $data['operation_key']))->header('Cache-Control', 'no-store');
    }

    private function allowOnly(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed)) throw ValidationException::withMessages(['inventory' => 'Unsupported fields. Actor and ledger results are server controlled.']);
    }
}
