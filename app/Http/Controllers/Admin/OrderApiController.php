<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderDataService;
use App\Services\Admin\OrderOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderApiController extends Controller
{
    public function index(OrderDataService $data): JsonResponse
    {
        return response()->json(['orders' => $data->collection()]);
    }

    public function updateStatus(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        $validated = $request->validate([
            'paymentStatus' => ['nullable', Rule::in(['paid', 'pending', 'refunded', 'partially_refunded', 'failed'])],
            'fulfillmentStatus' => ['nullable', Rule::in(['unfulfilled', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $saved = $operations->updateStatus($order, $validated['paymentStatus'] ?? null, $validated['fulfillmentStatus'] ?? null, (int) $request->user()->id, $validated['note'] ?? null);
        return response()->json(['data' => $data->data($saved)]);
    }

    public function tracking(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        $validated = $request->validate(['carrier' => ['required', 'string', 'max:120'], 'trackingNumber' => ['required', 'string', 'max:120']]);
        $saved = $operations->dispatch($order, $validated['carrier'], $validated['trackingNumber'], (int) $request->user()->id);
        return response()->json(['data' => $data->data($saved)]);
    }

    public function refund(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:2000']]);
        $saved = $operations->refund($order, (int) round(((float) $validated['amount']) * 100), $validated['reason'], (int) $request->user()->id);
        return response()->json(['data' => $data->data($saved)]);
    }
}
