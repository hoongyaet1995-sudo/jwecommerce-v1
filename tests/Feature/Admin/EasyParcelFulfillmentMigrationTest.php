<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ShippingProvider;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelFulfillmentMigrationTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_fulfillment_schema_upgrade_and_safe_rollback_preserve_references_and_full_length_label(): void
    {
        $upgradePath = database_path('migrations/2026_08_27_000800_harden_easyparcel_fulfillment_columns.php');
        $this->assertFileExists($upgradePath);

        $upgradeMigration = require $upgradePath;
        $this->guardTestingSchemaMutation();

        try {
            $upgradeMigration->down();

            $this->assertTrue(Schema::hasColumn('shipment_quotes', 'idempotency_reference'));
            $this->assertFalse(Schema::hasColumn('shipment_quotes', 'reconciliation_reference'));

            $actor = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
            $customer = Customer::query()->create(['email' => 'migration-buyer@example.test']);
            $order = Order::query()->create([
                'number' => 'JW-EASYPARCEL-MIGRATION-1',
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'currency' => 'MYR',
                'status' => 'processing',
                'subtotal' => 10000,
                'discount_amount' => 0,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'total' => 10000,
            ]);
            $provider = ShippingProvider::query()->create([
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'enabled' => true,
                'connection_state' => 'connected',
                'configuration_version' => 1,
            ]);
            DB::table('shipment_quotes')->insert([
                'order_id' => $order->id,
                'shipping_provider_id' => $provider->id,
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'provider_configuration_version' => 1,
                'package_snapshot' => json_encode(['weight_kg' => 1], JSON_THROW_ON_ERROR),
                'request_snapshot' => json_encode(['booking' => []], JSON_THROW_ON_ERROR),
                'response_snapshot' => json_encode(['services' => []], JSON_THROW_ON_ERROR),
                'idempotency_reference' => 'JWEP-LEGACY-REFERENCE',
                'booking_state' => 'available',
                'expires_at' => now()->addMinutes(15),
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $shipmentId = DB::table('shipments')->insertGetId([
                'order_id' => $order->id,
                'sequence' => 1,
                'source' => 'manual',
                'carrier_name' => 'Legacy courier',
                'status' => 'pending',
                'currency' => 'MYR',
                'label_path' => 'https://app.easyparcel.com/legacy-label',
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $upgradeMigration->up();

            $this->assertFalse(Schema::hasColumn('shipment_quotes', 'idempotency_reference'));
            $this->assertTrue(Schema::hasColumn('shipment_quotes', 'reconciliation_reference'));
            $this->assertSame(
                'JWEP-LEGACY-REFERENCE',
                DB::table('shipment_quotes')->where('order_id', $order->id)->value('reconciliation_reference'),
            );

            $prefix = 'https://app.easyparcel.com/labels/';
            $longLabel = $prefix.str_repeat('a', 2048 - strlen($prefix));
            DB::table('shipments')->where('id', $shipmentId)->update(['label_path' => $longLabel]);

            $this->assertSame($longLabel, DB::table('shipments')->where('id', $shipmentId)->value('label_path'));

            $rollbackQueries = [];
            DB::listen(function ($query) use (&$rollbackQueries): void {
                $rollbackQueries[] = $query->sql;
            });

            $upgradeMigration->down();

            $this->assertFalse(collect($rollbackQueries)->contains(
                fn (string $query): bool => str_contains(strtolower($query), 'shipments'),
            ), 'Rollback must not rebuild or narrow the shipments table after accepting a long label URL.');
            $this->assertTrue(Schema::hasColumn('shipment_quotes', 'idempotency_reference'));
            $this->assertFalse(Schema::hasColumn('shipment_quotes', 'reconciliation_reference'));
            $this->assertSame(
                'JWEP-LEGACY-REFERENCE',
                DB::table('shipment_quotes')->where('order_id', $order->id)->value('idempotency_reference'),
            );
            $this->assertSame($longLabel, DB::table('shipments')->where('id', $shipmentId)->value('label_path'));

            $upgradeMigration->up();

            $this->assertFalse(Schema::hasColumn('shipment_quotes', 'idempotency_reference'));
            $this->assertTrue(Schema::hasColumn('shipment_quotes', 'reconciliation_reference'));
            $this->assertSame(
                'JWEP-LEGACY-REFERENCE',
                DB::table('shipment_quotes')->where('order_id', $order->id)->value('reconciliation_reference'),
            );
            $this->assertSame($longLabel, DB::table('shipments')->where('id', $shipmentId)->value('label_path'));
        } finally {
            $this->restoreCurrentFulfillmentSchema($upgradeMigration);
        }
    }

    public function test_webhook_receipt_schema_round_trip_is_safe_and_has_no_raw_payload_column(): void
    {
        $migrationPath = database_path('migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php');
        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $this->guardTestingSchemaMutation();

        try {
            $migration->down();

            $this->assertFalse(Schema::hasTable('shipping_webhook_receipts'));

            $migration->up();

            $this->assertTrue(Schema::hasTable('shipping_webhook_receipts'));
            $this->assertTrue(Schema::hasColumns('shipping_webhook_receipts', [
                'provider',
                'environment',
                'topic',
                'provider_uuid',
                'payload_hash',
                'shipment_id',
                'correlation_snapshot',
                'processing_state',
                'received_at',
            ]));
            $this->assertFalse(Schema::hasColumn('shipping_webhook_receipts', 'raw_payload'));
        } finally {
            $this->restoreCurrentWebhookReceiptSchema($migration);
        }
    }

    public function test_fulfillment_schema_is_restored_when_the_downgraded_body_throws(): void
    {
        $migration = require database_path('migrations/2026_08_27_000800_harden_easyparcel_fulfillment_columns.php');
        $this->guardTestingSchemaMutation();

        try {
            try {
                $migration->down();
                throw new RuntimeException('intentional fulfillment downgrade probe');
            } finally {
                $this->restoreCurrentFulfillmentSchema($migration);
            }
        } catch (RuntimeException $exception) {
            $this->assertSame('intentional fulfillment downgrade probe', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('shipment_quotes', 'idempotency_reference'));
        $this->assertTrue(Schema::hasColumn('shipment_quotes', 'reconciliation_reference'));
    }

    public function test_webhook_receipt_schema_is_restored_when_the_missing_table_body_throws(): void
    {
        $migration = require database_path('migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php');
        $this->guardTestingSchemaMutation();

        try {
            try {
                $migration->down();
                throw new RuntimeException('intentional webhook downgrade probe');
            } finally {
                $this->restoreCurrentWebhookReceiptSchema($migration);
            }
        } catch (RuntimeException $exception) {
            $this->assertSame('intentional webhook downgrade probe', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('shipping_webhook_receipts'));
        $this->assertTrue(Schema::hasColumns('shipping_webhook_receipts', [
            'provider',
            'environment',
            'topic',
            'provider_uuid',
            'payload_hash',
            'shipment_id',
            'correlation_snapshot',
            'processing_state',
            'received_at',
        ]));
    }

    private function restoreCurrentFulfillmentSchema(Migration $migration): void
    {
        $this->guardTestingSchemaMutation();

        if (! Schema::hasTable('shipment_quotes')) {
            throw new LogicException('The shipment_quotes table disappeared during the fulfillment migration test.');
        }

        $hasLegacyColumn = Schema::hasColumn('shipment_quotes', 'idempotency_reference');
        $hasCurrentColumn = Schema::hasColumn('shipment_quotes', 'reconciliation_reference');

        if ($hasCurrentColumn && ! $hasLegacyColumn) {
            return;
        }

        if ($hasLegacyColumn && ! $hasCurrentColumn) {
            $migration->up();

            return;
        }

        throw new LogicException('The shipment_quotes reference columns are in an unknown migration-test state.');
    }

    private function restoreCurrentWebhookReceiptSchema(Migration $migration): void
    {
        $this->guardTestingSchemaMutation();

        if (! Schema::hasTable('shipping_webhook_receipts')) {
            $migration->up();
        }

        if (! Schema::hasColumns('shipping_webhook_receipts', [
            'provider',
            'environment',
            'topic',
            'provider_uuid',
            'payload_hash',
            'shipment_id',
            'correlation_snapshot',
            'processing_state',
            'received_at',
        ])) {
            throw new LogicException('The shipping_webhook_receipts table is not in its current schema.');
        }
    }

    private function guardTestingSchemaMutation(): void
    {
        if (config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'jw_ecommerce_testing') {
            throw new LogicException('EasyParcel migration tests require the jw_ecommerce_testing database.');
        }
    }
}
