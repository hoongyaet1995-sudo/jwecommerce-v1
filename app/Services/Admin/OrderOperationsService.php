<?php

namespace App\Services\Admin;

use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Inventory\WebsiteCurrentStockOrderEffects;
use App\Services\Notifications\OrderNotificationMilestoneService;
use App\Services\ShipmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderOperationsService
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly ShipmentService $shipments,
        private readonly OrderNotificationMilestoneService $notifications,
    ) {}

    public function updateStatus(
        Order $order,
        ?string $paymentStatus,
        ?string $fulfillmentStatus,
        int $userId,
        ?string $note,
        bool $notifyCustomer = true,
    ): OrderStatusUpdateResult {
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
            $result = DB::transaction(function () use (
                $fulfillmentStatus,
                $note,
                $notifyCustomer,
                $order,
                $paymentStatus,
                $userId,
            ): OrderStatusUpdateResult {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $actor = User::query()->findOrFail($userId);
                $notificationDispatch = null;

                if ($paymentStatus === 'paid' && $order->status === 'pending_payment') {
                    $order = $this->checkout->transition($order, 'paid', $userId, $note, false);
                    if ($notifyCustomer) {
                        $history = $order->statusHistory()->where('to_status', 'paid')->latest('id')->firstOrFail();
                        $notificationDispatch = $this->recordNotification($order, 'payment_verified', $history, $actor, $note);
                    }
                }

                $target = match ($fulfillmentStatus) {
                    'processing' => 'processing',
                    'delivered' => 'delivered',
                    'cancelled' => 'cancelled',
                    default => null,
                };
                if ($target !== null && $order->status !== $target) {
                    $order = $this->checkout->transition($order, $target, $userId, $note, false);
                    if ($notifyCustomer && in_array($target, ['delivered', 'cancelled'], true)) {
                        $history = $order->statusHistory()->where('to_status', $target)->latest('id')->firstOrFail();
                        $notificationDispatch = $this->recordNotification($order, $target, $history, $actor, $note)
                            ?? $notificationDispatch;
                    }
                }

                return new OrderStatusUpdateResult($order->fresh(), $notificationDispatch);
            });

            // Automatic delivery happens in the transaction's after-commit callback.
            // Refresh so the API reports the persisted sent/failed outcome, never the
            // pre-commit queued snapshot.
            if ($result->notificationDispatch instanceof NotificationDispatch) {
                $result->notificationDispatch->refresh();
            }

            return $result;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fulfillmentStatus' => $exception->getMessage()]);
        }
    }

    public function dispatch(Order $order, string $carrier, string $trackingNumber, int $userId): Order
    {
        try {
            $actor = User::query()->findOrFail($userId);
            $this->shipments->dispatchManually($order, $actor, $carrier, $trackingNumber);

            return $order->fresh();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['trackingNumber' => $exception->getMessage()]);
        }
    }

    public function refund(Order $order, int $amount, string $reason, int $userId, array $restockItems = []): Order
    {
        return DB::transaction(function () use ($order, $amount, $reason, $userId, $restockItems): Order {
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

            if (config('inventory.current_stock_only')) {
                $effects = app(WebsiteCurrentStockOrderEffects::class);
                if ($amount === $remaining && $amount === (int) $locked->total) {
                    $restockItems = $effects->remaining($locked);
                } elseif (! $restockItems) {
                    throw ValidationException::withMessages(['restock_items' => 'Select refunded order items and quantities before a partial refund.']);
                }
                $available = $effects->remaining($locked);
                foreach ($restockItems as $itemId => $quantity) {
                    if (! is_int($quantity) || $quantity < 1 || $quantity > ($available[$itemId] ?? 0)) {
                        throw ValidationException::withMessages(['restock_items' => 'Refund stock quantities exceed the selected order items.']);
                    }
                }
            }

            $refund = OrderRefund::query()->create(['order_id' => $locked->id, 'order_payment_id' => $payment->id, 'user_id' => $userId, 'amount' => $amount, 'reason' => $reason, 'status' => 'completed']);
            if (config('inventory.current_stock_only') && $restockItems) {
                $effects->restore($locked, $restockItems, 'refund:'.$refund->id);
            }
            $payment->update(['status' => $amount === $remaining ? 'refunded' : 'partially_refunded']);
            OrderStatusHistory::query()->create(['order_id' => $locked->id, 'from_status' => $locked->status, 'to_status' => $locked->status, 'user_id' => $userId, 'note' => 'Refunded '.number_format($amount / 100, 2).' '.$locked->currency.': '.$reason]);

            return $locked->fresh();
        });
    }

    private function recordNotification(
        Order $order,
        string $eventKey,
        OrderStatusHistory $history,
        User $actor,
        ?string $note,
    ): ?NotificationDispatch {
        $dispatch = $this->notifications->record(
            $order,
            $eventKey,
            'order_status_history',
            $history->id,
            $actor,
            ['staff_note' => $note ?: 'No additional notes.'],
        );

        return $dispatch instanceof NotificationDispatch && $dispatch->wasRecentlyCreated
            ? $dispatch
            : null;
    }
}
