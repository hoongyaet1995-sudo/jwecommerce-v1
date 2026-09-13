<?php

namespace App\Services\Marketplace;

use DomainException;

/** Bind JW-owned fields only. Category, media and seller capabilities need separate validation. */
final class LazadaPublishingMasterData
{
    public static function bind(array $source, array $payload): array
    {
        $product=$payload['Request']['Product'] ?? null;
        $rows=$product['Skus']['Sku'] ?? null;
        if (!is_array($product) || !is_array($product['Attributes'] ?? null)
            || !is_array($rows) || !array_is_list($rows) || !$rows
            || !is_array($source['variants'] ?? null) || !$source['variants']) {
            throw new DomainException('Review every enabled JW variant before publishing.');
        }
        if (isset($product['AssociatedSku'])) {
            throw new DomainException('New master product publishing cannot append to an existing product.');
        }
        $bySku=[];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['SellerSku'] ?? null) || isset($bySku[$row['SellerSku']])) {
                throw new DomainException('Each JW variant must appear exactly once.');
            }
            foreach (['special_price','special_from_date','special_to_date','supply_price','AssociatedSku'] as $field) {
                if (array_key_exists($field,$row)) throw new DomainException('Alternate pricing or existing-product association requires a separate review.');
            }
            $bySku[$row['SellerSku']]=$row;
        }
        $bound=[];
        foreach ($source['variants'] as $variant) {
            $sku=$variant['sku'];
            if (!isset($bySku[$sku]) || !is_int($variant['price_minor']) || $variant['price_minor']<=0) {
                throw new DomainException('Publishing variants must match the reviewed JW product.');
            }
            $row=$bySku[$sku];
            $row['SellerSku']=$sku;
            $row['price']=intdiv($variant['price_minor'],100).'.'.str_pad((string)($variant['price_minor']%100),2,'0',STR_PAD_LEFT);
            $row['quantity']='0';
            $bound[]=$row;
            unset($bySku[$sku]);
        }
        if ($bySku) throw new DomainException('Publishing contains variants outside the reviewed JW product.');
        $payload['Request']['Product']['Attributes']['name']=$source['title'];
        $payload['Request']['Product']['Attributes']['description']=$source['description'] ?? '';
        $payload['Request']['Product']['Skus']['Sku']=$bound;
        return $payload;
    }
}
