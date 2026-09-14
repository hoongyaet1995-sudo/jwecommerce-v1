<?php
namespace App\Services\Marketplace;

use DomainException;

final class ShopeePublishingImages
{
    public function __construct(private MarketplaceImageReceipts $receipts, private ShopeeProductImageService $images) {}

    public function prepare(int $storeId,array $source,int $actorId): void
    {
        if (empty($source['images'])) throw new DomainException('Add JW product images before publishing.');
        foreach ($source['images'] as $image) {
            if (!$this->receipts->current($storeId,(int)$source['product_id'],(int)$image['id'])) {
                $this->images->prepare($storeId,(int)$source['product_id'],(int)$image['id'],$actorId);
            }
        }
        app(ShopeeVariantImages::class)->prepare($storeId,$source,$actorId);
    }

    public function bind(int $storeId,array $source,array $payload): array
    {
        $ids=[];
        foreach ($source['images']??[] as $image) {
            $receipt=$this->receipts->current($storeId,(int)$source['product_id'],(int)$image['id']);
            if (!$receipt) throw new DomainException('JW product images changed. Review the product again before publishing.');
            $ids[]=$receipt->provider_identity;
        }
        if (!$ids) throw new DomainException('Add JW product images before publishing.');
        $payload['image']=['image_id_list'=>$ids];
        foreach ($payload['jw_variations']['tier_variation']??[] as $tier) {
            foreach ($tier['option_list']??[] as $option) {
                if (isset($option['image']) && (!is_array($option['image'])
                    || !in_array($option['image']['image_id']??null,$ids,true))) {
                    throw new DomainException('Variant images must belong to the reviewed JW product.');
                }
            }
        }
        return app(ShopeeVariantImages::class)->bind($storeId,$source,$payload);
    }
}
