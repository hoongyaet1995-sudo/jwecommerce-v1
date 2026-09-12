<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\PhysicalCountReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhysicalCountController extends Controller
{
    public function adjustment(Request $request, PhysicalCountReviewService $service): JsonResponse
    {
        $this->allowFields($request,['balance_id','quantity_delta','reason','_token']);
        $data=$request->validate(['balance_id'=>'required|integer|min:1','quantity_delta'=>'required|integer|min:-2147483647|max:2147483647','reason'=>'required|string|max:255']);
        return response()->json($service->review((int)$data['balance_id'],(int)$data['quantity_delta'],$data['reason'],$request->user(),'delta'),201)->header('Cache-Control','no-store');
    }
    public function review(Request $request, PhysicalCountReviewService $service): JsonResponse
    {
        $this->allowFields($request, ['balance_id','physical_count','reason','_token']);
        $data = $request->validate([
            'balance_id'=>'required|integer|min:1',
            'physical_count'=>'required|integer|min:0|max:2147483647',
            'reason'=>'required|string|max:255',
        ]);
        return response()->json($service->review((int)$data['balance_id'], (int)$data['physical_count'],
            $data['reason'], $request->user()),201)->header('Cache-Control','no-store');
    }

    public function confirm(Request $request, string $review, PhysicalCountReviewService $service): JsonResponse
    {
        $this->allowFields($request, ['acknowledged','_token']);
        $request->validate(['acknowledged'=>'required|accepted']);
        return response()->json($service->confirm($review,$request->user(),true))->header('Cache-Control','no-store');
    }

    private function allowFields(Request $request, array $allowed): void
    {
        abort_unless(config('marketplace.basic_wms_enabled'),403,'Basic WMS manual stock operations are disabled.');
        if (array_diff(array_keys($request->all()),$allowed)) {
            throw ValidationException::withMessages(['inventory'=>'Unsupported fields. Employee and reviewed quantities are server controlled.']);
        }
    }
}
