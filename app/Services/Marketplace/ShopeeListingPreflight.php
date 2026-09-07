<?php
namespace App\Services\Marketplace;

/** Partial publishing preflight; does not create a task or assert the whole listing is valid. */
final class ShopeeListingPreflight
{
    public function __construct(private ShopeeClient $client) {}

    /** Basic per-channel preflight; volume and cross-channel constraints must also be resolved before dispatch. */
    public function logistics(#[\SensitiveParameter] string $accessToken,string $shopId,array $selection,
        float $weightKg,array $centimetres): array
    {
        if (!array_is_list($selection) || !$selection) throw new \DomainException('Select at least one logistics channel.');
        $channels=$this->client->logisticsChannels($accessToken,$shopId);
        $result=[];$seen=[];
        foreach ($selection as $review) {
            $id=$review['logistic_id'] ?? null;
            if (!is_int($id) || $id<=0 || isset($seen[$id])) throw new \DomainException('Invalid or duplicate logistics channel.');
            $matches=array_values(array_filter($channels,fn($channel)=>$channel['logistics_channel_id']===$id));
            if (count($matches)!==1) throw new \DomainException('The logistics channel is not available in this store.');
            ShopeeLogisticsSelection::validate($matches[0],$review);
            ShopeePackageLimits::validate($matches[0],$weightKg,$centimetres);
            $seen[$id]=true;
            $result[]=array_merge(['logistic_id'=>$id,'enabled'=>true],array_intersect_key($review,array_flip(['size_id','shipping_fee','is_free'])));
        }
        return $result;
    }

    /** Resolves existing platform brands. Registering a custom brand is a separate operation. */
    public function brand(#[\SensitiveParameter] string $accessToken,string $shopId,int $categoryId,?int $brandId): ?array
    {
        if ($brandId!==null && $brandId<0) throw new \DomainException('Invalid brand identity.');
        $offset=0;
        for ($pageNumber=0;$pageNumber<20;$pageNumber++) {
            $page=$this->client->brandPage($accessToken,$shopId,$categoryId,$offset);
            if ($brandId===null) {
                if ($page['is_mandatory']) throw new \DomainException('This Shopee category requires a brand selection.');
                return null;
            }
            foreach ($page['brand_list'] as $brand) {
                if ($brand['brand_id']===$brandId) return ['brand_id'=>$brandId,'original_brand_name'=>$brand['original_brand_name']];
            }
            if (!$page['has_next_page']) throw new \DomainException('The selected brand is not available for this Shopee category.');
            $offset=$page['next_offset'];
        }
        throw new \RuntimeException('Brand verification exceeded the page limit. No listing was submitted.');
    }

    public function attributes(#[\SensitiveParameter] string $accessToken,string $shopId,int $categoryId,array $selection): array
    {
        ShopeeListingCategory::requireLeaf($this->client->categoryTree($accessToken,$shopId),$categoryId);
        $tree=$this->client->attributeTree($accessToken,$shopId,$categoryId);
        $selected=[];
        foreach ($selection as $attribute) {
            if (is_int($attribute['attribute_id'] ?? null)) $selected[$attribute['attribute_id']]=$attribute['attribute_value_list'] ?? [];
        }
        $reads=0;
        $hydrate=function(array $nodes,int $depth=0) use (&$hydrate,&$reads,$selected,$accessToken,$shopId): array {
            if ($depth>20) throw new \RuntimeException('Attribute dependency tree is too deep.');
            foreach ($nodes as &$node) {
                $id=$node['attribute_id'] ?? null;
                if (!isset($selected[$id])) continue;
                $wanted=[];
                foreach ($selected[$id] as $value) if (is_int($value['value_id'] ?? null) && $value['value_id']>0) $wanted[$value['value_id']]=true;
                if (($node['attribute_info']['support_search_value'] ?? false)===true && $wanted) {
                    $cursor=0;$found=[];
                    do {
                        if (++$reads>20) throw new \RuntimeException('Attribute verification needs too many pages. Refine the listing and retry.');
                        $page=$this->client->searchAttributeValues($accessToken,$shopId,$id,'',$cursor);
                        foreach ($page['value_list'] as $option) {
                            if (isset($wanted[$option['value_id']])) {
                                $found[$option['value_id']]=['value_id'=>$option['value_id'],'name'=>$option['value_name']];
                            }
                        }
                        $cursor=$page['page_info']['cursor'];
                    } while ($page['page_info']['has_next'] && array_diff_key($wanted,$found));
                    if (array_diff_key($wanted,$found)) throw new \DomainException('A selected attribute option is no longer available in Shopee.');
                    $existing=[];
                    foreach ($node['attribute_value_list'] as $option) $existing[$option['value_id']]=$option;
                    foreach ($found as $valueId=>$option) $existing[$valueId]=array_replace($existing[$valueId] ?? [],$option);
                    $node['attribute_value_list']=array_values($existing);
                }
                foreach ($node['attribute_value_list'] as &$option) {
                    if (isset($wanted[$option['value_id']]) && isset($option['child_attribute_list'])) {
                        $option['child_attribute_list']=$hydrate($option['child_attribute_list'],$depth+1);
                    }
                }
                unset($option);
            }
            unset($node);
            return $nodes;
        };
        $tree=$hydrate($tree);
        return ShopeeListingAttributes::normalize($tree,$selection);
    }
}
