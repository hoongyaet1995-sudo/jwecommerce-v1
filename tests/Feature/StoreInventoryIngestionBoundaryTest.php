<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\InventoryIngestionBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryIngestionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_blockers_are_scoped_to_the_exact_mapped_variant(): void
    {
        $case = $this->case();
        $other = (new StoreInventoryFixture)->sku()['variant'];
        $otherListing = $this->listing($case['store']->id, '200', '2');
        $this->map($otherListing, $case['store']->id, $other->id);
        $unrelated = $this->order($case['store']->id, 'OLD-OTHER', 1000, [['item_id' => 200, 'model_id' => 2, 'model_quantity_purchased' => 1]]);

        $boundary = app(InventoryIngestionBoundary::class);
        $this->assertSame([], $boundary->unresolvedHistoricalOrderIds($case['store']->id, $case['variant']->id, now()));

        $affected = $this->order($case['store']->id, 'OLD-MAPPED', 1001, [['item_id' => 100, 'model_id' => 1, 'model_quantity_purchased' => 2]]);
        $this->assertSame([$affected], $boundary->unresolvedHistoricalOrderIds($case['store']->id, $case['variant']->id, now()));
        $this->assertNotSame($unrelated, $affected);
    }

    public function test_boundary_creation_is_idempotent_and_preserves_the_original_cutoff(): void
    {
        $case = $this->case();
        $actor = User::factory()->create(['is_admin' => true]);
        $boundary = app(InventoryIngestionBoundary::class);

        $first = $boundary->create($case['store']->id, $case['variant']->id, $case['balance']['id'], $actor, 'Enable managed sync');
        $second = $boundary->create($case['store']->id, $case['variant']->id, $case['balance']['id'], $actor, 'Repeated request');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['orders_created_after'], $second['orders_created_after']);
        $this->assertDatabaseCount('inventory_order_activations', 1);
    }

    public function test_reconciled_pre_boundary_order_is_not_a_blocker_and_post_boundary_order_is_managed(): void
    {
        $case = $this->case();
        $actor = User::factory()->create(['is_admin' => true]);
        $boundary = app(InventoryIngestionBoundary::class);
        $oldOrder = $this->order($case['store']->id, 'OLD', 1000, [['item_id' => 100, 'model_id' => 1, 'model_quantity_purchased' => 1]]);
        DB::table('stock_allocations')->insert([
            'owner_type' => 'marketplace_order', 'owner_id' => $oldOrder, 'line_key' => '100:1',
            'product_variant_id' => $case['variant']->id, 'inventory_balance_id' => $case['balance']['id'],
            'quantity' => 1, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame([], $boundary->unresolvedHistoricalOrderIds($case['store']->id, $case['variant']->id, now()));

        $activation = $boundary->create($case['store']->id, $case['variant']->id, $case['balance']['id'], $actor, 'Enable');
        $after = \Carbon\CarbonImmutable::parse($activation['orders_created_after'])->addSecond();
        $this->assertNotNull($boundary->eligible($case['store']->id, $case['variant']->id, $after));
        $this->assertNull($boundary->eligible($case['store']->id, $case['variant']->id, $after->subSeconds(2)));
    }

    private function case(): array
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('shopee', strtoupper(bin2hex(random_bytes(3))));
        $variant = $fixture->sku()['variant'];
        $listing = $this->listing($store->id, '100', '1');
        $this->map($listing, $store->id, $variant->id);
        $balance = app(AccountResolver::class)->balance($variant->id, 'marketplace:'.$store->id);
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        return compact('store', 'variant', 'balance');
    }

    private function listing(int $storeId, string $productId, string $variantId): int
    {
        return DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $storeId, 'external_product_id' => $productId, 'external_variant_id' => $variantId,
            'observed_data' => '{}', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function map(int $listingId, int $storeId, int $variantId): void
    {
        DB::table('marketplace_variant_mappings')->insert([
            'listing_variant_id' => $listingId, 'store_id' => $storeId, 'product_variant_id' => $variantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function order(int $storeId, string $externalId, int $createdAt, array $items): int
    {
        return DB::table('marketplace_orders')->insertGetId([
            'store_id' => $storeId, 'external_order_id' => $externalId, 'platform_status' => 'READY_TO_SHIP',
            'platform_updated_at' => $createdAt, 'currency' => 'MYR', 'total_amount' => 1,
            'encrypted_payload' => Crypt::encryptString(json_encode([
                'create_time' => $createdAt, 'fulfillment_flag' => 'fulfilled_by_local_seller', 'item_list' => $items,
            ], JSON_THROW_ON_ERROR)),
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
