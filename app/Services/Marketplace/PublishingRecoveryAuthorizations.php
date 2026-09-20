<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PublishingRecoveryAuthorizations
{
    public function issue(int $taskId, int $actorId, array $confirmation): array
    {
        return DB::transaction(function () use ($taskId,$actorId,$confirmation) {
            $task=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('actor_id',$actorId)->lockForUpdate()->first();
            abort_unless($task,404);
            if (!in_array($task->status,['uncertain','rejected'],true)) throw new \DomainException('Only failed or uncertain tasks can start a new reviewed attempt.');
            if ((int)($confirmation['expectedRevision']??0)!==(int)($task->revision??1)) throw new \DomainException('This task changed. Reload it before continuing.');
            $id=(string)Str::uuid();
            DB::table('marketplace_publishing_recovery_authorizations')->insert([
                'id'=>$id,'task_id'=>$taskId,'actor_id'=>$actorId,'store_id'=>$task->store_id,'product_id'=>$task->product_id,
                'reason'=>trim($confirmation['reason']),'confirmation_version'=>$confirmation['confirmationVersion'],
                'blocker_hash'=>$this->blockerHash($task),'expires_at'=>now()->addMinutes(30),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['authorizationId'=>$id,'expiresAt'=>now()->addMinutes(30)->toIso8601String(),'previousTaskId'=>$taskId];
        });
    }

    public function blockerHash(object $task): string
    {
        return hash('sha256',implode('|',[$task->id,$task->status,$task->external_item_id??'',(int)($task->revision??1)]));
    }
}
