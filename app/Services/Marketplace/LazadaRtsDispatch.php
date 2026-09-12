<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaRtsDispatch
{
 public function __construct(private LazadaRtsPreflight $preflight,private LazadaRtsClient $client,private LazadaTokenManager $tokens) {}
 public function dispatch(int $id):array
 {
  if (config('marketplace.fulfillment_operations_enabled')!==true) throw new RuntimeException('Marketplace fulfillment is disabled.');
  $attempt=DB::transaction(function()use($id) {
   $task=DB::table('marketplace_fulfillment_attempts')->find($id);
   if (!$task||$task->operation!=='rts') throw new RuntimeException('RTS task unavailable.');
   $storeId=DB::table('marketplace_orders')->where('id',$task->order_id)->value('store_id');
   $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
   $order=DB::table('marketplace_orders')->where('id',$task->order_id)->lockForUpdate()->first();
   if (!$order||!$store||$store->status!=='connected'||$store->platform!=='lazada') throw new RuntimeException('Store is not connected.');
   if (DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','queued')->update(['status'=>'processing','updated_at'=>now()])!==1) throw new RuntimeException('RTS task was already claimed.');
   return $task;
  });
  $sent=false;
  try {
   $packages=json_decode($attempt->request,true,512,JSON_THROW_ON_ERROR)['packages'];
   $fresh=$this->preflight->inspect((int)$attempt->order_id,$packages);
   if (DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','processing')->update([
    'item_ids'=>json_encode($fresh['packages'],JSON_THROW_ON_ERROR),'updated_at'=>now(),
   ])!==1) throw new RuntimeException('RTS item review could not be persisted.');
   $token=$this->tokens->accessToken($fresh['store_id']);
   $sent=true;
   $result=$this->client->send($token,$packages);
   $status=$result['complete']?'succeeded':'partial';
   if (DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','processing')->update([
    'status'=>$status,'confirmed_result'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now(),
   ])!==1) throw new RuntimeException('Result persistence failed.');
   return ['id'=>$id,'status'=>$status,'result'=>$result];
  } catch (\Throwable) {
   try { DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status','processing')->update(['status'=>$sent?'uncertain':'rejected','updated_at'=>now()]); }
   catch (\Throwable) { /* A processing attempt remains claimed and cannot be resent. */ }
   throw new RuntimeException($sent?'RTS outcome is uncertain. Reconcile before retrying.':'RTS preflight failed. Refresh the order.');
  }
 }
}
