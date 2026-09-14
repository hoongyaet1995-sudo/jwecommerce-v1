<?php
namespace App\Services\Marketplace;

final class ShopeeVariantImagePlan
{
    public static function fromSource(array $source, array $choices=[]): array
    {
        $variations=ShopeeMasterVariants::fromSource($source);
        if ($variations===null) return [];
        $name=$variations['tier_variation'][0]['name'];
        if(array_diff(array_keys($choices),array_column($variations['tier_variation'][0]['option_list'],'option')))throw new \DomainException('Unknown variant image group.');
        $result=[];
        foreach ($variations['tier_variation'][0]['option_list'] as $index=>$option) {
            $path=null;$id=null;
            if(isset($choices[$option['option']])) {
                $chosen=$choices[$option['option']];
                $matches=array_values(array_filter($source['variants'],fn($v)=>$v['id']===$chosen && ($v['options'][$name]??null)===$option['option']));
                $variant=$matches[0]??null;
                $image=($variant['option_image']??null)?:($variant['image_url']??null);
                if(!$variant||!$image)throw new \DomainException('Select a saved image belonging to this variant group.');
                $result[$index]=['variant_id'=>$variant['id'],'path'=>$image];
                continue;
            }

            foreach ($source['variants'] as $variant) {
                if ($variant['options'][$name]!==$option['option']) continue;
                $candidate=($variant['option_image'] ?? null) ?: ($variant['image_url'] ?? null);
                if (!$candidate) continue;
                if ($path!==null && $path!==$candidate) throw new \DomainException('Variants sharing '.$name.' '.$option['option'].' need the same option image. Review their images before publishing.');
                $path=$candidate;$id??=$variant['id'];
            }
            if ($path!==null) $result[$index]=['variant_id'=>$id,'path'=>$path];
        }
        return $result;
    }
}
