<?php
namespace App\Services\Marketplace;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Reconciles committed balances with immutable tasks; no HTTP inside inventory transactions. */
class ManagedInventoryStockSweep
{
    public function run(): array
    {
        $counts=['staged'=>0,'confirmed'=>0,'errors'=>0];
        DB::table('inventory_order_activations')->where('channel_key','like','marketplace:%')
            ->orderBy('id')->chunkById(100,function($activations) use(&$counts){
                foreach($activations as $activation) {
                    try {
                        $storeId=(int)substr($activation->channel_key,12);
                        $balance=DB::table('inventory_balances')->find($activation->inventory_balance_id);
                        $route=DB::table('inventory_fulfillment_routes')->where('channel_key',$activation->channel_key)->first();
                        if (!$balance || !$route || !$route->is_enabled) continue;
                        $actor=User::findOrFail($activation->actor_id);
                        $key='managed:'.$activation->id.':balance:'.$balance->version.':route:'.$route->version;
                        $task=app(MarketplaceStockSyncTaskService::class)->stage($storeId,(int)$balance->id,$key,$actor);
                        if ($task['status']==='uncertain' && config('marketplace.sandbox_stock_writes_enabled')===true) {
                            $result=app(MarketplaceStockSyncDispatchService::class)->recheck($task['id']);
                            if ($result['status']==='confirmed') $counts['confirmed']++;
                            continue;
                        }
                        if ($task['status']!=='staged') continue;
                        $counts['staged']++;
                        if (config('marketplace.stock_sync_operations_enabled')===true && config('marketplace.sandbox_stock_writes_enabled')===true) {
                            $result=app(MarketplaceStockSyncDispatchService::class)->dispatch($task['id']);
                            if ($result['status']==='confirmed') $counts['confirmed']++;
                        }
                    } catch (\Throwable $error) {
                        $counts['errors']++;
                        // Stable identities only: provider exception text may contain signed URLs.
                        Log::warning('Managed inventory sync needs review',['activation_id'=>$activation->id,'error_type'=>get_class($error)]);
                    }
                }
            });
        return $counts;
    }
}
