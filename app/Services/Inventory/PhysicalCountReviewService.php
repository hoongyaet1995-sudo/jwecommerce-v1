<?php

namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Physical count staging only. Does not activate accounting or infer order coverage. */
class PhysicalCountReviewService
{
    public function review(int $balanceId, int $count, string $reason, User $actor, string $kind='count'): array
    {
        $this->authorize($actor);
        if (!in_array($kind,['count','delta'],true) || ($kind==='count' && $count<0) || abs($count)>2147483647 || trim($reason) === '' || mb_strlen($reason) > 255) {
            $this->reject('Enter a valid physical count and reason.');
        }
        return DB::transaction(function () use ($balanceId,$count,$reason,$actor,$kind) {
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            // Use the same variant-before-balance lock order as confirmation and future writers.
            $identity = DB::table('inventory_balances')->find($balanceId);
            if (!$identity) $this->reject('Inventory balance does not exist.');
            $variant = DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
            $balance = DB::table('inventory_balances')->where('id',$balanceId)->lockForUpdate()->first();
            $delta=$kind==='delta'?$count:null;
            if($kind==='delta'){
                if($balance->physical_stock===null||$balance->counted_at===null)$this->reject('Count physical stock before applying a delta.');
                $count=(int)$balance->physical_stock+$delta;
                if($count<0||$count>2147483647)$this->reject('Adjustment exceeds physical stock bounds.');
            }
            $review = [
                'id'=>(string)Str::uuid(), 'inventory_balance_id'=>$balanceId, 'actor_id'=>$actor->id,
                'physical_count'=>$count, 'balance_version'=>(int)$balance->version,
                'legacy_stock_version'=>(int)$variant->stock_version, 'reason'=>trim($reason),
                'operation_kind'=>$kind,'quantity_delta'=>$delta,
                'configuration_version'=>(int)$configuration->version,
                'expires_at'=>now()->addMinutes(15)->toDateTimeString(), 'created_at'=>now()->toDateTimeString(),
            ];
            $atp=$this->activeProjection($configuration,$balance,$count);
            DB::table('inventory_count_reviews')->insert($review);
            return $review + ['physical_before'=>$balance->physical_stock === null ? null : (int)$balance->physical_stock,
                'atp'=>$atp['after']??null, 'coverage_verified'=>$atp!==null];
        },3);
    }

    public function confirm(string $reviewId, User $actor, bool $acknowledged): array
    {
        $this->authorize($actor);
        if (!$acknowledged) $this->reject('Acknowledge the reviewed physical count before confirming.');
        return DB::transaction(function () use ($reviewId,$actor) {
            $configuration=DB::table('inventory_configuration')->where('id',1)->lockForUpdate()->first();
            $review = DB::table('inventory_count_reviews')->where('id',$reviewId)->lockForUpdate()->first();
            if (!$review || (int)$review->actor_id !== (int)$actor->id) $this->reject('Review is unavailable for this employee.');
            $existing = DB::table('inventory_physical_movements')->where('review_id',$reviewId)->first();
            if ($existing) return $this->receipt($existing);
            if(($review->configuration_version===null && $configuration->accounting_enabled)
                ||($review->configuration_version!==null && (int)$review->configuration_version!==(int)$configuration->version))$this->reject('Inventory configuration changed. Review again.');
            if (Carbon::parse($review->expires_at)->lte(now())) $this->reject('Review expired. Review the count again.');
            $identity = DB::table('inventory_balances')->find($review->inventory_balance_id);
            $variant = DB::table('product_variants')->where('id',$identity->product_variant_id)->lockForUpdate()->first();
            $balance = DB::table('inventory_balances')->where('id',$identity->id)->lockForUpdate()->first();
            if ((int)$balance->version !== (int)$review->balance_version
                || (int)$variant->stock_version !== (int)$review->legacy_stock_version) {
                $this->reject('Inventory changed. Review the count again.');
            }
            $before = $balance->physical_stock === null ? null : (int)$balance->physical_stock;
            $count = (int)$review->physical_count;
            $version = (int)$balance->version + 1;
            $atp=$this->activeProjection($configuration,$balance,$count);
            if($atp!==null)app(InventoryMutationService::class)->change((int)$balance->product_variant_id,$atp['after']-$atp['before'],
                'physical-review:'.$reviewId,'Warehouse stock operation',(int)$actor->id);
            DB::table('inventory_balances')->where('id',$balance->id)->update([
                'physical_stock'=>$count,'counted_at'=>$review->operation_kind==='delta'?$balance->counted_at:now(),'version'=>$version,
                // Legacy writers are not yet routed through physical accounting.
                'obligations_reconciled_at'=>$atp!==null?$balance->obligations_reconciled_at:null,'updated_at'=>now(),
            ]);
            $id = DB::table('inventory_physical_movements')->insertGetId([
                'review_id'=>$reviewId,'inventory_balance_id'=>$balance->id,'actor_id'=>$actor->id,
                'physical_before'=>$before,'physical_after'=>$count,'physical_delta'=>$before === null ? null : $count-$before,
                'balance_version'=>$version,'reason'=>$review->reason,'created_at'=>now(),
            ]);
            return $this->receipt(DB::table('inventory_physical_movements')->find($id));
        },3);
    }

    private function authorize(User $actor): void
    {
        if (!$actor->exists || !User::query()->whereKey($actor->id)->where('is_admin',true)->exists()) {
            $this->reject('An authenticated inventory administrator is required.');
        }
    }

    private function activeProjection(object $configuration,object $balance,int $count):?array
    {
        if(!$configuration->accounting_enabled)return null;
        $pool=DB::table('inventory_pools')->find($balance->inventory_pool_id);
        $kind=$configuration->operating_mode==='shared_pool'?'shared':($configuration->operating_mode==='independent_warehouses'?'warehouse':null);
        if(!$kind||!$pool||$pool->kind!==$kind)$this->reject('Balance does not belong to the active inventory mode.');
        $reader=app(PhysicalBalanceReader::class);$before=$reader->project($balance);
        if(!$before['verified'])$this->reject('Reconcile warehouse obligations before an active stock adjustment.');
        $next=clone $balance;$next->physical_stock=$count;
        return ['before'=>$before['atp'],'after'=>$reader->project($next)['atp']];
    }

    private function receipt(object $row): array
    {
        return ['id'=>(int)$row->id,'review_id'=>$row->review_id,'actor_id'=>(int)$row->actor_id,
            'physical_before'=>$row->physical_before === null ? null : (int)$row->physical_before,
            'physical_after'=>(int)$row->physical_after,
            'physical_delta'=>$row->physical_delta === null ? null : (int)$row->physical_delta,
            'balance_version'=>(int)$row->balance_version,'reason'=>$row->reason];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['inventory'=>$message]);
    }
}
