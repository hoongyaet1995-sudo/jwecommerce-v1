<?php
namespace App\Services\Marketplace;

use DomainException;

final class ShopeePublishingMasterData
{
    public static function bind(array $source,array $payload): array
    {
        $variants=$source['variants']??[];
        if (!$variants) throw new DomainException('Review enabled JW variants before publishing.');
        $payload['item_name']=$source['title'];
        $payload['description']=$source['description']??'';
        $payload['item_sku']=$variants[0]['sku'];
        $payload['original_price']=$variants[0]['price_minor']/100;
        $payload['seller_stock']=[['stock'=>0]];
        unset($payload['original_stock']);
        // Descriptions use the reviewed JW text, never a second unreviewed rich-text body.
        unset($payload['description_info']);
        $payload['description_type']='normal';
        $generated=ShopeeMasterVariants::fromSource($source);
        if ($generated!==null) $payload['jw_variations']=$generated;
        else unset($payload['jw_variations']);
        if (isset($payload['jw_variations']['model'])) {
            $bySku=array_column($variants,null,'sku');
            foreach ($payload['jw_variations']['model'] as &$model) {
                $variant=$bySku[$model['model_sku']??'']??null;
                if (!$variant) throw new DomainException('The selected Shopee variant does not belong to this JW product.');
                $model['original_price']=$variant['price_minor']/100;
                $model['seller_stock']=[['stock'=>0]];
                unset($model['original_stock']);
            }
            unset($model);
        }
        ShopeePublishingVariants::validate($payload,$source);
        BasicPublishingQuantity::validate('shopee',$payload);
        return $payload;
    }
}
