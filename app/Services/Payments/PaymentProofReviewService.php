<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentProofReviewService
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentProofService $proofs,
    ) {
    }

    public function review(Order $order, OrderPayment $payment, User $actor, string $decision, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $payment, $actor, $decision, $reason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedPayment = OrderPayment::query()
                ->whereKey($payment->id)
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->gateway !== 'bank_transfer') {
                throw ValidationException::withMessages(['decision' => 'Only Bank In / DuitNow payment proofs require manual review.']);
            }
            if ($lockedPayment->proof_status !== 'uploaded' || ! $lockedPayment->proof_path) {
                throw ValidationException::withMessages(['decision' => 'Only an uploaded, unreviewed payment proof can be reviewed.']);
            }

            $lockedPayment->update([
                'proof_status' => $decision,
                'proof_reviewed_at' => now(),
            ]);
            $lockedPayment = $lockedPayment->fresh();
            $this->proofs->appendEvent($lockedPayment, $decision, $actor, $reason);

            if ($decision === 'verified' && $lockedOrder->status === 'pending_payment') {
                $this->checkout->transition($lockedOrder, 'paid', $actor->id, $reason ?: 'Bank In / DuitNow proof verified.');
            }

            return $lockedOrder->fresh();
        });
    }
}
