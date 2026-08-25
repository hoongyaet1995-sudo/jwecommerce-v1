<?php

namespace App\Services\Payments;

use App\Contracts\HostedPaymentGatewayAdapter;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentGatewayCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BillplzBillAdapter implements HostedPaymentGatewayAdapter
{
    public function provider(): string
    {
        return 'billplz';
    }

    public function begin(OrderPayment $payment, Order $order): string
    {
        $credentials = PaymentGatewayCredential::forGateway('billplz', $payment->environment ?? 'live')?->credentials ?? [];
        $baseUrl = $payment->environment === 'sandbox' ? 'https://www.billplz-sandbox.com' : 'https://www.billplz.com';
        $returnUrl = url('/checkout/payment-return/'.$payment->id);
        $response = Http::asForm()
            ->withBasicAuth((string) ($credentials['apiKey'] ?? ''), '')
            ->post($baseUrl.'/api/v3/bills', [
                'collection_id' => (string) ($credentials['collectionId'] ?? ''),
                'email' => $order->email,
                'name' => $order->email,
                'amount' => $payment->amount,
                'description' => 'Order '.$order->number,
                'reference_1_label' => 'Order Number',
                'reference_1' => $order->number,
                'callback_url' => url('/payments/webhooks/billplz'),
                'redirect_url' => $returnUrl.'?state=pending',
            ]);
        if (! $response->successful() || ! $response->json('id') || ! $response->json('url')) {
            throw new RuntimeException('Billplz could not create a hosted bill.');
        }

        $payment->update(['reference' => (string) $response->json('id'), 'payload' => ['billplz_bill_id' => $response->json('id')]]);

        return (string) $response->json('url');
    }
}
