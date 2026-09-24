<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class MarketplaceFacilityRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_scans_classify_zero_one_and_multiple_facilities_without_changing_stock(): void
    {
        $store = (new StoreInventoryFixture)->store('lazada', 'FACILITY');
        $registry = app(FacilityRegistry::class);

        $this->assertSame('no_warehouse', $registry->confirm((int) $store->id, [])['topology']);
        $this->assertSame('single_warehouse', $registry->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
        ])['topology']);
        $multi = $registry->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        $this->assertSame('multi_warehouse', $multi['topology']);
        $this->assertSame('pending', $multi['ledger_migration_status']);
        $this->assertFalse($multi['stock_operations_ready']);
        $this->assertCount(2, $multi['facilities']);
        $this->assertSame($multi['revision'], $registry->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ])['revision']);
        $reduced = $registry->confirm((int) $store->id, [['provider_id' => 'WH-A', 'name' => 'A']]);
        $this->assertSame('single_warehouse', $reduced['topology']);
        $this->assertCount(1, $reduced['facilities']);
        $this->assertSame($reduced['revision'], $registry->confirm((int) $store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
        ])['revision']);
        $this->assertSame(0, DB::table('inventory_balances')->count());
    }

    public function test_incomplete_or_duplicate_scan_cannot_replace_last_confirmed_list(): void
    {
        $store = (new StoreInventoryFixture)->store('shopee', 'FACILITY');
        $registry = app(FacilityRegistry::class);
        $registry->confirm((int) $store->id, [['provider_id' => 'LOC-1', 'name' => 'One']]);

        try {
            $registry->confirm((int) $store->id, [['provider_id' => 'LOC-2', 'name' => 'Two']], false);
            $this->fail('Incomplete scan should fail.');
        } catch (InvalidArgumentException) {
            $this->assertSame('verification_pending', $registry->read((int) $store->id)['status']);
        }
        $this->assertSame('LOC-1', $registry->read((int) $store->id)['facilities'][0]['provider_id']);

        $this->expectException(InvalidArgumentException::class);
        $registry->confirm((int) $store->id, [
            ['provider_id' => 'LOC-2', 'name' => 'Two'],
            ['provider_id' => 'LOC-2', 'name' => 'Duplicate'],
        ]);
    }

    public function test_admin_can_read_pending_state_and_last_confirmed_facilities_without_credentials(): void
    {
        $store = (new StoreInventoryFixture)->store('lazada', 'READ');
        $url = '/admin/api/inventory/store-management/stores/'.$store->id.'/facilities';
        $this->getJson($url)->assertUnauthorized();
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('status', 'verification_pending')->assertJsonPath('facilities', [])
            ->assertJsonPath('stock_operations_ready', false);

        $registry = app(FacilityRegistry::class);
        $registry->confirm((int) $store->id, [['provider_id' => 'MY-WH-1', 'name' => 'One']]);
        $registry->fail((int) $store->id, 'provider_unavailable');
        $response = $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('status', 'verification_pending')
            ->assertJsonPath('topology', 'single_warehouse')
            ->assertJsonPath('facilities.0.provider_id', 'MY-WH-1');
        $this->assertArrayNotHasKey('access_token', $response->json());
        $this->actingAs($admin)->getJson('/admin/api/inventory/store-management/stores/999999/facilities')->assertNotFound();
    }

    public function test_only_super_admin_can_request_provider_discovery(): void
    {
        config(['marketplace.multiwarehouse_discovery_enabled' => true]);
        $store = (new StoreInventoryFixture)->store('shopee', 'REFRESH');
        $url = '/admin/api/inventory/store-management/stores/'.$store->id.'/facilities/refresh';
        $this->postJson($url)->assertUnauthorized();
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($url)->assertForbidden();
        $super = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->actingAs($super)->postJson($url)->assertOk()
            ->assertJsonPath('status', 'verification_pending')
            ->assertJsonPath('error_code', 'complete_location_api_unverified');
        config(['marketplace.multiwarehouse_discovery_enabled' => false]);
        $this->actingAs($super)->postJson($url)->assertNotFound();
    }
}
