<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
class AuthBoundaryTest extends TestCase
{
 protected function setUp():void
 {
  parent::setUp();
  config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
  \Illuminate\Support\Facades\DB::purge('sqlite');
  \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
  $this->assertSame(':memory:',\Illuminate\Support\Facades\DB::connection()->getDatabaseName());
  \Illuminate\Support\Facades\Schema::create('users',function($t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->boolean('is_admin');$t->string('remember_token')->nullable();$t->timestamps();});
  \Illuminate\Support\Facades\Schema::create('customers',function($t){$t->id();$t->integer('user_id');});
 }
 public function test_customer_login_cannot_use_admin_intended_destination():void
 {
  $user=User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'test-password','is_admin'=>false]);
  \Illuminate\Support\Facades\DB::table('customers')->insert(['user_id'=>$user->id]);
  $this->withSession(['url.intended'=>url('/admin')])->post('/account/login',['email'=>$user->email,'password'=>'test-password'])->assertRedirect('/account');
  $this->assertAuthenticatedAs($user,'customer');
  $this->assertGuest('web');
 }
 public function test_admin_login_cannot_use_customer_intended_destination():void
 {
  $user=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'test-password','is_admin'=>true]);
  $this->withSession(['url.intended'=>url('/account')])->post('/admin/login',['email'=>$user->email,'password'=>'test-password'])->assertRedirect('/admin');
  $this->assertAuthenticatedAs($user,'web');
  $this->assertGuest('customer');
 }
 public function test_customer_guest_redirect_ignores_admin_identity():void
 {
  $admin=new User; $admin->id=9001; $admin->is_admin=true;
  $customer=new User; $customer->id=9002; $customer->is_admin=false;
  Auth::guard('web')->setUser($admin);
  Auth::guard('customer')->setUser($customer);
  $this->get('/account/login')->assertRedirect('/account');
 }
 public function test_admin_guest_redirect_ignores_customer_identity():void
 {
  $admin=new User; $admin->id=9001; $admin->is_admin=true;
  $customer=new User; $customer->id=9002; $customer->is_admin=false;
  Auth::guard('web')->setUser($admin);
  Auth::guard('customer')->setUser($customer);
  $this->get('/admin/login')->assertRedirect('/admin');
 }
}
