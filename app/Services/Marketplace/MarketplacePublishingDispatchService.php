<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Executes one queued, already-reviewed listing task and preserves provider ambiguity. */
final class MarketplacePublishingDispatchService
{
    public function __construct(
        private PublishingTasks $tasks,
        private ShopeeTokenManager $shopeeTokens,
        private ShopeeClient $shopee,
        private LazadaTokenManager $lazadaTokens,
        private LazadaClient $lazada,
    ) {}

    public function dispatch(int $taskId): array
    {
        $stage='review';
        if (config('marketplace.publish_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace publishing is disabled.');
        }
        if (!$this->tasks->transition($taskId, 'queued', 'processing')) {
            throw new RuntimeException('Marketplace publishing task is no longer queued.');
        }
        $task=DB::table('marketplace_publishing_tasks')->find($taskId);
        $store=$task ? DB::table('marketplace_stores')->find($task->store_id) : null;
        if (!$task || !$store || $store->status!=='connected') {
            $this->tasks->transition($taskId, 'processing', 'rejected');
            throw new RuntimeException('Marketplace store is no longer connected.');
        }
        try {
            $stage='credentials';
            app(MarketplaceStoreAccess::class)->publishingStore((int)$task->store_id);
            if ($task->source_revision !== null) {
                $current=app(MarketplacePublishingSource::class)->read((int)$task->product_id);
                if (!hash_equals($task->source_revision,$current['revision'])) {
                    throw new RuntimeException('The product changed after review. Review it again before publishing.');
                }
            }
            $stage='review';
            app(BasicPublishingDestination::class)->check((int)$store->id,(int)$task->product_id);
            $payload=json_decode($task->payload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) throw new RuntimeException('Marketplace publishing payload is invalid.');
            if ($store->platform==='shopee') ShopeePublishingVariants::validate($payload,$current??null);
            if (config('marketplace.basic_wms_enabled')===true && $store->platform==='shopee' && $task->source_revision!==null) {
                $bound=ShopeePublishingMasterData::bind($current,$payload);
                $bound=app(ShopeePublishingImages::class)->bind((int)$store->id,$current,$bound);
                if (!$this->tasks->payloadsMatch($bound,$payload)) throw new RuntimeException('JW publishing data or images changed after review. Review the product again.');
            }
            if (config('marketplace.basic_wms_enabled')===true && $store->platform==='lazada' && $task->source_revision!==null) {
                $withCurrentImages=app(LazadaPublishingImages::class)->bind((int)$store->id,$current,$payload);
                if ($withCurrentImages!==$payload) throw new RuntimeException('Product images changed after review. Review again before publishing.');
                app(LazadaPublishingWarehouses::class)->verify((int)$store->id,$payload);
            }
            if (config('marketplace.basic_wms_enabled')===true) BasicPublishingQuantity::validate($store->platform,$payload);
            $stage='create';
            $confirmed=match ($store->platform) {
                'shopee' => $this->publishShopee($taskId, $store, $payload),
                'lazada' => $this->publishLazada($taskId, $store, $payload),
                default => throw new RuntimeException('This marketplace publishing platform is not yet executable.'),
            };
            $externalItemId=$confirmed['item_id'];
            try {
                $saved=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('status','processing')
                    ->where(fn ($query) => $query->whereNull('external_item_id')->orWhere('external_item_id',$externalItemId))->whereNull('confirmed_result')
                    ->update(['external_item_id'=>$externalItemId,'confirmed_result'=>json_encode($confirmed,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
                if ($saved!==1 || !$this->tasks->transition($taskId, 'processing', 'succeeded', $externalItemId)) {
                    throw new RuntimeException('Confirmed result persistence failed.');
                }
            } catch (\Throwable) {
                throw new PublishingResultUncertain('Marketplace creation was confirmed but the local result could not be persisted. Reconcile before retrying.');
            }
            if (in_array($store->platform,['lazada','shopee'],true) && $task->source_revision!==null) {
                try {
                    app($store->platform==='shopee'?ShopeeCatalogService::class:LazadaCatalogService::class)->pullItem((int)$store->id,$externalItemId);
                } catch (\Throwable) {
                    // A delayed catalog read never makes a confirmed creation retryable.
                    \Illuminate\Support\Facades\Log::warning('Published listing awaits catalog readback',
                        ['task_id'=>$taskId,'store_id'=>$store->id,'external_item_id'=>$externalItemId]);
                }
            }
            return ['id'=>$taskId, 'status'=>'succeeded', 'external_item_id'=>$externalItemId];
        } catch (PublishingResultUncertain $error) {
            $details=PublishingFailureDetails::fromThrowable($error,$stage);
            $this->tasks->fail($taskId, 'processing', 'uncertain',$details);
            \Illuminate\Support\Facades\Log::warning('Marketplace publishing result requires reconciliation',
                ['task_id'=>$taskId,'platform'=>$store->platform]+$error->diagnostics());
            throw $error;
        } catch (\Throwable $error) {
            $details=PublishingFailureDetails::fromThrowable($error,$stage);
            $this->tasks->fail($taskId, 'processing', 'rejected',$details);
            throw $error;
        }
    }

    private function publishShopee(int $taskId, object $store, array $payload): array
    {
        $variations=ShopeePublishingVariants::validate($payload);
        unset($payload['jw_variations'],$payload['jw_variant_image_choices']);
        $this->assertProviderPayload($payload);
        if (!is_string($store->external_shop_id) || !preg_match('/^[1-9][0-9]*$/D', $store->external_shop_id)) {
            throw new RuntimeException('Shopee store identity is invalid.');
        }
        $token=$this->shopeeTokens->accessToken((int)$store->id);
        if (config('marketplace.basic_wms_enabled')===true) {
            $identity=$this->shopee->shopInfo($token,$store->external_shop_id);
            if ((string)($identity['shop_id']??'')!==$store->external_shop_id || ($identity['region']??'')!==$store->region) {
                throw new RuntimeException('Shopee test store identity changed.');
            }
            $payload=app(ShopeeListingPreflight::class)->listing($token,$store->external_shop_id,$payload);
        }
        $itemId=$this->shopee->createItem($token, $store->external_shop_id, $payload);
        if ($variations===null) return ['item_id'=>$itemId];
        try {
            if (!$this->tasks->recordParent($taskId,$itemId)) throw new RuntimeException('Parent result persistence failed.');
            \Illuminate\Support\Sleep::for(5)->seconds();
            // Recheck connection and credentials after the provider's mandatory processing delay.
            $token=$this->shopeeTokens->accessToken((int)$store->id);
            $ids=$this->shopee->initializeModels($token,$store->external_shop_id,$itemId,$variations);
            return ['item_id'=>$itemId,'model_ids'=>$ids];
        } catch (\Throwable) {
            throw new PublishingResultUncertain('Shopee parent was created, but the complete variant result is not confirmed. Reconcile before retrying.');
        }
    }

    private function publishLazada(int $taskId, object $store, array $payload): array
    {
        $sizeChart=$payload['jw_size_chart']??null;
        if ($sizeChart!==null && !is_string($sizeChart)) throw new RuntimeException('Invalid Lazada size chart selection.');
        $token=$this->lazadaTokens->accessToken((int)$store->id);
        if (config('marketplace.basic_wms_enabled')===true) {
            $identity=$this->lazada->read('/seller/get',$token,[],(int)$store->id);
            LazadaSellerIdentity::assertCurrent($store,$identity,'before product publishing');
            $payload=app(LazadaListingPreflight::class)->validate($token,$payload,(int)$store->id);
        }
        unset($payload['jw_size_chart']);
        $this->assertProviderPayload($payload);
        $confirmed=$this->lazada->createProduct($token, $payload, (int)$store->id);
        if ($sizeChart===null) return $confirmed;
        if (!$this->tasks->recordParent($taskId,$confirmed['item_id'])) {
            throw new PublishingResultUncertain('Lazada product was created, but its parent identity could not be saved before the size chart update. Reconcile before retrying.');
        }
        try {
            $this->lazada->updateSizeChart($token,$confirmed['item_id'],$sizeChart,(int)$store->id);
        } catch (\Throwable) {
            throw new PublishingResultUncertain('Lazada product was created, but its size chart update is not confirmed. Reconcile before retrying.');
        }
        return $confirmed;
    }

    private function assertProviderPayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && str_starts_with($key,'jw_')) {
                throw new RuntimeException('Internal publishing fields were not resolved before provider dispatch.');
            }
            if (is_array($value)) $this->assertProviderPayload($value);
        }
    }
}
