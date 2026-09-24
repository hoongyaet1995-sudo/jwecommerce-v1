<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\BatchFirstCountReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class BatchFirstCountController extends Controller
{
    public function review(Request $request, BatchFirstCountReview $service): JsonResponse
    {
        $this->allowOnly($request, ['variant_id', 'physical_count', 'allocations', 'reason', '_token']);
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'physical_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($service->review($request->user(), $data['variant_id'],
            $data['physical_count'], $data['allocations'], $data['reason']), 201)
            ->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, BatchFirstCountReview $service): JsonResponse
    {
        $this->allowOnly($request, ['operation_key', '_token']);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($service->confirm($request->user(), $review, $data['operation_key']))
            ->header('Cache-Control', 'no-store');
    }

    private function allowOnly(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages(['inventory' => 'Unsupported fields. Actor and ledger results are server controlled.']);
        }
    }
}
