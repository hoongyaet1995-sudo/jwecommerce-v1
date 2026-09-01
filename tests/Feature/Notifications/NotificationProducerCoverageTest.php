<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderNotificationJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderPaymentProofEvent;
use App\Models\PaymentGatewayCredential;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentQuote;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\ShippingWebhookReceipt;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Notifications\NotificationTemplateDefaults;
use App\Services\Notifications\OrderNotificationMilestoneService;
use App\Services\Payments\PaymentProofReviewService;
use App\Services\ShipmentService;
use App\Services\Shipping\EasyParcelBookingReconciliationService;
use App\Services\Shipping\EasyParcelShipmentDetailsService;
use App\Services\Shipping\EasyParcelTrackingService;
use App\Services\Shipping\EasyParcelWebhookReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificationProducerCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        app(NotificationTemplateDefaults::class)->synchronize();
    }

    public function test_checkout_creation_records_one_order_created_intent_from_the_persisted_order(): void
    {
        $variant = $this->checkoutVariant();
        $this->configureCheckout();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData())
            ->assertRedirect('/order/success');

        $order = Order::query()->sole();
        $dispatch = NotificationDispatch::query()->sole();

        self::assertSame('order_created', $dispatch->event_key);
        self::assertSame('order', $dispatch->source_type);
        self::assertSame((string) $order->id, $dispatch->source_id);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'pending_payment',
        ]);
        Queue::assertPushed(SendOrderNotificationJob::class, 1);
    }

    public function test_first_producer_synchronizes_missing_defaults_before_recording_its_intent(): void
    {
        NotificationTemplate::query()->delete();
        $variant = $this->checkoutVariant();
        $this->configureCheckout();

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData())
            ->assertRedirect('/order/success');

        $this->assertDatabaseCount('notification_templates', 8);
        $this->assertDatabaseHas('notification_dispatches', [
            'order_id' => Order::query()->sole()->id,
            'event_key' => 'order_created',
        ]);
    }

    public function test_admin_update_and_notify_defaults_to_true_and_false_suppresses_only_that_request(): void
    {
        $admin = $this->superAdmin();
        $notified = $this->order('pending_payment', 'ADMIN-NOTIFY');

        $this->actingAs($admin)->patchJson("/admin/api/orders/{$notified->id}/status", [
            'paymentStatus' => 'paid',
            'fulfillmentStatus' => 'processing',
            'note' => 'Payment confirmed and packing started.',
        ])->assertOk()
            ->assertJsonPath('data.notificationSummary.eventKey', 'payment_verified')
            ->assertJsonPath('data.notificationSummary.state', 'queued');

        $paidHistory = $notified->statusHistory()->where('to_status', 'paid')->sole();
        $dispatch = $notified->notificationDispatches()->sole();
        self::assertSame('order_status_history', $dispatch->source_type);
        self::assertSame((string) $paidHistory->id, $dispatch->source_id);
        self::assertSame('Payment confirmed and packing started.', $dispatch->variable_snapshot['staff_note']);

        $suppressed = $this->order('pending_payment', 'ADMIN-SUPPRESSED', gateway: 'stripe');
        $suppressedPayment = $suppressed->payments()->sole();
        $suppressedPayment->update([
            'provider' => 'stripe',
            'environment' => 'sandbox',
            'reference' => 'cs_admin_suppressed',
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe',
            'environment' => 'sandbox',
            'credentials' => [
                'publishableKey' => 'pk_test_public',
                'secretKey' => 'sk_test_private',
                'webhookSecret' => 'whsec_test_private',
            ],
        ]);

        $this->actingAs($admin)->patchJson("/admin/api/orders/{$suppressed->id}/status", [
            'paymentStatus' => 'paid',
            'fulfillmentStatus' => 'processing',
            'note' => 'Do not email for this Admin action.',
            'notifyCustomer' => false,
        ])->assertOk()
            ->assertJsonPath('data.fulfillmentStatus', 'processing')
            ->assertJsonPath('data.notificationSummary', null);

        self::assertSame(0, $suppressed->notificationDispatches()->count());

        $payload = json_encode([
            'id' => 'evt_admin_suppressed_later_verified',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $suppressedPayment->reference, 'payment_status' => 'paid']],
        ], JSON_THROW_ON_ERROR);
        $this->postStripeWebhook($payload)->assertOk();

        $later = $suppressed->notificationDispatches()->sole();
        $webhook = PaymentWebhookEvent::query()->where('order_payment_id', $suppressedPayment->id)->sole();
        self::assertSame('payment_verified', $later->event_key);
        self::assertSame('payment_webhook_event', $later->source_type);
        self::assertSame((string) $webhook->id, $later->source_id);
    }

    public function test_payment_proof_reviews_use_each_persisted_review_event_identity(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        [$verifiedOrder, $verifiedPayment] = $this->proofOrder('PROOF-VERIFIED');

        app(PaymentProofReviewService::class)->review(
            $verifiedOrder,
            $verifiedPayment,
            $admin,
            'verified',
            'Amount and reference match.',
        );

        $verifiedEvent = $verifiedPayment->proofEvents()->where('event_type', 'verified')->sole();
        $verifiedDispatch = $verifiedOrder->notificationDispatches()->sole();
        self::assertSame('payment_verified', $verifiedDispatch->event_key);
        self::assertSame('order_payment_proof_event', $verifiedDispatch->source_type);
        self::assertSame((string) $verifiedEvent->id, $verifiedDispatch->source_id);

        [$rejectedOrder, $rejectedPayment] = $this->proofOrder('PROOF-REJECTED');
        $reviews = app(PaymentProofReviewService::class);
        $reviews->review($rejectedOrder, $rejectedPayment, $admin, 'rejected', 'Receipt is unclear.');
        $rejectedPayment->refresh()->forceFill(['proof_status' => 'uploaded', 'proof_reviewed_at' => null])->save();
        $reviews->review($rejectedOrder, $rejectedPayment->fresh(), $admin, 'rejected', 'Reference does not match.');

        $events = OrderPaymentProofEvent::query()
            ->where('order_payment_id', $rejectedPayment->id)
            ->where('event_type', 'rejected')
            ->oldest('id')
            ->get();
        $dispatches = $rejectedOrder->notificationDispatches()->oldest('id')->get();
        self::assertCount(2, $events);
        self::assertCount(2, $dispatches);
        self::assertSame($events->pluck('id')->map(strval(...))->all(), $dispatches->pluck('source_id')->all());
        self::assertSame(['Receipt is unclear.', 'Reference does not match.'], $dispatches->pluck('variable_snapshot')->map(
            static fn (array $snapshot): string => $snapshot['payment_proof_reason'],
        )->all());
    }

    public function test_verified_hosted_payment_webhooks_use_persisted_events_and_provider_replays_do_not_duplicate(): void
    {
        Http::preventStrayRequests();

        $stripe = $this->hostedPaymentOrder('stripe', 'cs_test_notify');
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe',
            'environment' => 'sandbox',
            'credentials' => ['publishableKey' => 'pk_test', 'secretKey' => 'sk_test', 'webhookSecret' => 'stripe_notify_secret'],
        ]);
        $stripePayload = json_encode([
            'id' => 'evt_notify_stripe',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $stripe['payment']->reference, 'payment_status' => 'paid']],
        ], JSON_THROW_ON_ERROR);
        $this->postStripeWebhook($stripePayload, 'stripe_notify_secret')->assertOk();
        $this->postStripeWebhook($stripePayload, 'stripe_notify_secret')->assertOk();

        $billplz = $this->hostedPaymentOrder('billplz', 'BILL-NOTIFY-1');
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'billplz',
            'environment' => 'sandbox',
            'credentials' => ['apiKey' => 'bill-key', 'collectionId' => 'collection', 'signatureKey' => 'bill-notify-secret'],
        ]);
        $callback = [
            'id' => $billplz['payment']->reference,
            'bank_account_number' => '1234567890',
            'status' => 'paid',
            'total' => 10000,
            'reference_id' => $billplz['order']->number,
            'epoch' => 1724550000,
        ];
        $callback['x_signature'] = hash_hmac('sha512', implode('', array_values($callback)), 'bill-notify-secret');
        $this->post('/payments/webhooks/billplz', $callback)->assertOk();
        $this->post('/payments/webhooks/billplz', $callback)->assertOk();

        $paypal = $this->hostedPaymentOrder('paypal', 'PAYPAL-NOTIFY-1');
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'paypal',
            'environment' => 'sandbox',
            'credentials' => ['clientId' => 'paypal-client', 'clientSecret' => 'paypal-secret'],
        ]);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
            'https://api-m.sandbox.paypal.com/v1/notifications/webhooks' => Http::response([
                'webhooks' => [['id' => 'WH-NOTIFY', 'url' => url('/payments/webhooks/paypal')]],
            ]),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ]),
        ]);
        $paypalPayload = json_encode([
            'id' => 'WH-EVENT-NOTIFY',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => $paypal['payment']->reference]]],
        ], JSON_THROW_ON_ERROR);
        $paypalHeaders = [
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api-m.sandbox.paypal.com/certs/test',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-notify',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        ];
        $this->call('POST', '/payments/webhooks/paypal', [], [], [], $paypalHeaders, $paypalPayload)->assertOk();
        $this->call('POST', '/payments/webhooks/paypal', [], [], [], $paypalHeaders, $paypalPayload)->assertOk();

        foreach ([$stripe, $billplz, $paypal] as $case) {
            $dispatch = $case['order']->notificationDispatches()->sole();
            $webhook = PaymentWebhookEvent::query()->where('order_payment_id', $case['payment']->id)->sole();
            self::assertSame('payment_verified', $dispatch->event_key);
            self::assertSame('payment_webhook_event', $dispatch->source_type);
            self::assertSame((string) $webhook->id, $dispatch->source_id);
        }
    }

    public function test_manual_dispatch_and_delivery_emit_only_the_two_business_milestones(): void
    {
        $actor = $this->superAdmin();
        $order = $this->order('processing', 'MANUAL-SHIP');
        $shipments = app(ShipmentService::class);

        $shipment = $shipments->dispatchManually($order, $actor, 'DHL Express', 'DHL-NOTIFY-1');
        $shipments->appendManualEvent($shipment, $actor, 'in_transit', 'Departed sorting hub.');
        $shipments->appendManualEvent($shipment->fresh(), $actor, 'out_for_delivery', 'Out for delivery.');
        $deliveryEvent = $shipments->appendManualEvent($shipment->fresh(), $actor, 'delivered', 'Delivered to customer.');

        $dispatches = $order->notificationDispatches()->oldest('id')->get();
        self::assertSame(['shipped', 'delivered'], $dispatches->pluck('event_key')->all());
        $shippingEvent = $shipment->events()->where('status', 'shipped')->sole();
        self::assertSame((string) $shippingEvent->id, $dispatches[0]->source_id);
        self::assertSame((string) $deliveryEvent->id, $dispatches[1]->source_id);
        self::assertSame('shipment_event', $dispatches[0]->source_type);
        self::assertSame('shipment_event', $dispatches[1]->source_type);
    }

    public function test_booking_recovery_records_the_first_official_awb_once(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $actor = $this->superAdmin();

        $recoveredOrder = $this->order('processing', 'AWB-RECOVERED');
        $recoveredQuote = $this->shipmentQuote($recoveredOrder, $provider, $actor, 'uncertain');
        $recoveredQuote->forceFill([
            'booking_claim_key' => 'easyparcel:order:'.$recoveredOrder->id,
            'booking_started_at' => now(),
            'selected_service_code' => null,
        ])->save();
        Http::fake([
            EasyParcelBookingReconciliationService::LIST_URL => Http::response([
                'status_code' => 200,
                'data' => [['shipment_number' => 'ES-AWB-RECOVERED']],
            ]),
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->detailsResponse(
                $recoveredQuote,
                'ES-AWB-RECOVERED',
                'AWB-RECOVERED-1',
                7,
            )),
        ]);
        $recoveredShipment = app(EasyParcelBookingReconciliationService::class)->reconcile($recoveredQuote);

        $recoveredDispatch = $recoveredOrder->notificationDispatches()->sole();
        self::assertSame('ready_to_ship', $recoveredDispatch->event_key);
        self::assertSame('shipment', $recoveredDispatch->source_type);
        self::assertSame((string) $recoveredShipment->id, $recoveredDispatch->source_id);
    }

    public function test_authenticated_details_first_awb_exception_statuses_do_not_consume_ready_to_ship_before_a_valid_replacement(): void
    {
        Http::preventStrayRequests();
        $this->connectedEasyParcelProvider();
        $detailsResponses = [];
        Http::fake(function (Request $request) use (&$detailsResponses) {
            $shipmentNumber = $request->data()['shipment_number'] ?? null;
            if ($request->url() !== EasyParcelShipmentDetailsService::DETAILS_URL
                || ! is_string($shipmentNumber)
                || ! isset($detailsResponses[$shipmentNumber])) {
                throw new RuntimeException('Unexpected EasyParcel details request.');
            }

            return Http::response($detailsResponses[$shipmentNumber]);
        });

        foreach ([
            0 => 'Cancelled',
            6 => 'Returned',
            8 => 'On Hold',
            11 => 'Drop-off Required',
        ] as $statusCode => $description) {
            $order = $this->order('processing', 'DETAILS-EXCEPTION-'.$statusCode);
            $exceptionShipment = $this->easyParcelShipment($order, 'ES-DETAILS-EXCEPTION-'.$statusCode);
            $detailsResponses['ES-DETAILS-EXCEPTION-'.$statusCode] = $this->detailsResponseForFirstAwb(
                $exceptionShipment,
                'AWB-DETAILS-EXCEPTION-'.$statusCode,
                $statusCode,
                $description,
            );

            app(EasyParcelShipmentDetailsService::class)->reconcile($exceptionShipment);

            self::assertSame('AWB-DETAILS-EXCEPTION-'.$statusCode, $exceptionShipment->fresh()->tracking_number);
            self::assertSame(0, $order->notificationDispatches()->count(), 'provider code '.$statusCode);
            $this->assertDatabaseMissing('notification_dispatches', [
                'idempotency_key' => 'automatic:email:order:'.$order->id.':event:ready_to_ship',
            ]);

            $replacement = $this->easyParcelShipment(
                $order,
                'ES-DETAILS-REPLACEMENT-'.$statusCode,
                sequence: 2,
            );
            $detailsResponses['ES-DETAILS-REPLACEMENT-'.$statusCode] = $this->detailsResponseForFirstAwb(
                $replacement,
                'AWB-DETAILS-REPLACEMENT-'.$statusCode,
                7,
                'Schedule In Arrangement',
            );

            app(EasyParcelShipmentDetailsService::class)->reconcile($replacement);

            $dispatch = $order->notificationDispatches()->sole();
            self::assertSame('ready_to_ship', $dispatch->event_key);
            self::assertSame((string) $replacement->id, $dispatch->source_id);
        }
    }

    public function test_booking_recovery_first_awb_exception_statuses_do_not_consume_ready_to_ship_before_a_valid_rebook(): void
    {
        Http::preventStrayRequests();
        $provider = $this->connectedEasyParcelProvider();
        $actor = $this->superAdmin();
        $listedShipmentNumber = null;
        $detailsResponses = [];
        Http::fake(function (Request $request) use (&$detailsResponses, &$listedShipmentNumber) {
            if ($request->url() === EasyParcelBookingReconciliationService::LIST_URL
                && is_string($listedShipmentNumber)) {
                return Http::response([
                    'status_code' => 200,
                    'data' => [['shipment_number' => $listedShipmentNumber]],
                ]);
            }

            $shipmentNumber = $request->data()['shipment_number'] ?? null;
            if ($request->url() === EasyParcelShipmentDetailsService::DETAILS_URL
                && is_string($shipmentNumber)
                && isset($detailsResponses[$shipmentNumber])) {
                return Http::response($detailsResponses[$shipmentNumber]);
            }

            throw new RuntimeException('Unexpected EasyParcel recovery request.');
        });

        foreach ([
            0 => 'Cancelled',
            6 => 'Returned',
            8 => 'On Hold',
            11 => 'Drop-off Required',
        ] as $statusCode => $description) {
            $order = $this->order('processing', 'RECOVERY-EXCEPTION-'.$statusCode);
            $exceptionQuote = $this->shipmentQuote($order, $provider, $actor, 'uncertain');
            $exceptionQuote->forceFill([
                'booking_claim_key' => 'easyparcel:order:'.$order->id,
                'booking_started_at' => now(),
                'selected_service_code' => null,
            ])->save();
            $exceptionResponse = $this->detailsResponse(
                $exceptionQuote,
                'ES-RECOVERY-EXCEPTION-'.$statusCode,
                'AWB-RECOVERY-EXCEPTION-'.$statusCode,
                $statusCode,
            );
            data_set(
                $exceptionResponse,
                'data.0.shipment_details.shipment_status',
                $description,
            );
            $listedShipmentNumber = 'ES-RECOVERY-EXCEPTION-'.$statusCode;
            $detailsResponses[$listedShipmentNumber] = $exceptionResponse;

            $exceptionShipment = app(EasyParcelBookingReconciliationService::class)->reconcile($exceptionQuote);

            self::assertSame('AWB-RECOVERY-EXCEPTION-'.$statusCode, $exceptionShipment->tracking_number);
            self::assertSame(0, $order->notificationDispatches()->count(), 'provider code '.$statusCode);
            $this->assertDatabaseMissing('notification_dispatches', [
                'idempotency_key' => 'automatic:email:order:'.$order->id.':event:ready_to_ship',
            ]);

            $exceptionShipment->forceFill(['status' => 'cancelled'])->save();
            $exceptionQuote->forceFill([
                'booking_state' => 'failed',
                'booking_claim_key' => null,
            ])->save();
            $replacementQuote = $this->shipmentQuote($order, $provider, $actor, 'in_progress');
            $replacementQuote->forceFill([
                'booking_claim_key' => 'easyparcel:order:'.$order->id,
                'booking_started_at' => now(),
                'selected_service_code' => null,
            ])->save();
            $listedShipmentNumber = 'ES-RECOVERY-REBOOK-'.$statusCode;
            $detailsResponses[$listedShipmentNumber] = $this->detailsResponse(
                $replacementQuote,
                $listedShipmentNumber,
                'AWB-RECOVERY-REBOOK-'.$statusCode,
                7,
            );

            $replacement = app(EasyParcelBookingReconciliationService::class)->reconcile($replacementQuote);

            $dispatch = $order->notificationDispatches()->sole();
            self::assertSame('ready_to_ship', $dispatch->event_key);
            self::assertSame((string) $replacement->id, $dispatch->source_id);
        }
    }

    public function test_late_awb_reconciliation_emits_ready_to_ship_once_and_rebooking_does_not_repeat_it(): void
    {
        $order = $this->order('processing', 'AWB-LATE');
        $service = app(ShipmentService::class);
        $first = $this->easyParcelShipment($order, 'ES-AWB-LATE-1');

        $service->reconcileEasyParcelDetails(
            $first,
            'ES-AWB-LATE-1',
            null,
            'EI-AWB-LATE-1',
            'AWB-LATE-1',
            'https://app.easyparcel.com/labels/AWB-LATE-1',
            'https://app.easyparcel.com/tracking/AWB-LATE-1',
            7,
            [],
        );
        $service->reconcileEasyParcelDetails(
            $first->fresh(),
            'ES-AWB-LATE-1',
            'AWB-LATE-1',
            'EI-AWB-LATE-1',
            'AWB-LATE-1',
            'https://app.easyparcel.com/labels/AWB-LATE-1',
            'https://app.easyparcel.com/tracking/AWB-LATE-1',
            7,
            [],
        );

        $service->recordEasyParcelEvents($first->fresh(), 'AWB-LATE-1', [[
            'external_event_id' => 'easyparcel:awb-cancelled-1',
            'status' => 'cancelled',
            'occurred_at' => CarbonImmutable::parse('2026-08-31T01:00:00Z'),
            'description' => 'AWB cancelled for replacement.',
            'location' => null,
        ]]);

        $replacement = $this->easyParcelShipment($order, 'ES-AWB-LATE-2', sequence: 2);
        $service->reconcileEasyParcelDetails(
            $replacement,
            'ES-AWB-LATE-2',
            null,
            'EI-AWB-LATE-2',
            'AWB-LATE-2',
            'https://app.easyparcel.com/labels/AWB-LATE-2',
            'https://app.easyparcel.com/tracking/AWB-LATE-2',
            7,
            [],
        );

        self::assertSame(1, $order->notificationDispatches()->where('event_key', 'ready_to_ship')->count());
        self::assertSame(0, $order->notificationDispatches()->where('event_key', 'cancelled')->count());
        self::assertSame('processing', $order->fresh()->status);
    }

    public function test_webhook_and_poll_replays_create_one_first_carrier_acceptance_milestone(): void
    {
        Http::preventStrayRequests();
        $this->connectedEasyParcelProvider();
        $order = $this->order('processing', 'WEBHOOK-SHIPPED');
        $shipment = $this->easyParcelShipment($order, 'ES-WEBHOOK-SHIPPED', 'AWB-WEBHOOK-SHIPPED');
        $shipment->forceFill([
            'provider_order_reference' => 'EI-WEBHOOK-SHIPPED',
            'label_path' => 'https://app.easyparcel.com/labels/AWB-WEBHOOK-SHIPPED',
        ])->save();
        $receipt = ShippingWebhookReceipt::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'topic' => 'shipment.status.update',
            'provider_uuid' => 'WEBHOOK-SHIPPED-1',
            'payload_hash' => hash('sha256', 'WEBHOOK-SHIPPED-1'),
            'shipment_id' => $shipment->id,
            'correlation_snapshot' => ['shipment_number' => $shipment->provider_reference],
            'processing_state' => 'queued',
            'received_at' => now(),
            'queued_at' => now(),
        ]);
        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->detailsResponseForShipment($shipment, 3)),
            EasyParcelTrackingService::TRACKING_URL => Http::response($this->trackingResponse($shipment, 3)),
        ]);

        $webhooks = app(EasyParcelWebhookReconciliationService::class);
        $webhooks->reconcile($receipt);
        $webhooks->reconcile($receipt->fresh());

        $dispatch = $order->notificationDispatches()->sole();
        self::assertSame('shipped', $dispatch->event_key);
        self::assertSame('shipment_event', $dispatch->source_type);
        $this->assertDatabaseHas('shipment_events', [
            'id' => (int) $dispatch->source_id,
            'shipment_id' => $shipment->id,
            'status' => 'shipped',
        ]);
        self::assertSame('processed', $receipt->fresh()->processing_state);
    }

    public function test_first_provider_truth_at_delivery_backfills_shipped_then_delivered_once(): void
    {
        $order = $this->order('processing', 'PROVIDER-DELIVERED');
        $shipment = $this->easyParcelShipment($order, 'ES-PROVIDER-DELIVERED', 'AWB-PROVIDER-DELIVERED');
        $events = [[
            'external_event_id' => 'easyparcel:first-truth-delivered',
            'status' => 'delivered',
            'occurred_at' => CarbonImmutable::parse('2026-08-31T09:30:00Z'),
            'description' => 'Delivered to recipient.',
            'location' => 'Kuala Lumpur',
        ]];

        $service = app(ShipmentService::class);
        $service->recordEasyParcelEvents($shipment, 'AWB-PROVIDER-DELIVERED', $events);
        $service->recordEasyParcelEvents($shipment->fresh(), 'AWB-PROVIDER-DELIVERED', $events);

        $dispatches = $order->notificationDispatches()->oldest('id')->get();
        self::assertSame(['shipped', 'delivered'], $dispatches->pluck('event_key')->all());
        self::assertSame($dispatches[0]->source_id, $dispatches[1]->source_id);
        self::assertSame('delivered', $order->fresh()->status);
    }

    public function test_provider_exception_scans_do_not_emit_business_milestones_or_cancel_the_commerce_order(): void
    {
        $service = app(ShipmentService::class);
        $cases = [
            ['cancelled', 'AWB cancelled by EasyParcel.'],
            ['on_hold', 'On Hold for provider review.'],
            ['returned', 'Returned to sender.'],
            ['pending', 'Drop-off Required (code 11).'],
            ['provider_update', 'Location-only provider update.'],
        ];

        foreach ($cases as $index => [$status, $description]) {
            $order = $this->order('processing', 'PROVIDER-EXCEPTION-'.$index);
            $shipment = $this->easyParcelShipment($order, 'ES-PROVIDER-EXCEPTION-'.$index, 'AWB-PROVIDER-EXCEPTION-'.$index);
            $service->recordEasyParcelEvents($shipment, (string) $shipment->tracking_number, [[
                'external_event_id' => 'easyparcel:provider-exception-'.$index,
                'status' => $status,
                'occurred_at' => CarbonImmutable::parse('2026-08-31T10:00:00Z')->addMinute($index),
                'description' => $description,
                'location' => $status === 'provider_update' ? 'Scan hub' : null,
            ]]);

            self::assertSame(0, $order->notificationDispatches()->count(), $status);
            self::assertSame('processing', $order->fresh()->status, $status);
        }
    }

    public function test_only_authoritative_commerce_cancellation_emits_cancelled(): void
    {
        $order = $this->order('processing', 'COMMERCE-CANCELLED');

        app(CheckoutService::class)->transition($order, 'cancelled', $this->superAdmin()->id, 'Customer requested cancellation.');

        $history = $order->statusHistory()->where('to_status', 'cancelled')->sole();
        $dispatch = $order->notificationDispatches()->sole();
        self::assertSame('cancelled', $dispatch->event_key);
        self::assertSame('order_status_history', $dispatch->source_type);
        self::assertSame((string) $history->id, $dispatch->source_id);
        self::assertSame('cancelled', $order->fresh()->status);
    }

    public function test_notification_intent_failure_is_reported_without_rolling_back_the_authoritative_mutation(): void
    {
        $order = $this->order('processing', 'INTENT-FAILURE');
        $notifications = Mockery::mock(OrderNotificationMilestoneService::class);
        $notifications->shouldReceive('record')->once()->withArgs(function (Order $recorded, string $eventKey) use ($order): bool {
            self::assertSame($order->id, $recorded->id);
            self::assertSame('cancelled', $eventKey);
            self::assertGreaterThan(0, DB::transactionLevel());
            $this->assertDatabaseHas('order_status_histories', [
                'order_id' => $order->id,
                'to_status' => 'cancelled',
            ]);

            return true;
        })->andThrow(new RuntimeException('notification template rendering failed'));
        $this->app->instance(OrderNotificationMilestoneService::class, $notifications);

        app(CheckoutService::class)->transition($order, 'cancelled', null, 'Authoritative cancellation.');

        self::assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseCount('notification_dispatches', 0);
    }

    public function test_after_commit_queue_publication_failure_does_not_fail_the_committed_business_mutation(): void
    {
        $order = $this->order('processing', 'QUEUE-PUBLISH-FAILURE');
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue backend unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        app(CheckoutService::class)->transition($order, 'cancelled', null, 'Cancellation still commits.');

        self::assertSame('cancelled', $order->fresh()->status);
        $dispatch = $order->notificationDispatches()->sole();
        self::assertSame('failed', $dispatch->state);
        self::assertSame('queue', $dispatch->last_error_code);
        self::assertNotNull($dispatch->failed_at);
    }

    private function postStripeWebhook(string $payload, string $secret = 'whsec_test_private')
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', '/payments/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }

    /** @return array{order: Order, payment: OrderPayment} */
    private function hostedPaymentOrder(string $gateway, string $reference): array
    {
        $order = $this->order('pending_payment', 'HOSTED-'.strtoupper($gateway), createPayment: false);
        $payment = $order->payments()->create([
            'gateway' => $gateway,
            'provider' => $gateway,
            'environment' => 'sandbox',
            'reference' => $reference,
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        return ['order' => $order, 'payment' => $payment];
    }

    /** @return array{0: Order, 1: OrderPayment} */
    private function proofOrder(string $suffix): array
    {
        $order = $this->order('pending_payment', $suffix, createPayment: false);
        $payment = $order->payments()->create([
            'gateway' => 'bank_transfer',
            'provider' => 'bank_transfer',
            'environment' => 'offline',
            'reference' => 'BANK-'.$suffix,
            'status' => 'pending',
            'proof_path' => 'payment-proofs/'.$order->id.'/receipt.png',
            'proof_disk' => 'local',
            'proof_original_name' => 'receipt.png',
            'proof_mime_type' => 'image/png',
            'proof_status' => 'uploaded',
            'proof_uploaded_at' => now(),
        ]);

        return [$order, $payment];
    }

    private function order(
        string $status,
        string $suffix,
        string $gateway = 'simulated_card',
        bool $createPayment = true,
    ): Order {
        $customer = Customer::query()->create([
            'email' => strtolower($suffix).'@example.test',
            'first_name' => 'Alex',
            'last_name' => $suffix,
            'phone' => '+60123456789',
        ]);
        $order = Order::query()->create([
            'number' => 'JW-NOTIFY-'.$suffix,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => $status,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $order->items()->create([
            'sku' => 'SKU-'.$suffix,
            'title' => 'Notification Product',
            'variant_title' => 'Default',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
            'package_snapshot' => [
                'weight_grams' => 1000,
                'length_cm' => 20,
                'width_cm' => 15,
                'height_cm' => 10,
            ],
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Alex',
            'last_name' => $suffix,
            'phone' => '+60123456789',
            'line_1' => '10 Studio Road',
            'city' => 'Kuala Lumpur',
            'state' => 'Selangor',
            'postal_code' => '50000',
            'country' => 'MY',
        ]);
        if ($createPayment) {
            $order->payments()->create([
                'gateway' => $gateway,
                'provider' => $gateway,
                'environment' => str_contains($gateway, 'simulated') ? 'simulated' : 'sandbox',
                'reference' => 'PAY-'.$suffix,
                'status' => 'pending',
                'amount' => 10000,
                'currency' => 'MYR',
            ]);
        }

        return $order;
    }

    private function easyParcelShipment(
        Order $order,
        string $providerReference,
        ?string $awb = null,
        int $sequence = 1,
    ): Shipment {
        return Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => $sequence,
            'source' => 'easyparcel',
            'carrier_code' => 'EP-CR0AP',
            'carrier_name' => 'Aramex',
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'tracking_number' => $awb,
            'status' => 'pending',
            'currency' => 'MYR',
            'shipping_cost' => 1084,
            'provider_reference' => $providerReference,
        ]);
    }

    private function connectedEasyParcelProvider(): ShippingProvider
    {
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 1,
            'public_metadata' => ['apiVersion' => '2026-06'],
            'last_verified_at' => now(),
        ]);
        ShippingProviderCredential::query()->create([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => [
                'client_id' => 'sandbox-client',
                'client_secret' => 'sandbox-secret',
                'token_type' => 'Bearer',
                'access_token' => 'sandbox-access-token',
                'access_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
                'sender_profile' => [
                    'name' => 'JW Studio Dispatch',
                    'phone_number_country_code' => 'MY',
                    'phone_number' => '123456789',
                    'address_1' => '18 Industry Road',
                    'postcode' => '47600',
                    'city' => 'Subang Jaya',
                    'subdivision_code' => 'MY-10',
                    'country_code' => 'MY',
                ],
            ],
        ]);

        return $provider;
    }

    private function shipmentQuote(
        Order $order,
        ShippingProvider $provider,
        User $actor,
        string $state,
    ): ShipmentQuote {
        return ShipmentQuote::query()->create([
            'order_id' => $order->id,
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'provider_configuration_version' => 1,
            'package_snapshot' => ['weight_kg' => 1, 'length_cm' => 20, 'width_cm' => 15, 'height_cm' => 10],
            'request_snapshot' => [
                'booking' => [
                    'collection_date' => now()->addWeekday()->toDateString(),
                    'sender' => ['name' => 'JW Studio Dispatch'],
                    'receiver' => ['name' => 'Alex Buyer'],
                    'items' => [[
                        'content' => 'Notification Product',
                        'weight' => 1,
                        'height' => 10,
                        'length' => 20,
                        'width' => 15,
                        'value' => 100,
                        'quantity' => 1,
                    ]],
                    'cod' => null,
                ],
            ],
            'response_snapshot' => ['services' => [$this->easyParcelService()]],
            'reconciliation_reference' => 'JWEP-'.$order->id.'-'.$state,
            'booking_state' => $state,
            'expires_at' => now()->addMinutes(15),
            'created_by' => $actor->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function easyParcelService(): array
    {
        return [
            'service_code' => 'EP-CS096',
            'service_name' => 'Aramex (Pick Up)',
            'courier_code' => 'EP-CR0AP',
            'courier_name' => 'Aramex',
            'delivery_duration' => '1-3 working days',
            'is_pickup' => true,
            'is_dropoff' => false,
            'supports_cod' => false,
            'currency' => 'MYR',
            'amount_minor' => 1084,
        ];
    }

    /** @return array<string, mixed> */
    private function detailsResponse(
        ShipmentQuote $quote,
        string $shipmentNumber,
        string $awb,
        int $statusCode,
    ): array {
        return [
            'status_code' => 200,
            'data' => [[
                'shipment_number' => $shipmentNumber,
                'order_number' => 'EI-'.$shipmentNumber,
                'shipment_details' => [
                    'shipment_status_code' => $statusCode,
                    'shipment_status' => 'Schedule In Arrangement',
                    'awb_number' => $awb,
                    'awb_url' => 'https://app.easyparcel.com/labels/'.$awb,
                    'tracking_url' => 'https://easyparcel.com/my/en/track/details/'.$awb,
                    'reference' => $quote->reconciliation_reference,
                ],
                'courier' => [
                    'service_id' => 'EP-CS096',
                    'courier_id' => 'EP-CR0AP',
                    'courier_name' => 'Aramex',
                    'service_types' => 'Aramex (Pick Up)',
                ],
                'pricing' => ['currency_code' => 'MYR', 'total_price' => '10.84'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function detailsResponseForShipment(Shipment $shipment, int $statusCode): array
    {
        $quote = new ShipmentQuote(['reconciliation_reference' => null]);
        $response = $this->detailsResponse($quote, (string) $shipment->provider_reference, (string) $shipment->tracking_number, $statusCode);
        data_set($response, 'data.0.order_number', $shipment->provider_order_reference);
        data_set($response, 'data.0.shipment_details.reference', null);
        data_set($response, 'data.0.shipment_details.shipment_status', 'Parcel accepted by carrier.');

        return $response;
    }

    /** @return array<string, mixed> */
    private function detailsResponseForFirstAwb(
        Shipment $shipment,
        string $awb,
        int $statusCode,
        string $description,
    ): array {
        $quote = new ShipmentQuote(['reconciliation_reference' => null]);
        $response = $this->detailsResponse(
            $quote,
            (string) $shipment->provider_reference,
            $awb,
            $statusCode,
        );
        data_set($response, 'data.0.shipment_details.shipment_status', $description);
        data_set($response, 'data.0.shipment_details.reference', null);

        return $response;
    }

    /** @return array<string, mixed> */
    private function trackingResponse(Shipment $shipment, int $statusCode): array
    {
        return [
            'status_code' => 200,
            'data' => ['results' => [[
                'awb_number' => $shipment->tracking_number,
                'shipment_number' => $shipment->provider_reference,
                'order_number' => $shipment->provider_order_reference,
                'status' => 'success',
                'status_log' => [[
                    'event_date' => '2026-08-31 10:00:00',
                    'shipment_status_code' => $statusCode,
                    'tracking_status' => 'Parcel accepted by carrier.',
                    'location' => 'Kuala Lumpur',
                ]],
            ]]],
        ];
    }

    private function checkoutVariant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Studio Headphones',
            'handle' => 'studio-headphones',
            'brand' => 'JW Studio',
            'description' => 'Test',
            'base_price' => 10000,
            'status' => 'active',
        ]);

        return $product->variants()->create([
            'sku' => 'STUDIO-NOTIFY-1',
            'title' => 'Default',
            'price' => 10000,
            'stock' => 10,
            'enabled' => true,
        ]);
    }

    private function configureCheckout(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'shipping_rates'], ['value' => ['data' => [[
            'id' => 'standard',
            'name' => 'Standard Delivery',
            'zoneRegion' => 'all',
            'logisticType' => 'all',
            'calculationMode' => 'per_order',
            'rateType' => 'flat',
            'tiers' => [],
            'flatRateCost' => 0,
            'freeShippingThreshold' => 0,
            'isActive' => true,
        ]]]]);
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [[
                'id' => 'maybank-primary',
                'bankName' => 'Maybank',
                'accountNumber' => '514000004432',
                'isPrimary' => true,
                'isActive' => true,
            ]],
        ]]]);
    }

    /** @return array<string, string> */
    private function checkoutData(): array
    {
        return [
            'email' => 'checkout-notify@example.test',
            'first_name' => 'Checkout',
            'last_name' => 'Buyer',
            'line_1' => '10 Studio Road',
            'city' => 'Kuala Lumpur',
            'state' => 'Selangor',
            'postal_code' => '50000',
            'country' => 'MY',
            'delivery' => 'standard',
            'payment_method' => 'bank_transfer',
        ];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}
