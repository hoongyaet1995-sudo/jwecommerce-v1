<?php

namespace Tests\Feature;

use App\Models\User;
use App\Contracts\StoreInventoryPublisher;
use App\Jobs\ProcessStoreInventorySync;
use App\Services\Inventory\StockAllocationService;
use App\Services\Inventory\StoreManagement\FacilityRegistry;
use App\Services\Inventory\StoreManagement\RebaselineFreeze;
use App\Services\Inventory\StoreManagement\OrderInventoryRouter;
use App\Services\Inventory\StoreManagement\SyncOutbox;
use App\Services\Marketplace\MarketplaceOrderInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\StoreInventoryFixture;
use Tests\TestCase;

class InventoryRebaselineFreezeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.rebaseline_cutover_enabled' => true]);
    }

    public function test_freeze_is_not_exposed_when_the_cutover_gate_is_disabled(): void
    {
        config(['marketplace.rebaseline_cutover_enabled' => false]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(RebaselineFreeze::class)->start($admin, 'blocked-cutover');
    }

    public function test_freeze_is_idempotent_and_blocks_new_compatibility_reservations(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $variant = (new StoreInventoryFixture)->sku()['variant'];
        $freeze = app(RebaselineFreeze::class);

        $first = $freeze->start($admin, 'test-freeze-1');
        $second = $freeze->start($admin, 'test-freeze-1');
        $this->assertSame($first['epoch_id'], $second['epoch_id']);
        $this->assertSame('frozen', $first['status']);
        $this->assertSame(1, DB::table('inventory_rebaseline_epochs')->count());
        $this->expectException(ValidationException::class);
        app(StockAllocationService::class)->reserve('order', 1, 'line-1', $variant->id, 1);
    }

    public function test_open_order_blocks_freeze_before_epoch_is_written(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'FREEZE');
        app(FacilityRegistry::class)->confirm($store->id, []);
        $variant = $fixture->sku()['variant'];
        DB::table('stock_allocations')->insert([
            'owner_type' => 'order', 'owner_id' => 1, 'line_key' => 'line-1',
            'product_variant_id' => $variant->id, 'quantity' => 1, 'status' => 'reserved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            app(RebaselineFreeze::class)->start($admin, 'test-freeze-blocked');
            $this->fail('Open reservations must block the freeze.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, DB::table('inventory_rebaseline_epochs')->count());
        }
    }

    public function test_freeze_registers_each_exact_provider_destination_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'DESTINATIONS');
        $variant = $fixture->sku()['variant'];
        $listing = DB::table('marketplace_listing_variants')->insertGetId([
            'store_id' => $store->id, 'external_product_id' => 'P-1',
            'external_variant_id' => 'V-1', 'observed_data' => '{}',
            'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketplace_variant_mappings')->insert([
            'store_id' => $store->id, 'listing_variant_id' => $listing,
            'product_variant_id' => $variant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(FacilityRegistry::class)->confirm($store->id, [
            ['provider_id' => 'WH-A', 'name' => 'A'],
            ['provider_id' => 'WH-B', 'name' => 'B'],
        ]);
        foreach (['WH-A', 'WH-B'] as $code) {
            $warehouse = DB::table('inventory_warehouses')->insertGetId([
                'code' => $code, 'name' => $code, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_store_facilities')->where('marketplace_store_id', $store->id)
                ->where('provider_facility_id', $code)->update(['inventory_warehouse_id' => $warehouse]);
        }

        $epoch = app(RebaselineFreeze::class)->start($admin, 'freeze-destinations');
        $this->assertSame(2, DB::table('inventory_rebaseline_destinations')
            ->where('epoch_id', $epoch['epoch_id'])->count());
        $this->assertEqualsCanonicalizing(['WH-A', 'WH-B'], DB::table('inventory_rebaseline_destinations')
            ->where('epoch_id', $epoch['epoch_id'])->pluck('route_key')->all());
    }

    public function test_late_marketplace_order_stays_visible_as_an_exception_without_reserving_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(RebaselineFreeze::class)->start($admin, 'test-freeze-late-order');
        $store = (new StoreInventoryFixture)->store('lazada', 'LATE-ORDER');
        $orderId = DB::table('marketplace_orders')->insertGetId([
            'store_id' => $store->id, 'external_order_id' => 'LATE-1',
            'platform_status' => 'ready_to_ship', 'platform_updated_at' => time(),
            'encrypted_payload' => 'not-decrypted-during-freeze', 'fetched_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(MarketplaceOrderInventoryService::class)->process($orderId);

        $this->assertDatabaseHas('marketplace_order_inventory', [
            'order_id' => $orderId, 'status' => 'exception', 'error_code' => 'inventory_rebaseline_frozen',
        ]);
        $this->assertSame(0, DB::table('stock_allocations')->count());
    }

    public function test_frozen_epoch_rejects_an_ordinary_stock_push_without_creating_a_task(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(RebaselineFreeze::class)->start($admin, 'test-freeze-outbox');
        $variant = (new StoreInventoryFixture)->sku()['variant'];

        try {
            app(SyncOutbox::class)->enqueueBalance(null, (object) [
                'inventory_pool_id' => 1, 'product_variant_id' => $variant->id,
                'physical_stock' => 10, 'reserved_stock' => 0, 'safety_stock' => 0,
                'version' => 0,
            ], 'account', 'ordinary-push');
            $this->fail('Ordinary stock pushes must be paused.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, DB::table('inventory_store_sync_tasks')->count());
        }
    }

    public function test_queued_legacy_workers_do_not_send_stock_during_freeze(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        app(RebaselineFreeze::class)->start($admin, 'test-freeze-worker');
        $fixture = new StoreInventoryFixture;
        $store = $fixture->store('lazada', 'QUEUED');
        $variant = $fixture->sku()['variant'];
        $taskId = (string) \Illuminate\Support\Str::uuid();
        DB::table('inventory_store_sync_tasks')->insert([
            'id' => $taskId, 'marketplace_store_id' => $store->id,
            'product_variant_id' => $variant->id, 'stage' => 'stock',
            'status' => 'queued', 'balance_version' => 0,
            'event_key' => 'queued-before-freeze',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $publisher = \Mockery::mock(StoreInventoryPublisher::class);
        $publisher->shouldNotReceive('publish');

        (new ProcessStoreInventorySync($taskId))->handle($publisher);
        $this->assertSame('queued', DB::table('inventory_store_sync_tasks')->where('id', $taskId)->value('status'));
    }

    public function test_managed_order_router_cannot_register_a_new_line_during_freeze(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $variant = (new StoreInventoryFixture)->sku()['variant'];
        app(RebaselineFreeze::class)->start($admin, 'test-freeze-router');

        try {
            app(OrderInventoryRouter::class)->register('website', 'late-line-1', $variant->id, 1);
            $this->fail('A new order line cannot be created during the freeze.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Sales are paused', $exception->getMessage());
            $this->assertSame(0, DB::table('inventory_store_order_lines')->count());
        }
    }
}
