<?php
namespace App\Services\Marketplace;

use RuntimeException;

/** Initial listing quantities are derived from seller warehouses, never WMS balances or browser warehouse IDs. */
final class LazadaPublishingWarehouses
{
    public function __construct(private LazadaTokenManager $tokens, private LazadaClient $client) {}

    public function bind(int $storeId, array $payload): array
    {
        $codes=$this->codes($storeId);
        foreach ($payload['Request']['Product']['Skus']['Sku'] as &$sku) {
            unset($sku['MultiWarehouseInventories']);
            if (count($codes)>1) $sku['MultiWarehouseInventories']=['MultiWarehouseInventory'=>array_map(
                fn($code)=>['WarehouseCode'=>$code,'Quantity'=>'0'],$codes)];
        }
        unset($sku);
        BasicPublishingQuantity::validate('lazada',$payload);
        return $payload;
    }

    public function verify(int $storeId, array $payload): void
    {
        BasicPublishingQuantity::validate('lazada',$payload);
        $expected=$this->codes($storeId);
        foreach ($payload['Request']['Product']['Skus']['Sku'] as $sku) {
            if (count($expected)===1 && !array_key_exists('MultiWarehouseInventories',$sku)) continue;
            $actual=array_column($sku['MultiWarehouseInventories']['MultiWarehouseInventory']??[],'WarehouseCode');
            sort($actual,SORT_STRING);
            if ($actual!==$expected) throw new RuntimeException('The Lazada warehouse list changed after review. Review the listing again.');
        }
    }

    private function codes(int $storeId): array
    {
        $rows=$this->client->read('/seller/warehouse/get',$this->tokens->accessToken($storeId),['addressTypes'=>'["warehouse"]'],$storeId);
        if (!array_is_list($rows)||!$rows) throw new RuntimeException('Lazada seller warehouses are unavailable.');
        $codes=[];
        foreach ($rows as $row) {
            $code=$row['code']??null;
            if (!is_string($code)||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$code)||isset($codes[$code])
                ||($row['status']??null)!=='ACTIVE') throw new RuntimeException('Review the current Lazada seller warehouses before publishing.');
            $codes[$code]=true;
        }
        $result=array_keys($codes);sort($result,SORT_STRING);return $result;
    }
}
