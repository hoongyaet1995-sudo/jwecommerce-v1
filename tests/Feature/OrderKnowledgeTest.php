<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\SupportConversation;
use App\Services\Support\OrderKnowledgeService;
use App\Services\Support\SupportChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
class OrderKnowledgeTest extends TestCase {
 protected function setUp():void{parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::setDefaultConnection('sqlite');$this->assertSame(':memory:',DB::connection()->getDatabaseName());
 Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin')->default(false);$t->timestamps();});
 Schema::create('customers',function(Blueprint $t){$t->id();$t->integer('user_id');});
 Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->string('number');$t->string('status');$t->string('email');$t->string('tracking_carrier')->nullable();$t->string('tracking_number')->nullable();});
 Schema::create('order_tracking_verifications',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('public_id');$t->timestamp('verified_at')->nullable();});
 DB::table('orders')->insert([['id'=>1,'customer_id'=>1,'number'=>'JW-1001','status'=>'shipped','email'=>'private@example.com'],['id'=>2,'customer_id'=>2,'number'=>'JW-2002','status'=>'SECRET_OTHER_STATUS','email'=>'other@example.com']]);
 $r=\Illuminate\Http\Request::create('/support/chat');$r->setLaravelSession(app('session')->driver());app()->instance('request',$r);
 }
 private function scope():array{return ['isEnabled'=>true,'allowedFields'=>['orderStatus'=>true]];}
 private function conversation():SupportConversation{[$hash,$id]=app(SupportChatService::class)->owner(request());return new SupportConversation(['owner_hash'=>$hash,'customer_user_id'=>$id]);}
 public function test_customer_ownership_field_permissions_and_conversation_binding():void{
 $u=User::create(['name'=>'Test','email'=>'private@example.com','password'=>'not-used']);$this->actingAs($u,'customer');DB::table('customers')->insert(['id'=>1,'user_id'=>$u->id]);$c=$this->conversation();$s=app(OrderKnowledgeService::class);
 $this->assertStringContainsString('shipped',$s->reply('JW-1001',$this->scope(),$c));$this->assertStringNotContainsString('private@example.com',$s->reply('JW-1001',$this->scope(),$c));$this->assertStringNotContainsString('SECRET_OTHER_STATUS',$s->reply('JW-2002',$this->scope(),$c));
 $this->assertStringNotContainsString('shipped',$s->reply('JW-1001',['isEnabled'=>true,'allowedFields'=>[]],$c));$this->assertSame('',$s->reply('JW-1001',['isEnabled'=>false],$c));$c->owner_hash=str_repeat('0',64);$this->assertStringContainsString('Please sign in',$s->reply('JW-1001',$this->scope(),$c));
 }
 public function test_guest_needs_current_same_session_verified_attempt():void{
 $s=app(OrderKnowledgeService::class);$c=$this->conversation();$this->assertStringContainsString('verify',$s->reply('JW-1001',$this->scope(),$c));
 DB::table('order_tracking_verifications')->insert(['public_id'=>'verified','order_id'=>1,'verified_at'=>now()]);request()->session()->put('order_tracking.verified_attempt_id','verified');$this->assertStringContainsString('shipped',$s->reply('JW-1001',$this->scope(),$c));$this->assertStringNotContainsString('SECRET_OTHER_STATUS',$s->reply('JW-2002',$this->scope(),$c));
 DB::table('order_tracking_verifications')->update(['verified_at'=>now()->subMinutes(16)]);$this->assertStringContainsString('verify',$s->reply('JW-1001',$this->scope(),$c));
 }
}
