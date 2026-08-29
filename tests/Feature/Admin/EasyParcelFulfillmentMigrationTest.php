<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ShippingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EasyParcelFulfillmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfillment_schema_upgrade_and_safe_rollback_preserve_references_and_full_length_label(): void
    {
        $upgradePath = database_path('migrations/2026_08_27_000800_harden_easyparcel_fulfillment_columns.php');
        $this->assertFileExists($upgradePath);

        $upgradeMigration = require $upgradePath;
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
    }

    public function test_webhook_receipt_schema_round_trip_is_safe_and_has_no_raw_payload_column(): void
    {
        $migrationPath = database_path('migrations/2026_08_29_000000_create_shipping_webhook_receipts_table.php');
        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
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
    }
}
