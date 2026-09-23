<?php
namespace App\Services\Marketplace;

use App\Models\MarketplaceOAuthBatch;
use App\Models\MarketplaceOAuthBatchStore;
use App\Models\MarketplaceStoreToken;
use App\Services\Inventory\StoreManagement\StoreEnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ShopeeOAuthBatchProcessor
{
    public function __construct(private ShopeeClient $client, private StoreEnrollmentService $enrollment) {}

    public function process(int $batchId, bool $retry=false): void
    {
        $batch=MarketplaceOAuthBatch::findOrFail($batchId);
        if ($batch->provider!=='shopee') return;
        try {
            $context=app(ShopeeApplicationResolver::class)->forEnvironment($batch->environment);
        } catch (RuntimeException) {
            return;
        }
        if ($batch->application_id!==$context->partnerId) return;
        $client=$batch->environment===config('marketplace.shopee.environment')
            && $batch->application_id===(string)config('marketplace.shopee.partner_id')
            && hash_equals($context->partnerKey, (string)config('marketplace.shopee.partner_key'))
            ? $this->client : $this->client->forApplication($context);
        $query=$batch->stores()->whereIn('status',$retry?['failed']:['pending','failed'])->orderBy('id');
        foreach ($query->get() as $row) $this->processStore($batch,$row,$client);
        $this->recalculate($batch->fresh());
    }

    private function processStore(MarketplaceOAuthBatch $batch, MarketplaceOAuthBatchStore $row, ShopeeClient $client): void
    {
        $claimed=MarketplaceOAuthBatchStore::whereKey($row->id)->whereIn('status',['pending','failed'])->update([
            'status'=>'processing','attempt_count'=>DB::raw('attempt_count + 1'),'last_attempt_at'=>now(),
            'failure_code'=>null,'failure_message'=>null,'updated_at'=>now()]);
        if ($claimed!==1) return;
        $row->refresh();
        try {
            if (!$batch->seed_refresh_token || ($batch->seed_refresh_expires_at && now()->greaterThanOrEqualTo($batch->seed_refresh_expires_at))) {
                throw new RuntimeException('reauthorize');
            }
            $tokens=$client->refreshShopCredential($batch->seed_refresh_token,$row->external_shop_id);
            $identity=$client->shopInfo($tokens['access_token'],$row->external_shop_id);
            [$storeId,$type]=DB::transaction(function () use($batch,$identity,$tokens) {
                $store=DB::table('marketplace_stores')->where('platform','shopee')->where('environment',$batch->environment)
                    ->where('external_shop_id',$identity['shop_id'])->lockForUpdate()->first();
                if ($store) {
                    $bound=MarketplaceStoreToken::where('store_id',$store->id)->first();
                    if ($bound?->application_id && $bound->application_id!==$batch->application_id) {
                        throw new RuntimeException('identity_conflict');
                    }
                    $storeId=(int)$store->id; $type='reauthorized';
                    DB::table('marketplace_stores')->where('id',$storeId)->update(['shop_name'=>$identity['shop_name'],
                        'region'=>$identity['region'],'status'=>'connected','updated_at'=>now()]);
                } else {
                    $storeId=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','environment'=>$batch->environment,
                        'account_mode'=>'production','external_shop_id'=>$identity['shop_id'],'shop_name'=>$identity['shop_name'],
                        'region'=>$identity['region'],'status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
                    $type='connected';
                }
                MarketplaceStoreToken::updateOrCreate(['store_id'=>$storeId],['application_id'=>$batch->application_id,
                    'access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token'],
                    'access_expires_at'=>now()->addSeconds($tokens['expire_in']),'refresh_expires_at'=>null]);
                DB::table('marketplace_store_capabilities')->insertOrIgnore(['store_id'=>$storeId,'catalog_read'=>true,
                    'product_publish'=>false,'order_receive'=>false,'inventory_update'=>false,'fulfillment_write'=>false,
                    'created_at'=>now(),'updated_at'=>now()]);
                return [$storeId,$type];
            });
            $this->enrollment->enroll($storeId);
            $row->update(['status'=>'succeeded','result_type'=>$type,'marketplace_store_id'=>$storeId]);
            try { \App\Jobs\WarmShopeeListingMetadata::dispatch($storeId); } catch (\Throwable) {}
        } catch (\Throwable $e) {
            $code=$e->getMessage()==='reauthorize'?'reauthorize_main_account':($e->getMessage()==='identity_conflict'?'identity_conflict':'provider_temporarily_unavailable');
            $message=match($code){
                'reauthorize_main_account'=>'Reauthorize Main Account to continue.',
                'identity_conflict'=>'This store belongs to another Shopee application or environment.',
                default=>'Shopee is temporarily unavailable. Retry this store.',
            };
            $row->update(['status'=>'failed','failure_code'=>$code,'failure_message'=>$message]);
            Log::warning('Shopee OAuth batch store failed',['batch_id'=>$batch->id,'shop_id'=>$row->external_shop_id,'failure_code'=>$code]);
        }
    }

    private function recalculate(MarketplaceOAuthBatch $batch): void
    {
        $rows=$batch->stores()->get();
        $connected=$rows->where('result_type','connected')->where('status','succeeded')->count();
        $reauthorized=$rows->where('result_type','reauthorized')->where('status','succeeded')->count();
        $failed=$rows->where('status','failed')->count();
        $processing=$rows->whereIn('status',['pending','processing'])->count();
        $status=$processing?'processing':($failed?($connected+$reauthorized?'partial':'failed'):'success');
        $values=['connected_count'=>$connected,'reauthorized_count'=>$reauthorized,'failed_count'=>$failed,'status'=>$status,
            'completed_at'=>$processing?null:now()];
        if ($status==='success') $values+=['seed_access_token'=>null,'seed_refresh_token'=>null];
        $batch->update($values);
    }
}
