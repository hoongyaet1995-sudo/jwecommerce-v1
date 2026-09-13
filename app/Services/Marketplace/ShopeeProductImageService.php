<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShopeeProductImageService
{
    public function __construct(private MarketplaceLocalImage $images, private MarketplaceImageReceipts $receipts,
        private ShopeeTokenManager $tokens, private ShopeeClient $client, private BasicPublishingDestination $destinations) {}

    public function prepare(int $storeId, int $productId, int $imageId, int $actorId): int
    {
        if (config('marketplace.publish_operations_enabled')!==true) throw new RuntimeException('Marketplace image processing is disabled.');
        if (!DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) throw new RuntimeException('An authenticated administrator is required.');
        // Store-first locking also serializes disconnect and duplicate preparations. Never retry an HTTP transaction.
        return DB::transaction(function () use ($storeId,$productId,$imageId,$actorId) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            if (!$store || $store->platform!=='shopee' || $store->status!=='connected') throw new RuntimeException('Connect the Shopee store before preparing images.');
            $this->destinations->check($storeId,$productId);
            $bytes=$this->images->read($productId,$imageId);
            $token=$this->tokens->accessToken($storeId);
            // Include provider identity/application so a rebound store cannot reuse a prior account's result.
            $hash=MarketplaceImageReceipts::fingerprint($store,$bytes,'');
            $existing=$this->receipts->find($storeId,$imageId,$hash);
            if ($existing) return (int)$existing->id;
            $identity=$this->client->shopInfo($token,$store->external_shop_id);
            if ((string)($identity['shop_id']??'')!==$store->external_shop_id || ($identity['region']??'')!==$store->region) {
                throw new RuntimeException('Shopee store identity changed before image upload.');
            }
            $uploaded=$this->client->uploadImage($bytes);
            return $this->receipts->record($storeId,$productId,$imageId,$actorId,$hash,$uploaded);
        });
    }
}
