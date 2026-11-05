<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerchandiseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_merchandise_export_is_rejected_before_a_download_starts(): void
    {
        $orders=[];for($i=0;$i<1001;$i++)$orders[]=['number'=>'LIMIT-'.$i,'email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1,'total'=>1,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0];
        DB::table('orders')->insert($orders);$payments=[];
        foreach(DB::table('orders')->pluck('id') as $id)$payments[]=['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'LIMIT-'.$id,'amount'=>1,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00'];
        DB::table('order_payments')->insert($payments);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->getJson('/admin/api/reports/merchandise?from=2026-11-02&to=2026-11-02&currency=MYR&export=csv')->assertUnprocessable();
    }

    public function test_historical_allocations_and_counted_stock_are_read_without_live_price_or_stock_fallback(): void
    {
        config(['inventory.current_stock_only'=>true]);
        DB::table('inventory_configuration')->where('id',1)->update(['operating_mode'=>'shared_pool']);
        $product=Product::create(['handle'=>'report-product','title'=>'Current edited title','base_price'=>9999]);
        $variant=$product->variants()->create(['sku'=>'REAL-SKU','title'=>'Default','price'=>9999,'stock'=>999]);
        $pool=DB::table('inventory_pools')->insertGetId(['pool_key'=>'shared','kind'=>'shared']);
        $balance=DB::table('inventory_balances')->insertGetId(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id,'current_stock'=>3,'counted_at'=>'2026-11-01 00:00:00','physical_stock'=>999,'reserved_stock'=>888,'safety_stock'=>5]);
        $order=DB::table('orders')->insertGetId(['number'=>'SNAPSHOT-SALE','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'discount_amount'=>200,'shipping_amount'=>50,'payment_surcharge_amount'=>0,'tax_amount'=>80,'total'=>830,'promotion_snapshot'=>json_encode(['version'=>1,'type'=>'coupon','discount'=>200,'lines'=>[(string)$variant->id=>['quantity'=>2,'gross'=>1000,'discount'=>200,'net'=>800,'tax'=>80,'rewards'=>100]]])]);
        DB::table('order_items')->insert(['order_id'=>$order,'product_variant_id'=>$variant->id,'sku'=>'HISTORICAL-SKU','title'=>'Historical title','unit_price'=>500,'quantity'=>2,'line_total'=>1000]);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'SNAPSHOT-PAID','amount'=>830,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00']);
        $url='/admin/api/reports/merchandise?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->getJson($url)->assertOk()->assertJsonPath('records.data.0.sku','HISTORICAL-SKU')->assertJsonPath('records.data.0.title','Historical title')->assertJsonPath('records.data.0.net_units',2)->assertJsonPath('records.data.0.net_merchandise_minor',700)->assertJsonPath('records.data.0.available',3)->assertJsonPath('stats.net_merchandise_minor',700);
        DB::table('inventory_balances')->where('id',$balance)->update(['current_stock'=>0]);
        $this->getJson($url)->assertJsonPath('records.data.0.available',0)->assertJsonPath('records.data.0.availability','Out of stock');
        DB::table('inventory_balances')->where('id',$balance)->update(['current_stock'=>null,'counted_at'=>null]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.available',106)->assertJsonPath('records.data.0.stock_verified',false);
        DB::table('inventory_balances')->where('id',$balance)->update(['physical_stock'=>null]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.available',null);
        $this->assertDatabaseCount('inventory_current_stock_movements',0);
        $this->assertSame(999,(int)$variant->fresh()->stock);
        // Merchandise uses the first confirmed payment cohort, not a full-collection gate.
        DB::table('order_payments')->where('order_id',$order)->update(['amount'=>100]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',700);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'UNTRUSTED-DATE','amount'=>1,'currency'=>'MYR','confirmed_at'=>null]);
        $this->getJson($url)->assertOk()->assertJsonPath('coverage.uncertain_payment_orders',1)->assertJsonPath('trend.0.merchandise_minor_before_refunds',null)->assertJsonPath('trend.0.net_merchandise_minor',null);
    }

    public function test_unlinked_refund_prevents_false_sku_net_revenue_allocation(): void
    {
        $product=Product::create(['handle'=>'refund-product','title'=>'Recorded item','base_price'=>1000]);
        $v=$product->variants()->create(['sku'=>'REFUND-SKU','title'=>'Default','price'=>1000,'stock'=>0]);
        $order=DB::table('orders')->insertGetId(['number'=>'UNALLOCATED','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'total'=>1000,'promotion_snapshot'=>json_encode(['version'=>1,'type'=>null,'discount'=>0,'lines'=>[(string)$v->id=>['quantity'=>1,'gross'=>1000,'discount'=>0,'net'=>1000,'tax'=>0,'rewards'=>0]]])]);
        DB::table('order_items')->insert(['order_id'=>$order,'product_variant_id'=>$v->id,'sku'=>'REFUND-SKU','title'=>'Recorded item','unit_price'=>1000,'quantity'=>1,'line_total'=>1000]);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'partially_refunded','reference'=>'REFUND-EVIDENCE','amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00']);
        DB::table('order_refunds')->insert(['order_id'=>$order,'amount'=>200,'status'=>'completed','reason'=>'Unallocated adjustment','completed_at'=>'2026-11-03 01:00:00']);
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]))->getJson('/admin/api/reports/merchandise?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',null)->assertJsonPath('stats.net_merchandise_minor',null)->assertJsonPath('coverage.unallocated_refund_orders',1);
        $customer=DB::table('customers')->insertGetId(['email'=>'refund-customer@example.test']);
        $item=DB::table('order_items')->where('order_id',$order)->value('id');$refund=DB::table('order_refunds')->where('order_id',$order)->value('id');
        DB::table('return_requests')->insert(['order_id'=>$order,'order_item_id'=>$item,'customer_id'=>$customer,'resolved_refund_id'=>$refund,'status'=>'resolved_refund','resolution'=>'refund','quantity'=>1,'reason'=>'Item return','eligible_until'=>'2026-12-01','resolved_at'=>'2026-11-03 01:00:00']);
        // A linked partial-value refund still cannot prove a full merchandise reversal.
        $url='/admin/api/reports/merchandise?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->getJson($url)->assertJsonPath('records.data.0.net_merchandise_minor',null);
        DB::table('order_refunds')->where('id',$refund)->update(['amount'=>1000]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',0)->assertJsonPath('records.data.0.net_units',0)->assertJsonPath('coverage.unallocated_refund_orders',0);
        $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('net_merchandise_minor',$csv);
        $this->assertStringNotContainsString('@example.test',$csv);
    }

    public function test_exact_tax_free_partial_returns_use_historical_quantity_offsets(): void
    {
        $product=Product::create(['handle'=>'partial-report','title'=>'Historical','base_price'=>1001]);
        $v=$product->variants()->create(['sku'=>'PARTIAL','title'=>'Default','price'=>1001,'stock'=>0]);
        $order=DB::table('orders')->insertGetId(['number'=>'PARTIAL-REPORT','email'=>'partial@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1002,'discount_amount'=>1,'shipping_amount'=>0,'tax_amount'=>0,'total'=>1001,'promotion_snapshot'=>json_encode(['version'=>1,'type'=>'coupon','lines'=>[(string)$v->id=>['quantity'=>3,'gross'=>1002,'discount'=>1,'net'=>1001,'tax'=>0,'rewards'=>0]]])]);
        $item=DB::table('order_items')->insertGetId(['order_id'=>$order,'product_variant_id'=>$v->id,'sku'=>'PARTIAL','title'=>'Historical','unit_price'=>334,'quantity'=>3,'line_total'=>1002]);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'partially_refunded','reference'=>'PARTIAL-REPORT','amount'=>1001,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00']);
        $customer=DB::table('customers')->insertGetId(['email'=>'partial@example.test']);
        $add=function(int $amount,string $time)use($order,$item,$customer){
            $refund=DB::table('order_refunds')->insertGetId(['order_id'=>$order,'amount'=>$amount,'reason'=>'Historical return','status'=>'completed','completed_at'=>$time]);
            DB::table('return_requests')->insert(['order_id'=>$order,'order_item_id'=>$item,'customer_id'=>$customer,'resolved_refund_id'=>$refund,'status'=>'resolved_refund','resolution'=>'refund','quantity'=>1,'reason'=>'Historical return','eligible_until'=>'2026-12-01','resolved_at'=>$time]);
        };
        $url='/admin/api/reports/merchandise?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $add(333,'2026-11-03 01:00:00');
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.returned_units',1)->assertJsonPath('records.data.0.net_units',2)->assertJsonPath('records.data.0.net_merchandise_minor',668);
        $add(334,'2026-11-03 02:00:00');
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',334)->assertJsonPath('records.data.0.net_units',1);
        $add(334,'2026-11-03 03:00:00');
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',0)->assertJsonPath('records.data.0.net_units',0);
        DB::table('order_refunds')->where('order_id',$order)->orderBy('id')->limit(1)->update(['amount'=>332]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.data.0.net_merchandise_minor',null)->assertJsonPath('coverage.unallocated_refund_orders',1);
        $this->assertDatabaseCount('inventory_current_stock_movements',0);
    }
}
