<?php

namespace App\Services\Marketplace;

/** Presence/dependency validation only; values, types, units and search results need separate validation. */
final class ShopeeAttributePresence
{
    public static function validate(array $tree, array $selection): void
    {
        $byId=[];
        foreach ($selection as $attribute) {
            $id=$attribute['attribute_id'] ?? null;
            if (!is_int($id) || $id<=0 || isset($byId[$id])
                || !is_array($attribute['attribute_value_list'] ?? null)
                || !array_is_list($attribute['attribute_value_list']) || !$attribute['attribute_value_list']) {
                throw new \DomainException('Each selected attribute must occur once with at least one value.');
            }
            $byId[$id]=$attribute['attribute_value_list'];
        }
        $active=[];
        $visit=function(array $nodes,int $depth=0) use (&$visit,&$active,$byId): void {
            if ($depth>20) throw new \RuntimeException('Attribute dependency tree is too deep.');
            foreach ($nodes as $node) {
                $id=$node['attribute_id'] ?? null;
                if (!is_int($id) || $id<=0 || !is_bool($node['mandatory'] ?? null)
                    || !is_array($node['attribute_value_list'] ?? null)) {
                    throw new \RuntimeException('Attribute dependency metadata is incomplete.');
                }
                $active[$id]=true;
                if ($node['mandatory'] && !isset($byId[$id])) throw new \DomainException("Required attribute {$id} is missing.");
                foreach ($byId[$id] ?? [] as $selected) {
                    foreach ($node['attribute_value_list'] as $option) {
                        if (($option['value_id'] ?? null)===($selected['value_id'] ?? null)) {
                            $visit($option['child_attribute_list'] ?? [],$depth+1);
                        }
                    }
                }
            }
        };
        $visit($tree);
        if (array_diff_key($byId,$active)) throw new \DomainException('An attribute does not belong to the selected category or active dependency branch.');
    }
}
