<?php

namespace App\Services\Marketplace;

use DomainException;
use Illuminate\Support\Facades\DB;

/** Internal cache of confirmed transport results, never client-supplied upload claims. */
final class MarketplaceImageReceipts
{
    /** Only an owned Lazada CDN URL is sent to Lazada; JW never fetches arbitrary remote hosts. */
    public static function isLazadaCatalogUrl(string $path): bool
    {
        return \App\Domain\Cms\MediaUrlPolicy::isSafe($path)
            && str_ends_with(strtolower((string)parse_url($path, PHP_URL_HOST)), '.slatic.net')
            && (string)parse_url($path, PHP_URL_PATH) !== ''
            && parse_url($path, PHP_URL_FRAGMENT) === null;
    }

    public static function fingerprint(object $store, string $bytes, string $path): string
    {
        $parts=[$store->environment,$store->external_shop_id,(string)config('marketplace.'.$store->platform.($store->platform==='shopee'?'.partner_id':'.app_key'))];
        if ($store->platform==='lazada' && self::isLazadaCatalogUrl($path)) {
            // The receipt binds the owned URL to Lazada's confirmed migration result, not a claimed byte digest.
            return hash('sha256',json_encode([...$parts,'lazada-catalog-url-v1',$path],JSON_THROW_ON_ERROR));
        }
        if ($store->platform==='lazada') $parts[]=rtrim((string)config('app.url'),'/').$path;
        $parts[]=hash('sha256',$bytes);
        return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }

    public function current(int $storeId, int $productId, int $imageId): ?object
    {
        $store=DB::table('marketplace_stores')->find($storeId);
        $image=DB::table('product_images')->where('product_id',$productId)->find($imageId);
        if (!$store || !$image || !in_array($store->platform,['shopee','lazada'],true)) throw new DomainException('Select an owned product image and supported store.');
        $bytes=$store->platform==='lazada' && self::isLazadaCatalogUrl($image->path)
            ? '' : ($store->platform==='shopee'
                ? app(MarketplaceLocalImage::class)->readForShopee($productId,$imageId)
                : app(MarketplaceLocalImage::class)->read($productId,$imageId));
        return $this->find($storeId,$imageId,self::fingerprint($store,$bytes,$image->path));
    }

    public function find(int $storeId, int $imageId, string $sourceHash): ?object
    {
        return DB::table('marketplace_image_receipts')->where('store_id',$storeId)
            ->where('image_id',$imageId)->where('source_hash',$sourceHash)->first();
    }

    public function record(int $storeId, int $productId, int $imageId, int $actorId, string $sourceHash, string $identity): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$sourceHash) || trim($identity)==='' || strlen($identity)>2048) {
            throw new DomainException('A confirmed image result and source fingerprint are required.');
        }
        return DB::transaction(function () use ($storeId,$productId,$imageId,$actorId,$sourceHash,$identity) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            $image=DB::table('product_images')->where('id',$imageId)->where('product_id',$productId)->lockForUpdate()->first();
            if (!$store || !$image || !DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) {
                throw new DomainException('The image must belong to this product and an authenticated administrator.');
            }
            $existing=$this->find($storeId,$imageId,$sourceHash);
            if ($existing) {
                if ($existing->provider_identity!==$identity) throw new DomainException('A different confirmed image already exists for this source.');
                return (int)$existing->id;
            }
            return DB::table('marketplace_image_receipts')->insertGetId([
                'store_id'=>$storeId,'product_id'=>$productId,'image_id'=>$imageId,'actor_id'=>$actorId,
                'source_hash'=>$sourceHash,'provider_identity'=>$identity,'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
    }
}
