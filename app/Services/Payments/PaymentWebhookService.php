<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentWebhookEvent;
use App\Services\Notifications\OrderNotificationMilestoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentWebhookService
{
    public function __construct(private readonly OrderNotificationMilestoneService $notifications) {}

    /** @return array{received: bool, duplicate: bool} */
    public function receiveStripe(Request $request): array
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['id'], $event['type'])) {
            throw ValidationException::withMessages(['payload' => 'Stripe sent an invalid webhook payload.']);
        }

        $sessionId = data_get($event, 'data.object.id');
        $payment = is_string($sessionId)
            ? OrderPayment::query()->where('gateway', 'stripe')->where('reference', $sessionId)->first()
            : null;
        $environment = $payment?->environment ?? 'sandbox';
        $credential = PaymentGatewayCredential::forGateway('stripe', $environment);
        $secret = (string) data_get($credential?->credentials, 'webhookSecret');

        if ($secret === '' || ! $this->validStripeSignature($request->header('Stripe-Signature'), $payload, $secret)) {
            abort(403);
        }

        return DB::transaction(function () use ($event, $payload, $payment, $environment): array {
            $existing = PaymentWebhookEvent::query()
                ->where('provider', 'stripe')
                ->where('provider_event_id', $event['id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['received' => true, 'duplicate' => true];
            }

            $webhook = PaymentWebhookEvent::query()->create([
                'order_payment_id' => $payment?->id,
                'provider' => 'stripe',
                'provider_event_id' => $event['id'],
                'environment' => $environment,
                'raw_payload' => $payload,
                'normalized_payload' => $event,
                'verified_at' => now(),
            ]);

            if ($payment && $event['type'] === 'checkout.session.completed' && data_get($event, 'data.object.payment_status') === 'paid') {
                $this->assertQuotationSettlement($payment,data_get($event,'data.object.amount_total'),data_get($event,'data.object.currency'));
                $this->confirmPayment($payment, $webhook, 'Verified Stripe webhook '.$webhook->provider_event_id);
            }

            return ['received' => true, 'duplicate' => false];
        });
    }

    /** @return array{received: bool, duplicate: bool} */
    public function receiveBillplz(Request $request): array
    {
        $callback = $request->all();
        $billId = (string) ($callback['id'] ?? '');
        $payment = $billId === ''
            ? null
            : OrderPayment::query()->where('gateway', 'billplz')->where('reference', $billId)->first();
        $environment = $payment?->environment ?? 'sandbox';
        $credentials = PaymentGatewayCredential::forGateway('billplz', $environment)?->credentials ?? [];
        $signatureKey = (string) ($credentials['signatureKey'] ?? '');

        if (! $payment || $signatureKey === '' || ! $this->validBillplzSignature($callback, $signatureKey)) {
            abort(403);
        }

        return DB::transaction(function () use ($callback, $payment, $environment, $request): array {
            $existing = PaymentWebhookEvent::query()
                ->where('provider', 'billplz')
                ->where('provider_event_id', (string) $callback['id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['received' => true, 'duplicate' => true];
            }

            $webhook = PaymentWebhookEvent::query()->create([
                'order_payment_id' => $payment->id,
                'provider' => 'billplz',
                'provider_event_id' => (string) $callback['id'],
                'environment' => $environment,
                'raw_payload' => $request->getContent(),
                'normalized_payload' => $callback,
                'verified_at' => now(),
            ]);

            if (($callback['status'] ?? null) === 'paid') {
                $this->assertQuotationSettlement($payment,$callback['amount']??null,'MYR');
                $this->confirmPayment($payment, $webhook, 'Verified Billplz callback '.$webhook->provider_event_id);
            }

            return ['received' => true, 'duplicate' => false];
        });
    }

    /** @return array{received: bool, duplicate: bool} */
    public function receivePayPal(Request $request): array
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);
        $orderId = data_get($event, 'resource.supplementary_data.related_ids.order_id');
        $payment = is_string($orderId)
            ? OrderPayment::query()->where('gateway', 'paypal')->where('reference', $orderId)->first()
            : null;

        if (! is_array($event) || ! isset($event['id']) || ! $payment || ! $this->validPayPalWebhook($request, $event, $payment)) {
            abort(403);
        }

        return DB::transaction(function () use ($event, $payload, $payment): array {
            $existing = PaymentWebhookEvent::query()
                ->where('provider', 'paypal')
                ->where('provider_event_id', $event['id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['received' => true, 'duplicate' => true];
            }

            $webhook = PaymentWebhookEvent::query()->create([
                'order_payment_id' => $payment->id,
                'provider' => 'paypal',
                'provider_event_id' => $event['id'],
                'environment' => $payment->environment ?? 'live',
                'raw_payload' => $payload,
                'normalized_payload' => $event,
                'verified_at' => now(),
            ]);

            if (($event['event_type'] ?? null) === 'PAYMENT.CAPTURE.COMPLETED') {
                $value=data_get($event,'resource.amount.value');
                $minor=null;
                if(is_string($value)&&preg_match('/^([0-9]{1,10})\.([0-9]{2})$/D',$value,$parts))$minor=(int)$parts[1]*100+(int)$parts[2];
                $this->assertQuotationSettlement($payment,$minor,data_get($event,'resource.amount.currency_code'));
                $this->confirmPayment($payment, $webhook, 'Verified PayPal webhook '.$webhook->provider_event_id);
            }

            return ['received' => true, 'duplicate' => false];
        });
    }

    private function validStripeSignature(?string $header, string $payload, string $secret): bool
    {
        if (! $header) {
            return false;
        }

        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });
        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! is_string($timestamp) || ! is_string($signature) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }

    /** @param array<string, mixed> $callback */
    private function validBillplzSignature(array $callback, string $signatureKey): bool
    {
        $required = ['id', 'bank_account_number', 'status', 'total', 'reference_id', 'epoch', 'x_signature'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $callback)) {
                return false;
            }
        }

        $source = implode('', array_map(
            static fn (string $key): string => (string) $callback[$key],
            ['id', 'bank_account_number', 'status', 'total', 'reference_id', 'epoch'],
        ));
        $expected = hash_hmac('sha512', $source, $signatureKey);

        return hash_equals($expected, (string) $callback['x_signature']);
    }

    /** @param array<string, mixed> $event */
    private function validPayPalWebhook(Request $request, array $event, OrderPayment $payment): bool
    {
        $environment = $payment->environment ?? 'live';
        $credentials = PaymentGatewayCredential::forGateway('paypal', $environment)?->credentials ?? [];
        $baseUrl = $environment === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $tokenResponse = Http::asForm()
            ->withBasicAuth((string) ($credentials['clientId'] ?? ''), (string) ($credentials['clientSecret'] ?? ''))
            ->post($baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $accessToken = (string) $tokenResponse->json('access_token');
        if (! $tokenResponse->successful() || $accessToken === '') {
            return false;
        }

        $subscriptions = Http::withToken($accessToken)->get($baseUrl.'/v1/notifications/webhooks');
        $webhookId = collect($subscriptions->json('webhooks', []))
            ->first(fn (array $webhook): bool => ($webhook['url'] ?? null) === url('/payments/webhooks/paypal'))['id'] ?? null;
        if (! $subscriptions->successful() || ! is_string($webhookId) || $webhookId === '') {
            return false;
        }

        $verification = Http::withToken($accessToken)->post($baseUrl.'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PayPal-Auth-Algo'),
            'cert_url' => $request->header('PayPal-Cert-Url'),
            'transmission_id' => $request->header('PayPal-Transmission-Id'),
            'transmission_sig' => $request->header('PayPal-Transmission-Sig'),
            'transmission_time' => $request->header('PayPal-Transmission-Time'),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        return $verification->successful() && $verification->json('verification_status') === 'SUCCESS';
    }

    private function assertQuotationSettlement(OrderPayment $payment,mixed $amount,mixed $currency):void
    {
        $order=$payment->order;
        if($order?->shipping_method!=='quoted_manual')return;
        $version=\App\Models\EnquiryQuotationVersion::findOrFail(data_get($order->shipping_snapshot,'quotation_version_id'));
        $expected=$version->document;
        if(!is_scalar($amount)||!preg_match('/^[0-9]+$/D',(string)$amount)||(int)$amount!==(int)$payment->amount||(int)$amount!==$expected['totalMinor']||!is_string($currency)||strtoupper($currency)!==$expected['currency']['code']||$payment->currency!==$expected['currency']['code'])throw ValidationException::withMessages(['payment'=>'The verified payment amount or currency does not match this quotation.']);
    }

    private function confirmPayment(OrderPayment $payment, PaymentWebhookEvent $webhook, string $note): void
    {
        $lockedPayment = OrderPayment::query()->lockForUpdate()->findOrFail($payment->id);
        $lockedPayment->update(['status' => 'paid']);

        $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);
        if ($order->status === 'pending_payment') {
            $order->update(['status' => 'paid']);
            app(\App\Services\Inventory\OrderAllocationService::class)->reservePaid($order);
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => 'pending_payment',
                'to_status' => 'paid',
                'note' => $note,
            ]);
        }

        $this->notifications->record(
            $order,
            'payment_verified',
            'payment_webhook_event',
            $webhook->id,
            extra: ['staff_note' => $note],
        );
    }
}
