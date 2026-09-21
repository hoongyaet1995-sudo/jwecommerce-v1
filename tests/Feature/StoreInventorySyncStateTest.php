<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\StoreEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

final class StoreInventorySyncStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_and_resume_only_change_external_sync_state_and_are_idempotent(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('lazada', 'SYNC-STATE');
        app(StoreEnrollmentService::class)->enroll($store->id);
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'P1', 'external_variant_id' => 'V1',
            'observed_data' => json_encode(['status' => 'active']), 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'listing_variant_id' => $listing, 'store_id' => $store->id, 'product_variant_id' => $sku['variant']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_listing_variants')->insert([
            'store_id' => $store->id, 'external_product_id' => 'ARCHIVED', 'external_variant_id' => 'ARCHIVED',
            'observed_data' => json_encode(['status' => 'archived']), 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 8, 'reserved_stock' => 2, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $base = '/admin/api/inventory/store-management/stores/'.$store->id;

        $this->actingAs($admin)->postJson($base.'/pause-sync', ['reason' => 'Provider maintenance'])
            ->assertOk()->assertJsonPath('sync_state', 'paused');
        $this->postJson($base.'/pause-sync', ['reason' => 'Provider maintenance'])
            ->assertOk()->assertJsonPath('sync_state', 'paused');
        $review = $this->getJson($base.'/sync-readiness')->assertOk()->assertJsonPath('ready', true)->json();
        $this->postJson($base.'/resume-sync', ['reason' => 'Provider restored', 'review_token' => $review['review_token']])
            ->assertOk()->assertJsonPath('sync_state', 'active');

        $this->assertDatabaseHas('inventory_balances', ['id' => $balance['id'], 'physical_stock' => 8, 'reserved_stock' => 2]);
        $this->assertSame(1, DB::table('inventory_store_accounts')->where('marketplace_store_id', $store->id)->count());
        $this->assertSame(2, DB::table('inventory_store_operations')->whereIn('kind', ['pause_sync', 'resume_sync'])->count());
    }
}
