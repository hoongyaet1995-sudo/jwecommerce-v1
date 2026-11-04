<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, OrderPayment, OrderRefund, StoreSetting, User};
use App\Services\Rewards\RewardsWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RewardsWalletTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): array
    {
        StoreSetting::create(['key'=>'rewards_rules','value'=>['enabled'=>true,'coins_per_unit'=>100,'minimum_coins'=>100,'max_discount_percent'=>20,'expiry_months'=>12]]);
        $user=User::factory()->create();
        $customer=Customer::create(['user_id'=>$user->id,'email'=>$user->email,'name'=>'Buyer']);
        $this->actingAs($user,'customer');
        DB::table('reward_wallets')->insert(['customer_id'=>$customer->id,'created_at'=>now(),'updated_at'=>now()]);
        $wallet=DB::table('reward_wallets')->value('id');
        DB::table('reward_lots')->insert(['wallet_id'=>$wallet,'source'=>'opening','coins'=>1250,'remaining'=>1250,'expires_at'=>now()->addMonth(),'created_at'=>now(),'updated_at'=>now()]);
        $order=Order::create(['number'=>'REWARD-1','customer_id'=>$customer->id,'email'=>$user->email,'status'=>'pending_payment','currency'=>'MYR','subtotal'=>44900,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>0,'total'=>43650]);
        return [$customer,$order,app(RewardsWallet::class)];
    }

    public function test_reserve_settle_duplicate_callback_and_partial_full_refund(): void
    {
        [$customer,$order,$service]=$this->prepare();
        DB::transaction(fn()=> $service->attach($order,$service->quote(44900,$customer->id,true),['coins'=>2182,'offer_id'=>'a']));
        $this->assertSame(0,$service->balance($customer->id));
        $payment=OrderPayment::create(['order_id'=>$order->id,'gateway'=>'bank_transfer','status'=>'paid','amount'=>43650]);
        $service->synchronize($order);$payment->save();
        $this->assertSame(2182,$service->balance($customer->id));
        $this->assertSame(1,DB::table('reward_entries')->where('kind','earned')->count());
        OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'amount'=>21825,'reason'=>'Partial','status'=>'completed']);
        $this->assertSame(1716,$service->balance($customer->id)); // 2182 - 1091 + 625
        OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'amount'=>21825,'reason'=>'Remainder','status'=>'completed']);
        $service->synchronize($order);
        $this->assertSame(1250,$service->balance($customer->id));
    }

    public function test_cancellation_returns_reservation_once_and_keeps_original_expiry(): void
    {
        [$customer,$order,$service]=$this->prepare();
        $expiry=DB::table('reward_lots')->where('source','opening')->value('expires_at');
        DB::transaction(fn()=> $service->attach($order,$service->quote(44900,$customer->id,true),['coins'=>2182]));
        $order->update(['status'=>'cancelled']);$service->synchronize($order);
        $this->assertSame(1250,$service->balance($customer->id));
        $this->assertSame($expiry,DB::table('reward_lots')->where('source','opening')->value('expires_at'));
        $this->assertSame(1,DB::table('reward_entries')->where('kind','returned')->count());
        OrderPayment::create(['order_id'=>$order->id,'gateway'=>'bank_transfer','status'=>'paid','amount'=>43650]);
        $this->assertSame(1250,$service->balance($customer->id));
    }

    public function test_expired_coins_and_guest_cannot_redeem_and_cap_is_enforced(): void
    {
        [$customer,$order,$service]=$this->prepare();
        $this->assertSame(200,$service->quote(1000,$customer->id,true)['amount']);
        $this->assertSame(0,$service->quote(44900,null,true)['coins']);
        DB::table('reward_lots')->update(['expires_at'=>now()->subSecond()]);
        $this->assertSame(0,$service->balance($customer->id));
        $this->post('/checkout/rewards',['use_rewards'=>1])->assertRedirect('/checkout');
        auth('customer')->logout();
        $this->post('/checkout/rewards',['use_rewards'=>1])->assertForbidden();
    }

    public function test_item_refunds_exclude_redeemed_coins_and_preserve_minor_units(): void
    {
        $amounts=app(\App\Services\Promotions\OrderPromotionAmounts::class);
        $snapshot=$amounts->snapshot(['promotion_snapshot'=>['type'=>'coupon'],'discount'=>101,'tax'=>17,'rewards'=>['amount'=>79]],collect([
            ['variant'=>(object)['id'=>1,'price'=>100],'quantity'=>3],
            ['variant'=>(object)['id'=>2,'price'=>200],'quantity'=>1],
        ]));
        $order=new Order(['promotion_snapshot'=>$snapshot]);
        $cash=0;
        foreach($snapshot['lines'] as $id=>$line){
            $item=new \App\Models\OrderItem(['product_variant_id'=>$id]);
            for($offset=0;$offset<$line['quantity'];$offset++) $cash+=$amounts->itemAmount($order,$item,1,$offset);
        }
        $this->assertSame(79,array_sum(array_column($snapshot['lines'],'rewards')));
        $this->assertSame(337,$cash);
    }

    public function test_partial_payment_does_not_credit_and_preference_keeps_checkout_fields(): void
    {
        [$customer,$order,$service]=$this->prepare();
        DB::transaction(fn()=> $service->attach($order,$service->quote(44900,$customer->id,true),['coins'=>2182]));
        OrderPayment::create(['order_id'=>$order->id,'gateway'=>'bank_transfer','status'=>'paid','amount'=>100]);
        $this->assertSame(0,$service->balance($customer->id));
        OrderPayment::create(['order_id'=>$order->id,'gateway'=>'bank_transfer','status'=>'paid','amount'=>43550]);
        $this->assertSame(2182,$service->balance($customer->id));
        $this->post('/checkout/rewards',['use_rewards'=>1,'first_name'=>'Kept','line_1'=>'Saved road','password'=>'never-flash'])
            ->assertRedirect('/checkout')->assertSessionHas('_old_input.first_name','Kept')
            ->assertSessionHas('_old_input.line_1','Saved road')->assertSessionMissing('_old_input.password');
    }
}
