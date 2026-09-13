<?php
namespace App\Services\Marketplace;

use Illuminate\Validation\ValidationException;

final class LazadaPublishingImages
{
    public function __construct(private MarketplaceImageReceipts $receipts) {}

    /** Called by staging; staff never handle platform image IDs or preparation steps. */
    public function prepare(int $storeId, array $source, int $actorId): void
    {
        $images=$source['images']??[];
        if (!$images || count($images)>8) throw ValidationException::withMessages(['images'=>'Review one to eight JW product images before publishing.']);
        foreach ($images as $image) {
            if ($this->receipts->current($storeId,(int)$source['product_id'],(int)$image['id'])) continue;
            app(LazadaProductImageService::class)->prepare($storeId,(int)$source['product_id'],(int)$image['id'],$actorId);
        }
    }

    public function bind(int $storeId, array $source, array $payload): array
    {
        $urls=[];
        foreach ($source['images'] ?? [] as $image) {
            $receipt=$this->receipts->current($storeId,(int)$source['product_id'],(int)$image['id']);
            if (!$receipt) throw ValidationException::withMessages(['images'=>'Prepare the current JW product images for this store before publishing.']);
            $urls[]=$receipt->provider_identity;
        }
        if (!$urls || count($urls)>8) throw ValidationException::withMessages(['images'=>'Review one to eight JW product images before publishing.']);
        foreach ($payload['Request']['Product']['Skus']['Sku'] as $sku) {
            if (!array_key_exists('Images',$sku)) continue;
            $images=$sku['Images']['Image'] ?? null;
            if (!is_array($images) || !array_is_list($images) || !$images || count($images)>8) {
                throw ValidationException::withMessages(['images'=>'Review the variant image selection.']);
            }
            foreach ($images as $url) {
                if (!is_string($url) || !in_array($url,$urls,true)) throw ValidationException::withMessages(['images'=>'Variant images must use current prepared JW product images.']);
            }
        }
        $payload['Request']['Product']['Images']=['Image'=>$urls];
        return $payload;
    }
}
