<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Services\Returns\ReturnRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerReturnRequestController extends Controller
{
    public function store(Request $request, ReturnRequestService $service): RedirectResponse
    {
        $data = $request->validate(['order_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'resolution' => ['required', Rule::in(['refund', 'replacement'])], 'reason' => ['required', Rule::in(['defective', 'damaged_in_transit', 'incorrect_item', 'missing_parts', 'quality', 'other'])], 'details' => ['nullable', 'string', 'max:2000'], 'evidence' => ['array', 'max:5'], 'evidence.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']]);
        $customer = $request->user('customer')->customer;
        abort_unless($customer, 403);
        $service->create($customer->id, OrderItem::query()->findOrFail($data['order_item_id']), $data, $request->file('evidence', []));

        return redirect('/account#purchase_history')->with('return_status', 'Return request submitted.');
    }
}
