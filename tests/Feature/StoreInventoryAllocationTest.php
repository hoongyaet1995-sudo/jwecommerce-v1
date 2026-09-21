<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->permissions()->create(['permission' => 'inventory.edit']);

        return $user;
    }

    private function map(int $storeId, int $variantId): void
    {
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $storeId, 'external_product_id' => 'P-'.$storeId,
            'external_variant_id' => 'V-'.$storeId, 'observed_data' => json_encode(['stock' => 99]),
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $storeId, 'listing_variant_id' => $listing,
            'product_variant_id' => $variantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_independent_allocation_is_reviewed_and_atomically_initializes_every_store_account(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores', 'version' => 4]);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $shopee = $fixture->store('shopee', 'S1');
        $lazada = $fixture->store('lazada', 'L1');
        $this->map($shopee->id, $sku['variant']->id);
        $this->map($lazada->id, $sku['variant']->id);
        $actor = $this->editor();

        $review = $this->actingAs($actor)->postJson('/admin/api/inventory/store-management/allocations', [
            'variant_id' => $sku['variant']->id, 'physical_count' => 22,
            'allocations' => ['website' => 8, 'marketplace:'.$shopee->id => 7, 'marketplace:'.$lazada->id => 7],
            'reason' => 'Initial independent allocation',
        ])->assertCreated()->assertJsonPath('after.total_physical', 22)->json();

        $receipt = $this->postJson('/admin/api/inventory/store-management/allocations/'.$review['id'].'/confirm', [
            'operation_key' => 'allocation-1',
        ])->assertOk()->assertJsonPath('ledger_status', 'applied')->json();

        $this->assertCount(3, $receipt['balances']);
        $this->assertSame(22, collect($receipt['balances'])->sum('physical'));
        $this->assertDatabaseHas('inventory_store_operations', ['id' => $receipt['operation_id'], 'kind' => 'allocation']);
    }

    public function test_allocation_rejects_mismatch_but_allows_unmapped_stores_to_remain_outside_the_ledger(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $mapped = $fixture->store('shopee', 'MAPPED');
        $unmapped = $fixture->store('lazada', 'UNMAPPED');
        $this->map($mapped->id, $sku['variant']->id);
        $actor = $this->editor();
        $url = '/admin/api/inventory/store-management/allocations';

        $this->actingAs($actor)->postJson($url, [
            'variant_id' => $sku['variant']->id, 'physical_count' => 10,
            'allocations' => ['website' => 5, 'marketplace:'.$mapped->id => 4], 'reason' => 'Mismatch',
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations');

        $review = $this->postJson($url, [
            'variant_id' => $sku['variant']->id, 'physical_count' => 10,
            'allocations' => ['website' => 5, 'marketplace:'.$mapped->id => 5], 'reason' => 'Unmapped blocker',
        ])->assertCreated()->json();

        $this->postJson($url, [
            'variant_id' => $sku['variant']->id, 'physical_count' => 10,
            'allocations' => ['website' => 10, 'marketplace:'.$mapped->id => 0, 'marketplace:'.$unmapped->id => 0],
            'reason' => 'Unmapped account must remain excluded',
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations');

        $this->assertDatabaseHas('inventory_store_reviews', ['id' => $review['id'], 'status' => 'pending']);
    }
}
