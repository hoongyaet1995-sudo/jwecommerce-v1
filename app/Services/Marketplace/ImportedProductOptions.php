<?php
namespace App\Services\Marketplace;
use App\Models\Product;
final class ImportedProductOptions
{
    public static function merge(Product $product,array $values): void
    {
        if (!$values) return;
        $options=$product->options??[];
        $names=array_column($options,'name');
        if ($names && (array_diff($names,array_keys($values)) || array_diff(array_keys($values),$names))) {
            throw new \DomainException('Channel option groups do not match the selected JW product. Review its variant options.');
        }
        if (!$options) foreach($values as $name=>$value) $options[]=['id'=>'marketplace-'.substr(hash('sha256',$name),0,12),'name'=>$name,'values'=>[],'displayType'=>'pills'];
        foreach($options as &$option) if(!in_array($values[$option['name']],$option['values'],true)) $option['values'][]=$values[$option['name']];
        unset($option);
        $product->update(['options'=>$options,'has_variants'=>true]);
    }
}
