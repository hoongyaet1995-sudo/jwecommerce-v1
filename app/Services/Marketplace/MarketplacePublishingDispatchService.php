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
            $payload=json_decode($task->payload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) throw new RuntimeException('Marketplace publishing payload is invalid.');
            $confirmed=match ($store->platform) {
                'shopee' => ['item_id'=>$this->publishShopee($store, $payload)],
                'lazada' => $this->publishLazada($store, $payload),
                default => throw new RuntimeException('This marketplace publishing platform is not yet executable.'),
            };
            $externalItemId=$confirmed['item_id'];
            try {
                $saved=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('status','processing')
                    ->whereNull('external_item_id')->whereNull('confirmed_result')
                    ->update(['external_item_id'=>$externalItemId,'confirmed_result'=>json_encode($confirmed,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
                if ($saved!==1 || !$this->tasks->transition($taskId, 'processing', 'succeeded', $externalItemId)) {
                    throw new RuntimeException('Confirmed result persistence failed.');
                }
            } catch (\Throwable) {
                throw new PublishingResultUncertain('Marketplace creation was confirmed but the local result could not be persisted. Reconcile before retrying.');
            }
            return ['id'=>$taskId, 'status'=>'succeeded', 'external_item_id'=>$externalItemId];
        } catch (PublishingResultUncertain $error) {
            $this->tasks->transition($taskId, 'processing', 'uncertain');
            \Illuminate\Support\Facades\Log::warning('Marketplace publishing result requires reconciliation',
                ['task_id'=>$taskId,'platform'=>$store->platform]+$error->diagnostics());
            throw $error;
        } catch (\Throwable $error) {
            $this->tasks->transition($taskId, 'processing', 'rejected');
            throw $error;
        }
    }

    private function publishShopee(object $store, array $payload): string
    {
        if (!is_string($store->external_shop_id) || !preg_match('/^[1-9][0-9]*$/D', $store->external_shop_id)) {
            throw new RuntimeException('Shopee store identity is invalid.');
        }
        return $this->shopee->createItem($this->shopeeTokens->accessToken((int)$store->id), $store->external_shop_id, $payload);
    }

    private function publishLazada(object $store, array $payload): array
    {
        return $this->lazada->createProduct($this->lazadaTokens->accessToken((int)$store->id), $payload);
    }
}
