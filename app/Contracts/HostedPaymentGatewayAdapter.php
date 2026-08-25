<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\OrderPayment;

interface HostedPaymentGatewayAdapter
{
    public function provider(): string;

    public function begin(OrderPayment $payment, Order $order): string;
}
