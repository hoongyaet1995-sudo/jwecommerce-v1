<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class SalesReportTest extends TestCase {
 use RefreshDatabase;
 public function test_missing_receipt_dependency_fails_before_csv_headers(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  \Illuminate\Support\Facades\Schema::drop('order_payments');
  $this->getJson('/admin/api/reports/sales?from=2026-11-02&to=2026-11-02&currency=MYR&export=csv')->assertStatus(503)->assertJsonPath('answer_kind','unavailable');
 }
 public function test_multiple_completed_cross_period_refunds_can_make_net_collections_negative(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $id=DB::table('orders')->insertGetId(['number'=>'CROSS-PERIOD-REFUNDS','email'=>'private@example.test','currency'=>'MYR','status'=>'cancelled','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
  DB::table('order_payments')->insert(['order_id'=>$id,'gateway'=>'bank','status'=>'partially_refunded','reference'=>'EARLIER-RECEIPT','amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-01 01:00:00']);
  foreach([100,200] as $i=>$amount)DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>$amount,'status'=>'completed','reason'=>'Confirmed partial refund','completed_at'=>'2026-11-02 0'.($i+1).':00:00']);
  DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>500,'status'=>'pending','reason'=>'Not completed']);
  $url='/admin/api/reports/sales?from=2026-11-02&to=2026-11-02&currency=MYR';
  $this->getJson($url)->assertOk()->assertJsonPath('totals.confirmed_minor',0)->assertJsonPath('totals.refunded_minor',300)->assertJsonPath('totals.net_minor',-300)->assertJsonPath('totals.paid_orders',0)->assertJsonPath('records.total',2);
  $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();$this->assertSame(3,count(array_filter(explode("\n",trim($csv)))));
 }
 public function test_future_dated_receipts_and_refunds_are_excluded_at_one_as_of(): void {
  $this->travelTo(\Carbon\Carbon::parse('2026-11-02 01:30:00','UTC'));
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $id=DB::table('orders')->insertGetId(['number'=>'FUTURE-EVIDENCE','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
  DB::table('order_payments')->insert([
   ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'RECORDED','amount'=>400,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00'],
   ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'FUTURE','amount'=>600,'currency'=>'MYR','confirmed_at'=>'2026-11-02 02:00:00'],
  ]);
  DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>100,'status'=>'completed','reason'=>'Future timestamp','completed_at'=>'2026-11-02 03:00:00']);
  $url='/admin/api/reports/sales?from=2026-11-02&to=2026-11-03&currency=MYR';
  $this->getJson($url)->assertOk()->assertJsonPath('totals.confirmed_minor',400)->assertJsonPath('totals.refunded_minor',0)->assertJsonPath('records.total',1);
  $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();$this->assertStringNotContainsString('600',$csv);$this->assertStringNotContainsString(',refund,',$csv);
 }
 public function test_paid_orders_and_aov_use_first_confirmed_cohort_and_comparison_uses_equal_period(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $id=DB::table('orders')->insertGetId(['number'=>'COHORT-SALES','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'total'=>1100,'discount_amount'=>0,'shipping_amount'=>100,'tax_amount'=>0]);
  DB::table('order_payments')->insert([
   ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'FIRST','amount'=>500,'currency'=>'MYR','confirmed_at'=>'2026-11-01 01:00:00'],
   ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'SECOND','amount'=>600,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00'],
  ]);
  $this->getJson('/admin/api/reports/sales?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('totals.confirmed_minor',600)->assertJsonPath('totals.paid_orders',0)->assertJsonPath('totals.average_order_minor',null)->assertJsonPath('comparison.previous.confirmed_minor',500)->assertJsonPath('comparison.from','2026-11-01');
  $this->getJson('/admin/api/reports/sales?from=2026-11-01&to=2026-11-01&currency=MYR')->assertOk()->assertJsonPath('totals.paid_orders',1)->assertJsonPath('totals.average_order_minor',1100)->assertJsonPath('comparison.confirmed_change.state','new_activity');
 }
 public function test_original_confirmed_payment_does_not_disappear_after_refund_status_transition(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $id=DB::table('orders')->insertGetId(['number'=>'REFUNDED-RECEIPT','email'=>'private@example.test','currency'=>'MYR','status'=>'cancelled','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
  DB::table('order_payments')->insert(['order_id'=>$id,'gateway'=>'bank','status'=>'refunded','reference'=>'ORIGINAL-RECEIPT','amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00']);
  DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>1000,'status'=>'completed','reason'=>'Completed refund','completed_at'=>'2026-11-02 02:00:00']);
  $this->getJson('/admin/api/reports/sales?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('totals.confirmed_minor',1000)->assertJsonPath('totals.refunded_minor',1000)->assertJsonPath('totals.net_minor',0);
 }
 public function test_report_permission_and_cashflow_dates(): void {
  $this->getJson('/admin/api/reports/sales')->assertUnauthorized();
  $staff=User::factory()->create(['is_admin'=>true]);
  $this->actingAs($staff)->getJson('/admin/api/reports/sales')->assertForbidden();
  $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
  $id=DB::table('orders')->insertGetId(['number'=>'REPORT-1','email'=>'x@example.test','currency'=>'MYR','status'=>'cancelled','subtotal'=>10000,'total'=>10600,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>600,'created_at'=>'2026-10-01','updated_at'=>'2026-11-04']);
  DB::table('order_payments')->insert(['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'REPORT-P1','amount'=>10600,'currency'=>'MYR','confirmed_at'=>'2026-11-03 16:00:00','created_at'=>'2026-10-01','updated_at'=>'2026-11-04']);
  DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>1200,'reason'=>'test','status'=>'completed','completed_at'=>'2026-11-04 01:00:00','created_at'=>'2026-10-01','updated_at'=>'2026-11-04']);
  $url='/admin/api/reports/sales?from=2026-11-04&to=2026-11-04&currency=MYR';
  $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('totals.confirmed_minor',10600)->assertJsonPath('totals.refunded_minor',1200)->assertJsonPath('totals.net_minor',9400)->assertJsonPath('records.total',2);
  $this->getJson($url.'&currency=SGD')->assertJsonPath('totals.confirmed_minor',0);
  $this->getJson('/admin/api/reports/sales?from=2026-11-05&to=2026-11-04')->assertUnprocessable();
  // Kuala Lumpur midnight is 16:00 UTC; the upper boundary is exclusive.
  DB::table('order_payments')->insert([
   ['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'BEFORE','amount'=>800,'currency'=>'MYR','confirmed_at'=>'2026-11-03 15:59:59'],
   ['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'AFTER','amount'=>900,'currency'=>'MYR','confirmed_at'=>'2026-11-04 16:00:00'],
   ['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'pending','reference'=>'PENDING','amount'=>700,'currency'=>'MYR','confirmed_at'=>'2026-11-04 02:00:00'],
   ['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'UNDATED','amount'=>600,'currency'=>'MYR','confirmed_at'=>null],
  ]);
  DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>500,'reason'=>'Requested only','status'=>'pending','completed_at'=>null]);
  $this->getJson($url)->assertOk()->assertJsonPath('totals.net_minor',9400)->assertJsonPath('totals.paid_orders',0)->assertJsonPath('coverage.undated_payments',1)->assertJsonCount(1,'trend');
  $this->getJson($url.'&search=missing')->assertJsonPath('records.total',0)->assertJsonPath('totals.net_minor',0);
  $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();
  $this->assertStringContainsString('REPORT-1,payment,',$csv);
  $this->assertStringContainsString('REPORT-1,refund,',$csv);
  $this->assertSame(3,count(array_filter(explode("\n",trim($csv)))));
  DB::table('orders')->where('id',$id)->update(['number'=>" \t=HYPERLINK(1)"]);
  $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();
  $this->assertStringContainsString("' \t=HYPERLINK(1)",$csv);
 }
}
