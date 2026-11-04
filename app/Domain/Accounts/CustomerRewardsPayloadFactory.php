<?php
namespace App\Domain\Accounts;
use App\Models\Customer;
use App\Services\Rewards\RewardsWallet;
use Illuminate\Support\Facades\DB;
class CustomerRewardsPayloadFactory
{
 public function for(Customer $customer): array
 {
  $service=app(RewardsWallet::class); $rules=$service->rules();
  $wallet=DB::table('reward_wallets')->where('customer_id',$customer->id)->value('id');
  $balance=$service->balance($customer->id);
  $expiry=DB::table('reward_lots')->where('wallet_id',$wallet)->where('remaining','>',0)->where('expires_at','>',now())->orderBy('expires_at')->first();
  $filter=in_array(request('reward_filter'),['earned','used'],true)?request('reward_filter'):'all';
  $entries=DB::table('reward_entries as e')->leftJoin('orders as o','o.id','=','e.order_id')->where('e.wallet_id',$wallet)->where(fn($q)=>$q->whereNull('o.id')->orWhere('o.customer_id',$customer->id));
  if($filter==='earned')$entries->where('e.coins','>',0);
  if($filter==='used')$entries->where('e.coins','<',0);
  return ['enabled'=>(bool)($rules['enabled']??false),'balance'=>$balance,'value'=>intdiv($balance*100,max(1,(int)($rules['coins_per_unit']??100))),'rules'=>$rules,'expiry'=>$expiry,'filter'=>$filter,'entries'=>$entries->select('e.*')->orderByDesc('e.id')->paginate(20,['*'],'rewards_page')->withQueryString()->fragment('rewards')];
 }
}
