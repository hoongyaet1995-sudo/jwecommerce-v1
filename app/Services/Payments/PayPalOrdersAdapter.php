<?php

namespace App\Services\Payments;

use App\Contracts\HostedPaymentGatewayAdapter;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalOrdersAdapter implements HostedPaymentGatewayAdapter
{
    public function provider(): string
    {
        return 'paypal';
    }

    public function begin(OrderPayment $payment, Order $order): string
    {
        $credentials = PaymentGatewayCredential::forGateway('paypal', $payment->environment ?? 'live')?->credentials ?? [];
        $baseUrl = $payment->environment === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $token = Http::asForm()
            ->withBasicAuth((string) ($credentials['clientId'] ?? ''), (string) ($credentials['clientSecret'] ?? ''))
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $accessToken = (string) $token->json('access_token');
        if (! $token->successful() || $accessToken === '') {
            throw new RuntimeException('PayPal could not obtain an access token.');
        }

        $returnUrl = url('/checkout/payment-return/'.$payment->id);
        $response = Http::withToken($accessToken)->post($baseUrl.'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                'custom_id' => (string) $payment->id,
                'amount' => ['currency_code' => strtoupper($payment->currency), 'value' => number_format($payment->amount / 100, 2, '.', '')],
            ]],
            'application_context' => ['return_url' => $returnUrl.'?state=pending', 'cancel_url' => $returnUrl.'?state=cancelled'],
        ]);
        $approvalUrl = collect($response->json('links', []))->firstWhere('rel', 'approve')['href'] ?? null;
        if (! $response->successful() || ! $response->json('id') || ! $approvalUrl) {
            throw new RuntimeException('PayPal could not create a hosted order.');
        }

        $payment->update(['reference' => (string) $response->json('id'), 'payload' => ['paypal_order_id' => $response->json('id')]]);

        return (string) $approvalUrl;
    }

    public function capture(OrderPayment $payment): void
    {
        $credentials = PaymentGatewayCredential::forGateway('paypal', $payment->environment ?? 'live')?->credentials ?? [];
        $baseUrl = $payment->environment === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $token = Http::asForm()
            ->withBasicAuth((string) ($credentials['clientId'] ?? ''), (string) ($credentials['clientSecret'] ?? ''))
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $accessToken = (string) $token->json('access_token');
        if (! $token->successful() || $accessToken === '') {
            throw new RuntimeException('PayPal could not obtain an access token for capture.');
        }

        $response = Http::withToken($accessToken)
            ->withHeaders(['PayPal-Request-Id' => $payment->idempotency_key ?: 'jw-payment-'.$payment->id])
            ->post($baseUrl.'/v2/checkout/orders/'.$payment->reference.'/capture');
        if (! $response->successful()) {
            throw new RuntimeException('PayPal could not capture the approved hosted order.');
        }

        $payment->update(['payload' => array_merge($payment->payload ?? [], ['paypal_capture' => $response->json()])]);
    }
}
