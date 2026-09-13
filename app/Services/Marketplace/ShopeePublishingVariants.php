<?php
namespace App\Services\Marketplace;

use DomainException;

final class ShopeePublishingVariants
{
    /** Validate the internal second request before any parent is created. */
    public static function validate(array $payload, ?array $source = null): ?array
    {
        if (!array_key_exists('jw_variations',$payload)) {
            if ($source!==null && count($source['variants'])>1) throw new DomainException('Every enabled JW variant must be included before Shopee publishing.');
            return null;
        }
        $invalid=static fn()=>throw new DomainException('Review the Shopee variant options, unique SKUs, prices and zero initial stock.');
        $value=$payload['jw_variations'];
        if (!is_array($value) || array_diff(array_keys($value),['tier_variation','model'])) $invalid();
        $tiers=$value['tier_variation']??null;
        $models=$value['model']??null;
        if (!is_array($tiers)||!array_is_list($tiers)||count($tiers)<1||count($tiers)>2
            ||!is_array($models)||!array_is_list($models)||count($models)<1||count($models)>50) $invalid();
        foreach ($tiers as $tier) {
            if (!is_array($tier)||!is_string($tier['name']??null)||trim($tier['name'])===''
                ||!is_array($tier['option_list']??null)||!array_is_list($tier['option_list'])||!$tier['option_list']) $invalid();
            foreach ($tier['option_list'] as $option) {
                if (!is_array($option)||!is_string($option['option']??null)||trim($option['option'])==='') $invalid();
            }
        }
        $skus=[]; $combinations=[];
        foreach ($models as $model) {
            if (!is_array($model)||array_diff(array_keys($model),['tier_index','model_sku','original_price','seller_stock','original_stock'])
                ||!is_string($model['model_sku']??null)||trim($model['model_sku'])===''
                ||mb_strlen($model['model_sku'])>100||isset($skus[$model['model_sku']])
                ||(!is_int($model['original_price']??null)&&!is_float($model['original_price']??null))
                ||!is_finite((float)$model['original_price'])||$model['original_price']<=0
                ||!is_array($model['tier_index']??null)||!array_is_list($model['tier_index'])||count($model['tier_index'])!==count($tiers)) $invalid();
            foreach ($model['tier_index'] as $tier=>$index) {
                if (!is_int($index)||$index<0||!isset($tiers[$tier]['option_list'][$index])) $invalid();
            }
            $key=implode(':',$model['tier_index']);
            if (isset($combinations[$key])) $invalid();
            $skus[$model['model_sku']]=true; $combinations[$key]=true;
            BasicPublishingQuantity::validate('shopee',$model);
        }
        if ($source!==null) {
            if (count($source['variants'])!==count($models)) $invalid();
            $prices=[];
            foreach ($models as $model) $prices[$model['model_sku']]=$model['original_price'];
            foreach ($source['variants'] as $variant) {
                if (!isset($prices[$variant['sku']]) || abs($prices[$variant['sku']]*100-$variant['price_minor'])>0.000001) $invalid();
            }
        }
        return $value;
    }
}
