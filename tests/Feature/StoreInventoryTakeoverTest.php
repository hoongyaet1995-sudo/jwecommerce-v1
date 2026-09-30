<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function setupStore(string $status = 'connected'): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $f = new StoreInventoryFixture;
        $sku = $f->sku();
        $store = $f->store('lazada', 'TAKEOVER');
        DB::table('marketplace_stores')->where('id', $store->id)->update(['status' => $status]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id, 'external_product_id' => '1', 'external_variant_id' => '2', 'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['listing_variant_id' => $listing, 'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id, 'created_at' => now(), 'updated_at' => now()]);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        return compact('store', 'balance', 'admin');
    }

    public function test_takeover_and_pause_only_toggle_the_shared_pool_marker(): void
    {
        $c = $this->setupStore();
        DB::table('inventory_balances')->where('id', $c['balance']['id'])->update(['physical_stock' => 5, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now()]);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/takeover', [])
            ->assertOk()->assertJsonPath('shared_pool_takeover_enabled', true);
        $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id,
            'management_status' => 'setup_required', 'shared_pool_takeover_enabled' => true]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/pause', [])
            ->assertOk()->assertJsonPath('shared_pool_takeover_enabled', false);
        $this->assertDatabaseHas('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id,
            'management_status' => 'setup_required', 'shared_pool_takeover_enabled' => false]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance']['id'], 'physical_stock' => 5]);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 0);
    }

    public function test_pause_does_not_mutate_reservations(): void
    {
        $c = $this->setupStore();
        DB::table('inventory_balances')->where('id', $c['balance']['id'])->update(['physical_stock' => 5, 'reserved_stock' => 2, 'safety_stock' => 0, 'counted_at' => now()]);
        $this->actingAs($c['admin'])->postJson('/admin/api/inventory/store-management/stores/'.$c['store']->id.'/pause')->assertOk();
        $this->assertDatabaseHas('inventory_balances', ['id' => $c['balance']['id'], 'reserved_stock' => 2]);
    }

    public function test_takeover_requires_shared_mode_connected_store_and_super_admin(): void
    {
        $c = $this->setupStore('disconnected');
        $url = '/admin/api/inventory/store-management/stores/'.$c['store']->id.'/takeover';
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        DB::table('marketplace_stores')->where('id', $c['store']->id)->update(['status' => 'connected']);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $this->actingAs($c['admin'])->postJson($url)->assertUnprocessable();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($staff)->postJson($url)->assertForbidden();
        $this->assertDatabaseMissing('inventory_store_accounts', ['marketplace_store_id' => $c['store']->id]);
    }
}
