<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EasyParcelProviderMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_version_is_added_to_the_previously_released_provider_schema(): void
    {
        Schema::dropIfExists('shipping_provider_credentials');
        Schema::dropIfExists('shipping_providers');

        $createMigration = require database_path('migrations/2026_08_27_000400_create_shipping_provider_tables.php');
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

        $upgradePath = database_path('migrations/2026_08_27_000500_add_configuration_version_to_shipping_providers.php');
        $this->assertFileExists($upgradePath);

        $upgradeMigration = require $upgradePath;
        $upgradeMigration->up();

        $this->assertTrue(Schema::hasColumn('shipping_providers', 'configuration_version'));
        $this->assertSame(0, DB::table('shipping_providers')->where('provider', 'easyparcel')->value('configuration_version'));
        $this->assertDatabaseHas('shipping_providers', [
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'connection_state' => 'unverified',
        ]);
    }
}
