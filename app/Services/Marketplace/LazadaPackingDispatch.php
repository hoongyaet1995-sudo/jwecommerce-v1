<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaPackingDispatch
{
 public function __construct(private LazadaPackingPreflight $preflight,private LazadaPackingClient $client,private LazadaTokenManager $tokens) {}
 public function dispatch(int $id):array
 {
  if (config('marketplace.fulfillment_operations_enabled')!==true) throw new RuntimeException('Marketplace fulfillment is disabled.');
  $task=DB::transaction(function()use($id) {
   $task=DB::table('marketplace_fulfillment_attempts')->find($id);
   if (!$task||$task->operation!=='pack') throw new RuntimeException('Packing task is unavailable.');
   $storeId=DB::table('marketplace_orders')->where('id',$task->order_id)->value('store_id');
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $order=DB::table('marketplace_orders')->where('id',$task->order_id)->lockForUpdate()->first();
   if (!$store||$store->status!=='connected'||$store->platform!=='lazada'||!$order) throw new RuntimeException('Store is not connected.');
   if (DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','queued')->update(['status'=>'processing','updated_at'=>now()])!==1) throw new RuntimeException('Packing task was already claimed.');
   return [$task,(int)$storeId,$order->external_order_id];
  });
  [$attempt,$storeId,$orderId]=$task; $sent=false;
  try {
   $items=json_decode($attempt->item_ids,true,512,JSON_THROW_ON_ERROR);
   $request=json_decode($attempt->request,true,512,JSON_THROW_ON_ERROR);
   $fresh=$this->preflight->inspect($storeId,$orderId,$items,$request['allocation_type']);
   // Seller-selected carriers need an explicit, verified provider selection contract.
   if (!$fresh['platform_default']||$request['provider_id']!==null) throw new RuntimeException('Shipment provider selection requires review.');
   $token=$this->tokens->accessToken($storeId);
   $sent=true;
   $result=$this->client->pack($token,$orderId,$items,$request['allocation_type']);
   $status=$result['complete']?'succeeded':'partial';
   if (DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','processing')
       ->update(['status'=>$status,'confirmed_result'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now()])!==1) throw new RuntimeException('Packing result could not be persisted.');
   return ['id'=>$id,'status'=>$status,'result'=>$result];
  } catch (\Throwable) {
   DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','processing')->update(['status'=>$sent?'uncertain':'rejected','updated_at'=>now()]);
   throw new RuntimeException($sent?'Packing outcome is uncertain. Reconcile before retrying.':'Packing preflight failed. Refresh the order and review the selection.');
  }
 }
}
