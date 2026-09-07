<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceAutomaticOrders;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceAutomaticOrderDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commits are essential here; RefreshDatabase would hide after-commit failures.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    public function test_queue_insert_failure_does_not_leave_an_orphan_run_and_retry_can_schedule(): void
    {
        config(['marketplace.automatic_orders_enabled'=>true, 'marketplace.read_operations_enabled'=>true,
            'marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'fixture',
            'marketplace.shopee.callback_url'=>'https://example.test/callback',
            'queue.connections.marketplace'=>['driver'=>'database', 'connection'=>config('database.default'),
                'table'=>'missing_queue_fixture', 'queue'=>'marketplace', 'retry_after'=>1500, 'after_commit'=>true]]);
        DB::table('marketplace_stores')->insert(['platform'=>'shopee', 'shop_name'=>'Fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        try {
            app(MarketplaceAutomaticOrders::class)->enqueueDue();
            $this->fail('Expected database queue insert failure.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('missing_queue_fixture', $error->getSql());
        }
        $this->assertDatabaseCount('marketplace_order_runs', 0);
        $this->assertDatabaseCount('jobs', 0);
        // Restore the missing queue table without replacing the resolved queue instance.
        \Illuminate\Support\Facades\Schema::rename('jobs', 'missing_queue_fixture');
        $this->assertSame(1, app(MarketplaceAutomaticOrders::class)->enqueueDue());
        $this->assertDatabaseCount('marketplace_order_runs', 1);
        $this->assertDatabaseCount('missing_queue_fixture', 1);
        \Illuminate\Support\Facades\Schema::rename('missing_queue_fixture', 'jobs');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonAtomicQueues')]
    public function test_rejects_a_queue_that_cannot_share_the_run_transaction(string $driver, ?string $connection): void
    {
        config(['marketplace.automatic_orders_enabled'=>true, 'marketplace.read_operations_enabled'=>true,
            'marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'fixture',
            'marketplace.shopee.callback_url'=>'https://example.test/callback',
            'database.connections.separate'=>config('database.connections.sqlite'),
            'queue.connections.marketplace'=>['driver'=>$driver, 'connection'=>$connection,
                'table'=>'jobs', 'queue'=>'marketplace', 'retry_after'=>1500, 'after_commit'=>true]]);
        try {
            app(MarketplaceAutomaticOrders::class)->enqueueDue();
            $this->fail('Expected rejection before scheduling.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('application database connection', $error->getMessage());
        }
        $this->assertDatabaseCount('marketplace_order_runs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public static function nonAtomicQueues(): array
    {
        return ['synchronous driver'=>['sync', null], 'separate database connection'=>['database', 'separate']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('manualActions')]
    public function test_manual_dispatch_failure_preserves_retryable_state(bool $retry): void
    {
        config(['marketplace.read_operations_enabled'=>true, 'marketplace.shopee.enabled'=>true,
            'marketplace.shopee.environment'=>'sandbox', 'marketplace.shopee.partner_id'=>'123',
            'marketplace.shopee.partner_key'=>'fixture', 'marketplace.shopee.callback_url'=>'https://example.test/callback',
            'queue.connections.marketplace'=>['driver'=>'database', 'connection'=>config('database.default'),
                'table'=>'missing_queue_fixture', 'queue'=>'marketplace', 'retry_after'=>1500, 'after_commit'=>true]]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        $run = $retry ? DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store, 'status'=>'failed',
            'time_from'=>100, 'time_to'=>200, 'next_cursor'=>'original', 'error_code'=>'order_page_failed']) : null;
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $url = $retry ? '/admin/api/marketplace/order-runs/'.$run.'/retry' : '/admin/api/marketplace/stores/'.$store.'/orders-fetch';
        $this->postJson($url, ['from'=>100, 'to'=>200])->assertStatus(500);
        $this->assertDatabaseCount('marketplace_order_runs', $retry ? 1 : 0);
        if ($retry) $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$run, 'status'=>'failed',
            'next_cursor'=>'original', 'error_code'=>'order_page_failed']);
        \Illuminate\Support\Facades\Schema::rename('jobs', 'missing_queue_fixture');
        $this->postJson($url, ['from'=>100, 'to'=>200])->assertStatus(202);
        $this->assertDatabaseCount('marketplace_order_runs', 1);
        $this->assertDatabaseCount('missing_queue_fixture', 1);
        \Illuminate\Support\Facades\Schema::rename('missing_queue_fixture', 'jobs');
    }

    public static function manualActions(): array
    {
        return ['fetch'=>[false], 'retry'=>[true]];
    }

    public function test_failed_next_page_dispatch_preserves_the_retry_cursor(): void
    {
        config(['marketplace.read_operations_enabled'=>true,
            'queue.connections.marketplace'=>['driver'=>'database', 'connection'=>config('database.default'),
                'table'=>'missing_queue_fixture', 'queue'=>'marketplace', 'retry_after'=>1500, 'after_commit'=>true]]);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        $run = DB::table('marketplace_order_runs')->insertGetId(['store_id'=>$store, 'status'=>'queued',
            'time_from'=>100, 'time_to'=>200, 'next_cursor'=>'original']);
        $service = \Mockery::mock(\App\Services\Marketplace\ShopeeOrderService::class);
        $service->shouldReceive('pullPage')->twice()->with($store, 100, 200, 'original')
            ->andReturn(['orders'=>2, 'has_more'=>true, 'next_cursor'=>'next']);
        (new \App\Jobs\PullShopeeOrders($run))->handle($service);
        $saved = DB::table('marketplace_order_runs')->find($run);
        $this->assertSame('failed', $saved->status);
        $this->assertSame('original', $saved->next_cursor);
        $this->assertEquals(0, $saved->pages_completed);
        $this->assertEquals(0, $saved->orders_processed);
        $this->assertDatabaseCount('jobs', 0);
        \Illuminate\Support\Facades\Schema::rename('jobs', 'missing_queue_fixture');
        DB::table('marketplace_order_runs')->where('id', $run)->update(['status'=>'queued']);
        (new \App\Jobs\PullShopeeOrders($run))->handle($service);
        $saved = DB::table('marketplace_order_runs')->find($run);
        $this->assertSame('queued', $saved->status);
        $this->assertSame('next', $saved->next_cursor);
        $this->assertEquals(1, $saved->pages_completed);
        $this->assertEquals(2, $saved->orders_processed);
        $this->assertDatabaseCount('missing_queue_fixture', 1);
        \Illuminate\Support\Facades\Schema::rename('missing_queue_fixture', 'jobs');
    }
}
