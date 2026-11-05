<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShippingReportTest extends TestCase
{
    use RefreshDatabase;
    public function test_cancelled_attempts_remain_in_history_but_not_effective_progress(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-02 04:00:00','UTC'));
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $order=DB::table('orders')->insertGetId(['number'=>'CANCELLED-ATTEMPT','email'=>'private@example.test','currency'=>'MYR','status'=>'processing','subtotal'=>100,'total'=>100,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-11-02 01:00:00']);
        DB::table('shipments')->insert(['order_id'=>$order,'sequence'=>1,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'cancelled','created_at'=>'2026-11-02 01:00:00','dispatched_at'=>'2026-11-02 02:00:00','delivered_at'=>'2026-11-03 02:00:00','cancelled_at'=>'2026-11-03 03:00:00','shipping_cost'=>80]);
        $this->getJson('/admin/api/reports/shipping?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('stats.shipments_created',1)->assertJsonPath('stats.effective_shipments',0)->assertJsonPath('stats.recorded_deliveries',0)->assertJsonPath('coverage.inactive_attempts',1)->assertJsonPath('trend.0.dispatched_count',0)->assertJsonCount(1,'records.data')->assertJsonPath('stats.awaiting_dispatch_orders',1)->assertJsonPath('stats.average_awaiting_dispatch_hours',3);
    }
    public function test_transit_statistics_use_recorded_dates_and_do_not_infer_missing_delivery_times(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $order=DB::table('orders')->insertGetId(['number'=>'SHIP-REPORT','email'=>'private@example.test','currency'=>'MYR','status'=>'pending','subtotal'=>100,'total'=>100,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
        DB::table('shipments')->insert([
            ['order_id'=>$order,'sequence'=>1,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'delivered','created_at'=>'2026-11-01 16:00:00','dispatched_at'=>'2026-11-02 01:00:00','delivered_at'=>'2026-11-03 01:00:00','shipping_cost'=>100],
            ['order_id'=>$order,'sequence'=>2,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'delivered','created_at'=>'2026-11-02 01:00:00','dispatched_at'=>'2026-11-03 01:00:00','delivered_at'=>'2026-11-02 01:00:00','shipping_cost'=>null],
            ['order_id'=>$order,'sequence'=>3,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'delivered','created_at'=>'2026-11-02 02:00:00','dispatched_at'=>null,'delivered_at'=>null,'shipping_cost'=>50],
        ]);
        $this->getJson('/admin/api/reports/shipping?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('stats.shipments_created',3)->assertJsonPath('stats.recorded_deliveries',1)->assertJsonPath('stats.average_transit_hours',24)->assertJsonPath('stats.recorded_shipping_minor',null)->assertJsonPath('coverage.invalid_time_pairs',1)->assertJsonPath('coverage.deliveries_missing_dates',1)->assertJsonPath('carriers.0.shipments',3);
        $this->getJson('/admin/api/reports/shipping?from=2026-11-02&to=2026-11-02&currency=MYR&metric=delivered_shipments')->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.shipment_status','delivered');
    }
}
