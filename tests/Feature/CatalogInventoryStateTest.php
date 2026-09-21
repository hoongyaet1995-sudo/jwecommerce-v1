<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Inventory\CatalogInventoryState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class CatalogInventoryStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sku_without_an_active_balance_is_uninitialized_and_editable(): void
    {
        $sku = (new StoreInventoryFixture)->sku();

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertFalse($state['initialized']);
        $this->assertFalse($state['verified']);
        $this->assertTrue($state['editable']);
        $this->assertNull($state['physical']);
        $this->assertFalse($state['repair_required']);
    }

    public function test_initialized_zero_is_editable_until_explicitly_verified(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $balanceId = $this->balance($sku['variant']->id, 0, null);
        DB::table('product_variants')->where('id', $sku['variant']->id)->update([
            'inventory_initialized_at' => now(),
        ]);

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertTrue($state['initialized']);
        $this->assertFalse($state['verified']);
        $this->assertTrue($state['editable']);
        $this->assertSame(0, $state['physical']);
        $this->assertSame($balanceId, $state['balance_id']);
    }

    public function test_explicitly_verified_zero_is_permanently_read_only(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $this->balance($sku['variant']->id, 0, now());
        DB::table('product_variants')->where('id', $sku['variant']->id)->update([
            'inventory_initialized_at' => now(),
            'first_physical_verified_at' => now(),
            'first_physical_verification_operation' => 'count-zero',
        ]);

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertTrue($state['verified']);
        $this->assertFalse($state['editable']);
        $this->assertSame(0, $state['physical']);
    }

    public function test_generated_counted_timestamp_without_count_evidence_is_ambiguous_and_locked(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $this->balance($sku['variant']->id, 9, now());

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertFalse($state['verified']);
        $this->assertFalse($state['editable']);
        $this->assertTrue($state['repair_required']);
    }

    public function test_confirmed_count_is_legacy_verification_evidence(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $balanceId = $this->balance($sku['variant']->id, 7, now());
        $this->confirmedOperation($balanceId, 'count');

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertTrue($state['verified']);
        $this->assertFalse($state['editable']);
        $this->assertFalse($state['repair_required']);
    }

    public function test_inactive_pool_is_excluded_from_shared_physical_total(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $this->balance($sku['variant']->id, 12, null, 'shared', 'shared');
        $this->balance($sku['variant']->id, 90, now(), 'store:website', 'store');
        DB::table('product_variants')->where('id', $sku['variant']->id)->update([
            'inventory_initialized_at' => now(),
        ]);

        $state = app(CatalogInventoryState::class)->snapshot($sku['variant']->id);

        $this->assertSame(12, $state['physical']);
        $this->assertFalse($state['repair_required']);
    }

    public function test_ownership_report_does_not_mutate_inventory(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $this->balance($sku['variant']->id, 4, now());
        $before = DB::table('inventory_balances')->orderBy('id')->get()->toJson();

        $this->artisan('inventory:catalog-ownership-report')->assertSuccessful();

        $this->assertSame($before, DB::table('inventory_balances')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('product_variants', [
            'id' => $sku['variant']->id,
            'first_physical_verified_at' => null,
        ]);
    }

    public function test_mark_verified_is_idempotent_and_preserves_first_operation(): void
    {
        $sku = (new StoreInventoryFixture)->sku();
        $this->balance($sku['variant']->id, 0, null);
        $states = app(CatalogInventoryState::class);

        $states->markVerified($sku['variant']->id, 'first-count');
        $states->markVerified($sku['variant']->id, 'replayed-count');

        $this->assertTrue($states->snapshot($sku['variant']->id)['verified']);
        $this->assertDatabaseHas('product_variants', [
            'id' => $sku['variant']->id,
            'first_physical_verification_operation' => 'first-count',
        ]);
    }

    private function balance(
        int $variantId,
        int $physical,
        mixed $countedAt,
        string $poolKey = 'shared',
        string $kind = 'shared',
    ): int {
        DB::table('inventory_configuration')->where('id', 1)->update(['operating_mode' => 'shared_pool']);
        DB::table('inventory_pools')->insertOrIgnore([
            'pool_key' => $poolKey,
            'kind' => $kind,
            'warehouse_id' => null,
            'marketplace_store_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $poolId = DB::table('inventory_pools')->where('pool_key', $poolKey)->value('id');

        return DB::table('inventory_balances')->insertGetId([
            'inventory_pool_id' => $poolId,
            'product_variant_id' => $variantId,
            'physical_stock' => $physical,
            'reserved_stock' => 0,
            'safety_stock' => 0,
            'counted_at' => $countedAt,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function confirmedOperation(int $balanceId, string $kind): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $reviewId = (string) Str::uuid();
        DB::table('inventory_store_reviews')->insert([
            'id' => $reviewId,
            'kind' => $kind,
            'actor_id' => $actor->id,
            'configuration_version' => 0,
            'balance_versions' => json_encode([(string) $balanceId => 0]),
            'payload' => json_encode(['balance_id' => $balanceId]),
            'before' => json_encode([]),
            'after' => json_encode([]),
            'blockers' => json_encode([]),
            'status' => 'confirmed',
            'expires_at' => now()->addMinute(),
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_store_operations')->insert([
            'id' => (string) Str::uuid(),
            'review_id' => $reviewId,
            'operation_key' => 'op-'.Str::uuid(),
            'kind' => $kind,
            'actor_id' => $actor->id,
            'ledger_status' => 'applied',
            'receipt' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
