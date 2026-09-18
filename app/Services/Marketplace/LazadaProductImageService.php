<?php
namespace App\Services\Marketplace;

use App\Domain\Cms\MediaUrlPolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LazadaProductImageService
{
    public function __construct(private MarketplaceLocalImage $images, private MarketplaceImageReceipts $receipts,
        private LazadaTokenManager $tokens, private LazadaClient $client, private BasicPublishingDestination $destinations) {}

    public function prepare(int $storeId, int $productId, int $imageId, int $actorId): int
    {
        if (config('marketplace.publish_operations_enabled')!==true) throw new RuntimeException('Marketplace image processing is disabled.');
        if (!DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) throw new RuntimeException('An authenticated administrator is required.');
        return DB::transaction(function () use ($storeId,$productId,$imageId,$actorId) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if (!$store || $store->platform!=='lazada' || $store->status!=='connected') throw new RuntimeException('Connect the Lazada store before preparing images.');
            $this->destinations->check($storeId,$productId);
            $image=DB::table('product_images')->where('product_id',$productId)->where('id',$imageId)->lockForUpdate()->first();
            if (!$image) throw new RuntimeException('Select an image owned by this JW product.');
            $remote=MarketplaceImageReceipts::isLazadaCatalogUrl($image->path);
            $bytes=$remote ? '' : $this->images->read($productId,$imageId);
            $url=$remote ? $image->path : rtrim((string)config('app.url'),'/').$image->path;
            if (!MediaUrlPolicy::isSafe($url)) throw new RuntimeException('Lazada needs a publicly accessible HTTPS JW image. Localhost images cannot be migrated.');
            $token=$this->tokens->accessToken($storeId);
            $hash=MarketplaceImageReceipts::fingerprint($store,$bytes,$image->path);
            $existing=$this->receipts->find($storeId,$imageId,$hash);
            if ($existing) return (int)$existing->id;
            $identity=$this->client->read('/seller/get',$token);
            LazadaSellerIdentity::assertCurrent($store,$identity,'before image migration');
            $uploaded=$this->client->migrateImage($token,$url);
            return $this->receipts->record($storeId,$productId,$imageId,$actorId,$hash,$uploaded);
        });
    }
}
