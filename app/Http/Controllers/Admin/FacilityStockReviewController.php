<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\FacilityStockReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class FacilityStockReviewController extends Controller
{
    public function review(Request $request, int $store, int $facility, int $variant,
        FacilityStockReview $service): JsonResponse
    {
        $this->allowOnly($request, ['kind', 'quantity', 'reason', '_token']);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['count', 'intake', 'audit'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($service->review($request->user(), $store, $facility, $variant,
            $data['kind'], $data['quantity'], $data['reason']), 201)->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, FacilityStockReview $service): JsonResponse
    {
        $this->allowOnly($request, ['operation_key', '_token']);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($service->confirm($request->user(), $review, $data['operation_key']))
            ->header('Cache-Control', 'no-store');
    }

    private function allowOnly(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages(['inventory' => 'Unsupported fields. Facility identity and actor are server controlled.']);
        }
    }
}
