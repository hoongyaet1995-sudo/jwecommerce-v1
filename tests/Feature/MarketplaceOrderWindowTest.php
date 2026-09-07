<?php

namespace Tests\Feature;

use App\Jobs\PullShopeeOrders;
use App\Services\Marketplace\ShopeeOrderService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceOrderWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_thirty_day_request_runs_in_bounded_windows_without_finishing_early(): void
    {
        config(['marketplace.read_operations_enabled'=>true, 'marketplace.shopee.enabled'=>true,
            'marketplace.shopee.environment'=>'sandbox', 'marketplace.shopee.partner_id'=>'123',
            'marketplace.shopee.partner_key'=>'fixture', 'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        Queue::fake();
        $store = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Window fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $to = now()->timestamp;
        $from = $to - 30 * 86400;
        $id = $this->postJson('/admin/api/marketplace/stores/'.$store.'/orders-fetch', ['from'=>$from, 'to'=>$to])
            ->assertStatus(202)->json('runId');
        $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$id, 'time_from'=>$from, 'time_to'=>$from+14*86400,
            'requested_time_from'=>$from, 'requested_time_to'=>$to, 'status'=>'queued']);
        $service = $this->mock(ShopeeOrderService::class);
        $service->shouldReceive('pullPage')->once()->with($store, $from, $from+14*86400, '')
            ->andReturn(['orders'=>1, 'has_more'=>true, 'next_cursor'=>'page-two']);
        (new PullShopeeOrders($id, 0))->handle($service);
        $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$id, 'time_from'=>$from, 'time_to'=>$from+14*86400,
            'next_cursor'=>'page-two', 'status'=>'queued', 'finished_at'=>null]);
        foreach ([[1, $from, $from+14*86400], [2, $from+14*86400, $from+28*86400], [3, $from+28*86400, $to]] as [$page, $start, $end]) {
            $service->shouldReceive('pullPage')->once()->with($store, $start, $end, $page === 1 ? 'page-two' : '')
                ->andReturn(['orders'=>1, 'has_more'=>false, 'next_cursor'=>'']);
            (new PullShopeeOrders($id, $page))->handle($service);
            $this->assertDatabaseHas('marketplace_order_runs', ['id'=>$id, 'status'=>$page === 3 ? 'succeeded' : 'queued',
                'pages_completed'=>$page+1, 'orders_processed'=>$page+1]);
        }
        $this->assertDatabaseCount('marketplace_order_runs', 1);
        $this->postJson('/admin/api/marketplace/stores/'.$store.'/orders-fetch', ['from'=>$from-86400, 'to'=>$to])->assertUnprocessable();
    }
}
