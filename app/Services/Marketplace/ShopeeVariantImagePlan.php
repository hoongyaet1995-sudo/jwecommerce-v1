<?php
namespace App\Services\Marketplace;

final class ShopeeVariantImagePlan
{
    public static function fromSource(array $source): array
    {
        $variations=ShopeeMasterVariants::fromSource($source);
        if ($variations===null) return [];
        $name=$variations['tier_variation'][0]['name'];
        $result=[];
        foreach ($variations['tier_variation'][0]['option_list'] as $index=>$option) {
            $path=null;$id=null;
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
