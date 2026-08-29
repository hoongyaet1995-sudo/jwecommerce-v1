<?php

namespace Tests\Feature;

use App\Jobs\ReconcileEasyParcelTask;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\ShippingWebhookReceipt;
use App\Services\Shipping\EasyParcelReconciliationTaskService;
use App\Services\Shipping\EasyParcelShipmentDetailsService;
use App\Services\Shipping\EasyParcelTrackingException;
use App\Services\Shipping\EasyParcelTrackingService;
use App\Services\Shipping\EasyParcelWebhookReconciliationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EasyParcelWebhookReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_awb_webhook_uses_authenticated_details_before_updating_shipment(): void
    {
        Queue::fake();
        $this->connectedProvider();
        [$order, $shipment] = $this->easyParcelShipmentWithoutAwb('ES-2608-AWB01');

        $receipt = $this->receipt([
            'topic' => 'shipment.awb.update',
            'shipment_number' => 'ES-2608-AWB01',
            'awb_number' => 'FORGED-WEBHOOK-AWB',
        ], $shipment);

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->shipmentDetailsResponse(
                shipmentNumber: 'ES-2608-AWB01',
                awb: 'AUTHENTIC-AWB-01',
            )),
            EasyParcelTrackingService::TRACKING_URL => Http::response($this->trackingNotFoundResponse(
                'AUTHENTIC-AWB-01',
            )),
        ]);

        app(EasyParcelWebhookReconciliationService::class)->reconcile($receipt);

        self::assertSame('AUTHENTIC-AWB-01', $shipment->fresh()->tracking_number);
        self::assertNotSame('FORGED-WEBHOOK-AWB', $shipment->fresh()->tracking_number);
        self::assertSame('EI-2608-AWB01', $shipment->fresh()->provider_order_reference);
        self::assertSame(
            'https://app.easyparcel.com/labels/AUTHENTIC-AWB-01',
            $shipment->fresh()->label_path,
        );
        self::assertSame(
            'https://easyparcel.com/my/en/track/details/AUTHENTIC-AWB-01',
            $shipment->fresh()->tracking_url,
        );
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('processed', $receipt->fresh()->processing_state);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === EasyParcelShipmentDetailsService::DETAILS_URL
                && $request->data() === ['shipment_number' => 'ES-2608-AWB01']
                && $request->hasHeader('Authorization', 'Bearer sandbox-access-token');
        });
    }

    public function test_mismatched_authenticated_details_cannot_mutate_the_existing_shipment(): void
    {
        $this->connectedProvider();
        [$order, $shipment] = $this->easyParcelShipmentWithoutAwb('ES-2608-MISMATCH');
        $receipt = $this->receipt([
            'topic' => 'shipment.awb.update',
            'shipment_number' => 'ES-2608-MISMATCH',
            'awb_number' => 'FORGED-AWB',
        ], $shipment);

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->shipmentDetailsResponse(
                shipmentNumber: 'ES-SOMEONE-ELSE',
                awb: 'WRONG-AWB',
            )),
        ]);

        try {
            app(EasyParcelWebhookReconciliationService::class)->reconcile($receipt);
            self::fail('A mismatched authenticated shipment identity must be rejected.');
        } catch (EasyParcelTrackingException) {
            // Expected stable provider failure boundary.
        }

        self::assertNull($shipment->fresh()->tracking_number);
        self::assertNull($shipment->fresh()->provider_order_reference);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('retrying', $receipt->fresh()->processing_state);
        self::assertSame(1, $receipt->fresh()->attempt_count);
        self::assertSame('provider_unavailable', $receipt->fresh()->last_error);
    }

    public function test_authenticated_details_require_the_exact_unpadded_requested_identity(): void
    {
        $this->connectedProvider();
        [, $shipment] = $this->easyParcelShipmentWithoutAwb('ES-2608-EXACT01');

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($this->shipmentDetailsResponse(
                shipmentNumber: ' ES-2608-EXACT01 ',
                awb: 'WRONG-AWB',
            )),
        ]);

        $this->expectException(EasyParcelTrackingException::class);

        try {
            app(EasyParcelShipmentDetailsService::class)->reconcile($shipment);
        } finally {
            self::assertNull($shipment->fresh()->tracking_number);
            self::assertNull($shipment->fresh()->provider_order_reference);
        }
    }

    public function test_stale_details_response_cannot_overwrite_an_awb_replaced_while_request_was_in_flight(): void
    {
        $this->connectedProvider();
        [, $shipment] = $this->easyParcelShipment('ES-2608-AWB-RACE', 'AWB-ORIGINAL');

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => function () use ($shipment) {
                Shipment::query()->whereKey($shipment->id)->update([
                    'tracking_number' => 'AWB-REPLACEMENT',
                    'updated_at' => now()->addSecond(),
                ]);

                return Http::response($this->shipmentDetailsResponse(
                    shipmentNumber: 'ES-2608-AWB-RACE',
                    awb: 'AWB-STALE-RESPONSE',
                ));
            },
        ]);

        try {
            app(EasyParcelShipmentDetailsService::class)->reconcile($shipment);
            self::fail('A stale Shipment Details response must be rejected.');
        } catch (EasyParcelTrackingException) {
            // Expected stale-response boundary.
        }

        self::assertSame('AWB-REPLACEMENT', $shipment->fresh()->tracking_number);
        self::assertNull($shipment->fresh()->provider_order_reference);
    }

    public function test_non_easyparcel_https_reference_is_rejected_without_partial_mutation(): void
    {
        $this->connectedProvider();
        [, $shipment] = $this->easyParcelShipmentWithoutAwb('ES-2608-URL01');
        $receipt = $this->receipt([
            'topic' => 'shipment.created',
            'shipment_number' => 'ES-2608-URL01',
        ], $shipment);
        $response = $this->shipmentDetailsResponse('ES-2608-URL01', 'AUTHENTIC-AWB-URL01');
        data_set($response, 'data.0.shipment_details.awb_url', 'https://attacker.example.test/label');

        Http::fake([
            EasyParcelShipmentDetailsService::DETAILS_URL => Http::response($response),
        ]);

        $this->expectException(EasyParcelTrackingException::class);

        try {
            app(EasyParcelWebhookReconciliationService::class)->reconcile($receipt);
        } finally {
            self::assertNull($shipment->fresh()->tracking_number);
            self::assertNull($shipment->fresh()->label_path);
            self::assertSame('retrying', $receipt->fresh()->processing_state);
        }
    }

    public function test_tracking_webhook_dispatches_one_unique_job_and_never_applies_payload_status_directly(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'test-token']);
        [$order, $shipment] = $this->easyParcelShipment('ES-2608-TRK01', 'AWB-TRK01');

        $payload = $this->trackingWebhook($shipment, latestCode: 5);
        $this->postJson('/webhooks/easyparcel/test-token', $payload)->assertOk();
        $this->postJson('/webhooks/easyparcel/test-token', $payload)->assertOk();

        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('pending', $shipment->fresh()->status);
        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertSame('queued', $receipt->processing_state);
        self::assertNotNull($receipt->queued_at);
    }

    public function test_unmatched_receipts_are_durably_requeued_while_ignored_receipts_are_not(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'test-token']);

        $this->postJson('/webhooks/easyparcel/test-token', [
            'topic' => 'shipment.status.update',
            'shipment_number' => 'ES-UNKNOWN',
        ])->assertOk();
        $this->postJson('/webhooks/easyparcel/test-token', [
            'topic' => 'ondemand.status.update',
            'shipment_number' => 'ES-UNKNOWN',
        ])->assertOk();

        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
        self::assertSame(
            ['ignored', 'unmatched'],
            ShippingWebhookReceipt::query()->orderBy('processing_state')->pluck('processing_state')->all(),
        );
    }

    public function test_reconciliation_job_relies_on_the_database_lease_instead_of_a_queue_unique_lock(): void
    {
        [, $shipment] = $this->easyParcelShipment('ES-2608-JOB01', 'AWB-JOB01');
        $receipt = $this->receipt([
            'topic' => 'shipment.tracking.update',
            'shipment_number' => 'ES-2608-JOB01',
            'awb_number' => 'AWB-JOB01',
        ], $shipment);
        $task = app(EasyParcelReconciliationTaskService::class)->ensureWebhookReceipt($receipt);
        $task->forceFill([
            'state' => 'processing',
            'lease_token' => (string) str()->uuid(),
            'lease_expires_at' => now()->subSecond(),
        ])->save();
        $job = new ReconcileEasyParcelTask($task->id);

        self::assertInstanceOf(ShouldQueue::class, $job);
        self::assertNotInstanceOf(ShouldBeUnique::class, $job);
        self::assertSame(1, $job->tries);
        self::assertSame(60, $job->timeout);

        $job->failed(new RuntimeException('raw provider secret must not persist'));

        $task->refresh();
        self::assertSame('retrying', $task->state);
        self::assertNull($task->lease_token);
        self::assertSame('worker_crashed', $task->last_error);
    }

    private function connectedProvider(): ShippingProvider
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
            ],
        ]);

        return $provider;
    }

    /** @return array{Order, Shipment} */
    private function easyParcelShipmentWithoutAwb(string $providerReference): array
    {
        return $this->easyParcelShipment($providerReference, null);
    }

    /** @return array{Order, Shipment} */
    private function easyParcelShipment(string $providerReference, ?string $awb): array
    {
        $order = Order::query()->create([
            'number' => 'JW-WEB-'.Str::upper(Str::random(8)),
            'email' => 'webhook-reconcile@example.test',
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'EasyParcel Sandbox Courier',
            'tracking_number' => $awb,
            'status' => 'pending',
            'currency' => 'MYR',
            'provider_reference' => $providerReference,
        ]);

        return [$order, $shipment];
    }

    /** @param array<string, int|string> $correlation */
    private function receipt(array $correlation, Shipment $shipment): ShippingWebhookReceipt
    {
        return ShippingWebhookReceipt::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'topic' => $correlation['topic'],
            'provider_uuid' => null,
            'payload_hash' => hash('sha256', json_encode($correlation, JSON_THROW_ON_ERROR)),
            'shipment_id' => $shipment->id,
            'correlation_snapshot' => $correlation,
            'processing_state' => 'queued',
            'received_at' => now(),
            'queued_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function shipmentDetailsResponse(string $shipmentNumber, string $awb): array
    {
        return [
            'status_code' => 200,
            'message' => 'success',
            'data' => [[
                'shipment_number' => $shipmentNumber,
                'order_number' => 'EI-2608-AWB01',
                'shipment_details' => [
                    'awb_number' => $awb,
                    'awb_url' => 'https://app.easyparcel.com/labels/'.$awb,
                    'tracking_url' => 'https://easyparcel.com/my/en/track/details/'.$awb,
                ],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function trackingNotFoundResponse(string $awb): array
    {
        return [
            'status_code' => 200,
            'message' => '1 request success, 0 request error.',
            'data' => ['results' => [[
                'status' => 'not_found',
                'awb_number' => $awb,
                'shipment_number' => 'ES-'.$awb,
                'order_number' => 'EI-'.$awb,
                'latest_shipment_status_code' => null,
                'latest_tracking_status' => null,
                'latest_event_date' => null,
                'status_log' => [],
            ]]],
        ];
    }

    /** @return array<string, mixed> */
    private function trackingWebhook(Shipment $shipment, int $latestCode): array
    {
        return [
            'topic' => 'shipment.tracking.update',
            'uuid' => 'webhook-'.$shipment->id,
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
            'latest_shipment_status_code' => $latestCode,
            'latest_tracking_status' => 'Webhook hint only',
            'timestamp' => '2026-08-29 12:00:00',
        ];
    }
}
