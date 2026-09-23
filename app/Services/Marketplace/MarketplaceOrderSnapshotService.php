<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceOrderSnapshotService
{
    public function __construct(private MarketplaceOrderInventoryService $inventory) {}

    public function saveShopee(int $storeId, #[\SensitiveParameter] array $order): int
    {
        if (!is_string($order['order_sn'] ?? null) || $order['order_sn'] === '' || strlen($order['order_sn']) > 100
            || !is_string($order['order_status'] ?? null) || $order['order_status'] === '' || strlen($order['order_status']) > 60
            || !is_int($order['update_time'] ?? null) || $order['update_time'] < 0) {
            throw new RuntimeException('Marketplace order identity, status or version is incomplete.');
        }

        return DB::transaction(function () use ($storeId, $order) {
            $store = DB::table('marketplace_stores')->where('id', $storeId)->lockForUpdate()->first();
            if (!$store || $store->platform !== 'shopee' || $store->status !== 'connected') {
                throw new RuntimeException('Store is not connected for order ingestion.');
            }
            if ($store->region === 'SG' && ($order['currency'] ?? null) !== 'SGD') {
                throw new RuntimeException('Shopee SG order currency is unavailable or inconsistent.');
            }
            $key = ['store_id'=>$storeId, 'external_order_id'=>$order['order_sn']];
            $existing = DB::table('marketplace_orders')->where($key)->first();
            if ($existing && $existing->platform_updated_at >= $order['update_time']) {
                $this->inventory->process((int) $existing->id);
                return $existing->id;
            }
            $values = ['platform_status'=>$order['order_status'], 'platform_updated_at'=>$order['update_time'],
                'currency'=>$order['currency'] ?? null, 'total_amount'=>$order['total_amount'] ?? null,
                'encrypted_payload'=>Crypt::encryptString(json_encode($order, JSON_THROW_ON_ERROR)),
                'fetched_at'=>now(), 'updated_at'=>now()];
            if ($existing) {
                DB::table('marketplace_orders')->where('id', $existing->id)->update($values);
                $this->inventory->process((int) $existing->id);
                return $existing->id;
            }

            $id = DB::table('marketplace_orders')->insertGetId($key + $values + app(\App\Services\Inventory\TestDataClassificationService::class)->inherited($store) + ['created_at'=>now()]);
            $this->inventory->process($id);
            return $id;
        });
    }

    /**
     * Lazada represents status per order item. The normalized order status is
     * display-only; stock processing uses each item's status under store policy.
     */
    public function saveLazada(int $storeId, #[\SensitiveParameter] array $order): int
    {
        $id=$order['order_id'] ?? null;
        $updated=$order['updated_at'] ?? null;
        $statuses=$order['statuses'] ?? null;
        if ((!is_string($id) && !is_int($id)) || (string)$id==='' || strlen((string)$id)>100
            || !is_string($updated) || !is_array($statuses) || !array_is_list($statuses)
            || array_filter($statuses,fn($status)=>!is_string($status)||$status==='')) {
            throw new RuntimeException('Lazada order identity, item status or update time is incomplete.');
        }
        try {$updatedAt=\Carbon\CarbonImmutable::parse($updated)->getTimestamp();}
        catch (\Throwable) {throw new RuntimeException('Lazada order update time is invalid.');}
        if($updatedAt<0)throw new RuntimeException('Lazada order update time is invalid.');
        $status=implode('|',array_values(array_unique($statuses)));
        if($status===''||strlen($status)>60)throw new RuntimeException('Lazada order item status is incomplete.');

        return DB::transaction(function()use($storeId,$order,$id,$updatedAt,$status){
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if(!$store||$store->platform!=='lazada'||$store->status!=='connected') {
                throw new RuntimeException('Store is not connected for Lazada order ingestion.');
            }
            $key=['store_id'=>$storeId,'external_order_id'=>(string)$id];
            $existing=DB::table('marketplace_orders')->where($key)->first();
            if($existing&&($existing->platform_updated_at>$updatedAt
                || ($existing->platform_updated_at==$updatedAt&&!$this->hasNewerLazadaItems($existing,$order)))){
                if ($existing->currency===null) {
                    $savedPayload=json_decode(Crypt::decryptString($existing->encrypted_payload),true,512,JSON_THROW_ON_ERROR);
                    $currency=LazadaOrderCurrency::fromPayload($savedPayload);
                    if ($currency!==null) DB::table('marketplace_orders')->where('id',$existing->id)->update(['currency'=>$currency]);
                }
                $this->inventory->process((int)$existing->id);
                return $existing->id;
            }
            $values=['platform_status'=>$status,'platform_updated_at'=>$updatedAt,
                'currency'=>LazadaOrderCurrency::fromPayload($order),
                'total_amount'=>is_numeric($order['price']??null)?$order['price']:null,
                'encrypted_payload'=>Crypt::encryptString(json_encode($order,JSON_THROW_ON_ERROR)),
                'fetched_at'=>now(),'updated_at'=>now()];
            if($existing){
                DB::table('marketplace_orders')->where('id',$existing->id)->update($values);
                $this->inventory->process((int)$existing->id);
                return $existing->id;
            }
            $saved=DB::table('marketplace_orders')->insertGetId($key+$values+app(\App\Services\Inventory\TestDataClassificationService::class)->inherited($store)+['created_at'=>now()]);
            $this->inventory->process($saved);
            return $saved;
        });
    }

    private function hasNewerLazadaItems(object $existing,array $incoming):bool
    {
        $previous=json_decode(Crypt::decryptString($existing->encrypted_payload),true,512,JSON_THROW_ON_ERROR);
        $versions=static function (array $payload):?array {
            $items=$payload['order_items']??null;
            if (!is_array($items)||!array_is_list($items)||!$items) return null;
            $result=[];
            foreach ($items as $item) {
                if (!is_array($item)) return null;
                $id=$item['order_item_id']??null; $time=$item['updated_at']??null;
                if ((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)
                    ||isset($result[(string)$id])||!is_string($time)) return null;
                try {
                    $parsed=\Carbon\CarbonImmutable::createFromFormat('!Y-m-d H:i:s O',$time);
                    if (!$parsed||$parsed->format('Y-m-d H:i:s O')!==$time) return null;
                    $result[(string)$id]=$parsed->timestamp;
                } catch (\Throwable) { return null; }
            }
            ksort($result);
            return $result;
        };
        $old=$versions($previous); $new=$versions($incoming);
        if ($old===null||$new===null||array_keys($old)!==array_keys($new)) return false;
        $advanced=false;
        foreach ($new as $id=>$time) {
            if ($time<$old[$id]) return false;
            $advanced=$advanced||$time>$old[$id];
        }
        return $advanced;
    }
}
