<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaPackingReconciliation
{
    public function __construct(private LazadaClient $client, private LazadaTokenManager $tokens) {}

    public function reconcile(int $id): array
    {
        $task=DB::table('marketplace_fulfillment_attempts')->find($id);
        if (!$task || $task->operation!=='pack' || !in_array($task->status,['uncertain','partial'],true)) throw new RuntimeException('Packing task is not awaiting reconciliation.');
        $order=DB::table('marketplace_orders')->find($task->order_id);
        $store=$order ? DB::table('marketplace_stores')->find($order->store_id) : null;
        if (!$store || $store->platform!=='lazada' || $store->status!=='connected' || $store->region!=='MY' || $store->environment!=='live') throw new RuntimeException('Connected store required.');
        $expected=json_decode($task->item_ids,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($expected) || !array_is_list($expected) || !$expected || count(array_unique($expected))!==count($expected)) throw new RuntimeException('Persisted packing item review required.');
        foreach ($expected as $line) if (!is_string($line) || !preg_match('/\A[1-9][0-9]*\z/D',$line)) throw new RuntimeException('Persisted packing item review required.');
        $items=$this->client->read('/order/items/get',$this->tokens->accessToken($store->id),['order_id'=>$order->external_order_id]);
        if (!$items || !array_is_list($items)) throw new RuntimeException('Complete order read required.');
        $seen=[]; $confirmed=[];
        foreach ($items as $item) {
            $line=(string)($item['order_item_id']??'');
            if ((string)($item['order_id']??'')!==$order->external_order_id || !preg_match('/\A[1-9][0-9]*\z/D',$line) || isset($seen[$line])) throw new RuntimeException('Order identity mismatch.');
            $seen[$line]=true;
            if (!in_array($line,$expected,true)) continue;
            $package=$item['package_id']??null;
            if (!is_string($package) || !preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$package)
                || !in_array($item['status']??null,['packed','ready_to_ship','ready_to_ship_pending','shipped','delivered'],true)) continue;
            $confirmed[$line]=['status'=>'packed_confirmed','package_id'=>$package];
        }
        if (count($confirmed)!==count($expected)) return ['id'=>$id,'status'=>$task->status,'confirmed'=>false];
        $result=['complete'=>true,'source'=>'provider_order_readback','items'=>$confirmed];
        $updated=DB::table('marketplace_fulfillment_attempts')->where('id',$id)->where('status',$task->status)->where('updated_at',$task->updated_at)
            ->update(['status'=>'succeeded','confirmed_result'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
        if ($updated!==1) throw new RuntimeException('Task changed during reconciliation.');
        return ['id'=>$id,'status'=>'succeeded','confirmed'=>true];
    }
}
