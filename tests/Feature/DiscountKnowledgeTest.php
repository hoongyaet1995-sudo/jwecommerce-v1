<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\Support\DiscountKnowledgeService;
class DiscountKnowledgeTest extends TestCase {
 protected function setUp():void{parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');$this->assertSame(':memory:',DB::connection()->getDatabaseName());Schema::create('coupons',function(Blueprint $t){$t->id();$t->text('code');$t->text('name');$t->text('description')->nullable();$t->text('discount_type');$t->integer('value');$t->integer('min_spend')->nullable();$t->boolean('is_active');$t->integer('usage_count')->default(0);$t->integer('usage_limit')->nullable();$t->integer('per_customer_limit')->default(1);$t->timestamp('starts_at')->nullable();$t->timestamp('ends_at')->nullable();});}
 public function test_expired_code_enquiry_reports_status_without_recommending_it():void{
 DB::table('coupons')->insert(['id'=>1,'code'=>'MERDEKA20','name'=>'Merdeka','discount_type'=>'fixed_amount','value'=>2000,'is_active'=>true,'ends_at'=>now()->subDay()]);
 $s=app(DiscountKnowledgeService::class);$scope=['isEnabled'=>true,'activeIds'=>['1']];
 $this->assertSame([],$s->facts($scope,'Any available vouchers?'));
 $facts=$s->facts($scope,'i got merdeka20 voucher on hand, how can i redeem?');
 $this->assertCount(1,$facts);$data=json_decode($facts[0]['answer'],true);$this->assertSame('Expired',$data['status']);$this->assertFalse($data['redeemable_now']);$this->assertSame('specific_code',$data['query_mode']);
 $this->assertSame([],$s->facts(['isEnabled'=>true,'activeIds'=>[]],'MERDEKA20'));
 $this->assertSame([],$s->facts($scope,'MERDEKA200'));
 }
 public function test_only_explicitly_enabled_live_offers_are_supplied():void{
 foreach([['is_active'=>true],['is_active'=>false],['is_active'=>true,'ends_at'=>now()->subDay()],['is_active'=>true,'starts_at'=>now()->addDay()],['is_active'=>true,'usage_limit'=>1,'usage_count'=>1]] as $i=>$state)DB::table('coupons')->insert($state+['id'=>$i+1,'code'=>'TEST'.$i,'name'=>'Offer','discount_type'=>'fixed_amount','value'=>500,'min_spend'=>20000]);
 $s=app(DiscountKnowledgeService::class);$this->assertCount(5,$s->records());$this->assertSame([],$s->facts(['isEnabled'=>false,'activeIds'=>['1']]));$this->assertSame([],$s->facts(['isEnabled'=>true,'activeIds'=>[]]));$facts=$s->facts(['isEnabled'=>true,'activeIds'=>['1','2','3','4','5']]);$this->assertCount(1,$facts);$this->assertSame('discount:1',$facts[0]['id']);$data=json_decode($facts[0]['answer'],true);$this->assertSame('RM 5.00 OFF',$data['benefit']);$this->assertEquals(200,$data['minimumSpend']);$this->assertStringContainsString('Not verified',$data['eligibility']);
 }
}
