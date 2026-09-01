<?php

namespace Tests\Feature;

use App\Jobs\ReconcileEasyParcelTask;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingWebhookReceipt;
use App\Services\Shipping\EasyParcelWebhookReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelWebhookIngressTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_stable_webhook_route_is_csrf_exempt_outside_the_testing_environment(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');

        $this->call('POST', '/webhooks/easyparcel', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}')->assertStatus(415);
    }

    public function test_supported_webhook_is_sanitized_deduplicated_and_does_not_mutate_order(): void
    {
        Queue::fake();
        [$order, $shipment] = $this->easyParcelShipment('ES-2608-WEB01', 'AWB-WEB01');
        $payload = [
            'topic' => 'shipment.tracking.update',
            'uuid' => 'webhook-test-uuid-123',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
            'latest_shipment_status_code' => 5,
            'latest_tracking_status' => 'Delivered to recipient',
            'timestamp' => '2026-08-29 12:00:00',
            'receiver_email' => 'must-not-persist@example.test',
        ];

        $this->postJson('/webhooks/easyparcel', $payload)->assertOk();
        $this->postJson('/webhooks/easyparcel', $payload)->assertOk();

        $this->assertDatabaseCount('shipping_webhook_receipts', 1);
        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertSame('shipment.tracking.update', $receipt->topic);
        self::assertSame($shipment->id, $receipt->shipment_id);
        self::assertSame([
            'shipment_number' => 'ES-2608-WEB01',
            'awb_number' => 'AWB-WEB01',
            'latest_shipment_status_code' => 5,
            'timestamp' => '2026-08-29 12:00:00',
        ], $receipt->correlation_snapshot);
        self::assertStringNotContainsString('must-not-persist@example.test', json_encode($receipt->getAttributes()));
        self::assertStringNotContainsString('Delivered to recipient', json_encode($receipt->getAttributes()));
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('pending', $shipment->fresh()->status);
        self::assertSame('queued', $receipt->fresh()->processing_state);
        self::assertNotNull($receipt->fresh()->queued_at);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_ondemand_and_unknown_standard_webhooks_are_classified_without_a_lifecycle_mutation(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
        [$order, $shipment] = $this->easyParcelShipment('ES-2608-WEB02', 'AWB-WEB02');

        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'ondemand.status.update',
            'uuid' => 'ondemand-uuid',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
        ])->assertOk();
        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'shipment.status.update',
            'uuid' => 'unknown-uuid',
            'shipment_number' => 'ES-UNKNOWN',
            'awb_number' => 'AWB-UNKNOWN',
        ])->assertOk();

        $this->assertDatabaseHas('shipping_webhook_receipts', [
            'topic' => 'ondemand.status.update',
            'shipment_id' => null,
            'processing_state' => 'ignored',
        ]);
        $this->assertDatabaseHas('shipping_webhook_receipts', [
            'topic' => 'shipment.status.update',
            'shipment_id' => null,
            'processing_state' => 'unmatched',
        ]);
        self::assertSame('processing', $order->fresh()->status);
        self::assertSame('pending', $shipment->fresh()->status);
    }

    public function test_webhook_correlation_ignores_non_easyparcel_shipments(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
        [, $manualShipment] = $this->easyParcelShipment('MANUAL-PROVIDER-REF', 'MANUAL-AWB');
        $manualShipment->forceFill(['source' => 'manual'])->save();

        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'shipment.status.update',
            'shipment_number' => 'MANUAL-PROVIDER-REF',
            'awb_number' => 'MANUAL-AWB',
        ])->assertOk();

        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertNull($receipt->shipment_id);
        self::assertSame('unmatched', $receipt->processing_state);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_webhook_with_two_identifiers_requires_both_to_resolve_to_the_same_easyparcel_shipment(): void
    {
        Queue::fake();
        config(['services.easyparcel.webhook_path_token' => 'test-endpoint-token']);
        [, $first] = $this->easyParcelShipment('ES-CONSISTENT-A', 'AWB-CONSISTENT-A');
        [, $second] = $this->easyParcelShipment('ES-CONSISTENT-B', 'AWB-CONSISTENT-B');

        $this->postJson('/webhooks/easyparcel/test-endpoint-token', [
            'topic' => 'shipment.tracking.update',
            'shipment_number' => $first->provider_reference,
            'awb_number' => $second->tracking_number,
        ])->assertOk();

        $receipt = ShippingWebhookReceipt::query()->sole();
        self::assertNull($receipt->shipment_id);
        self::assertSame('unmatched', $receipt->processing_state);
        Queue::assertPushed(ReconcileEasyParcelTask::class, 1);
    }

    public function test_concurrent_matched_duplicates_return_one_queued_receipt_and_dispatch_once(): void
    {
        $providerReference = 'ES-CONCURRENT-'.Str::upper(Str::random(12));
        $queue = 'easyparcel';
        [$orderId, $shipmentId] = $this->createConcurrentShipmentFixture($providerReference);
        $rawBody = json_encode([
            'topic' => 'shipment.tracking.update',
            'uuid' => 'concurrent-webhook-'.Str::uuid(),
            'shipment_number' => $providerReference,
            'awb_number' => 'AWB-'.$providerReference,
        ], JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $rawBody);
        $barrier = 'easyparcel-webhook-'.Str::uuid();
        $lock = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$barrier]);
        self::assertSame(1, (int) $lock->acquired);

        $workers = [
            $this->concurrentReceiptWorker($barrier, $rawBody, $queue),
            $this->concurrentReceiptWorker($barrier, $rawBody, $queue),
        ];

        try {
            foreach ($workers as $worker) {
                $worker->start();
            }

            $this->waitForWorkersAtReceiptLookup($workers);
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$barrier]);

            foreach ($workers as $worker) {
                $worker->wait();
                self::assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                self::assertStringContainsString('status:200', $worker->getOutput());
            }

            $receiptIds = array_map(function (Process $worker): string {
                preg_match('/receipt:(\d+)/', $worker->getOutput(), $matches);

                self::assertArrayHasKey(1, $matches, $worker->getOutput());

                return $matches[1];
            }, $workers);

            self::assertSame($receiptIds[0], $receiptIds[1]);
            $claimUpdates = array_map(function (Process $worker): int {
                preg_match('/claim-updates:(\d+)/', $worker->getOutput(), $matches);

                self::assertArrayHasKey(1, $matches, $worker->getOutput());

                return (int) $matches[1];
            }, $workers);
            self::assertSame(1, array_sum($claimUpdates));
            $this->assertDatabaseCount('shipping_webhook_receipts', 1);
            $this->assertDatabaseHas('shipping_webhook_receipts', [
                'id' => $receiptIds[0],
                'payload_hash' => $payloadHash,
                'shipment_id' => $shipmentId,
                'processing_state' => 'queued',
            ]);
            self::assertNotNull(ShippingWebhookReceipt::query()->sole()->queued_at);
            self::assertSame(1, DB::table('jobs')->where('queue', $queue)->count());
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$barrier]);

            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }

            $this->deleteConcurrentFixture($payloadHash, $queue, $shipmentId, $orderId);
        }
    }

    public function test_queue_claim_failure_rolls_back_the_new_matched_receipt(): void
    {
        [, $shipment] = $this->easyParcelShipment('ES-ATOMIC-ROLLBACK', 'AWB-ATOMIC-ROLLBACK');
        $payload = [
            'topic' => 'shipment.tracking.update',
            'uuid' => 'atomic-rollback-webhook',
            'shipment_number' => $shipment->provider_reference,
            'awb_number' => $shipment->tracking_number,
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $rawBody);
        $failClaim = true;

        DB::connection()->beforeExecuting(function (string $query) use (&$failClaim): void {
            if ($failClaim && str_contains(strtolower($query), 'update `shipping_webhook_receipts`')) {
                $failClaim = false;

                throw new RuntimeException('simulated_queue_claim_failure');
            }
        });

        try {
            app(EasyParcelWebhookReceiptService::class)
                ->receiveAndQueue($rawBody, $payload);
            self::fail('A failed queue claim must abort receipt creation.');
        } catch (RuntimeException $exception) {
            self::assertSame('simulated_queue_claim_failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('shipping_webhook_receipts', [
            'payload_hash' => $payloadHash,
        ]);
    }

    public function test_wrong_token_and_oversized_body_are_rejected_without_receipt(): void
    {
        config(['services.easyparcel.webhook_path_token' => 'correct-token']);

        $this->postJson('/webhooks/easyparcel/wrong-token', ['topic' => 'shipment.created'])
            ->assertNotFound();
        $this->call('POST', '/webhooks/easyparcel/correct-token', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['topic' => 'shipment.created', 'padding' => str_repeat('x', 65537)]))
            ->assertStatus(413);

        $this->assertDatabaseCount('shipping_webhook_receipts', 0);
    }

    /** @return array{Order, Shipment} */
    private function easyParcelShipment(string $providerReference, string $awb): array
    {
        $order = Order::query()->create([
            'number' => 'JW-WEBHOOK-'.Str::upper(Str::random(8)),
            'email' => 'webhook-fixture@example.test',
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

    private function concurrentReceiptWorker(string $barrier, string $rawBody, string $queue): Process
    {
        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$barrier = $argv[2];
$rawBody = base64_decode($argv[3], true);
if ($rawBody === false) {
    throw new \RuntimeException('Worker received an invalid raw body.');
}
$blocked = false;
$claimUpdates = 0;

\Illuminate\Support\Facades\DB::connection()->beforeExecuting(function (string $query) use (&$blocked, &$claimUpdates, $barrier): void {
    if (str_contains(strtolower($query), 'update `shipping_webhook_receipts`')) {
        $claimUpdates++;
    }

    if ($blocked || ! str_contains(strtolower($query), 'shipping_webhook_receipts')) {
        return;
    }

    $blocked = true;
    fwrite(STDOUT, "snapshot-ready\n");
    $lock = \Illuminate\Support\Facades\DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$barrier]);
    if ((int) $lock->acquired !== 1) {
        throw new \RuntimeException('Timed out waiting for the concurrent receipt barrier.');
    }
    \Illuminate\Support\Facades\DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$barrier]);
});

config(['services.easyparcel.webhook_path_token' => 'concurrency-token']);
$request = \Illuminate\Http\Request::create(
    '/webhooks/easyparcel/concurrency-token',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    $rawBody,
);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
if ($response->getStatusCode() !== 200) {
    throw new \RuntimeException('Webhook returned HTTP '.$response->getStatusCode().': '.$response->getContent());
}
$receipt = \App\Models\ShippingWebhookReceipt::query()
    ->where('payload_hash', hash('sha256', $rawBody))
    ->sole();
fwrite(
    STDOUT,
    'status:'.$response->getStatusCode().' receipt:'.$receipt->id.' claim-updates:'.$claimUpdates.PHP_EOL,
);
PHP;

        return (new Process(
            [PHP_BINARY, '-r', $script, base_path(), $barrier, base64_encode($rawBody)],
            base_path(),
            array_replace(getenv(), [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'jw_ecommerce_testing',
                'DB_USERNAME' => 'root',
                'DB_PASSWORD' => '',
                'QUEUE_CONNECTION' => 'database',
                'DB_QUEUE' => $queue,
            ]),
        ))->setTimeout(15);
    }

    /** @param array<int, Process> $workers */
    private function waitForWorkersAtReceiptLookup(array $workers): void
    {
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            if (collect($workers)->every(
                static fn (Process $worker): bool => str_contains($worker->getOutput(), 'snapshot-ready'),
            )) {
                return;
            }

            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    self::fail($worker->getErrorOutput().$worker->getOutput());
                }
            }

            usleep(10_000);
        }

        self::fail('Timed out waiting for both concurrent workers to establish their receipt lookup snapshot.');
    }

    /** @return array{int, int} */
    private function createConcurrentShipmentFixture(string $providerReference): array
    {
        $pdo = $this->workerPdo();
        $now = now()->format('Y-m-d H:i:s');
        $order = $pdo->prepare(
            'INSERT INTO orders '
            .'(number, email, currency, status, subtotal, discount_amount, shipping_amount, tax_amount, total, created_at, updated_at) '
            .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $order->execute([
            'JW-CONCURRENT-'.Str::upper(Str::random(12)),
            'concurrent-webhook@example.test',
            'MYR',
            'processing',
            10000,
            0,
            0,
            0,
            10000,
            $now,
            $now,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $shipment = $pdo->prepare(
            'INSERT INTO shipments '
            .'(order_id, sequence, source, carrier_name, tracking_number, status, currency, provider_reference, created_at, updated_at) '
            .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $shipment->execute([
            $orderId,
            1,
            'easyparcel',
            'EasyParcel Sandbox Courier',
            'AWB-'.$providerReference,
            'pending',
            'MYR',
            $providerReference,
            $now,
            $now,
        ]);

        return [$orderId, (int) $pdo->lastInsertId()];
    }

    private function deleteConcurrentFixture(
        string $payloadHash,
        string $queue,
        int $shipmentId,
        int $orderId,
    ): void {
        $pdo = $this->workerPdo();
        $pdo->prepare('DELETE FROM jobs WHERE queue = ?')->execute([$queue]);
        $pdo->prepare('DELETE FROM easyparcel_reconciliation_tasks WHERE shipping_webhook_receipt_id IN (SELECT id FROM shipping_webhook_receipts WHERE payload_hash = ?)')->execute([$payloadHash]);
        $statement = $pdo->prepare('DELETE FROM shipping_webhook_receipts WHERE payload_hash = ?');
        $statement->execute([$payloadHash]);
        $pdo->prepare('DELETE FROM shipments WHERE id = ?')->execute([$shipmentId]);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    }

    private function workerPdo(): PDO
    {
        $connection = config('database.connections.mysql');

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $connection['host'],
                $connection['port'],
                $connection['database'],
            ),
            $connection['username'],
            $connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
