<?php

namespace Tests\Feature;

use App\Services\Marketplace\MarketplaceAutomaticOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceAutomaticOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_orders_start_with_thirty_days_then_resume_from_success_only(): void
    {
        $this->freezeTime();
        config(['marketplace.automatic_orders_enabled'=>true, 'marketplace.read_operations_enabled'=>true,
            'marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'fixture',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Auto fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        $service = app(MarketplaceAutomaticOrders::class);
        $this->assertSame(1, $service->enqueueDue());
        $run = DB::table('marketplace_order_runs')->sole();
        $this->assertEquals(now()->timestamp-30*86400, $run->requested_time_from);
        $this->assertEquals(now()->timestamp, $run->requested_time_to);
        $this->assertSame(0, $service->enqueueDue());
        DB::table('marketplace_order_runs')->where('id', $run->id)->update(['status'=>'failed']);
        $this->travel(10)->minutes();
        $this->assertSame(0, $service->enqueueDue());
        DB::table('marketplace_order_runs')->where('id', $run->id)->update(['status'=>'succeeded']);
        $this->assertSame(1, $service->enqueueDue());
        $next = DB::table('marketplace_order_runs')->orderByDesc('id')->first();
        $this->assertEquals($run->requested_time_to-300, $next->requested_time_from);
        $this->assertEquals(now()->timestamp, $next->requested_time_to);
        DB::table('marketplace_order_runs')->where('id', $next->id)->update(['status'=>'succeeded']);
        $this->assertSame(0, $service->enqueueDue());
        $this->travel(10)->minutes();
        DB::table('marketplace_stores')->where('id', $store)->update(['status'=>'disconnected']);
        $this->assertSame(0, $service->enqueueDue());
        $this->assertDatabaseCount('marketplace_order_runs', 2);
    }

    public function test_automatic_order_creation_is_disabled_by_default(): void
    {
        config(['marketplace.automatic_orders_enabled'=>false]);
        $this->assertSame(0, app(MarketplaceAutomaticOrders::class)->enqueueDue());
        $this->assertDatabaseCount('marketplace_order_runs', 0);
    }
}
