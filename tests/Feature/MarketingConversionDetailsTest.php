<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use App\Models\{User,MarketingCampaign,Order};
class MarketingConversionDetailsTest extends TestCase {
 use RefreshDatabase;
 private function seedReport():array {
  Http::preventStrayRequests();$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$m=app(\App\Services\Marketing\MarketingPosts::class)->merchant();
  $c=MarketingCampaign::create(['merchant_key'=>$m,'user_id'=>$u->id,'name'=>'Report campaign','objective'=>'Sales','status'=>'Active','starts_on'=>'2026-01-01','ends_on'=>'2027-01-01','timezone'=>'UTC','destination_url'=>'http://localhost','cta'=>'Shop now','version'=>1]);
  $link=(string)Str::uuid();DB::table('marketing_tracking_links')->insert(['id'=>$link,'campaign_id'=>$c->id,'post_id'=>(string)Str::uuid(),'destination_id'=>(string)Str::uuid(),'merchant_key'=>$m,'user_id'=>$u->id,'post_version'=>1,'destination_url'=>'http://localhost']);
  foreach(['MYR','USD'] as $currency){$o=Order::create(['number'=>'REPORT-'.$currency,'email'=>'PRIVATE@example.test','currency'=>$currency,'status'=>'paid','subtotal'=>10000,'total'=>10000]);DB::table('marketing_order_attributions')->insert(['order_id'=>$o->id,'merchant_key'=>$m,'campaign_id'=>$c->id,'link_id'=>$link,'owner_id'=>$u->id,'session_key'=>'PRIVATE-SESSION','clicked_at'=>now(),'placed_at'=>now(),'base_currency'=>$currency,'base_minor'=>10000,'refunded_minor'=>2500,'state'=>'paid','paid_at'=>now()]);}
  return [$u,$c,$link];
 }
 public function test_order_details_are_paginated_private_and_currency_safe():void {
  [$u,$c]=$this->seedReport();$this->actingAs($u);
  $r=$this->getJson('/admin/marketing/analytics/details?kind=orders')->assertOk()->assertJsonPath('total',2)->assertJsonCount(2,'rows');
  $r->assertDontSee('PRIVATE@example.test')->assertDontSee('PRIVATE-SESSION');
  $id=$r->json('rows.0.id');$this->getJson('/admin/marketing/analytics/record?kind=order&id='.$id)->assertOk()->assertJsonPath('record.net_minor',7500)->assertJsonPath('source.campaign_name','Report campaign');
  $this->getJson('/admin/marketing/analytics/data')->assertJsonCount(2,'currency_totals')->assertJsonPath('currency_totals.0.net_minor',7500);
  $csv=$this->get('/admin/marketing/analytics/export')->assertOk()->streamedContent();$this->assertStringContainsString('Gross minor units',$csv);$this->assertStringContainsString('MYR',$csv);$this->assertStringContainsString('USD',$csv);
  Http::assertNothingSent();
 }
 public function test_cross_owner_and_missing_permission_cannot_read_records():void {
  [$u]=$this->seedReport();$id=Order::firstOrFail()->id;
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $this->getJson('/admin/marketing/analytics/details?kind=orders')->assertOk()->assertJsonPath('total',0);
  $this->getJson('/admin/marketing/analytics/record?kind=order&id='.$id)->assertNotFound();
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>false]));$this->getJson('/admin/marketing/analytics/details?kind=orders')->assertForbidden();
 }
 public function test_filters_and_invalid_kinds_do_not_leak_or_return_unbounded_records():void {
  [$u]=$this->seedReport();$this->actingAs($u);
  $this->getJson('/admin/marketing/analytics/details?kind=orders&from=2000-01-01&to=2000-01-01')->assertOk()->assertJsonPath('total',0);
  $this->getJson('/admin/marketing/analytics/details?kind=arbitrary')->assertUnprocessable();
  $this->getJson('/admin/marketing/analytics/details?kind=orders&page=0')->assertUnprocessable();
 }
 public function test_enquiry_source_and_activity_are_read_only_and_anonymous():void {
  [$u,$c,$link]=$this->seedReport();$this->actingAs($u);$e=(string)Str::uuid();
  DB::table('product_enquiries')->insert(['id'=>$e,'reference'=>'ENQ-REPORT','owner_hash'=>str_repeat('a',64),'request_key'=>Str::uuid(),'payload_hash'=>str_repeat('b',64),'product_title'=>'Report item','product_snapshot'=>'{}','name'=>'PRIVATE PERSON','email'=>'PRIVATE@example.test','requirements'=>'PRIVATE message','created_at'=>now(),'updated_at'=>now()]);
  DB::table('marketing_enquiry_attributions')->insert(['enquiry_id'=>$e,'merchant_key'=>$c->merchant_key,'campaign_id'=>$c->id,'link_id'=>$link,'owner_id'=>$u->id,'session_key'=>'PRIVATE-SESSION','clicked_at'=>now(),'submitted_at'=>now()]);
  DB::table('marketing_analytics_events')->insert(['merchant_key'=>$c->merchant_key,'campaign_id'=>$c->id,'link_id'=>$link,'kind'=>'page_view','dedupe_key'=>'report-page','session_key'=>'PRIVATE-SESSION','occurred_at'=>now()]);
  $this->getJson('/admin/marketing/analytics/details?kind=enquiries')->assertOk()->assertJsonPath('rows.0.reference','ENQ-REPORT')->assertDontSee('PRIVATE');
  $this->getJson('/admin/marketing/analytics/record?kind=enquiry&id='.$e)->assertOk()->assertJsonPath('record.product','Report item')->assertJsonPath('record.detail_url','/admin/enquiries?enquiry='.$e)->assertDontSee('PRIVATE');
  $this->getJson('/admin/marketing/analytics/details?kind=events&event=page_view')->assertOk()->assertJsonPath('rows.0.events',1)->assertDontSee('PRIVATE');
  DB::table('marketing_analytics_events')->insert(['merchant_key'=>$c->merchant_key,'campaign_id'=>$c->id,'link_id'=>$link,'kind'=>'quotation_sent','dedupe_key'=>'report-quotation','details'=>json_encode(['enquiry_id'=>$e]),'occurred_at'=>now()]);
  $this->getJson('/admin/marketing/analytics/details?kind=events&event=quotation_sent')->assertOk()->assertJsonPath('rows.0.kind','enquiry')->assertJsonPath('rows.0.reference','ENQ-REPORT')->assertDontSee('PRIVATE');
  $this->getJson('/admin/marketing/analytics/details?kind=sources&campaign='.$c->id)->assertOk()->assertJsonPath('rows.0.enquiries',1)->assertJsonPath('rows.0.paid_orders',2);
  Http::assertNothingSent();
 }

 public function test_details_are_bounded_and_staff_reports_do_not_gain_enquiry_workflow_access():void {
  [$u,$c,$link]=$this->seedReport();
  for($n=0;$n<21;$n++)DB::table('marketing_tracking_links')->insert(['id'=>Str::uuid(),'campaign_id'=>$c->id,'post_id'=>Str::uuid(),'destination_id'=>Str::uuid(),'merchant_key'=>$c->merchant_key,'user_id'=>$u->id,'post_version'=>1,'destination_url'=>'http://localhost']);
  $this->actingAs($u)->getJson('/admin/marketing/analytics/details?kind=sources')->assertOk()->assertJsonPath('total',22)->assertJsonCount(20,'rows')->assertJsonPath('last_page',2);
  $this->getJson('/admin/marketing/analytics/details?kind=sources&page=2')->assertOk()->assertJsonCount(2,'rows');
  $u->update(['is_super_admin'=>false]);$u->permissions()->create(['permission'=>'marketing.view']);$u->permissions()->create(['permission'=>'marketing.view_analytics']);
  $this->getJson('/admin/marketing/analytics/details?kind=orders')->assertOk()->assertJsonPath('total',2);
  $this->get('/admin/marketing/analytics')->assertOk()->assertSee('conversion-reports.js')->assertDontSee('Everyday essentials launch');
 }

}
