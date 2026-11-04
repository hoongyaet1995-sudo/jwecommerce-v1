<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\{Customer,User};
use App\Domain\Accounts\CustomerRewardsPayloadFactory;
class CustomerRewardsPayloadTest extends TestCase
{
 use RefreshDatabase;
 public function test_wallet_activity_is_scoped_and_paginated(): void
 {
  $customers=[];
  foreach(range(1,2) as $n){$u=User::factory()->create();$c=Customer::create(['user_id'=>$u->id,'email'=>$u->email]);$customers[]=$c;$w=DB::table('reward_wallets')->insertGetId(['customer_id'=>$c->id]);DB::table('reward_lots')->insert(['wallet_id'=>$w,'source'=>'test'.$n,'coins'=>500,'remaining'=>500,'expires_at'=>now()->addMonth()]);foreach(range(1,21) as $i)DB::table('reward_entries')->insert(['wallet_id'=>$w,'event_key'=>$n.':'.$i,'kind'=>'earned','coins'=>10,'created_at'=>now()]);}
  $p=app(CustomerRewardsPayloadFactory::class)->for($customers[0]);
  $this->assertSame(500,$p['balance']);$this->assertSame(21,$p['entries']->total());$this->assertCount(20,$p['entries']->items());$this->assertSame(500,(int)$p['expiry']->remaining);
 }
}
