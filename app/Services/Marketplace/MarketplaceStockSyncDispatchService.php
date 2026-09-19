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

    /** Internal scheduler recovery: reads the existing attempt, never dispatches it. */
    public function recheck(int $taskId): array
    {
        $task=DB::table('marketplace_stock_sync_tasks')->find($taskId);
        if (!$task || $task->status!=='uncertain') throw new RuntimeException('Only uncertain tasks can be rechecked.');
        $result=json_decode($task->result_snapshot ?? '{}',true,512,JSON_THROW_ON_ERROR);
        $key=$result['sandbox_attempt_id']??null;
        if (!$key) throw new RuntimeException('This task has no linked sandbox observation.');
        $review=$this->tasks->review($taskId,['uncertain']);
        if (!$review['current']) throw new RuntimeException('Task changed; preserve its original observation.');
        $actor=\App\Models\User::findOrFail($task->requested_by);
        $attempts=app(SandboxInventoryAttemptService::class);
        $attempt=$attempts->inspect($key,$actor);
        if ($attempt['status']==='uncertain') $attempt=$attempts->recheck($key,$actor);
        if ($attempt['status']==='confirmed') {
            DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','uncertain')->update([
                'status'=>'confirmed','confirmed_at'=>now(),'error_code'=>null,'updated_at'=>now(),
                'result_snapshot'=>json_encode(['sandbox_attempt_id'=>$key,'status'=>'confirmed','recovered_by_readback'=>true],JSON_THROW_ON_ERROR),
            ]);
        }
        return ['id'=>$taskId,'status'=>$attempt['status'],'sandbox_attempt_id'=>$key];
    }

    public function dispatch(int $taskId): array
    {
        if (config('marketplace.stock_sync_operations_enabled') !== true) {
            throw new RuntimeException('Marketplace stock synchronization is disabled.');
        }
        $candidate=DB::table('marketplace_stock_sync_tasks')->find($taskId);
        $managed=$candidate && DB::table('inventory_order_activations')->where('channel_key','marketplace:'.$candidate->store_id)
            ->where('inventory_balance_id',$candidate->inventory_balance_id)->exists();
        if ($managed) {
            $actor=\App\Models\User::findOrFail($candidate->requested_by);
            // This check includes the separate test switch, designated store and credential provenance.
            app(SandboxInventoryWriteGuard::class)->review((int)$candidate->store_id,(int)$candidate->inventory_balance_id,$actor);
            if ($candidate->status==='confirmed') {
                $result=json_decode($candidate->result_snapshot,true,512,JSON_THROW_ON_ERROR);
                return ['id'=>$taskId,'status'=>'confirmed','desired_quantity'=>(int)$candidate->desired_quantity,'sandbox_attempt_id'=>$result['sandbox_attempt_id']];
            }
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
        if ($managed) {
            $attemptKey=\Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL,'jw:managed-stock-task:'.$taskId)->toString();
            try {
                $attempts=app(SandboxInventoryAttemptService::class);
                $attempt=$attempts->stage((int)$task->store_id,(int)$task->inventory_balance_id,$attemptKey,'Managed stock sync task '.$taskId,$actor);
                if ($attempt['snapshot']['balance_version']!==(int)$task->balance_version
                    || $attempt['snapshot']['desired_quantity']!==(int)$task->desired_quantity
                    || $attempt['snapshot']['route_version']!==(int)$task->route_version) {
                    $this->reject($taskId,'stale_wms_state');
                    throw new RuntimeException('Managed stock task changed before dispatch.');
                }
                $attempt=$attempts->dispatch($attemptKey,$actor);
                $confirmed=$attempt['status']==='confirmed';
                DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->update([
                    'status'=>$confirmed?'confirmed':'uncertain','confirmed_at'=>$confirmed?now():null,
                    'error_code'=>$confirmed?null:'managed_provider_result_unconfirmed',
                    'result_snapshot'=>json_encode(['sandbox_attempt_id'=>$attemptKey,'status'=>$attempt['status']],JSON_THROW_ON_ERROR),'updated_at'=>now(),
                ]);
                return ['id'=>$taskId,'status'=>$confirmed?'confirmed':'uncertain','desired_quantity'=>(int)$task->desired_quantity,'sandbox_attempt_id'=>$attemptKey];
            } catch (\Throwable $error) {
                $attemptState=DB::table('sandbox_inventory_attempts')->where('id',$attemptKey)->value('status');
                if ($attemptState==='staged') {
                    DB::table('marketplace_stock_sync_tasks')->where('id',$taskId)->where('status','processing')->update([
                        'status'=>'staged','dispatched_at'=>null,'error_code'=>'pre_send_retry_required','updated_at'=>now(),
                    ]);
                } else {
                    $this->uncertain($taskId,'managed_provider_result_unconfirmed');
                }
                throw $error;
            }
        }
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
