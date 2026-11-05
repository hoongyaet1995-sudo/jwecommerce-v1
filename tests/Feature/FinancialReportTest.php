<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;
    public function test_query_failure_before_reader_middleware_is_also_sanitized(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        \Illuminate\Support\Facades\Route::get('/__test/report-read-failure',fn()=>response()->json([]))
            ->middleware([MissingDependencyBeforeReportBoundary::class,\App\Http\Middleware\ReportReadBoundary::class]);
        $response=$this->getJson('/__test/report-read-failure')->assertStatus(503)->assertJsonPath('answer_kind','unavailable');
        $this->assertStringNotContainsString('SQLSTATE',$response->getContent());
    }
    public function test_missing_read_dependency_returns_a_safe_failure_without_sql_details(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
        \Illuminate\Support\Facades\Schema::drop('order_tax_lines');
        \Illuminate\Support\Facades\Log::spy();
        $response=$this->getJson('/admin/api/reports/financial?from=2026-11-02&to=2026-11-02&currency=MYR')->assertStatus(503)->assertJsonPath('message','Report data is temporarily unavailable. Please retry later.');
        $this->assertStringNotContainsString('order_tax_lines',$response->getContent());
        $this->assertStringNotContainsString('SQLSTATE',$response->getContent());
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('log');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('reports.read_unavailable',\Mockery::on(fn($context)=>!str_contains(json_encode($context),'order_tax_lines')))->once();
    }
    public function test_financial_access_is_separate_and_does_not_invent_settlements(): void
    {
        $staff=User::factory()->create(['is_admin'=>true]);
        $staff->permissions()->create(['permission'=>'reports.sales.view']);
        $url='/admin/api/reports/financial?from=2026-11-02&to=2026-11-02&currency=MYR';
        $this->actingAs($staff)->getJson($url)->assertForbidden();
        $staff->permissions()->create(['permission'=>'reports.financial.view']);
        $order=DB::table('orders')->insertGetId(['number'=>'FINANCIAL-1','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>1000,'total'=>1060,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>60]);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'FINANCE','amount'=>1060,'currency'=>'MYR','confirmed_at'=>'2026-11-02 01:00:00','proof_status'=>'verified','proof_reviewed_at'=>'2026-11-02 00:59:00','proof_path'=>'private/proof.jpg']);
        $response=$this->getJson($url)->assertOk()->assertJsonPath('totals.confirmed_minor',1060)->assertJsonPath('reconciliation.provider_settlement_minor',null)->assertJsonPath('reconciliation.actual_fees_minor',null)->assertJsonPath('reconciliation.cod_received_minor',null)->assertJsonPath('proof_reviews.0.proof_status','verified')->assertJsonPath('proof_reviews.0.reviews',1)->assertJsonPath('tax_snapshot.unverified_orders',1)->assertJsonPath('tax_snapshot.recorded_order_tax_minor',null);
        DB::table('order_tax_lines')->insert(['order_id'=>$order,'label'=>'Historical tax','tax_type'=>'sales_tax','rate_percent'=>6,'taxable_amount'=>1000,'tax_amount'=>60,'configuration_snapshot'=>'{"version":1,"rate_percent":6}']);
        $this->getJson($url)->assertOk()->assertJsonPath('tax_snapshot.recorded_order_tax_minor',60)->assertJsonPath('tax_snapshot.verified_orders',1)->assertJsonPath('reconciliation.refund_tax_minor',null);
        DB::table('order_payments')->insert(['order_id'=>$order,'gateway'=>'bank_transfer','status'=>'paid','reference'=>'FINANCE-SECOND','amount'=>100,'currency'=>'MYR','confirmed_at'=>'2026-11-02 02:00:00']);
        $this->getJson($url)->assertJsonPath('tax_snapshot.recorded_order_tax_minor',60);
        $this->assertStringNotContainsString('private/proof',$response->getContent());
        $this->assertStringNotContainsString('private@example',$response->getContent());
        $this->get($url.'&export=csv')->assertOk();
        $this->getJson('/admin/api/reports/sales')->assertOk();
    }
}

class MissingDependencyBeforeReportBoundary
{
    public function handle($request,\Closure $next)
    {
        DB::table('missing_report_read_dependency')->first();
        return $next($request);
    }
}
