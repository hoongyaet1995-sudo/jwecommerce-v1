<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelProviderMigrationTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_durable_oauth_authorization_transaction_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasTable('shipping_provider_authorization_transactions'));
        $this->assertTrue(Schema::hasColumns('shipping_provider_authorization_transactions', [
            'shipping_provider_id',
            'user_id',
            'state_hash',
            'session_fingerprint',
            'configuration_version',
            'expires_at',
            'consumed_at',
        ]));
    }

    public function test_configuration_version_is_added_to_the_previously_released_provider_schema(): void
    {
        $upgradePath = database_path('migrations/2026_08_27_000500_add_configuration_version_to_shipping_providers.php');
        $this->assertFileExists($upgradePath);

        $fulfillmentUpgradeMigration = require database_path('migrations/2026_08_27_000800_harden_easyparcel_fulfillment_columns.php');
        $fulfillmentMigration = require database_path('migrations/2026_08_27_000700_create_shipment_quotes_and_extend_shipments.php');
        $authorizationMigration = require database_path('migrations/2026_08_27_000600_create_shipping_provider_authorization_transactions.php');
        $configurationVersionMigration = require $upgradePath;
        $createMigration = require database_path('migrations/2026_08_27_000400_create_shipping_provider_tables.php');
        $reconciliationMigration = require database_path('migrations/2026_08_29_000100_create_easyparcel_reconciliation_tasks_table.php');

        try {
            $reconciliationMigration->down();
            $fulfillmentUpgradeMigration->down();
            $fulfillmentMigration->down();
            $authorizationMigration->down();
            $configurationVersionMigration->down();
            $createMigration->down();
            $createMigration->up();

            $this->assertFalse(Schema::hasColumn('shipping_providers', 'configuration_version'));

            DB::table('shipping_providers')->insert([
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'enabled' => false,
                'connection_state' => 'unverified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $configurationVersionMigration->up();

            $this->assertTrue(Schema::hasColumn('shipping_providers', 'configuration_version'));
            $this->assertSame(0, DB::table('shipping_providers')->where('provider', 'easyparcel')->value('configuration_version'));
            $this->assertDatabaseHas('shipping_providers', [
                'provider' => 'easyparcel',
                'environment' => 'sandbox',
                'connection_state' => 'unverified',
            ]);
        } finally {
            if (! Schema::hasTable('shipping_providers')) {
                $createMigration->up();
            }

            if (! Schema::hasColumn('shipping_providers', 'configuration_version')) {
                $configurationVersionMigration->up();
            }

            if (! Schema::hasTable('shipping_provider_authorization_transactions')) {
                $authorizationMigration->up();
            }

            if (! Schema::hasTable('shipment_quotes')) {
                $fulfillmentMigration->up();
            }

            if (Schema::hasColumn('shipment_quotes', 'idempotency_reference')) {
                $fulfillmentUpgradeMigration->up();
            }

            if (! Schema::hasTable('easyparcel_reconciliation_tasks')) {
                $reconciliationMigration->up();
            }
        }
    }
}
