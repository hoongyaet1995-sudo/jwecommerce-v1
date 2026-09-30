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
        $this->allowOnly($request, ['kind', 'balance_id', 'variant_id', 'channel_key', 'source_balance_id', 'destination_balance_id', 'physical_count', 'quantity', 'quantity_delta', 'confirmed_total', 'reference', 'reason', '_token']);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['count', 'intake', 'correction', 'transfer'])],
            'balance_id' => ['required_without_all:variant_id,source_balance_id', 'nullable', 'integer', 'min:1'],
            'variant_id' => ['required_without_all:balance_id,source_balance_id', 'nullable', 'integer', 'min:1'],
            'channel_key' => ['required_with:variant_id', 'nullable', 'string', 'max:100'],
            'source_balance_id' => ['required_if:kind,transfer', 'integer', 'min:1'],
            'destination_balance_id' => ['required_if:kind,transfer', 'integer', 'min:1', 'different:source_balance_id'],
            'physical_count' => ['required_if:kind,count', 'integer', 'min:0', 'max:2147483647'],
            'quantity' => ['required_if:kind,intake,transfer', 'integer', 'min:1', 'max:2147483647'],
            'quantity_delta' => [Rule::requiredIf($request->input('kind') === 'correction' && ! $request->exists('confirmed_total')), 'nullable', 'integer', 'min:-2147483647', 'max:2147483647', 'not_in:0'],
            'confirmed_total' => ['sometimes', 'required', 'integer', 'min:0', 'max:2147483647'],
            'reference' => ['nullable', 'string', 'max:80'],
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
