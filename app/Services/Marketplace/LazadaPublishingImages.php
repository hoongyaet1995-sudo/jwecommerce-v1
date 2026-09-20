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
        $urls=[];$identities=[];
        foreach ($source['images'] ?? [] as $image) {
            $receipt=$this->receipts->current($storeId,(int)$source['product_id'],(int)$image['id']);
            if (!$receipt) throw ValidationException::withMessages(['images'=>'Prepare the current JW product images for this store before publishing.']);
            $urls[]=$receipt->provider_identity;
            $identities[(int)$image['id']]=$receipt->provider_identity;
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
        $attributeImages=$payload['jw_attribute_image_ids']??[];
        unset($payload['jw_attribute_image_ids']);
        if (!is_array($attributeImages) || array_is_list($attributeImages)) {
            throw ValidationException::withMessages(['images'=>'Reviewed Lazada image attributes are invalid.']);
        }
        foreach ($attributeImages as $key=>$imageId) {
            if (!is_string($key)||!is_int($imageId)||!isset($identities[$imageId])) {
                throw ValidationException::withMessages(['images'=>'A reviewed Lazada image attribute changed.']);
            }
            if (preg_match('/^lazada:size-chart:([A-Za-z_][A-Za-z0-9_]*)$/D',$key)) {
                $payload['jw_size_chart']=$identities[$imageId];
                continue;
            }
            if (preg_match('/^lazada:product:([A-Za-z_][A-Za-z0-9_]*)$/D',$key,$match)) {
                $payload['Request']['Product']['Attributes'][$match[1]]=$identities[$imageId];
                continue;
            }
            $bound=false;
            foreach ($source['variants']??[] as $variant) {
                $sku=$variant['sku']??null;
                if (!is_string($sku)) continue;
                $prefix="lazada:variant:{$sku}:";
                if (!str_starts_with($key,$prefix)) continue;
                $name=substr($key,strlen($prefix));
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$name)) break;
                foreach ($payload['Request']['Product']['Skus']['Sku'] as &$payloadSku) {
                    if (($payloadSku['SellerSku']??null)===$sku) {
                        $payloadSku[$name]=$identities[$imageId];
                        $bound=true;
                        break;
                    }
                }
                unset($payloadSku);
                break;
            }
            if (!$bound) throw ValidationException::withMessages(['images'=>'A reviewed Lazada variant image attribute changed.']);
        }
        $sizeChartImageId=$payload['jw_size_chart_image_id']??null;
        unset($payload['jw_size_chart_image_id']);
        if ($sizeChartImageId!==null) {
            if (!is_int($sizeChartImageId)||!isset($identities[$sizeChartImageId])) {
                throw ValidationException::withMessages(['size_chart'=>'Select a current JW product image for the size chart.']);
            }
            $payload['jw_size_chart']=$identities[$sizeChartImageId];
        }
        if (isset($payload['jw_size_chart']) && (!is_string($payload['jw_size_chart']) || !in_array($payload['jw_size_chart'],$identities,true))) {
            throw ValidationException::withMessages(['size_chart'=>'The size chart image changed. Review the product again before publishing.']);
        }
        return $payload;
    }
}
