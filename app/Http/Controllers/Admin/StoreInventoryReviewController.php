<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StoreManagement\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreInventoryReviewController extends Controller
{
    public function store(Request $request, ReviewService $reviews): JsonResponse
    {
        $this->allowOnly($request, ['kind', 'balance_id', 'physical_count', 'quantity', 'quantity_delta', 'reason', '_token']);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['count', 'intake', 'correction'])],
            'balance_id' => ['required', 'integer', 'min:1'],
            'physical_count' => ['required_if:kind,count', 'integer', 'min:0', 'max:2147483647'],
            'quantity' => ['required_if:kind,intake', 'integer', 'min:1', 'max:2147483647'],
            'quantity_delta' => ['required_if:kind,correction', 'integer', 'min:-2147483647', 'max:2147483647', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($reviews->create($request->user(), $data['kind'], $data), 201)
            ->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $review, ReviewService $reviews): JsonResponse
    {
        $this->allowOnly($request, ['operation_key', '_token']);
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:120']]);

        return response()->json($reviews->confirm($request->user(), $review, $data['operation_key']))
            ->header('Cache-Control', 'no-store');
    }

    private function allowOnly(Request $request, array $allowed): void
    {
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['inventory' => 'Unsupported fields. Actor and ledger results are server controlled.']);
        }
    }
}
