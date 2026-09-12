<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaRtsPreflight
{
 public function __construct(private LazadaClient $client,private LazadaTokenManager $tokens) {}
 public function inspect(int $orderId,array $packages):array
 {
  if (!$packages||!array_is_list($packages)||count($packages)>20) throw new RuntimeException('Invalid RTS selection.');
  foreach ($packages as $package) if (!is_string($package)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$package)) throw new RuntimeException('Invalid RTS selection.');
  if (count($packages)!==count(array_unique($packages))) throw new RuntimeException('Duplicate RTS selection.');
  $order=DB::table('marketplace_orders')->find($orderId);
  $store=$order?DB::table('marketplace_stores')->find($order->store_id):null;
  if (!$store||$store->platform!=='lazada'||$store->status!=='connected'||$store->region!=='MY'||$store->environment!=='live') throw new RuntimeException('Connected Malaysia Lazada order required.');
  $items=$this->client->read('/order/items/get',$this->tokens->accessToken($store->id),['order_id'=>$order->external_order_id]);
  if (!$items||!array_is_list($items)) throw new RuntimeException('Order items unavailable.');
  $seen=[];$matched=[];
  foreach ($items as $item) {
   $id=(string)($item['order_item_id']??'');
   if ((string)($item['order_id']??'')!==$order->external_order_id||!preg_match('/\A[1-9][0-9]*\z/D',$id)||isset($seen[$id])) throw new RuntimeException('Order identity could not be verified.');
   $seen[$id]=true;$package=$item['package_id']??null;
   if (!in_array($package,$packages,true)) continue;
   if (($item['status']??null)!=='packed'||!in_array($item['is_fbl']??null,[0,'0'],true)
    ||!in_array($item['is_digital']??null,[0,'0'],true)||!in_array($item['delivery_option_sof']??null,[0,'0'],true)
    ||($item['semi_managed']??null)!==false) throw new RuntimeException('Package is no longer eligible for RTS.');
   $matched[$package][]=$id;
  }
  if (count($matched)!==count($packages)) throw new RuntimeException('Package does not belong to this order.');
  return ['store_id'=>(int)$store->id,'order_id'=>$order->external_order_id,'packages'=>$matched];
 }
}
