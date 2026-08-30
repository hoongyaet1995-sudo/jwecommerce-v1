<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Services\CheckoutService;
use App\Services\ShipmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderOperationsService
{
    public function __construct(private readonly CheckoutService $checkout, private readonly ShipmentService $shipments) {}

    public function updateStatus(Order $order, ?string $paymentStatus, ?string $fulfillmentStatus, int $userId, ?string $note): Order
    {
        if (in_array($paymentStatus, ['refunded', 'partially_refunded'], true)) {
            throw ValidationException::withMessages(['paymentStatus' => 'Use the refund action so the refund is recorded in the ledger.']);
        }
        if ($fulfillmentStatus === 'shipped') {
            throw ValidationException::withMessages(['fulfillmentStatus' => 'Add carrier and tracking details to dispatch this order.']);
        }
        if ($paymentStatus === 'paid') {
            $payment = $order->payments()->latest('id')->first();
            if ($payment?->gateway === 'bank_transfer' && $payment->proof_status !== 'verified') {
                throw ValidationException::withMessages(['paymentStatus' => 'Verify the Bank In / DuitNow payment proof before marking this order as paid.']);
            }
        }

        try {
            if ($paymentStatus === 'paid' && $order->status === 'pending_payment') {
                $order = $this->checkout->transition($order, 'paid', $userId, $note);
            }
            $target = match ($fulfillmentStatus) {
                'processing' => 'processing',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled',
                default => null,
            };
            if ($target !== null && $order->status !== $target) {
                $order = $this->checkout->transition($order, $target, $userId, $note);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fulfillmentStatus' => $exception->getMessage()]);
        }

        return $order->fresh();
    }

    public function dispatch(Order $order, string $carrier, string $trackingNumber, int $userId): Order
    {
        try {
            $actor = \App\Models\User::query()->findOrFail($userId);
            $this->shipments->dispatchManually($order, $actor, $carrier, $trackingNumber);
            return $order->fresh();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['trackingNumber' => $exception->getMessage()]);
        }
    }

    public function refund(Order $order, int $amount, string $reason, int $userId): Order
    {
        return DB::transaction(function () use ($order, $amount, $reason, $userId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payment = $locked->payments()->latest('id')->lockForUpdate()->first();
            if (! $payment || ! in_array($payment->status, ['paid', 'partially_refunded'], true)) {
                throw ValidationException::withMessages(['amount' => 'Only a paid order can be refunded.']);
            }
            $refunded = (int) $locked->refunds()->where('status', 'completed')->sum('amount');
            $remaining = max(0, (int) $locked->total - $refunded);
            if ($amount < 1 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the remaining refundable total.']);
            }

            OrderRefund::query()->create(['order_id' => $locked->id, 'order_payment_id' => $payment->id, 'user_id' => $userId, 'amount' => $amount, 'reason' => $reason, 'status' => 'completed']);
            $payment->update(['status' => $amount === $remaining ? 'refunded' : 'partially_refunded']);
            OrderStatusHistory::query()->create(['order_id' => $locked->id, 'from_status' => $locked->status, 'to_status' => $locked->status, 'user_id' => $userId, 'note' => 'Refunded '.number_format($amount / 100, 2).' '.$locked->currency.': '.$reason]);
            return $locked->fresh();
        });
    }
}
