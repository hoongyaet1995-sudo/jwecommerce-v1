<?php
namespace Tests\Feature;

use App\Models\{User,MarketingCampaign};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingReportBridgeTest extends TestCase
{
    use RefreshDatabase;
    public function test_reports_reuses_private_marketing_analytics_and_preserves_refund_cohort_basis(): void
    {
        Http::preventStrayRequests();
        $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$other=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $merchant=app(\App\Services\Marketing\MarketingPosts::class)->merchant();$ids=[];
        foreach([$owner,$other] as $i=>$user){
            $campaign=MarketingCampaign::create(['merchant_key'=>$merchant,'user_id'=>$user->id,'name'=>$i===0?'=Owned campaign':'Private other campaign','objective'=>'Purchases','status'=>'Active','starts_on'=>'2026-11-01','ends_on'=>'2026-11-30','timezone'=>'Asia/Kuala_Lumpur','destination_url'=>'http://localhost','cta'=>'Shop now']);$ids[]=$campaign->id;
            DB::table('marketing_analytics_events')->insert(['merchant_key'=>$merchant,'campaign_id'=>$campaign->id,'link_id'=>(string)Str::uuid(),'kind'=>'page_view','dedupe_key'=>'report-page-'.$i,'session_key'=>'secret-session-'.$i,'occurred_at'=>'2026-11-02 01:00:00']);
        }
        $order=DB::table('orders')->insertGetId(['number'=>'ATTRIBUTED','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
        DB::table('marketing_order_attributions')->insert(['order_id'=>$order,'merchant_key'=>$merchant,'campaign_id'=>$ids[0],'link_id'=>(string)Str::uuid(),'owner_id'=>$owner->id,'session_key'=>'private-session','clicked_at'=>'2026-11-01','placed_at'=>'2026-11-01','base_currency'=>'MYR','base_minor'=>1000,'state'=>'paid','paid_at'=>'2026-11-02 01:00:00','refunded_minor'=>200]);
        $url='/admin/api/reports/marketing?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->getJson($url)->assertUnauthorized();
        $staff=User::factory()->create(['is_admin'=>true]);$staff->permissions()->create(['permission'=>'reports.marketing.view']);
        $this->actingAs($staff)->getJson($url)->assertForbidden();
        $response=$this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('stats.page_views',1)->assertJsonPath('stats.attributed_net_minor',800);
        $this->assertStringNotContainsString('Private other campaign',$response->getContent());$this->assertStringNotContainsString('secret-session',$response->getContent());
        $this->getJson($url.'&search=missing')->assertJsonPath('records.total',0)->assertJsonPath('stats.page_views',0);
        $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();$this->assertStringContainsString("'=Owned campaign",$csv);
        $this->actingAs($other)->getJson($url)->assertJsonPath('records.data.0.campaign_id',$ids[1])->assertJsonPath('stats.attributed_net_minor',0);
        DB::table('marketing_order_attributions')->where('order_id',$order)->update(['state'=>'money_unconfirmed']);
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('stats.attributed_net_minor',null)->assertJsonPath('records.data.0.attributed_net_minor',null);
        Http::assertNothingSent();
    }
    public function test_retired_cross_staff_argument_cannot_expand_marketing_owner_scope():void {
        Http::preventStrayRequests();$owner=User::factory()->create(['is_admin'=>true]);$other=User::factory()->create(['is_admin'=>true]);$merchant=app(\App\Services\Marketing\MarketingPosts::class)->merchant();$mine=null;
        foreach([$owner,$other] as $i=>$user){$c=MarketingCampaign::create(['merchant_key'=>$merchant,'user_id'=>$user->id,'name'=>'Scope '.$i,'objective'=>'Purchases','status'=>'Active','starts_on'=>'2026-11-01','ends_on'=>'2026-11-30','timezone'=>'Asia/Kuala_Lumpur','destination_url'=>'http://localhost','cta'=>'Shop now']);if($i===0)$mine=$c->id;}
        $request=\Illuminate\Http\Request::create('/admin/marketing/analytics/data','GET',['from'=>'2026-11-01','to'=>'2026-11-05']);$request->setUserResolver(fn()=>$owner);$r=app(\App\Services\Marketing\MarketingAnalytics::class)->report($request,true);$this->assertSame([$mine],array_column($r['campaigns'],'id'));Http::assertNothingSent();
    }

}
