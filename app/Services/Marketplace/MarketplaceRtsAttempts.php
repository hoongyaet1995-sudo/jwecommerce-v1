<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

final class MarketplaceRtsAttempts
{
 public function stage(int $orderId,int $actorId,string $key,array $packages):int
 {
  if (!Str::isUuid($key)||!$packages||!array_is_list($packages)||count($packages)>20) throw new DomainException('Invalid RTS request.');
  foreach ($packages as $id) if (!is_string($id)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$id)) throw new DomainException('Invalid package identity.');
  if (count($packages)!==count(array_unique($packages))) throw new DomainException('Duplicate package selection.');
  sort($packages,SORT_STRING);
  return DB::transaction(function()use($orderId,$actorId,$key,$packages) {
   if (!DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) throw new DomainException('Administrator required.');
   $storeId=DB::table('marketplace_orders')->where('id',$orderId)->value('store_id');
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $order=DB::table('marketplace_orders')->where('id',$orderId)->lockForUpdate()->first();
   if (!$order||!$store||$store->platform!=='lazada'||$store->status!=='connected'||$store->region!=='MY'||$store->environment!=='live') throw new DomainException('Connected Malaysia Lazada order required.');
   $existing=DB::table('marketplace_fulfillment_attempts')->where('request_key',$key)->first();
   if ($existing) {
    if ($existing->operation!=='rts'||(int)$existing->order_id!==$orderId||(int)$existing->actor_id!==$actorId
      ||json_decode($existing->request,true)!==['packages'=>$packages]) throw new DomainException('Request identity has another selection.');
    return (int)$existing->id;
   }
   // Package identity is scoped to the store, including attempts recorded on another order.
   $attempts=DB::table('marketplace_fulfillment_attempts as a')->join('marketplace_orders as o','o.id','=','a.order_id')
    ->where('o.store_id',$storeId)->where('a.operation','rts')->whereIn('a.status',['queued','processing','uncertain','partial','succeeded'])->select('a.request')->get();
   foreach ($attempts as $attempt) if (array_intersect($packages,json_decode($attempt->request,true)['packages']??[])) throw new DomainException('Package already has an RTS attempt. Reconcile before resubmitting.');
   return DB::table('marketplace_fulfillment_attempts')->insertGetId(['order_id'=>$orderId,'actor_id'=>$actorId,'request_key'=>$key,
    'operation'=>'rts','item_ids'=>'[]','request'=>json_encode(['packages'=>$packages],JSON_THROW_ON_ERROR),'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
  });
 }
}
