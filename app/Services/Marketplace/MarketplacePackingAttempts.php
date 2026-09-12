<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

final class MarketplacePackingAttempts
{
 public function stage(int $orderId,int $actorId,string $requestKey,array $itemIds,string $allocationType,?string $providerId=null):int
 {
  if (!Str::isUuid($requestKey)||!$itemIds||!array_is_list($itemIds)||count($itemIds)!==count(array_unique($itemIds))
      ||!in_array($allocationType,['TFS','NTFS'],true)) throw new DomainException('Invalid packing request.');
  foreach ($itemIds as $id) if (!is_string($id)||!preg_match('/^[1-9][0-9]{0,29}$/D',$id)) throw new DomainException('Invalid packing item identity.');
  if ($providerId!==null&&!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$providerId)) throw new DomainException('Invalid packing provider.');
  sort($itemIds,SORT_STRING);
  $request=json_encode(['allocation_type'=>$allocationType,'provider_id'=>$providerId],JSON_THROW_ON_ERROR);
  return DB::transaction(function()use($orderId,$actorId,$requestKey,$itemIds,$request) {
   if (!DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) throw new DomainException('An authenticated administrator is required.');
   $storeId=DB::table('marketplace_orders')->where('id',$orderId)->value('store_id');
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $order=DB::table('marketplace_orders')->where('id',$orderId)->lockForUpdate()->first();
   if (!$order||!$store||$store->platform!=='lazada'||$store->status!=='connected') throw new DomainException('Lazada store is not connected.');
   $existing=DB::table('marketplace_fulfillment_attempts')->where('request_key',$requestKey)->first();
   if ($existing) {
    if ((int)$existing->order_id!==$orderId||(int)$existing->actor_id!==$actorId||$existing->operation!=='pack'
        ||json_decode($existing->item_ids,true)!==$itemIds||json_decode($existing->request,true)!==json_decode($request,true)) throw new DomainException('Request identity belongs to another reviewed selection.');
    return (int)$existing->id;
   }
   $attempts=DB::table('marketplace_fulfillment_attempts')->where('order_id',$orderId)->where('operation','pack')
      ->whereIn('status',['queued','processing','uncertain','partial','succeeded'])->get();
   foreach ($attempts as $attempt) if (array_intersect($itemIds,json_decode($attempt->item_ids,true))) throw new DomainException('Selected items already have a packing attempt. Reconcile before submitting again.');
   return DB::table('marketplace_fulfillment_attempts')->insertGetId(['order_id'=>$orderId,'actor_id'=>$actorId,'request_key'=>$requestKey,
      'operation'=>'pack','item_ids'=>json_encode($itemIds,JSON_THROW_ON_ERROR),'request'=>$request,'status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
  });
 }
}
