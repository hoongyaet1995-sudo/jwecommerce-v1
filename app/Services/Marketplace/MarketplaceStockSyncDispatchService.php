<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Executes a previously staged WMS stock task exactly once after a fresh review. */
final class MarketplaceStockSyncDispatchService
{
    public function __construct(
        private MarketplaceStockSyncTaskService $tasks,
        private LazadaTokenManager $lazadaTokens,
        private LazadaClient $lazada,
    ) {}

    public function dispatch(int $taskId): array
    {
        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace stock synchronization is disabled.');
        }
        $task=DB::transaction(function () use ($taskId) {
            $row=DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->lockForUpdate()->first();
            if (!$row || $row->status!=='staged') throw new RuntimeException('Marketplace stock task is no longer staged.');
            if (DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','staged')->update(['status'=>'processing','dispatched_at'=>now(),'updated_at'=>now()])!==1) {
                throw new RuntimeException('Marketplace stock task could not be claimed.');
            }
            return $row;
        });
        $review=$this->tasks->review($taskId, ['processing']);
        if (!$review['current']) {
            $this->reject($taskId, 'stale_wms_state');
            throw new RuntimeException('Marketplace stock task is stale: '.implode(',', $review['blockers']));
        }
        $snapshot=json_decode($task->request_snapshot,true,512,JSON_THROW_ON_ERROR);
        $store=DB::table('marketplace_stores')->find($task->store_id);
        if (!$store || $store->platform!=='lazada') {
            $this->uncertain($taskId,'platform_dispatch_not_implemented');
            throw new RuntimeException('This marketplace stock platform is not yet executable.');
        }
        try {
            $this->lazada->updateAbsoluteQuantity($this->lazadaTokens->accessToken((int)$task->store_id),
                (string)$snapshot['external_product_id'],(string)$snapshot['external_variant_id'],(int)$task->desired_quantity,
                (string)$task->platform_location_id);
        } catch (\Throwable $error) {
            $this->uncertain($taskId,'provider_result_unconfirmed');
            throw $error;
        }
        DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','processing')->update([
            'status'=>'confirmed','confirmed_at'=>now(),'result_snapshot'=>json_encode(['provider'=>'lazada','confirmed_quantity'=>(int)$task->desired_quantity],JSON_THROW_ON_ERROR),'updated_at'=>now(),
        ]);
        return ['id'=>$taskId,'status'=>'confirmed','desired_quantity'=>(int)$task->desired_quantity];
    }

    private function reject(int $taskId,string $code): void
    {
        DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','processing')->update(['status'=>'rejected','error_code'=>$code,'updated_at'=>now()]);
    }

    private function uncertain(int $taskId,string $code): void
    {
        DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','processing')->update(['status'=>'uncertain','error_code'=>$code,'updated_at'=>now()]);
    }
}
