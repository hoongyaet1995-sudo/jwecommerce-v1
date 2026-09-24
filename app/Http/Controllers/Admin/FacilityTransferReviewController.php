<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\FacilityTransferReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class FacilityTransferReviewController extends Controller
{
    public function review(Request $request, FacilityTransferReview $service): JsonResponse
    {
        $this->allowOnly($request, ['variant_id', 'source_facility_id', 'destination_facility_id',
            'quantity', 'reason', '_token']);
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'source_facility_id' => ['required', 'integer', 'min:1'],
            'destination_facility_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($service->review($request->user(), $data['variant_id'],
            $data['source_facility_id'], $data['destination_facility_id'],
            $data['quantity'], $data['reason']), 201)->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, FacilityTransferReview $service): JsonResponse
    {
        $this->allowOnly($request, ['operation_key', '_token']);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($service->confirm($request->user(), $review, $data['operation_key']))
            ->header('Cache-Control', 'no-store');
    }

    private function allowOnly(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            throw ValidationException::withMessages(['transfer' => 'Unsupported fields.']);
        }
    }
}
