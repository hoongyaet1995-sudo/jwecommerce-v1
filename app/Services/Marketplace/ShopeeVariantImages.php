<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;

final class ShopeeVariantImages
{
    public function prepare(int $storeId,array $source,int $actor,array $choices=[]): void
    {
        foreach (ShopeeVariantImagePlan::fromSource($source,$choices) as $image) {
            DB::transaction(function() use($storeId,$source,$actor,$image){
                abort_unless(config('marketplace.publish_operations_enabled')===true,403);
                abort_unless(DB::table('users')->where('id',$actor)->where('is_admin',true)->exists(),403);
                $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
                abort_unless($store && $store->platform==='shopee' && $store->status==='connected',409);
                app(BasicPublishingDestination::class)->check($storeId,$source['product_id']);
                $bytes=app(MarketplaceLocalImage::class)->readVariantForShopee($source['product_id'],$image['variant_id'],$image['path']);
                $hash=MarketplaceImageReceipts::fingerprint($store,$bytes,'');
                if ($this->receipt($storeId,$source['product_id'],$hash)) return;
                $token=app(ShopeeTokenManager::class)->accessToken($storeId);
                $client=app(ShopeeApplicationResolver::class)->clientForStore($storeId,app(ShopeeClient::class));
                $identity=$client->shopInfo($token,$store->external_shop_id);
                abort_unless((string)($identity['shop_id']??'')===$store->external_shop_id && ($identity['region']??'')===$store->region,409,'Shopee store identity changed.');
                $uploaded=$client->uploadImage($bytes);
                DB::table('shopee_variant_image_receipts')->insert(['store_id'=>$storeId,'product_id'=>$source['product_id'],
                    'source_hash'=>$hash,'provider_identity'=>$uploaded,'actor_id'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            });
        }
    }

    public function bind(int $storeId,array $source,array $payload): array
    {
        $plan=ShopeeVariantImagePlan::fromSource($source,$payload['jw_variant_image_choices']??[]);
        if (!$plan) return $payload;
        $store=DB::table('marketplace_stores')->find($storeId);
        abort_unless($store,404);
        foreach ($plan as $index=>$image) {
            $bytes=app(MarketplaceLocalImage::class)->readVariantForShopee($source['product_id'],$image['variant_id'],$image['path']);
            $hash=MarketplaceImageReceipts::fingerprint($store,$bytes,'');
            $receipt=$this->receipt($storeId,$source['product_id'],$hash);
            if (!$receipt) throw new \DomainException('Prepare the current variant images before publishing.');
            $payload['jw_variations']['tier_variation'][0]['option_list'][$index]['image']=['image_id'=>$receipt->provider_identity];
        }
        return $payload;
    }

    private function receipt(int $store,int $product,string $hash): ?object
    {
        return DB::table('shopee_variant_image_receipts')->where('store_id',$store)->where('product_id',$product)->where('source_hash',$hash)->first();
    }
}
