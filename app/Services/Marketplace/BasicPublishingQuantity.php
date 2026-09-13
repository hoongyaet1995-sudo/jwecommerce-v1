<?php
namespace App\Services\Marketplace;

use DomainException;

final class BasicPublishingQuantity
{
    public static function validate(string $platform,array $payload): void
    {
        $invalid=static fn()=>throw new DomainException('Basic publishing requires explicit zero initial quantities.');
        if ($platform==='lazada') {
            $skus=$payload['Request']['Product']['Skus']['Sku']??null;
            if (!is_array($skus)||!array_is_list($skus)||!$skus) $invalid();
            foreach ($skus as $sku) {
                if (!is_array($sku)||!in_array($sku['quantity']??null,[0,'0'],true)) $invalid();
                foreach (array_keys($sku) as $field) {
                    if (in_array(strtolower($field),['quantity','sellablequantity','multiwarehouseinventories'],true)
                        && !in_array($field,['quantity','MultiWarehouseInventories'],true)) $invalid();
                }
                if (array_key_exists('MultiWarehouseInventories',$sku)) {
                    $envelope=$sku['MultiWarehouseInventories'];
                    $rows=is_array($envelope)?($envelope['MultiWarehouseInventory']??null):null;
                    if (!is_array($rows)||!array_is_list($rows)||!$rows||count($envelope)!==1) $invalid();
                    $codes=[];
                    foreach ($rows as $row) {
                        if (!is_array($row)||count($row)!==2||!is_string($row['WarehouseCode']??null)
                            ||!preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$row['WarehouseCode'])
                            ||isset($codes[$row['WarehouseCode']])||!in_array($row['Quantity']??null,[0,'0'],true)) $invalid();
                        $codes[$row['WarehouseCode']]=true;
                    }
                }
            }
            return;
        }
        if ($platform!=='shopee') $invalid();
        $hasOriginal=array_key_exists('original_stock',$payload);
        $hasLocations=array_key_exists('seller_stock',$payload);
        if (!$hasOriginal&&!$hasLocations) $invalid();
        if ($hasOriginal&&$payload['original_stock']!==0) $invalid();
        if ($hasLocations) {
            if (!is_array($payload['seller_stock'])||!array_is_list($payload['seller_stock'])||!$payload['seller_stock']) $invalid();
            foreach ($payload['seller_stock'] as $row) {
                if (!is_array($row)||($row['stock']??null)!==0) $invalid();
            }
        }
    }
}
