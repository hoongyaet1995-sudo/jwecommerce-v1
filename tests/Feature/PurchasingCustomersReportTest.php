<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasingCustomersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_currency_history_does_not_become_a_new_buyer_claim(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $id=DB::table('customers')->insertGetId(['email'=>'private@example.test']);
        $order=DB::table('orders')->insertGetId(['number'=>'INCOMPLETE-HISTORY','email'=>'private@example.test','customer_id'=>$id,'currency'=>'MYR','status'=>'pending','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
        DB::table('order_payments')->insert([
            ['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'KNOWN-NOW','amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00'],
            ['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'UNKNOWN-CURRENCY','amount'=>500,'currency'=>null,'confirmed_at'=>'2026-10-01 01:00:00'],
        ]);
        $this->getJson('/admin/api/reports/customer-cohorts?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('stats.new_customers',0)->assertJsonPath('stats.unclassified_customers',1);
    }

    public function test_purchasing_cohorts_use_first_verified_payment_and_preserve_unknown_history(): void
    {
        $url='/admin/api/reports/customer-cohorts?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $ids=[];
        foreach(['new','returning','unknown'] as $kind){
            $customer=DB::table('customers')->insertGetId(['email'=>$kind.'@example.test']);$ids[$kind]=$customer;
            $order=DB::table('orders')->insertGetId(['number'=>'BUYER-'.$kind,'email'=>'private@example.test','customer_id'=>$customer,'currency'=>'MYR','status'=>'cancelled','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-09-01']);
            DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'COHORT-'.$kind,'amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00']);
            if($kind!=='new')DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'captured','reference'=>'HISTORY-'.$kind,'amount'=>500,'currency'=>'MYR','confirmed_at'=>$kind==='returning'?'2026-10-01 01:00:00':null]);
            if($kind==='new')DB::table('order_refunds')->insert(['order_id'=>$order,'amount'=>200,'status'=>'completed','reason'=>'test','completed_at'=>'2026-11-03 01:00:00']);
        }
        $r=$this->getJson($url)->assertOk()->assertJsonPath('records.total',3)->assertJsonPath('stats.purchasing_customers',3)->assertJsonPath('stats.new_customers',1)->assertJsonPath('stats.returning_customers',1)->assertJsonPath('stats.unclassified_customers',1)->assertJsonPath('coverage.undated_confirmed_payments',1);
        $rows=collect($r->json('records.data'))->keyBy('customer_id');
        $this->assertSame('new',$rows[$ids['new']]['cohort']);
        $this->assertSame(800,$rows[$ids['new']]['net_paid_minor']);
        $this->assertSame('returning',$rows[$ids['returning']]['cohort']);
        $this->assertSame('unknown',$rows[$ids['unknown']]['cohort']);
        $this->assertStringNotContainsString('@example.test',$r->getContent());
        $this->getJson($url.'&metric=new_buyers')->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.cohort','new');
        $this->getJson($url.'&metric=returning_buyers')->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.cohort','returning');
        $this->getJson($url.'&search='.$ids['returning'])->assertJsonPath('records.total',1)->assertJsonPath('stats.returning_customers',1);
        $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('net_paid_minor',$csv);
        $this->assertStringNotContainsString('@example.test',$csv);
        DB::table('customers')->where('id',$ids['new'])->update(['email'=>'invalid','phone'=>null]);
        $this->getJson($url)->assertOk()->assertJsonPath('stats.purchasing_customers',2);
        DB::table('customers')->where('id',$ids['new'])->update(['phone'=>'+60123456789']);
        $this->getJson($url)->assertOk()->assertJsonPath('stats.purchasing_customers',3);
        $order=DB::table('orders')->where('customer_id',$ids['new'])->value('id');
        DB::table('order_refunds')->insert(['order_id'=>$order,'amount'=>50,'status'=>'completed','reason'=>'Missing historical completion time','completed_at'=>null]);
        $this->getJson($url.'&search='.$ids['new'])->assertOk()->assertJsonPath('records.data.0.net_paid_minor',null)->assertJsonPath('records.data.0.refunded_minor_to_date',null)->assertJsonPath('coverage.undated_completed_refunds',1);
    }
}
