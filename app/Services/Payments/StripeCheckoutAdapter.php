<?php

namespace App\Services\Payments;

use App\Contracts\HostedPaymentGatewayAdapter;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutAdapter implements HostedPaymentGatewayAdapter
{
    public function provider(): string
    {
        return 'stripe';
    }

    public function begin(OrderPayment $payment, Order $order): string
    {
        $credentials = PaymentGatewayCredential::forGateway('stripe', $payment->environment ?? 'live')?->credentials ?? [];
        $secretKey = (string) ($credentials['secretKey'] ?? '');
        if ($secretKey === '') {
            throw new RuntimeException('Stripe is not configured for this environment.');
        }

        $returnUrl = url('/checkout/payment-return/'.$payment->id);
        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $returnUrl.'?state=pending',
                'cancel_url' => $returnUrl.'?state=cancelled',
                'line_items[0][price_data][currency]' => strtolower($payment->currency),
                'line_items[0][price_data][product_data][name]' => 'Order '.$order->number,
                'line_items[0][price_data][unit_amount]' => $payment->amount,
                'line_items[0][quantity]' => 1,
                'metadata[order_number]' => $order->number,
                'metadata[payment_id]' => $payment->id,
            ]);

        if (! $response->successful() || ! $response->json('id') || ! $response->json('url')) {
            throw new RuntimeException('Stripe could not create a hosted checkout session.');
        }

        $payment->update([
            'reference' => (string) $response->json('id'),
            'payload' => ['checkout_session_id' => $response->json('id')],
        ]);

        return (string) $response->json('url');
    }
}
