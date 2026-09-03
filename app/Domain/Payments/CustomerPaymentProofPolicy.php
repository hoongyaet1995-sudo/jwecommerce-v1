<?php

namespace App\Domain\Payments;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\Request;

final class CustomerPaymentProofPolicy
{
    public function allows(Request $request, Order $order, OrderPayment $payment): bool
    {
        if ($payment->order_id !== $order->id || ! $this->isMutable($order, $payment)) {
            return false;
        }

        $user = $request->user('customer');
        $owned = $user?->customer && $order->customer_id === $user->customer->id;
        $sessionOwned = $request->hasSession()
            && (int) $request->session()->get('last_order_id') === $order->id;

        return $owned || $sessionOwned;
    }

    public function authorize(Request $request, Order $order, OrderPayment $payment): void
    {
        abort_unless($this->allows($request, $order, $payment), 404);
    }

    public function isMutable(Order $order, OrderPayment $payment): bool
    {
        return $payment->gateway === 'bank_transfer'
            && $order->status === 'pending_payment'
            && $payment->status === 'pending'
            && $payment->proof_status !== 'verified';
    }
}
