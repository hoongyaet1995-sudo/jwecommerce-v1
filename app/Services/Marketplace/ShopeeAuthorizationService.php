<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\StoreManagement\StoreEnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\MarketplaceOAuthBatch;
use App\Models\MarketplaceOAuthBatchStore;
use App\Jobs\ProcessShopeeOAuthBatch;
use RuntimeException;

class ShopeeAuthorizationService
{
    public function __construct(
        private OAuthStateService $states,
        private ShopeeClient $client,
        private StoreEnrollmentService $enrollment,
    ) {}

    public function complete(string $state, int $actorId, #[\SensitiveParameter] string $code, string $shopId): int
    {
        $attempt = $this->states->consume($state, $actorId, 'shopee', config('app.url'));
        if (!$attempt) {
            throw new RuntimeException('Authorization expired or invalid. Start again.');
        }
        $context = $attempt->environment !== null
            ? app(ShopeeApplicationResolver::class)->forEnvironment($attempt->environment) : null;
        $client = $context ? $this->client->forApplication($context) : $this->client;
        $tokens = $client->exchange($code, $shopId);
        $identity = $client->shopInfo($tokens['access_token'], $shopId);
        $environment = $context?->environment ?? config('marketplace.shopee.environment');
        $applicationId = $context?->partnerId ?? (string) config('marketplace.shopee.partner_id');

        $storeId = DB::transaction(function () use ($attempt, $tokens, $identity, $environment, $applicationId) {
            $store = $attempt->reconnect_store_id
                ? DB::table('marketplace_stores')->where('id', $attempt->reconnect_store_id)->lockForUpdate()->first()
                : null;
            if (!DB::table('marketplace_oauth_states')->where('id', $attempt->id)->where('expires_at', '>', now())->exists()) {
                throw new RuntimeException('Authorization expired or invalid. Start again.');
            }
            if ($attempt->reconnect_store_id && (!$store || $store->platform !== 'shopee'
                || $store->environment !== $environment || $store->external_shop_id !== $identity['shop_id'])) {
                throw new RuntimeException('Reconnect must authorize the original store.');
            }
            if (!$store && DB::table('marketplace_stores')->where('platform', 'shopee')
                ->where('environment', $environment)->where('external_shop_id', $identity['shop_id'])->exists()) {
                throw new RuntimeException('This store already exists. Use Reconnect on that store.');
            }
            $values = ['platform'=>'shopee', 'environment'=>$environment, 'external_shop_id'=>$identity['shop_id'],
                'shop_name'=>$identity['shop_name'], 'region'=>$identity['region'], 'status'=>'connected', 'updated_at'=>now()];
            if ($store) {
                $id = $store->id;
                DB::table('marketplace_stores')->where('id', $id)->update($values);
            } else {
                $id = DB::table('marketplace_stores')->insertGetId($values + ['created_at'=>now()]);
            }
            MarketplaceStoreToken::updateOrCreate(['store_id'=>$id], [
                'application_id'=>$applicationId,
                'access_token'=>$tokens['access_token'], 'refresh_token'=>$tokens['refresh_token'],
                'access_expires_at'=>now()->addSeconds($tokens['expire_in']), 'refresh_expires_at'=>null,
            ]);

            return $id;
        });
        $this->enrollment->enroll($storeId);
        try {
            \App\Jobs\WarmShopeeListingMetadata::dispatch($storeId)->afterCommit();
        } catch (\Throwable) {
            // Authorization is durable; metadata remains available through on-demand reads.
            \Illuminate\Support\Facades\Log::warning('Shopee metadata warmup could not be queued', ['store_id'=>$storeId]);
        }
        return $storeId;
    }

    public function completeMainAccount(string $state, int $actorId, #[\SensitiveParameter] string $code, string $mainAccountId): MarketplaceOAuthBatch
    {
        if (config('marketplace.shopee.main_account_enabled')!==true) {
            throw new RuntimeException('Shopee Main Account authorization is not enabled.');
        }
        $attempt=$this->states->consume($state,$actorId,'shopee',config('app.url'));
        if (!$attempt) throw new RuntimeException('Authorization expired or invalid. Start again.');
        $context=$attempt->environment!==null
            ? app(ShopeeApplicationResolver::class)->forEnvironment($attempt->environment) : null;
        $client=$context ? $this->client->forApplication($context) : $this->client;
        $environment=$context?->environment ?? config('marketplace.shopee.environment');
        $applicationId=$context?->partnerId ?? (string)config('marketplace.shopee.partner_id');
        $tokens=$client->exchangeMainAccountCode($code,$mainAccountId);
        if ($attempt->reconnect_store_id) {
            $target=DB::table('marketplace_stores')->where('id',$attempt->reconnect_store_id)->first();
            if (!$target || $target->platform!=='shopee' || $target->environment!==$environment
                || !in_array((string)$target->external_shop_id,$tokens['shop_id_list'],true)) {
                throw new RuntimeException('Reconnect must include the original store.');
            }
        }
        $batch=DB::transaction(function () use($attempt,$actorId,$mainAccountId,$tokens,$environment,$applicationId) {
            $batch=MarketplaceOAuthBatch::create(['public_id'=>(string)Str::uuid(),'provider'=>'shopee',
                'environment'=>$environment,'application_id'=>$applicationId,
                'actor_id'=>$actorId,'reconnect_store_id'=>$attempt->reconnect_store_id,'main_account_id'=>$mainAccountId,
                'seed_access_token'=>$tokens['access_token'],'seed_refresh_token'=>$tokens['refresh_token'],
                'seed_access_expires_at'=>now()->addSeconds($tokens['expire_in']),'seed_refresh_expires_at'=>now()->addDays(30),
                'merchant_id_list'=>$tokens['merchant_id_list'],
                'status'=>'processing','total_count'=>count($tokens['shop_id_list'])]);
            foreach ($tokens['shop_id_list'] as $shopId) MarketplaceOAuthBatchStore::create(['batch_id'=>$batch->id,'external_shop_id'=>$shopId]);
            return $batch;
        });
        ProcessShopeeOAuthBatch::dispatch($batch->id)->afterCommit();
        return $batch;
    }
}
