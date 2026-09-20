<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Resolves one ambiguous Lazada creation by reading the seller catalog; it never sends another create request. */
final class LazadaPublishingReconciliation
{
    public function __construct(
        private LazadaClient $client,
        private LazadaTokenManager $tokens,
        private PublishingTasks $tasks,
        private LazadaCatalogService $catalog,
    ) {}

    public function reconcile(int $taskId): array
    {
        $task=DB::table('marketplace_publishing_tasks')->find($taskId);
        $store=$task ? DB::table('marketplace_stores')->find($task->store_id) : null;
        if (!$task || !$store || $task->status!=='uncertain' || $store->platform!=='lazada' || $store->status!=='connected') {
            throw new RuntimeException('This Lazada publishing task is not awaiting catalog reconciliation.');
        }
        $sellerSkus=$this->sellerSkus($task->payload);
        $token=$this->tokens->accessToken((int)$store->id);
        $matched=[];
        $total=null;
        for ($offset=0,$page=0; $offset<=5000 && $page<101; $offset+=50,$page++) {
            $response=$this->client->read('/products/get',$token,['filter'=>'all','limit'=>50,'offset'=>$offset],(int)$store->id);
            $products=$response['products']??null;
            $pageTotal=$response['total_products']??$response['total']??$response['countTotal']??null;
            if (!is_array($products)||!array_is_list($products)||!is_numeric($pageTotal)) {
                throw new RuntimeException('Lazada catalog reconciliation could not verify the seller catalog.');
            }
            $total=(int)$pageTotal;
            foreach ($products as $summary) {
                $itemId=$summary['item_id']??$summary['ItemId']??null;
                if ((!is_int($itemId)&&!is_string($itemId))||!preg_match('/^[1-9][0-9]*$/D',(string)$itemId)) {
                    throw new RuntimeException('Lazada catalog reconciliation received an incomplete product identity.');
                }
                $summarySkus=$summary['skus']??$summary['Skus']??null;
                $item=is_array($summarySkus) && array_is_list($summarySkus)
                    ? $summary
                    : $this->client->read('/product/item/get',$token,['item_id'=>(string)$itemId],(int)$store->id);
                foreach ($this->itemSkus($item,(string)$itemId) as $sellerSku=>$skuId) {
                    if (isset($sellerSkus[$sellerSku])) $matched[(string)$itemId][$sellerSku]=$skuId;
                }
            }
            if ($offset+count($products)>=$total) break;
            if (!$products) throw new RuntimeException('Lazada catalog reconciliation did not reach the catalog end.');
        }
        if ($total===null || $offset+count($products)<$total) {
            throw new RuntimeException('Lazada catalog reconciliation did not reach the catalog end.');
        }
        if (!$matched) {
            if (!$this->tasks->transition($taskId,'uncertain','rejected')) throw new RuntimeException('The saved publishing task changed during reconciliation.');
            return ['id'=>$taskId,'status'=>'rejected','confirmed'=>false];
        }
        if (count($matched)!==1) throw new RuntimeException('Multiple Lazada listings matched the saved SKU set. The task remains protected.');
        $itemId=(string)array_key_first($matched);
        $skuIds=$matched[$itemId];
        if (count($skuIds)!==count($sellerSkus) || array_diff_key($sellerSkus,$skuIds)) {
            throw new RuntimeException('Lazada catalog contains only part of the saved SKU set. The task remains protected.');
        }
        if (!$this->tasks->confirmReconciled($taskId,$itemId,['item_id'=>$itemId,'sku_ids'=>$skuIds])) {
            throw new RuntimeException('The saved publishing task changed during reconciliation.');
        }
        try {$this->catalog->pullItem((int)$store->id,$itemId);} catch (\Throwable) {
            // The result is already confirmed by exact seller SKUs. Catalog mapping can be fetched later.
        }
        return ['id'=>$taskId,'status'=>'succeeded','confirmed'=>true,'external_item_id'=>$itemId];
    }

    /** @return array<string,true> */
    private function sellerSkus(string $payload): array
    {
        try {$decoded=json_decode($payload,true,512,JSON_THROW_ON_ERROR);} catch (\Throwable) {throw new RuntimeException('Saved Lazada publishing payload is invalid.');}
        $skus=data_get($decoded,'Request.Product.Skus.Sku');
        if (!is_array($skus)||!array_is_list($skus)||!$skus) throw new RuntimeException('Saved Lazada publishing SKUs are unavailable.');
        $result=[];
        foreach ($skus as $sku) {
            $value=is_array($sku)?($sku['SellerSku']??null):null;
            if (!is_string($value)||!preg_match('/\A[ -~]{1,200}\z/D',$value)||isset($result[$value])) {
                throw new RuntimeException('Saved Lazada publishing SKUs are invalid.');
            }
            $result[$value]=true;
        }
        return $result;
    }

    /** @return array<string,string> */
    private function itemSkus(array $item,string $itemId): array
    {
        if ((string)($item['item_id']??$item['ItemId']??'')!==$itemId || !is_array($item['skus']??$item['Skus']??null)) {
            throw new RuntimeException('Lazada catalog reconciliation received an incomplete product.');
        }
        $result=[];
        foreach ($item['skus']??$item['Skus'] as $sku) {
            $sellerSku=is_array($sku)?($sku['SellerSku']??$sku['seller_sku']??null):null;
            $skuId=is_array($sku)?($sku['SkuId']??$sku['sku_id']??null):null;
            if (!is_string($sellerSku)||(!is_string($skuId)&&!is_int($skuId))||!preg_match('/^[1-9][0-9]*$/D',(string)$skuId)||isset($result[$sellerSku])) {
                throw new RuntimeException('Lazada catalog reconciliation received an incomplete SKU.');
            }
            $result[$sellerSku]=(string)$skuId;
        }
        return $result;
    }
}
