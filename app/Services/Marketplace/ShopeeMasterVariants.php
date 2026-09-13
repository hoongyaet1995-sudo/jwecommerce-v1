<?php
namespace App\Services\Marketplace;

use DomainException;

final class ShopeeMasterVariants
{
    public static function fromSource(array $source): ?array
    {
        $variants=$source['variants']??[];
        if (count($variants)<=1) return null;
        $options=$source['options']??null;
        if (!is_array($options)||!array_is_list($options)||!$options||count($options)>2||count($variants)>50) {
            throw new DomainException('Shopee supports up to two JW option groups and fifty enabled variants. Review the product options.');
        }
        $tiers=[];$names=[];
        foreach ($options as $option) {
            $name=$option['name']??null;$values=$option['values']??null;
            if (!is_string($name)||trim($name)===''||in_array($name,$names,true)||!is_array($values)||!array_is_list($values)||!$values) {
                throw new DomainException('Review the JW option names and values before publishing.');
            }
            $active=[];
            foreach ($variants as $variant) {
                $value=$variant['options'][$name]??null;
                if (!is_string($value)||trim($value)===''||!in_array($value,$values,true)) {
                    throw new DomainException('Every enabled JW variant needs a saved value for '.$name.'.');
                }
                if (!in_array($value,$active,true)) $active[]=$value;
            }
            // Retain master option ordering, omitting values used only by disabled variants.
            $selected=array_values(array_filter($values,fn($value)=>in_array($value,$active,true)));
            if (count($selected)!==count(array_unique($selected))) throw new DomainException('JW option values must be unique.');
            $names[]=$name;
            $tiers[]=['name'=>$name,'option_list'=>array_map(fn($value)=>['option'=>$value],$selected)];
        }
        $models=[];
        foreach ($variants as $variant) {
            if (!is_array($variant['options']??null)) throw new DomainException('Review saved JW variant option values.');
            if (array_diff(array_keys($variant['options']),$names)) throw new DomainException('A JW variant has options outside the product definition.');
            $indices=[];
            foreach ($tiers as $tier) $indices[]=array_search($variant['options'][$tier['name']],array_column($tier['option_list'],'option'),true);
            $models[]=['tier_index'=>$indices,'model_sku'=>$variant['sku'],'original_price'=>$variant['price_minor']/100,'seller_stock'=>[['stock'=>0]]];
        }
        $result=['tier_variation'=>$tiers,'model'=>$models];
        ShopeePublishingVariants::validate(['jw_variations'=>$result],$source);
        return $result;
    }
}
