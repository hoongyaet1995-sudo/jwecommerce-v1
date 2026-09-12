<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaRtsReconciliation
{
 public function __construct(private LazadaClient $client,private LazadaTokenManager $tokens) {}
 public function reconcile(int $id):array
 {
  $task=DB::table('marketplace_fulfillment_attempts')->find($id);
  if (!$task||$task->operation!=='rts'||!in_array($task->status,['uncertain','partial'],true)) throw new RuntimeException('RTS task is not awaiting reconciliation.');
  $order=DB::table('marketplace_orders')->find($task->order_id);
  $store=$order?DB::table('marketplace_stores')->find($order->store_id):null;
  if (!$store||$store->platform!=='lazada'||$store->status!=='connected'||$store->region!=='MY'||$store->environment!=='live') throw new RuntimeException('Connected store required.');
  $packages=json_decode($task->request,true,512,JSON_THROW_ON_ERROR)['packages'];
  $expected=json_decode($task->item_ids,true,512,JSON_THROW_ON_ERROR);
  if (!is_array($expected)||!$expected||count($expected)!==count($packages)) throw new RuntimeException('Persisted RTS item review required.');
  foreach ($packages as $package) if (!isset($expected[$package])||!is_array($expected[$package])||!$expected[$package]) throw new RuntimeException('Persisted RTS item review required.');
  $items=$this->client->read('/order/items/get',$this->tokens->accessToken($store->id),['order_id'=>$order->external_order_id]);
  if (!$items||!array_is_list($items)) throw new RuntimeException('Complete order read required.');
  $seen=[];$confirmed=[];$observed=[];
  foreach ($items as $item) {
   $line=(string)($item['order_item_id']??'');$package=$item['package_id']??null;
   if ((string)($item['order_id']??'')!==$order->external_order_id||!preg_match('/\A[1-9][0-9]*\z/D',$line)||isset($seen[$line])) throw new RuntimeException('Order identity mismatch.');
   $seen[$line]=true;
   if (!in_array($package,$packages,true)) continue;
   $observed[$package][]=$line;
   $confirmed[$package]=($confirmed[$package]??true)&&in_array($item['status']??null,['ready_to_ship','ready_to_ship_pending','shipped','delivered'],true);
  }
  foreach ($packages as $package) {
   $before=$expected[$package];$after=$observed[$package]??[];
   sort($before,SORT_STRING);sort($after,SORT_STRING);
   if ($before!==$after) return ['id'=>$id,'status'=>$task->status,'confirmed'=>false];
  }
  if (count($confirmed)!==count($packages)||in_array(false,$confirmed,true)) return ['id'=>$id,'status'=>$task->status,'confirmed'=>false];
  $result=['complete'=>true,'source'=>'provider_order_readback','packages'=>array_fill_keys($packages,['status'=>'ready_to_ship_confirmed'])];
  $updated=DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status',$task->status)->where('updated_at',$task->updated_at)
   ->update(['status'=>'succeeded','confirmed_result'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
  if ($updated!==1) throw new RuntimeException('Task changed during reconciliation.');
  return ['id'=>$id,'status'=>'succeeded','confirmed'=>true];
 }
}
