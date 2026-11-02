<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\PaymentProofPermission;
use App\Domain\Shipping\ShippingPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderDeliveryDetailsRequest;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\Admin\OrderDataService;
use App\Services\Admin\OrderOperationsService;
use App\Services\Payments\PaymentProofReviewService;
use App\Services\Payments\PaymentProofService;
use App\Services\Shipping\OrderDeliveryDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderApiController extends Controller
{
    public function invoice(Request $request, Order $order, OrderDataService $data)
    {
        abort_unless($request->user()?->is_admin, 403);
        $order->loadMissing(['items', 'addresses', 'customer', 'payments']);
        return response()->view('admin.orders.invoice', ['document' => $data->data($order), 'seller' => app(\App\Support\ShopBrand::class)->name()])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function updateDeliveryDetails(UpdateOrderDeliveryDetailsRequest $request, Order $order, OrderDeliveryDetailsService $details): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);
        $result = $details->amend($order, $request->user(), [
            'first_name'=>$request->string('firstName')->toString(),'last_name'=>$request->string('lastName')->toString(),'phone'=>$request->string('phone')->toString(),
            'line_1'=>$request->string('address1')->toString(),'line_2'=>$request->input('address2'),'postal_code'=>$request->string('postalCode')->toString(),
            'city'=>$request->string('city')->toString(),'state'=>$request->string('state')->toString(),'country'=>$request->string('country')->toString(),
            'reason_code'=>$request->string('reasonCode')->toString(),'internal_note'=>$request->string('internalNote')->toString(),'sync_address_book'=>$request->boolean('syncAddressBook'),
        ]);
        $address=$result['address'];
        return response()->json(['data'=>['shippingAddress'=>[
            'firstName'=>$address->first_name,'lastName'=>$address->last_name,'phone'=>$address->phone,'address1'=>$address->line_1,'address2'=>$address->line_2,
            'postalCode'=>$address->postal_code,'city'=>$address->city,'state'=>$address->state,'country'=>$address->country,
        ],'addressBookSyncResult'=>$result['address_book_sync_result'],'requiresReplacement'=>$result['requires_replacement']]]);
    }

    public function index(OrderDataService $data): JsonResponse
    {
        return response()->json(['orders' => $data->collection()]);
    }

    public function updateStatus(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        $validated = $request->validate([
            'orderStatus' => ['nullable', Rule::in(['pending', 'paid', 'processing', 'on_hold', 'cancelled'])],
            'paymentStatus' => ['nullable', Rule::in(['paid', 'pending', 'refunded', 'partially_refunded', 'failed'])],
            'fulfillmentStatus' => ['nullable', Rule::in(['unfulfilled', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'notifyCustomer' => ['sometimes', 'boolean'],
        ]);
        $result = $operations->updateStatus(
            $order,
            $validated['paymentStatus'] ?? null,
            $validated['fulfillmentStatus'] ?? null,
            (int) $request->user()->id,
            $validated['note'] ?? null,
            $validated['notifyCustomer'] ?? true,
            $validated['orderStatus'] ?? null,
        );

        return response()->json(['data' => $data->data($result->order, $result->notificationDispatch)]);
    }

    public function showPaymentProof(Request $request, Order $order, OrderPayment $payment, PaymentProofService $proofs)
    {
        $this->assertPaymentBelongsToOrder($order, $payment);
        $this->assertBankTransferPayment($payment);
        abort_unless(PaymentProofPermission::allows($request->user(), PaymentProofPermission::VIEW), 403);

        return $proofs->stream($payment, $request->user());
    }

    public function downloadPaymentProof(Request $request, Order $order, OrderPayment $payment, PaymentProofService $proofs)
    {
        $this->assertPaymentBelongsToOrder($order, $payment);
        $this->assertBankTransferPayment($payment);
        abort_unless(PaymentProofPermission::allows($request->user(), PaymentProofPermission::VIEW), 403);

        return $proofs->stream($payment, $request->user(), true);
    }

    public function reviewPaymentProof(Request $request, Order $order, OrderPayment $payment, PaymentProofReviewService $reviews, OrderDataService $data): JsonResponse
    {
        $this->assertPaymentBelongsToOrder($order, $payment);
        $this->assertBankTransferPayment($payment);
        abort_unless(PaymentProofPermission::allows($request->user(), PaymentProofPermission::REVIEW), 403);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validated['decision'] === 'rejected' && blank($validated['reason'] ?? null)) {
            abort(422, 'A rejection reason is required.');
        }

        $saved = $reviews->review($order, $payment, $request->user(), $validated['decision'], $validated['reason'] ?? null);

        return response()->json(['data' => $data->data($saved)]);
    }

    public function tracking(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        abort_unless(ShippingPermission::allows($request->user(), ShippingPermission::DISPATCH), 403);
        $validated = $request->validate(['carrier' => ['required', 'string', 'max:120'], 'trackingNumber' => ['required', 'string', 'max:120']]);
        $saved = $operations->dispatch($order, $validated['carrier'], $validated['trackingNumber'], (int) $request->user()->id);

        return response()->json(['data' => $data->data($saved)]);
    }

    public function refund(Request $request, Order $order, OrderOperationsService $operations, OrderDataService $data): JsonResponse
    {
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:2000'],
            'restock_items' => ['sometimes', 'array'], 'restock_items.*' => ['required', 'integer', 'min:1']]);
        $saved = $operations->refund($order, (int) round(((float) $validated['amount']) * 100), $validated['reason'], (int) $request->user()->id, $validated['restock_items'] ?? []);

        return response()->json(['data' => $data->data($saved)]);
    }

    private function assertPaymentBelongsToOrder(Order $order, OrderPayment $payment): void
    {
        abort_unless($payment->order_id === $order->id, 404);
    }

    private function assertBankTransferPayment(OrderPayment $payment): void
    {
        abort_unless($payment->gateway === 'bank_transfer', 404);
    }
}
