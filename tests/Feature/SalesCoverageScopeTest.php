<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan,DB};
use Tests\TestCase;
class SalesCoverageScopeTest extends TestCase
{
 use RefreshDatabase;
 public function test_diagnostic_all_currency_population_and_sales_currency_warning_are_distinct():void {
  $this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));
  $id=DB::table('orders')->insertGetId(['number'=>'COVERAGE','email'=>'private@example.test','currency'=>'MYR','status'=>'paid','subtotal'=>100,'total'=>100]);
  foreach(['MYR','SGD',null] as $i=>$currency)DB::table('order_payments')->insert(['order_id'=>$id,'gateway'=>'bank','status'=>'paid','reference'=>'UNDATED-'.$i,'currency'=>$currency,'amount'=>$currency===null?null:100,'confirmed_at'=>null]);
  Artisan::call('reports:diagnose');$r=json_decode(Artisan::output(),true);
  $this->assertSame(3,$r['coverage']['undated_confirmed_payments']);
  $rows=collect($r['undated_payment_scope']['by_currency'])->keyBy('currency');$this->assertSame(1,$rows['MYR']['count']);$this->assertSame(1,$rows['SGD']['count']);$this->assertSame(1,$rows['missing_or_invalid']['count']);
  $this->getJson('/admin/api/reports/sales?from=2026-10-01&to=2026-10-01&currency=MYR&search=does-not-match')->assertOk()->assertJsonPath('coverage.undated_payments',1)->assertJsonPath('coverage.incomplete_payment_money_all_currencies',1)->assertJsonPath('coverage.undated_scope','All recorded history in MYR; independent of the selected dates and search. Undated events cannot be assigned to a reporting period.');
  $this->assertDatabaseCount('order_payments',3);
 }
}
