<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;
    public function test_attention_counts_use_latest_proof_and_processing_orders_without_effective_dispatch(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$ids=[];
        foreach(['processing','paid','processing'] as $i=>$status)$ids[]=DB::table('orders')->insertGetId(['number'=>'ATTENTION-'.$i,'email'=>'private@example.test','currency'=>'MYR','status'=>$status,'subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-11-02 01:00:00']);
        foreach([$ids[0],$ids[1]] as $i=>$id)DB::table('order_payments')->insert(['order_id'=>$id,'gateway'=>'bank_transfer','status'=>'pending','reference'=>'REVIEW-'.$i,'amount'=>1000,'currency'=>'MYR','proof_status'=>'uploaded','proof_path'=>'private/proof.jpg']);
        DB::table('order_payments')->insert(['order_id'=>$ids[1],'gateway'=>'bank_transfer','status'=>'failed','reference'=>'LATEST-REJECTED','amount'=>1000,'currency'=>'MYR','proof_status'=>'rejected']);
        DB::table('shipments')->insert(['order_id'=>$ids[2],'sequence'=>1,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'cancelled','dispatched_at'=>'2026-11-02 02:00:00','cancelled_at'=>'2026-11-02 03:00:00']);
        $response=$this->getJson('/admin/api/reports/orders?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('lifecycle.payment_review_orders',1)->assertJsonPath('lifecycle.processing_without_dispatch',2);
        $this->assertStringNotContainsString('private/proof',$response->getContent());
        DB::table('order_payments')->insert(['order_id'=>$ids[2],'gateway'=>'bank','status'=>'captured','reference'=>'METRIC-PAID','amount'=>1000,'currency'=>'MYR','confirmed_at'=>'2026-11-03 01:00:00']);
        $metric='/admin/api/reports/orders?from=2026-11-02&to=2026-11-02&currency=MYR&metric=paid_orders';
        $this->getJson($metric)->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.number','ATTENTION-2');
        $csv=$this->get($metric.'&export=csv')->assertOk()->streamedContent();$this->assertStringContainsString('ATTENTION-2',$csv);$this->assertStringNotContainsString('ATTENTION-0',$csv);
    }
    public function test_read_only_diagnostic_exposes_report_query_plans_on_request(): void
    {
        \Illuminate\Support\Facades\Artisan::call('reports:diagnose',['--explain'=>true]);
        $data=json_decode(\Illuminate\Support\Facades\Artisan::output(),true);
        $this->assertTrue($data['read_only']);
        $this->assertNotEmpty($data['query_plans']['sales_cashflow']);
        $this->assertStringNotContainsString('reports:prepare-insights',json_encode($data));
        $this->assertStringNotContainsString('AI chat and fixed evidence tools',json_encode($data));
        $this->assertGreaterThanOrEqual(0,$data['elapsed_ms']);
    }

    public function test_diagnostic_fails_when_a_report_dependency_column_is_missing(): void
    {
        // The read-only Insights view depends on this column. Remove the dependent
        // view in this isolated fault fixture before intentionally corrupting schema.
        DB::statement('DROP VIEW IF EXISTS ai_business_shipments');
        \Illuminate\Support\Facades\Schema::table('shipments',fn($table)=>$table->dropColumn('carrier_name'));
        $this->artisan('reports:diagnose')->expectsOutputToContain('carrier_name')->assertFailed();
    }

    public function test_enquiry_cohort_uses_unique_quotation_acceptance_links_without_contact_data(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $enquiry=(string)\Illuminate\Support\Str::uuid();$quote=(string)\Illuminate\Support\Str::uuid();$version=(string)\Illuminate\Support\Str::uuid();
        DB::table('product_enquiries')->insert(['id'=>$enquiry,'reference'=>'ENQ-REAL','owner_hash'=>str_repeat('a',64),'request_key'=>(string)\Illuminate\Support\Str::uuid(),'payload_hash'=>str_repeat('b',64),'product_title'=>'Snapshot','product_snapshot'=>'{}','name'=>'Private name','email'=>'private@example.test','requirements'=>'Private message','status'=>'closed','created_at'=>'2026-11-01 16:00:00']);
        DB::table('enquiry_quotations')->insert(['id'=>$quote,'product_enquiry_id'=>$enquiry,'number'=>'QT-REAL','status'=>'sent','document'=>'{}']);
        DB::table('enquiry_quotation_versions')->insert(['id'=>$version,'enquiry_quotation_id'=>$quote,'revision'=>1,'document'=>'{}','sent_at'=>'2026-11-02 01:00:00','expires_at'=>'2026-11-20']);
        DB::table('enquiry_quotation_versions')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'enquiry_quotation_id'=>$quote,'revision'=>2,'document'=>'{}','sent_at'=>'2026-11-03 01:00:00','expires_at'=>'2026-11-21']);
        $order=DB::table('orders')->insertGetId(['number'=>'FROM-QUOTE','email'=>'private@example.test','currency'=>'SGD','status'=>'pending','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0]);
        DB::table('quotation_acceptances')->insert(['enquiry_quotation_id'=>$quote,'enquiry_quotation_version_id'=>$version,'accepted_at'=>'2026-11-03 01:00:00','order_id'=>$order]);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank','status'=>'paid','reference'=>'QUOTE-PAID','amount'=>1000,'currency'=>'SGD','confirmed_at'=>'2026-11-03 02:00:00']);
        $response=$this->getJson('/admin/api/reports/enquiries?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()
            ->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.reference','ENQ-REAL')->assertJsonPath('records.data.0.order_number','FROM-QUOTE')->assertJsonPath('records.data.0.order_currency','SGD')
            ->assertJsonPath('lifecycle.enquiries_created',1)->assertJsonPath('lifecycle.enquiries_quoted',1)->assertJsonPath('lifecycle.enquiries_accepted',1)->assertJsonPath('lifecycle.enquiries_linked_to_orders',1)->assertJsonPath('lifecycle.paid_quote_orders',1)->assertJsonPath('lifecycle.average_conversion_hours',34)->assertJsonPath('lifecycle.conversion_sample_size',1);
        $this->assertStringNotContainsString('private@example.test',$response->getContent());
        $this->assertStringNotContainsString('Private message',$response->getContent());
        $open=(array)DB::table('product_enquiries')->where('id',$enquiry)->first();$open['id']=(string)\Illuminate\Support\Str::uuid();$open['reference']='ENQ-OPEN';$open['request_key']=(string)\Illuminate\Support\Str::uuid();$open['status']='following_up';DB::table('product_enquiries')->insert($open);
        $this->getJson('/admin/api/reports/enquiries?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('lifecycle.open_enquiries',1)->assertJsonPath('lifecycle.average_conversion_hours',34)->assertJsonPath('lifecycle.conversion_sample_size',1);
        $this->getJson('/admin/api/reports/enquiries?from=2026-11-02&to=2026-11-02&currency=MYR&metric=paid_quotes')->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.reference','ENQ-REAL');
        DB::table('order_payments')->where('order_id',$order)->update(['confirmed_at'=>'2026-11-01 15:00:00']);
        $this->getJson('/admin/api/reports/enquiries?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()->assertJsonPath('lifecycle.average_conversion_hours',null)->assertJsonPath('lifecycle.conversion_sample_size',0)->assertJsonPath('lifecycle.conversion_invalid_time_pairs',1);
    }

    public function test_order_lifecycle_counts_do_not_multiply_orders_or_confuse_cashflow_dates(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $id=DB::table('orders')->insertGetId(['number'=>'LIFECYCLE','email'=>'private@example.test','currency'=>'MYR','status'=>'cancelled','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-11-01 16:00:00']);
        DB::table('order_payments')->insert([
            ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'LIFE-A','amount'=>600,'currency'=>'MYR','confirmed_at'=>'2026-11-03 00:00:00'],
            ['order_id'=>$id,'gateway'=>'bank','status'=>'captured','reference'=>'LIFE-B','amount'=>400,'currency'=>'MYR','confirmed_at'=>'2026-11-03 01:00:00'],
            ['order_id'=>$id,'gateway'=>'bank','status'=>'pending','reference'=>'LIFE-C','amount'=>800,'currency'=>'MYR','confirmed_at'=>null],
            ['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'LIFE-UNDATED','amount'=>200,'currency'=>'MYR','confirmed_at'=>null],
        ]);
        DB::table('order_refunds')->insert(['order_id'=>$id,'amount'=>100,'status'=>'completed','reason'=>'test','completed_at'=>'2026-11-03 03:00:00']);
        DB::table('shipments')->insert([
            ['order_id'=>$id,'sequence'=>1,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'delivered','dispatched_at'=>'2026-11-03 02:00:00','delivered_at'=>'2026-11-04 01:00:00'],
            ['order_id'=>$id,'sequence'=>2,'carrier_name'=>'Manual','currency'=>'MYR','status'=>'pending','dispatched_at'=>null,'delivered_at'=>null],
        ]);
        $this->getJson('/admin/api/reports/orders?from=2026-11-02&to=2026-11-02&currency=MYR')->assertOk()
            ->assertJsonPath('lifecycle.orders_created',1)->assertJsonPath('lifecycle.orders_with_confirmed_payments',1)
            ->assertJsonPath('lifecycle.orders_dispatched',1)->assertJsonPath('lifecycle.orders_delivered',1)
            ->assertJsonPath('lifecycle.orders_with_completed_refunds',1)->assertJsonPath('lifecycle.confirmed_minor_to_date',1000)
            ->assertJsonPath('lifecycle.refunded_minor_to_date',100)->assertJsonPath('lifecycle.undated_confirmed_payments_excluded',1)->assertJsonPath('records.total',1);
        // Same date selection, different basis: none of these payments occurred in this day's cashflow.
        $this->getJson('/admin/api/reports/sales?from=2026-11-02&to=2026-11-02&currency=MYR')->assertJsonPath('totals.confirmed_minor',0);
    }

    public function test_empty_csv_keeps_schema_and_pagination_does_not_truncate_export(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $url='/admin/api/reports/orders?from=2026-11-04&to=2026-11-04&currency=MYR';
        $empty=$this->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('number,order_status,created_at,ordered_minor',$empty);
        for ($i=0;$i<22;$i++) DB::table('orders')->insert(['number'=>'PAGE-'.$i,'email'=>'hidden@example.test','currency'=>'MYR','status'=>'pending','subtotal'=>100,'total'=>100,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-11-04 01:00:00']);
        $this->getJson($url.'&page=2')->assertJsonPath('records.total',22)->assertJsonCount(2,'records.data');
        $csv=$this->get($url.'&page=2&export=csv')->assertOk()->streamedContent();
        $this->assertCount(23,array_filter(explode("\n",trim($csv))));
    }

    public function test_stale_report_grant_update_cannot_overwrite_newer_access(): void
    {
        $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $staff=User::factory()->create(['is_admin'=>true]);
        $staff->permissions()->create(['permission'=>'reports.sales.view']);
        $this->actingAs($owner)->putJson('/admin/api/reports/staff/'.$staff->id,['permissions'=>['reports.orders.view'],'expected_permissions'=>[]])->assertConflict();
        $this->assertTrue($staff->permissions()->where('permission','reports.sales.view')->exists());
    }

    public function test_report_staff_grants_are_owned_scoped_and_revocable(): void
    {
        $owner=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);
        $staff=User::factory()->create(['is_admin'=>true]);
        $staff->permissions()->create(['permission'=>'marketing.view']);
        $this->actingAs($staff)->getJson('/admin/api/reports/staff')->assertForbidden();
        $this->actingAs($owner)->getJson('/admin/api/reports/staff')->assertOk()->assertJsonCount(2,'data');
        $this->putJson('/admin/api/reports/staff/'.$staff->id,['permissions'=>['reports.orders.view'],'expected_permissions'=>[]])->assertOk();
        $this->assertTrue($staff->permissions()->where('permission','marketing.view')->exists());
        $this->actingAs($staff)->getJson('/admin/api/reports/orders')->assertOk();
        $this->getJson('/admin/api/reports/products?export=csv')->assertForbidden();
        $this->putJson('/admin/api/reports/staff/'.$staff->id,['permissions'=>['reports.products.view']])->assertForbidden();
        $this->actingAs($owner)->putJson('/admin/api/reports/staff/'.$staff->id,['permissions'=>['is_super_admin']])->assertUnprocessable();
        $this->putJson('/admin/api/reports/staff/'.$staff->id,['permissions'=>[],'expected_permissions'=>['reports.orders.view']])->assertOk();
        $this->actingAs($staff)->getJson('/admin/api/reports/orders?export=csv')->assertForbidden();
    }

    public function test_diagnostic_reports_untrusted_dates_without_repairing_them(): void
    {
        $this->artisan('reports:diagnose')->expectsOutputToContain('"read_only": true')->assertSuccessful();
    }

    public function test_canonical_customer_grouping_currency_and_csv_safety(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $customer=DB::table('customers')->insertGetId(['email'=>'same@example.test','first_name'=>'Private']);
        foreach ([['=SUM(1)', 'MYR', $customer],['SECOND','MYR',$customer],['SGD','SGD',$customer],['OUTSIDE','MYR',$customer],['GUEST','MYR',null]] as [$number,$currency,$customerId]) {
            DB::table('orders')->insert(['number'=>$number,'email'=>'same@example.test','customer_id'=>$customerId,'currency'=>$currency,'status'=>'pending','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>$number==='OUTSIDE'?'2026-11-04 16:00:00':'2026-11-03 16:00:00']);
        }
        $url='/admin/api/reports/customers?from=2026-11-04&to=2026-11-04&currency=MYR';
        $this->getJson($url)->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.orders',2)->assertJsonPath('records.data.0.ordered_minor',2000)->assertJsonPath('coverage.guest_orders',1);
        $csv=$this->get(str_replace('customers?','orders?',$url).'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1)",$csv);
        $this->assertStringNotContainsString('OUTSIDE',$csv);
        $this->assertStringNotContainsString('same@example.test',$csv);
        $this->getJson(str_replace('customers?','orders?',$url).'&search=%')->assertJsonPath('records.total',0);
    }

    public function test_operational_reports_are_protected_and_use_real_order_cohorts(): void
    {
        $url='/admin/api/reports/orders?from=2026-11-04&to=2026-11-04&currency=MYR';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        $order=DB::table('orders')->insertGetId(['number'=>'REAL-1','email'=>'private@example.test','currency'=>'MYR','status'=>'pending','subtotal'=>1000,'total'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'created_at'=>'2026-11-03 16:00:00','updated_at'=>'2026-11-04']);
        DB::table('order_items')->insert(['order_id'=>$order,'sku'=>'TEST-SKU','title'=>'Recorded item','unit_price'=>500,'quantity'=>2,'line_total'=>1000]);
        $this->getJson($url)->assertOk()->assertJsonPath('records.total',1)->assertJsonPath('records.data.0.number','REAL-1')->assertJsonMissing(['email'=>'private@example.test']);
        $this->getJson(str_replace('orders?','products?',$url))->assertOk()->assertJsonPath('records.data.0.units',2)->assertJsonPath('records.data.0.ordered_minor',1000);
        $this->getJson(str_replace('orders?','customers?',$url))->assertOk()->assertJsonPath('coverage.guest_orders',1)->assertJsonPath('records.total',0);
        $csv=$this->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('REAL-1',$csv);
        $this->assertStringNotContainsString('private@example.test',$csv);
        $this->getJson($url.'&search=missing')->assertJsonPath('records.total',0);
        $this->getJson($url.'&from=2026-11-05')->assertUnprocessable();
        $this->getJson(str_replace('orders?','shipping?',$url))->assertOk()->assertJsonPath('records.total',0);
        $this->getJson('/admin/api/reports/arbitrary')->assertNotFound();
    }
}
