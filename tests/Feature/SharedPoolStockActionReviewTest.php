<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\StoreManagement\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

final class SharedPoolStockActionReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Queue::fake();
    }

    public function test_shared_actions_reject_legacy_configuration_without_mutation(): void
    {
        [$variantId, $balanceId] = $this->setupStock();
        config(['inventory.current_stock_only' => false]);
        DB::table('inventory_balances')->where('id', $balanceId)->update(['physical_stock' => 50]);
        foreach (['intake' => ['quantity' => 10], 'correction' => ['confirmed_total' => 47]] as $kind => $quantity) {
            $this->postJson('/admin/api/inventory/store-management/reviews', [
                'kind' => $kind, 'variant_id' => $variantId, 'channel_key' => 'website', 'reason' => 'Review', ...$quantity,
            ])->assertUnprocessable()->assertJsonValidationErrors('kind');
        }
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
    }

    public function test_independent_first_count_keeps_explicit_verification(): void
    {
        [$variantId] = $this->setupStock();
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'independent_stores']);
        $url = '/admin/api/inventory/store-management/reviews';
        $review = $this->postJson($url, ['kind' => 'count', 'variant_id' => $variantId, 'channel_key' => 'website',
            'physical_count' => 7, 'reason' => 'Independent first count'])->assertCreated()->json();
        $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => 'independent-count'])
            ->assertOk()->json();
        $this->assertDatabaseHas('product_variants', ['id' => $variantId,
            'first_physical_verification_operation' => $receipt['operation_id']]);
    }

    private function setupStock(): array
    {
        config(['inventory.current_stock_only' => true]);
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        $sku = (new StoreInventoryFixture)->sku();
        $balance = app(AccountResolver::class)->balance($sku['variant']->id, 'website');
        DB::table('inventory_balances')->where('id', $balance['id'])->update([
            'current_stock' => 50, 'counted_at' => now(), 'version' => 1,
        ]);
        $actor = User::factory()->create(['is_admin' => true]);
        $actor->permissions()->create(['permission' => 'inventory.edit']);
        $this->actingAs($actor);

        return [$sku['variant']->id, $balance['id']];
    }

    public function test_shared_intake_and_absolute_audit_use_one_ledger_and_review_versions(): void
    {
        [$variantId, $balanceId] = $this->setupStock();
        $url = '/admin/api/inventory/store-management/reviews';
        $intake = $this->postJson($url, [
            'kind' => 'intake', 'variant_id' => $variantId, 'channel_key' => 'website',
            'quantity' => 10, 'reason' => 'Supplier receipt', 'reference' => 'PO-123',
        ])->assertCreated()->assertJsonPath('after.current_stock', 60)->json();
        $saved = $this->postJson($url.'/'.$intake['id'].'/confirm', ['operation_key' => 'shared-intake-1'])
            ->assertOk()->assertJsonPath('balances.0.current_stock', 60)->json();
        $this->assertSame($saved, $this->postJson($url.'/'.$intake['id'].'/confirm',
            ['operation_key' => 'shared-intake-1'])->assertOk()->json());
        $this->assertDatabaseHas('inventory_current_stock_movements', [
            'inventory_balance_id' => $balanceId, 'delta' => 10, 'current_after' => 60,
            'reason' => 'Actual Stock Intake: Supplier receipt [PO-123]',
        ]);

        $audit = $this->postJson($url, [
            'kind' => 'correction', 'variant_id' => $variantId, 'channel_key' => 'website',
            'confirmed_total' => 47, 'reason' => 'Physical recount',
        ])->assertCreated()->assertJsonPath('after.current_stock', 47)->json();
        $this->postJson($url.'/'.$audit['id'].'/confirm', ['operation_key' => 'shared-audit-1'])
            ->assertOk()->assertJsonPath('balances.0.current_stock', 47);
        $this->assertDatabaseHas('inventory_current_stock_movements', [
            'inventory_balance_id' => $balanceId, 'delta' => -13, 'current_after' => 47,
        ]);

        $stale = $this->postJson($url, [
            'kind' => 'correction', 'variant_id' => $variantId, 'channel_key' => 'website',
            'confirmed_total' => 0, 'reason' => 'Empty shelf',
        ])->assertCreated()->json();
        DB::table('inventory_balances')->where('id', $balanceId)->increment('version');
        $this->postJson($url.'/'.$stale['id'].'/confirm', ['operation_key' => 'shared-stale'])
            ->assertConflict()->assertJsonPath('error.code', 'balance_changed');
    }

    public function test_shared_audit_rejects_delta_and_unchanged_total(): void
    {
        [$variantId] = $this->setupStock();
        $url = '/admin/api/inventory/store-management/reviews';
        $this->postJson($url, ['kind' => 'correction', 'variant_id' => $variantId,
            'channel_key' => 'website', 'quantity_delta' => -2, 'reason' => 'Recount'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed_total');
        $this->postJson($url, ['kind' => 'correction', 'variant_id' => $variantId,
            'channel_key' => 'website', 'confirmed_total' => 50, 'reason' => 'Recount'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed_total');
    }

    public function test_uncounted_actions_are_rejected_and_audit_replay_is_idempotent(): void
    {
        [$variantId, $balanceId] = $this->setupStock();
        $url = '/admin/api/inventory/store-management/reviews';
        $audit = $this->postJson($url, ['kind' => 'correction', 'variant_id' => $variantId,
            'channel_key' => 'website', 'confirmed_total' => 0, 'reason' => 'Empty shelf'])->assertCreated()->json();
        DB::table('inventory_balances')->where('id', $balanceId)->update(['current_stock' => null, 'counted_at' => null]);
        foreach (['intake' => ['quantity' => 10], 'correction' => ['confirmed_total' => 47]] as $kind => $quantity) {
            $this->postJson($url, ['kind' => $kind, 'variant_id' => $variantId,
                'channel_key' => 'website', 'reason' => 'Uncounted', ...$quantity])->assertUnprocessable();
        }
        $this->postJson($url.'/'.$audit['id'].'/confirm', ['operation_key' => 'audit-zero'])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_current_stock_movements', 0);
        DB::table('inventory_balances')->where('id', $balanceId)->update(['current_stock' => 50, 'counted_at' => now()]);
        $receipt = $this->postJson($url.'/'.$audit['id'].'/confirm', ['operation_key' => 'audit-zero'])->assertOk()->json();
        $this->assertSame($receipt, $this->postJson($url.'/'.$audit['id'].'/confirm', ['operation_key' => 'audit-zero'])->assertOk()->json());
        $this->postJson($url.'/'.$audit['id'].'/confirm', ['operation_key' => 'different-key'])->assertConflict();
        $this->assertDatabaseCount('inventory_current_stock_movements', 1);
    }

    public function test_intake_and_audit_keep_one_movement_when_destinations_pause_or_fail(): void
    {
        [$variantId, $balanceId] = $this->setupStock();
        $store = (new StoreInventoryFixture)->store('lazada', 'SHARED-ACTIONS');
        DB::table('inventory_store_accounts')->insert(['account_key' => 'marketplace:'.$store->id,
            'marketplace_store_id' => $store->id, 'management_status' => 'managed',
            'shared_pool_takeover_enabled' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $listing = DB::table('marketplace_listing_variants')->insertGetId(['store_id' => $store->id,
            'external_product_id' => '100', 'external_variant_id' => '200', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_variant_mappings')->insert(['store_id' => $store->id,
            'listing_variant_id' => $listing, 'product_variant_id' => $variantId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('marketplace_store_tokens')->insert(['store_id' => $store->id, 'access_token' => 'fake',
            'access_expires_at' => now()->addHour(), 'application_id' => 'test', 'credential_source' => 'oauth',
            'created_at' => now(), 'updated_at' => now()]);
        config(['marketplace.stock_sync_operations_enabled' => true, 'marketplace.stock_sync_platforms.lazada' => true,
            'marketplace.stock_sync_canary_enforced' => true,
            'marketplace.stock_sync_canary_identities' => ["lazada:{$store->id}:{$listing}"]]);
        DB::table('marketplace_store_facility_scans')->insert(['marketplace_store_id' => $store->id,
            'status' => 'verified', 'topology' => 'multi_warehouse', 'created_at' => now(), 'updated_at' => now()]);
        $facilityIds = [];
        foreach ([1, 2] as $i) {
            $facilityIds[] = DB::table('marketplace_store_facilities')->insertGetId(['marketplace_store_id' => $store->id,
                'provider_facility_id' => 'WH-'.$i, 'name' => 'Warehouse '.$i, 'is_active' => true,
                'shared_pool_takeover_enabled' => true, 'observed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $publisher = new class implements \App\Contracts\StoreInventoryPublisher {
            public int $calls = 0;
            public function publish(object $store, object $listing, int $quantity, string $eventKey): array
            {
                $this->calls++;
                throw new \RuntimeException('Simulated rejection');
            }
        };
        $url = '/admin/api/inventory/store-management/reviews';
        foreach (['intake' => ['quantity' => 10], 'correction' => ['confirmed_total' => 47]] as $kind => $quantity) {
            DB::table('marketplace_store_facilities')->whereIn('id', $facilityIds)->update(['shared_pool_takeover_enabled' => true]);
            $review = $this->postJson($url, ['kind' => $kind, 'variant_id' => $variantId,
                'channel_key' => 'website', 'reason' => 'Isolated sync test', ...$quantity])->assertCreated()->json();
            $receipt = $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => $kind.'-sync'])->assertOk()->json();
            $stages = $receipt['external_stages'];
            $this->assertCount(2, $stages);
            $this->assertSame($kind === 'intake' ? [60, 60] : [47, 47], array_column($stages, 'target_quantity'));
            DB::table('marketplace_store_facilities')->where('id', $stages[0]['facility_id'])->update(['shared_pool_takeover_enabled' => false]);
            (new \App\Jobs\ProcessStoreInventorySync($stages[0]['id']))->handle($publisher);
            $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $stages[0]['id'], 'status' => 'superseded']);
            (new \App\Jobs\ProcessStoreInventorySync($stages[1]['id']))->handle($publisher);
            $this->assertDatabaseHas('inventory_store_sync_tasks', ['id' => $stages[1]['id'], 'status' => 'failed']);
            $this->postJson($url.'/'.$review['id'].'/confirm', ['operation_key' => $kind.'-sync'])->assertOk();
            $this->assertSame($kind === 'intake' ? 60 : 47, (int) DB::table('inventory_balances')->where('id', $balanceId)->value('current_stock'));
        }
        $this->assertSame(2, $publisher->calls);
        $this->assertDatabaseCount('inventory_current_stock_movements', 2);
        $this->assertDatabaseCount('inventory_store_sync_tasks', 4);
    }
}
