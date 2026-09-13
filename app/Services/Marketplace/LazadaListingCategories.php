<?php
namespace App\Services\Marketplace;

use RuntimeException;

final class LazadaListingCategories
{
    public static function flatten(array $tree): array
    {
        $rows=[];$seen=[];
        $visit=function(array $nodes,int $parent,int $depth) use (&$visit,&$rows,&$seen):void {
            if (!array_is_list($nodes)||$depth>20) throw new RuntimeException('Invalid Lazada category tree.');
            foreach ($nodes as $node) {
                if (!is_array($node)||!is_int($node['category_id']??null)||$node['category_id']<=0
                    ||isset($seen[$node['category_id']])||!is_string($node['name']??null)||trim($node['name'])===''
                    ||!is_bool($node['leaf']??null)||count($rows)>=20000) throw new RuntimeException('Invalid Lazada category.');
                $children=$node['children']??[];
                if (!is_array($children)||($node['leaf']&&$children)||(!$node['leaf']&&!$children)) throw new RuntimeException('Incomplete Lazada category tree.');
                $seen[$node['category_id']]=true;
                $rows[]=['category_id'=>$node['category_id'],'parent_category_id'=>$parent,
                    'original_category_name'=>$node['name'],'display_category_name'=>$node['name'],'has_children'=>!$node['leaf']];
                $visit($children,$node['category_id'],$depth+1);
            }
        };
        $visit($tree,0,0);
        return $rows;
    }
}
