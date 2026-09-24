<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\FacilityLedgerMigration;
use App\Services\Inventory\StoreManagement\FacilityMigrationPreview;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityLedgerMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.multiwarehouse_migration_enabled' => true]);
    }

    public function test_reviewed_migration_moves_only_the_store_balance_once_and_conserves_physical_units(): void
    {
        $c = $this->context();
        $migration = app(FacilityLedgerMigration::class);
        $review = $migration->review($c['actor'], $c['store_id'], $c['variant_id'],
            ['WH-A' => 10, 'WH-B' => 15], 'First verified facility allocation');
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_pools')->where('kind', 'facility')->count());

        $receipt = $migration->confirm($c['actor'], $review['id'], 'facility-migrate-1');
        $repeat = $migration->confirm($c['actor'], $review['id'], 'facility-migrate-1');
        $this->assertSame($receipt, $repeat);
        $this->assertSame(25, array_sum(array_column($receipt['facility_balances'], 'physical')));
        $this->assertSame(0, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('physical_stock'));
        $this->assertSame(25, (int) DB::table('inventory_balances as balance')
            ->join('inventory_pools as pool', 'pool.id', '=', 'balance.inventory_pool_id')
            ->where('pool.kind', 'facility')->where('balance.product_variant_id', $c['variant_id'])
            ->sum('balance.physical_stock'));
        $this->assertSame(4, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('version'));
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $c['website_id'])->value('physical_stock'));
        $this->assertDatabaseHas('marketplace_store_facility_scans', [
            'marketplace_store_id' => $c['store_id'], 'ledger_migration_status' => 'pending',
        ]);
    }

    public function test_stale_source_version_rejects_confirmation_without_new_facility_balances(): void
    {
        $c = $this->context();
        $migration = app(FacilityLedgerMigration::class);
        $review = $migration->review($c['actor'], $c['store_id'], $c['variant_id'],
            ['WH-A' => 10, 'WH-B' => 15], 'First verified facility allocation');
        DB::table('inventory_balances')->where('id', $c['legacy_id'])->increment('version');

        try {
            $migration->confirm($c['actor'], $review['id'], 'facility-migrate-stale');
            $this->fail('Expected stale migration review to be rejected.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
        }
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_pools')->where('kind', 'facility')->count());
    }

    public function test_migration_rejects_an_existing_legacy_order_activation_even_without_reserved_units(): void
    {
        $c = $this->context();
        DB::table('inventory_order_activations')->insert([
            'channel_key' => 'marketplace:'.$c['store_id'],
            'product_variant_id' => $c['variant_id'],
            'inventory_balance_id' => $c['legacy_id'],
            'actor_id' => $c['actor']->id,
            'reason' => 'Existing live order route',
            'orders_created_after' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        $preview = app(FacilityMigrationPreview::class)->preview(
            $c['store_id'], $c['variant_id'], ['WH-A' => 10, 'WH-B' => 15]
        );

        $this->assertFalse($preview['ready']);
        $this->assertContains('legacy_order_activation_active', $preview['blockers']);
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_pools')->where('kind', 'facility')->count());
    }

    public function test_confirmation_rechecks_for_legacy_activation_created_after_review(): void
    {
        $c = $this->context();
        $migration = app(FacilityLedgerMigration::class);
        $review = $migration->review($c['actor'], $c['store_id'], $c['variant_id'],
            ['WH-A' => 10, 'WH-B' => 15], 'Move counted units to facilities');
        DB::table('inventory_order_activations')->insert([
            'channel_key' => 'marketplace:'.$c['store_id'],
            'product_variant_id' => $c['variant_id'],
            'inventory_balance_id' => $c['legacy_id'],
            'actor_id' => $c['actor']->id,
            'reason' => 'Activated after migration review',
            'orders_created_after' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        try {
            $migration->confirm($c['actor'], $review['id'], 'facility-migrate-late-activation');
            $this->fail('An activated legacy order route must block migration.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame('migration_conditions_changed',
                $exception->getResponse()->getData(true)['error']['code']);
        }
        $this->assertSame(25, (int) DB::table('inventory_balances')->where('id', $c['legacy_id'])->value('physical_stock'));
        $this->assertSame(0, DB::table('inventory_pools')->where('kind', 'facility')->count());
    }

    private function context(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'LEDGER-MIGRATE');
        $variant = $fixture->sku()['variant'];
        $legacy = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        $website = app(AccountResolver::class)->balance($variant->id, 'website');
        foreach ([$legacy['id'], $website['id']] as $balanceId) {
            DB::table('inventory_balances')->where('id', $balanceId)->update([
                'physical_stock' => 25, 'reserved_stock' => 0, 'safety_stock' => 0,
                'counted_at' => now(), 'version' => 3,
            ]);
        }
        app(FacilityRegistry::class)->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'], ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => '100', 'external_variant_id' => '200',
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listingId,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['A', 'B'] as $suffix) {
            $warehouseId = DB::table('inventory_warehouses')->insertGetId([
                'code' => 'JW-MIGRATE-'.$suffix, 'name' => 'Physical '.$suffix,
                'is_active' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', 'WH-'.$suffix)->update(['inventory_warehouse_id' => $warehouseId]);
        }

        return ['actor' => User::factory()->create(['is_admin' => true, 'is_super_admin' => true]),
            'store_id' => (int) $store->id, 'variant_id' => (int) $variant->id,
            'legacy_id' => (int) $legacy['id'], 'website_id' => (int) $website['id']];
    }
}
