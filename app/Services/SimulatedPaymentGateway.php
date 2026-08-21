<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\OrderPayment;

class SimulatedPaymentGateway implements PaymentGateway
{
    public function createPendingPayment(Order $order, string $method): OrderPayment
    {
        return OrderPayment::query()->create([
            'order_id' => $order->id,
            'gateway' => $method,
            'status' => 'pending',
        ]);
    }
}
