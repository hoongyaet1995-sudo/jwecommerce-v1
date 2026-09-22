<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use App\Services\Inventory\StoreManagement\InventoryThumbnail;
use App\Services\Inventory\StoreManagement\OverviewReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class StoreInventoryReadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function map(int $storeId, int $variantId): void
    {
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $storeId, 'external_product_id' => 'READ-'.$storeId,
            'external_variant_id' => 'READ-V-'.$storeId, 'observed_data' => json_encode(['stock' => 0]),
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $storeId, 'listing_variant_id' => $listing, 'product_variant_id' => $variantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_shared_overview_counts_one_ledger_balance_once_across_all_store_accounts(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $fixture->store('shopee', 'A');
        $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 20, 'reserved_stock' => 4, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/admin/api/inventory/store-management/overview')->assertOk();
        $response->assertJsonPath('totals.known.physical', 20)
            ->assertJsonPath('totals.known.reserved', 4)
            ->assertJsonPath('totals.known.available', 16)
            ->assertJsonPath('totals.unknown_accounts', 0)
            ->assertJsonPath('mode', 'shared');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_independent_overview_sums_known_balances_and_keeps_unknown_distinct_from_zero(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $first = $fixture->store('shopee', 'A');
        $second = $fixture->store('shopee', 'B');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $resolver = app(AccountResolver::class);
        $website = $resolver->balance($sku['variant']->id, 'website');
        $a = $resolver->balance($sku['variant']->id, 'marketplace:'.$first->id);
        $b = $resolver->balance($sku['variant']->id, 'marketplace:'.$second->id);
        $this->map($first->id, $sku['variant']->id);
        $this->map($second->id, $sku['variant']->id);
        DB::table('inventory_balances')->where('id', $website['id'])->update([
            'physical_stock' => 20, 'reserved_stock' => 4, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        DB::table('inventory_balances')->where('id', $a['id'])->update([
            'physical_stock' => 0, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/admin/api/inventory/store-management/overview?platform=shopee')->assertOk();
        $response->assertJsonPath('totals.known.physical', 0)
            ->assertJsonPath('totals.known.reserved', 0)
            ->assertJsonPath('totals.known.available', 0)
            ->assertJsonPath('totals.unknown_accounts', 1);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame([$first->id, $second->id], array_column($response->json('stores'), 'id'));
        $this->assertArrayNotHasKey('external_shop_id', $response->json('stores.0'));
        $this->assertArrayNotHasKey('access_token', $response->json('stores.0'));
    }

    public function test_independent_overview_does_not_reopen_first_count_for_unmapped_store_accounts(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $unmapped = $fixture->store('lazada', 'UNMAPPED');
        DB::table('products')->where('id', $sku['product']->id)->update(['status' => 'active']);
        $resolver = app(AccountResolver::class);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $legacyShared = $resolver->balance($sku['variant']->id, 'website');
        $this->assertSame('shared', $legacyShared['account_key']);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $website = $resolver->balance($sku['variant']->id, 'website');
        $resolver->balance($sku['variant']->id, 'marketplace:'.$unmapped->id);
        DB::table('inventory_balances')->where('id', $website['id'])->update([
            'physical_stock' => 22, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/admin/api/inventory/store-management/overview')->assertOk();
        $response->assertJsonPath('totals.unknown_accounts', 0)
            ->assertJsonPath('mode_catalog.summary.blocked', 0)
            ->assertJsonPath('mode_catalog.items.0.classification', 'ready_to_allocate');
        $rows = collect($response->json('data'))->keyBy('account_key');
        $this->assertTrue($rows['website']['allocation_eligible']);
        $this->assertFalse($rows['marketplace:'.$unmapped->id]['allocation_eligible']);
    }

    public function test_history_is_paginated_and_operation_detail_contains_sanitized_saved_receipt(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 8, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $actor = $this->admin();
        $actor->permissions()->create(['permission' => 'inventory.edit']);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, [
            'kind' => 'count', 'balance_id' => $balance['id'], 'physical_count' => 9, 'reason' => 'Cycle count',
        ])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'history-count-1'])->assertOk()->json();

        $history = $this->getJson('/admin/api/inventory/store-management/history?per_page=1')->assertOk();
        $history->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $receipt['operation_id']);
        $detail = $this->getJson('/admin/api/inventory/store-management/operations/'.$receipt['operation_id'])->assertOk();
        $detail->assertJsonPath('operation_id', $receipt['operation_id'])
            ->assertJsonMissingPath('provider_receipt.access_token')
            ->assertJsonMissingPath('payload');
    }

    public function test_transfer_operation_detail_exposes_reviewed_quantity_without_raw_payload(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('shopee', 'TRANSFER-DETAIL');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $source = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        $destination = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        foreach ([[$source, 8], [$destination, 1]] as [$balance, $physical]) {
            DB::table('inventory_balances')->where('id', $balance['id'])->update([
                'physical_stock' => $physical, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
            ]);
        }
        $actor = $this->admin();
        $actor->permissions()->create(['permission' => 'inventory.edit']);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, [
            'kind' => 'transfer', 'source_balance_id' => $source['id'], 'destination_balance_id' => $destination['id'],
            'quantity' => 2, 'reason' => 'History recovery',
        ])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'history-transfer-detail'])->assertOk()->json();

        $this->getJson('/admin/api/inventory/store-management/operations/'.$receipt['operation_id'])
            ->assertOk()
            ->assertJsonPath('transfer_quantity', 2)
            ->assertJsonMissingPath('payload');

        $this->getJson('/admin/api/inventory/store-management/history?kind=transfer')
            ->assertOk()
            ->assertJsonPath('data.0.result.transfer.master_sku', $sku['variant']->sku)
            ->assertJsonPath('data.0.result.transfer.product_name', $sku['product']->title)
            ->assertJsonPath('data.0.result.transfer.variant_name', $sku['variant']->title)
            ->assertJsonPath('data.0.result.transfer.source_store.id', 'website')
            ->assertJsonPath('data.0.result.transfer.source_store.name', 'JW Ecom Storefront')
            ->assertJsonPath('data.0.result.transfer.destination_store.id', (string) $store->id)
            ->assertJsonPath('data.0.result.transfer.destination_store.name', $store->shop_name)
            ->assertJsonPath('data.0.result.transfer.destination_store.platform', 'shopee')
            ->assertJsonPath('data.0.result.transfer.quantity', 2);
    }

    public function test_history_exposes_employee_facing_inventory_activity_without_raw_review_payload(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 8, 'reserved_stock' => 1, 'safety_stock' => 0, 'counted_at' => now(),
        ]);
        $actor = $this->admin();
        $actor->permissions()->create(['permission' => 'inventory.edit']);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->actingAs($actor)->postJson($url, [
            'kind' => 'intake', 'balance_id' => $balance['id'], 'quantity' => 3,
            'reason' => 'Supplier delivery',
        ])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'history-intake-summary'])->assertOk()->json();

        $history = $this->getJson('/admin/api/inventory/store-management/history')->assertOk();
        $history->assertJsonPath('data.0.id', $receipt['operation_id'])
            ->assertJsonPath('data.0.result.activity.master_sku', $sku['variant']->sku)
            ->assertJsonPath('data.0.result.activity.product_name', $sku['product']->title)
            ->assertJsonPath('data.0.result.activity.store.name', 'JW Ecom Storefront')
            ->assertJsonPath('data.0.result.activity.previous_stock', 8)
            ->assertJsonPath('data.0.result.activity.resulting_stock', 11)
            ->assertJsonPath('data.0.result.activity.quantity_change', 3)
            ->assertJsonPath('data.0.result.activity.reason', 'Supplier delivery')
            ->assertJsonMissingPath('data.0.result.payload');
    }

    public function test_history_identifies_allocation_and_platform_sync_records_for_staff(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $store = $fixture->store('shopee', 'HISTORY-SYNC');
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $website = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        $channel = app(AccountResolver::class)->balance($sku['variant']->id, 'marketplace:'.$store->id);
        $actor = $this->admin();

        $allocationReview = '00000000-0000-4000-8000-000000000101';
        $allocationOperation = '00000000-0000-4000-8000-000000000102';
        DB::table('inventory_store_reviews')->insert([
            'id' => $allocationReview, 'kind' => 'allocation', 'actor_id' => $actor->id,
            'payload' => json_encode(['variant_id' => $sku['variant']->id, 'physical_count' => 10,
                'allocations' => ['website' => 6, 'marketplace:'.$store->id => 4],
                'balance_ids' => ['website' => $website['id'], 'marketplace:'.$store->id => $channel['id']],
                'reason' => 'Initial store count']),
            'before' => json_encode(['accounts' => ['website' => ['physical' => null], 'marketplace:'.$store->id => ['physical' => null]], 'total_physical' => 0]),
            'after' => json_encode(['accounts' => ['website' => ['physical' => 6], 'marketplace:'.$store->id => ['physical' => 4]], 'total_physical' => 10]),
            'balance_versions' => json_encode([]), 'configuration_version' => 1, 'status' => 'confirmed',
            'expires_at' => now()->addHour(), 'confirmed_at' => now(), 'created_at' => now()->subMinute(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_operations')->insert([
            'id' => $allocationOperation, 'review_id' => $allocationReview, 'operation_key' => 'history-allocation',
            'kind' => 'allocation', 'actor_id' => $actor->id, 'ledger_status' => 'applied',
            'receipt' => json_encode(['operation_id' => $allocationOperation, 'balances' => []]),
            'created_at' => now()->subMinute(), 'updated_at' => now(),
        ]);

        $syncReview = '00000000-0000-4000-8000-000000000103';
        $syncOperation = '00000000-0000-4000-8000-000000000104';
        $syncSnapshot = ['store_id' => $store->id, 'variant_id' => $sku['variant']->id, 'sku' => $sku['variant']->sku,
            'quantity' => 4, 'ledger_changed' => false, 'reason' => 'Resync latest stock'];
        DB::table('inventory_store_reviews')->insert([
            'id' => $syncReview, 'kind' => 'platform_sync', 'actor_id' => $actor->id,
            'payload' => json_encode($syncSnapshot), 'before' => json_encode($syncSnapshot), 'after' => json_encode($syncSnapshot),
            'balance_versions' => json_encode([]), 'configuration_version' => 1, 'status' => 'confirmed',
            'expires_at' => now()->addHour(), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_store_operations')->insert([
            'id' => $syncOperation, 'review_id' => $syncReview, 'operation_key' => 'history-platform-sync',
            'kind' => 'platform_sync', 'actor_id' => $actor->id, 'ledger_status' => 'applied',
            'receipt' => json_encode($syncSnapshot), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = collect($this->actingAs($actor)->getJson('/admin/api/inventory/store-management/history')->assertOk()->json('data'))->keyBy('kind');
        $this->assertSame($sku['variant']->sku, $rows['allocation']['result']['activity']['master_sku']);
        $this->assertSame('Not Counted', $rows['allocation']['result']['activity']['previous_stock']);
        $this->assertSame(10, $rows['allocation']['result']['activity']['resulting_stock']);
        $this->assertSame($sku['variant']->sku, $rows['platform_sync']['result']['activity']['master_sku']);
        $this->assertSame($sku['product']->title, $rows['platform_sync']['result']['activity']['product_name']);
        $this->assertSame($store->shop_name, $rows['platform_sync']['result']['activity']['store']['name']);
        $this->assertSame(4, $rows['platform_sync']['result']['activity']['resulting_stock']);
    }

    public function test_overview_loads_sync_states_in_one_query_for_large_catalogs(): void
    {
        $fixture = new StoreInventoryFixture;
        $resolver = app(AccountResolver::class);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);

        foreach (range(1, 12) as $_) {
            $sku = $fixture->sku();
            $resolver->balance($sku['variant']->id, 'website');
        }

        $syncQueries = 0;
        DB::listen(function ($query) use (&$syncQueries): void {
            if (str_contains(strtolower($query->sql), 'inventory_store_sync_tasks')) {
                $syncQueries++;
            }
        });

        app(OverviewReader::class)->read();

        $this->assertSame(1, $syncQueries, 'Overview must batch-load sync states instead of querying once per balance.');
    }

    public function test_mode_catalog_lists_every_sku_with_explicit_allocation_eligibility(): void
    {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $fixture = new StoreInventoryFixture;
        $ready = $fixture->sku();
        $uncounted = $fixture->sku();
        $test = $fixture->sku();
        $archived = $fixture->sku();
        $unmanaged = $fixture->sku();

        DB::table('products')->where('id', $ready['product']->id)->update(['status' => 'active']);
        DB::table('products')->where('id', $uncounted['product']->id)->update(['status' => 'active']);
        DB::table('products')->where('id', $test['product']->id)->update(['status' => 'draft']);
        DB::table('product_variants')->where('id', $test['variant']->id)->update(['data_classification' => 'test']);
        DB::table('products')->where('id', $archived['product']->id)->update(['status' => 'archived']);
        DB::table('products')->where('id', $unmanaged['product']->id)->update(['status' => 'active']);

        $balance = app(AccountResolver::class)->balance($ready['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'physical_stock' => 12, 'reserved_stock' => 0, 'safety_stock' => 0, 'counted_at' => now(),
        ]);

        $store = $fixture->store('shopee', 'UNMANAGED');
        $listingId = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'P-1', 'external_variant_id' => 'V-1',
            'observed_data' => json_encode(['stock' => 3]), 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'listing_variant_id' => $listingId, 'store_id' => $store->id, 'product_variant_id' => $unmanaged['variant']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $catalog = $this->actingAs($this->admin())
            ->getJson('/admin/api/inventory/store-management/overview')
            ->assertOk()
            ->json('mode_catalog');

        $this->assertCount(5, $catalog['items']);
        $byVariant = collect($catalog['items'])->keyBy('variant_id');
        $this->assertSame('ready_to_allocate', $byVariant[$ready['variant']->id]['classification']);
        $this->assertSame(12, $byVariant[$ready['variant']->id]['physical_stock']);
        $this->assertSame(999, $byVariant[$ready['variant']->id]['catalog_stock_snapshot']);
        $this->assertSame('awaiting_first_count', $byVariant[$uncounted['variant']->id]['classification']);
        $this->assertSame('sandbox_test', $byVariant[$test['variant']->id]['classification']);
        $this->assertSame('excluded_status', $byVariant[$archived['variant']->id]['classification']);
        $this->assertSame('awaiting_first_count', $byVariant[$unmanaged['variant']->id]['classification']);
        $this->assertSame([
            'total' => 5, 'ready' => 1, 'blocked' => 2, 'excluded' => 2,
        ], $catalog['summary']);
    }

    public function test_test_store_mapping_does_not_reclassify_a_real_jw_catalog_sku_as_test(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        DB::table('products')->where('id', $sku['product']->id)->update(['status' => 'active']);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update([
            'data_classification' => 'unclassified',
        ]);
        $store = $fixture->store('shopee', 'SANDBOX');
        DB::table('marketplace_stores')->where('id', $store->id)->update([
            'data_classification' => 'test',
        ]);
        $this->map($store->id, $sku['variant']->id);

        $item = collect($this->actingAs($this->admin())
            ->getJson('/admin/api/inventory/store-management/overview')
            ->assertOk()
            ->json('mode_catalog.items'))
            ->firstWhere('variant_id', $sku['variant']->id);

        $this->assertSame('awaiting_first_count', $item['classification']);
        $this->assertSame('unclassified', $item['data_classification']);
    }

    public function test_mode_catalog_exposes_a_cached_inventory_thumbnail_for_local_catalog_media(): void
    {
        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $sku['product']->images()->create([
            'path' => '/images/catalog/solis-1.jpg',
            'alt' => 'Solis thumbnail',
            'position' => 0,
        ]);

        $item = collect($this->actingAs($this->admin())
            ->getJson('/admin/api/inventory/store-management/overview')
            ->assertOk()
            ->json('mode_catalog.items'))
            ->firstWhere('variant_id', $sku['variant']->id);

        $this->assertStringStartsWith('/admin/inventory/thumbnails/'.$sku['variant']->id.'?v=', $item['thumbnail_url']);
    }

    public function test_inventory_thumbnail_is_resized_and_cached_when_gd_is_available(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is not enabled in this PHP process.');
        }

        $fixture = new StoreInventoryFixture;
        $sku = $fixture->sku();
        $sku['product']->images()->create(['path' => '/images/catalog/solis-1.jpg', 'position' => 0]);

        $response = $this->actingAs($this->admin())
            ->get('/admin/inventory/thumbnails/'.$sku['variant']->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $size = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(160, $size[0]);
        $this->assertLessThanOrEqual(160, $size[1]);
    }

    public function test_unsplash_catalog_media_uses_a_small_cdn_thumbnail(): void
    {
        $thumbnail = app(InventoryThumbnail::class);
        $url = $thumbnail->url(9, null, null, 'https://images.unsplash.com/photo-test?w=1000&fit=crop&q=80');

        $this->assertStringContainsString('w=160', $url);
        $this->assertStringContainsString('h=160', $url);
        $this->assertStringContainsString('q=70', $url);
    }
}
