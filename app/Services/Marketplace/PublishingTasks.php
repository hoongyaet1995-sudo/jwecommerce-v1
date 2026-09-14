<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Internal persistence boundary; callers must supply a validated provider payload. */
final class PublishingTasks
{
    /** A lost worker is not evidence of a rejected write. Never requeue it here. */
    public function markInterrupted(\DateTimeInterface $inactiveBefore): int
    {
        return DB::table('marketplace_publishing_tasks')->where('status','processing')
            ->where('updated_at','<',$inactiveBefore)
            ->update(['status'=>'uncertain','updated_at'=>now()]);
    }

    public function create(int $storeId, int $productId, int $actorId, string $requestKey, array $payload, ?array $source = null): int
    {
        if (!DB::table('users')->where('id',$actorId)->where('is_admin',true)->exists()) {
            throw new \DomainException('An authenticated marketplace administrator is required.');
        }
        if (!Str::isUuid($requestKey)) throw new \DomainException('A stable request UUID is required.');
        $json=json_encode($this->canonical($payload),JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $hash=hash('sha256',$json);
        $revision=$source['revision'] ?? null;
        if ($source !== null && (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/D',$revision) || ($source['product_id'] ?? null)!==$productId)) {
            throw new \DomainException('A valid reviewed product source is required.');
        }
        $snapshot=$source===null ? null : json_encode($source,JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        return DB::transaction(function () use ($storeId,$productId,$actorId,$requestKey,$json,$hash,$revision,$snapshot) {
            $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
            abort_unless($store,404);
            $existing=DB::table('marketplace_publishing_tasks')->where('store_id',$storeId)->where('request_key',$requestKey)->first();
            if ($existing) {
                if ($existing->payload_hash!==$hash || (int)$existing->product_id!==$productId || (int)$existing->actor_id!==$actorId || $existing->source_revision!==$revision) {
                    throw new \DomainException('This request identity already belongs to another reviewed submission.');
                }
                return (int)$existing->id;
            }
            if ((new PublishingStoreStatus)->read($storeId)[(string)$productId]??false) throw new \DomainException('This product already has a listing or publishing attempt in this store. Review its mapping or saved task.');
            if ($store->status!=='connected') throw new \DomainException('Reconnect the destination store before publishing.');
            if (DB::table('marketplace_publishing_tasks')->where('store_id',$storeId)->where('product_id',$productId)
                ->where(fn ($query) => $query->whereIn('status',['queued','processing','uncertain','succeeded'])
                    ->orWhereNotNull('external_item_id'))->exists()) {
                throw new \DomainException('This product already has a listing attempt. Review its result before submitting again.');
            }
            return DB::table('marketplace_publishing_tasks')->insertGetId([
                'store_id'=>$storeId,'product_id'=>$productId,'actor_id'=>$actorId,'request_key'=>$requestKey,
                'payload_hash'=>$hash,'payload'=>$json,'status'=>'queued','created_at'=>now(),'updated_at'=>now(),
                'source_revision'=>$revision,'source_snapshot'=>$snapshot,
            ]);
        });
    }

    /** Store the confirmed parent before variant work; this is not whole-listing success. */
    public function recordParent(int $taskId, string $externalItemId): bool
    {
        if (trim($externalItemId)==='') throw new \DomainException('External item identity is required.');
        return DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('status','processing')
            ->whereNull('external_item_id')->update(['external_item_id'=>trim($externalItemId),'updated_at'=>now()])===1;
    }

    /** Compare-and-set prevents a duplicate or stale worker from claiming a transition. */
    public function transition(int $taskId, string $expectedStatus, string $nextStatus, ?string $externalItemId = null): bool
    {
        PublishingTransition::validate($expectedStatus,$nextStatus,$externalItemId);
        if ($nextStatus==='processing') {
            return DB::transaction(function () use ($taskId,$expectedStatus) {
                $storeId=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->value('store_id');
                if (!$storeId) return false;
                // Match disconnect's store-first lock order. Claiming is not permission
                // to bypass the provider's credential/connection check before HTTP.
                $store=DB::table('marketplace_stores')->where('id',$storeId)->lockForUpdate()->first();
                if (!$store || $store->status!=='connected') return false;
                return DB::table('marketplace_publishing_tasks')->where('id',$taskId)
                    ->where('status',$expectedStatus)
                    ->update(['status'=>'processing','updated_at'=>now()])===1;
            });
        }
        $changes=['status'=>$nextStatus,'updated_at'=>now()];
        if ($nextStatus==='succeeded') $changes['external_item_id']=trim($externalItemId);
        $query=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('status',$expectedStatus);
        if ($nextStatus==='succeeded') {
            $query->where(fn ($identity) => $identity->whereNull('external_item_id')
                ->orWhere('external_item_id',trim($externalItemId)));
        }
        return $query->update($changes)===1;
    }

    public function payloadsMatch(array $left,array $right): bool
    {
        return $this->canonical($left)===$this->canonical($right);
    }

    private function canonical(array $value): array
    {
        if (!array_is_list($value)) ksort($value,SORT_STRING);
        foreach ($value as &$item) if (is_array($item)) $item=$this->canonical($item);
        return $value;
    }
}
