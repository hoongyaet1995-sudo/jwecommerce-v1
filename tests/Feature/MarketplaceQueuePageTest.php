<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceQueuePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_operations_stop_queued_pages_without_provider_requests(): void
    {
        config(['marketplace.operations_enabled'=>false]);
        Http::preventStrayRequests();
        Http::fake();
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        foreach ([
            ['marketplace_catalog_runs', \App\Jobs\PullShopeeCatalog::class, \App\Services\Marketplace\ShopeeCatalogService::class, ['next_offset'=>50, 'items_processed'=>3, 'variants_processed'=>4]],
            ['marketplace_order_runs', \App\Jobs\PullShopeeOrders::class, \App\Services\Marketplace\ShopeeOrderService::class, ['time_from'=>100, 'time_to'=>200, 'next_cursor'=>'next-page', 'orders_processed'=>3]],
        ] as [$table, $jobClass, $serviceClass, $extra]) {
            $id = DB::table($table)->insertGetId($extra + ['store_id'=>$store, 'status'=>'queued',
                'pages_completed'=>1, 'created_at'=>now(), 'updated_at'=>now()]);
            (new $jobClass($id, 1))->handle(app($serviceClass));
            $this->assertDatabaseHas($table, $extra + ['id'=>$id, 'status'=>'failed',
                'pages_completed'=>1, 'error_code'=>'marketplace_operations_disabled']);
            $this->assertNotNull(DB::table($table)->where('id', $id)->value('finished_at'));
        }
        Http::assertNothingSent();
    }

    public function test_old_page_jobs_cannot_claim_or_fail_a_later_page(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Store',
            'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected', 'created_at'=>now(), 'updated_at'=>now()]);
        foreach ([
            ['marketplace_catalog_runs', \App\Jobs\PullShopeeCatalog::class, \App\Services\Marketplace\ShopeeCatalogService::class, []],
            ['marketplace_order_runs', \App\Jobs\PullShopeeOrders::class, \App\Services\Marketplace\ShopeeOrderService::class, ['time_from'=>100, 'time_to'=>200]],
        ] as [$table, $jobClass, $serviceClass, $extra]) {
            $id = DB::table($table)->insertGetId($extra + ['store_id'=>$store, 'status'=>'queued',
                'pages_completed'=>1, 'created_at'=>now(), 'updated_at'=>now()]);
            $old = new $jobClass($id, 0);
            $old->handle(app($serviceClass));
            $this->assertDatabaseHas($table, ['id'=>$id, 'status'=>'queued', 'pages_completed'=>1]);
            $old->failed();
            $this->assertDatabaseHas($table, ['id'=>$id, 'status'=>'queued', 'pages_completed'=>1, 'error_code'=>null]);
        }
        Http::assertNothingSent();
    }
}
