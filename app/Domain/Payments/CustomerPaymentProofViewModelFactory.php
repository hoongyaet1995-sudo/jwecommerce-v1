<?php

namespace App\Domain\Payments;

use App\Models\Order;
use App\Models\OrderPayment;

final class CustomerPaymentProofViewModelFactory
{
    public function __construct(private readonly CustomerPaymentProofPolicy $policy) {}

    public function for(Order $order, ?OrderPayment $payment): ?array
    {
        if (! $payment || $payment->order_id !== $order->id || $payment->gateway !== 'bank_transfer') {
            return null;
        }

        $status = $payment->proof_status === 'none' ? 'missing' : $payment->proof_status;
        $mutable = $this->policy->isMutable($order, $payment);
        $reason = $status === 'rejected'
            ? $payment->proofEvents()->where('event_type', 'rejected')->latest('id')->value('reason')
            : null;

        return [
            'paymentId' => $payment->id,
            'orderId' => $order->id,
            'paymentMethod' => 'bank_transfer',
            'proofStatus' => $status,
            'originalFileName' => $payment->proof_original_name,
            'fileSize' => $this->formatBytes($payment->proof_size),
            'uploadedAt' => $payment->proof_uploaded_at?->toIso8601String(),
            'rejectionReason' => $reason,
            'canUpload' => $mutable,
            'canReplace' => $mutable && in_array($status, ['uploaded', 'rejected'], true),
            'orderNumber' => $order->number,
            'orderTotal' => $order->total,
            'currency' => $order->currency,
            'currencyExchangeRate' => $order->currency_exchange_rate ?? 1,
        ];
    }

    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
