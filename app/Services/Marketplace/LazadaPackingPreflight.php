<?php
namespace App\Services\Marketplace;

use RuntimeException;

final class LazadaPackingPreflight
{
    public function __construct(private LazadaClient $client,private LazadaTokenManager $tokens) {}

    public function inspect(int $storeId,string $orderId,array $itemIds,string $allocationType):array
    {
        if (!preg_match('/^[1-9][0-9]{0,29}$/D',$orderId)||!$itemIds||!array_is_list($itemIds)
            ||count($itemIds)!==count(array_unique($itemIds))||!in_array($allocationType,['TFS','NTFS'],true)) {
            throw new RuntimeException('Invalid packing selection.');
        }
        foreach ($itemIds as $id) if (!is_string($id)||!preg_match('/^[1-9][0-9]{0,29}$/D',$id)) throw new RuntimeException('Invalid packing selection.');
        $token=$this->tokens->accessToken($storeId);
        $items=$this->client->read('/order/items/get',$token,['order_id'=>$orderId]);
        if (!array_is_list($items)) throw new RuntimeException('Lazada order items are incomplete.');
        $seen=[]; $selected=[];
        foreach ($items as $item) {
            if (!is_array($item)) throw new RuntimeException('Lazada order items are incomplete.');
            $id=(string)($item['order_item_id']??'');
            if (!preg_match('/^[1-9][0-9]{0,29}$/D',$id)||isset($seen[$id])||(string)($item['order_id']??'')!==$orderId) {
                throw new RuntimeException('Lazada order item identity is inconsistent.');
            }
            $seen[$id]=true;
            if (!in_array($id,$itemIds,true)) continue;
            if (!in_array($item['status']??null,['pending','repacked'],true)) throw new RuntimeException('Selected item is no longer eligible for packing.');
            foreach (['is_fbl','is_digital','delivery_option_sof'] as $field) {
                if (!in_array($item[$field]??null,[0,'0'],true)) throw new RuntimeException('This fulfillment type requires a separate workflow.');
            }
            if (($item['semi_managed']??null)!==false) throw new RuntimeException('This fulfillment type requires a separate workflow.');
            $selected[]=['order_item_id'=>$id,'status'=>$item['status'],'warehouse_code'=>$item['warehouse_code']??null,
                'product_id'=>$item['product_id']??null,'sku_id'=>$item['sku_id']??null];
        }
        if (count($selected)!==count($itemIds)) throw new RuntimeException('Selected order items are missing from the current platform response.');
        $provider=$this->client->read('/order/shipment/providers/get',$token,['getShipmentProvidersReq'=>json_encode([
            'orders'=>[['order_id'=>$orderId,'order_item_ids'=>$itemIds]],'shipping_allocate_type'=>$allocationType],JSON_THROW_ON_ERROR)]);
        if (($provider['shipping_allocate_type']??null)!==$allocationType||!in_array($provider['platform_default']??null,[0,1,'0','1'],true)) {
            throw new RuntimeException('Lazada shipment allocation is incomplete.');
        }
        return ['order_id'=>$orderId,'items'=>$selected,'shipping_allocate_type'=>$allocationType,
            'platform_default'=>(int)$provider['platform_default']===1,'shipment_providers'=>$provider['shipment_providers']??[]];
    }
}
