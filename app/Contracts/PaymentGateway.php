<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\OrderPayment;

interface PaymentGateway
{
    /**
     * Create a payment authorization record for an already-created order.
     * Implementations must never accept or persist raw card or CVV data.
     */
    public function createPendingPayment(Order $order, string $method): OrderPayment;
}
