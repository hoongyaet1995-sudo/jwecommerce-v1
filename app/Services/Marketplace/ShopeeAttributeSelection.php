<?php
namespace App\Services\Marketplace;

final class ShopeeAttributeSelection
{
    /** Definition and searchable options must come from server-side Shopee reads. */
    public static function normalize(array $definition,array $values): array
    {
        $info=$definition['attribute_info'] ?? [];
        $type=$info['input_type'] ?? null;
        $max=$info['max_value_count'] ?? null;
        if (!in_array($type,[1,2,3,4,5],true) || (in_array($type,[4,5],true) && (!is_int($max) || $max<1))
            || !is_array($definition['attribute_value_list'] ?? null)) {
            throw new \RuntimeException('Attribute selection metadata is incomplete.');
        }
        $limit=in_array($type,[1,2,3],true)?1:$max;
        if (!array_is_list($values) || !$values || count($values)>$limit) throw new \DomainException('Invalid number of attribute values.');
        $result=[];$seen=[];
        foreach ($values as $value) {
            $id=$value['value_id'] ?? null;
            if (!is_int($id) || $id<0) throw new \DomainException('Invalid attribute value identity.');
            if ($id===0) {
                if (!in_array($type,[2,3,5],true)) throw new \DomainException('This attribute requires a predefined value.');
                $name=$value['original_value_name'] ?? null;$unit=$value['value_unit'] ?? '';
                if (!is_string($name) || !is_string($unit)) throw new \DomainException('Invalid custom attribute value.');
                ShopeeAttributeValue::validate($name,$unit,$info);
            } else {
                $matches=array_values(array_filter($definition['attribute_value_list'],fn($row)=>($row['value_id'] ?? null)===$id));
                if (count($matches)!==1) throw new \DomainException('Selected value was not verified in Shopee options.');
                $name=$matches[0]['name'] ?? null;$unit=$matches[0]['value_unit'] ?? '';
                if (!is_string($name) || !is_string($unit)) throw new \RuntimeException('Shopee option metadata is incomplete.');
            }
            $key=json_encode([$id,$id===0?$name:null,$unit],JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) throw new \DomainException('Duplicate attribute value.');
            $seen[$key]=true;
            $result[]=['value_id'=>$id,'original_value_name'=>$name,'value_unit'=>$unit];
        }
        return $result;
    }
}
