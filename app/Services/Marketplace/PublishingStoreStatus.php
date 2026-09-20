<?php
namespace App\Services\Marketplace;
use Illuminate\Support\Facades\DB;
final class PublishingStoreStatus
{
    public function read(int $store): array
    {
        $mapped=DB::table('marketplace_variant_mappings as m')->join('product_variants as v','v.id','=','m.product_variant_id')->where('m.store_id',$store)->distinct()->pluck('v.product_id');
        $statuses=[];
        foreach ($mapped as $id) $statuses[(string)$id]='listed';
        $seen=[];
        foreach (DB::table('marketplace_publishing_tasks')->where('store_id',$store)->orderByDesc('id')->get(['product_id','status','external_item_id']) as $task) {
            $id=(string)$task->product_id;
            if (isset($seen[$id])) continue;
            $seen[$id]=true;
            if (($statuses[$id]??null)==='listed') continue;
            if ($task->status==='rejected' && !$task->external_item_id) continue;
            if (!in_array($task->status,['queued','processing','uncertain','succeeded'],true) && !$task->external_item_id) continue;
            $statuses[$id]=$task->status==='succeeded'?'listed':'review';
        }
        return $statuses;
    }

    public function reviewTasks(int $store, int $actorId): array
    {
        $result=[];
        $rows=DB::table('marketplace_publishing_tasks')->where('store_id',$store)
            ->orderByDesc('id')->get(['id','product_id']);
        $review=app(PublishingTaskReview::class);
        foreach ($rows as $row) if (!isset($result[(string)$row->product_id])) {
            $result[(string)$row->product_id]=$review->read((int)$row->id,$actorId);
        }
        return $result;
    }
}
