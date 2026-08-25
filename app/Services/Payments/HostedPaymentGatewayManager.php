<?php

namespace App\Services\Payments;

use App\Contracts\HostedPaymentGatewayAdapter;
use App\Models\Order;
use App\Models\OrderPayment;
use InvalidArgumentException;

class HostedPaymentGatewayManager
{
    /** @var array<string, HostedPaymentGatewayAdapter> */
    private array $adapters;

    public function __construct(StripeCheckoutAdapter $stripe, PayPalOrdersAdapter $paypal, BillplzBillAdapter $billplz)
    {
        $this->adapters = [
            $stripe->provider() => $stripe,
            $paypal->provider() => $paypal,
            $billplz->provider() => $billplz,
        ];
    }

    public function begin(OrderPayment $payment, Order $order): string
    {
        $adapter = $this->adapters[$payment->gateway] ?? null;
        if (! $adapter) {
            throw new InvalidArgumentException('No hosted payment adapter is available for '.$payment->gateway.'.');
        }

        return $adapter->begin($payment, $order);
    }

    public function captureApprovedPayPalOrder(OrderPayment $payment): void
    {
        if ($payment->gateway !== 'paypal') {
            throw new InvalidArgumentException('Only PayPal hosted orders can be captured here.');
        }

        $adapter = $this->adapters['paypal'];
        if (! $adapter instanceof PayPalOrdersAdapter) {
            throw new InvalidArgumentException('The PayPal capture adapter is unavailable.');
        }

        $adapter->capture($payment);
    }
}
