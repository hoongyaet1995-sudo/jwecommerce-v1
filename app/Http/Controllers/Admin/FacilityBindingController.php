<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\FacilityBindingReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FacilityBindingController extends Controller
{
    public function review(Request $request, int $store, int $facility, FacilityBindingReview $bindings): JsonResponse
    {
        $input = $request->validate([
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        return response()->json($bindings->create($request->user(), $store, $facility,
            (int) $input['warehouse_id'], trim($input['reason'])), 201)->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, FacilityBindingReview $bindings): JsonResponse
    {
        $input = $request->validate(['operation_key' => ['required', 'string', 'regex:/\A[A-Za-z0-9:_-]{8,120}\z/D']]);

        return response()->json($bindings->confirm($request->user(), $review, $input['operation_key']))
            ->header('Cache-Control', 'no-store');
    }
}
