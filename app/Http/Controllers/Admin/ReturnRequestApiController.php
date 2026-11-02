<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Admin\OrderDataService;
use App\Services\Returns\ReturnRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnRequestApiController extends Controller
{
    public function store(Request $q, \App\Models\Order $order, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        abort_unless($q->user()?->is_admin, 403);
        $v = $q->validate(['order_item_id'=>['required','integer'], 'quantity'=>['required','integer','min:1'], 'resolution'=>['required',Rule::in(['refund','replacement'])], 'reason'=>['required',Rule::in(['defective','damaged_in_transit','incorrect_item','missing_parts','quality','other'])], 'details'=>['required','string','max:2000']]);
        $item = $order->items()->findOrFail($v['order_item_id']);
        abort_unless($order->customer_id, 422, 'This order has no customer record.');
        return $this->respond($s->create((int)$order->customer_id, $item, $v, [], $q->user()), $d);
    }

    public function approve(Request $q, ReturnRequest $returnRequest, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        return $this->respond($s->transition($returnRequest, 'approve', $q->user()), $d);
    }

    public function reject(Request $q, ReturnRequest $returnRequest, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        $v = $q->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $this->respond($s->transition($returnRequest, 'reject', $q->user(), $v), $d);
    }

    public function receive(Request $q, ReturnRequest $returnRequest, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        $v = $q->validate([
            'received_quantity' => ['required', 'integer', 'min:1', 'max:'.$returnRequest->quantity],
            'condition' => ['required', Rule::in(['restockable', 'damaged'])],
            'warehouse_note' => [Rule::requiredIf($q->input('condition') === 'damaged'), 'nullable', 'string', 'max:2000'],
        ]);

        return $this->respond($s->transition($returnRequest, 'receive', $q->user(), $v), $d);
    }

    public function resolveRefund(Request $q, ReturnRequest $returnRequest, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        abort_unless($returnRequest->resolution === 'refund', 422);
        $v = $q->validate([
            'refund_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'external_refund_reference' => ['required', 'string', 'max:255'],
            'refund_note' => ['required', 'string', 'max:2000'],
        ]);

        return $this->respond($s->transition($returnRequest, 'resolve_refund', $q->user(), $v), $d);
    }

    public function resolveReplacement(Request $q, ReturnRequest $returnRequest, ReturnRequestService $s, OrderDataService $d): JsonResponse
    {
        abort_unless($returnRequest->resolution === 'replacement', 422);

        return $this->respond($s->transition($returnRequest, 'resolve_replacement', $q->user()), $d);
    }

    private function respond(ReturnRequest $r, OrderDataService $d): JsonResponse
    {
        return response()->json(['data' => $d->data($r->order()->firstOrFail())]);
    }
}
