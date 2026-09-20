<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;

final class PublishingTaskReview
{
    public function read(int $taskId, int $actorId): array
    {
        $row=DB::table('marketplace_publishing_tasks')->where('id',$taskId)->where('actor_id',$actorId)->first();
        abort_unless($row,404);
        $store=DB::table('marketplace_stores')->find($row->store_id);
        $failure=$row->error_code ? [
            'code'=>$row->error_code,'message'=>$row->safe_error_message,'stage'=>$row->stage,
            'nextAction'=>$this->nextAction($row->error_code),'diagnostics'=>(object)(json_decode($row->safe_diagnostics??'{}',true)?:[]),
        ] : (($row->status==='rejected'||$row->status==='uncertain') ? [
            'code'=>'historical_reason_unavailable',
            'message'=>'Historical failure details were not saved. Check the marketplace Seller Center and store connection before choosing a recovery action.',
            'stage'=>$row->stage,'nextAction'=>'check_marketplace_result','diagnostics'=>(object)[],
        ] : null);
        return [
            'id'=>(int)$row->id,'storeId'=>(int)$row->store_id,'productId'=>(int)$row->product_id,
            'platform'=>$store?->platform,'status'=>$row->status,'stage'=>$row->stage,
            'externalItemId'=>$row->external_item_id,'creationCertainty'=>$this->certainty($row),
            'failure'=>$failure,'actions'=>$this->actions($row,$store),'previousTaskId'=>$row->previous_task_id ? (int)$row->previous_task_id : null,
            'revision'=>(int)($row->revision??1),'createdAt'=>(string)$row->created_at,'updatedAt'=>(string)$row->updated_at,
        ];
    }

    public function forProduct(int $storeId, int $productId, int $actorId): ?array
    {
        $id=DB::table('marketplace_publishing_tasks')->where('store_id',$storeId)->where('product_id',$productId)
            ->where('actor_id',$actorId)->latest('id')->value('id');
        return $id ? $this->read((int)$id,$actorId) : null;
    }

    private function certainty(object $row): string
    {
        if ($row->status==='succeeded' || $row->external_item_id) return 'confirmed';
        if ($row->status==='rejected') return 'not_created';
        if ($row->status==='uncertain') return 'unknown';
        return 'pending';
    }
    private function actions(object $row, ?object $store): array
    {
        if (!$store || $store->status!=='connected') return ['reauthorize_store'];
        return match ($row->status) {
            'queued'=>['resume_dispatch'], 'processing'=>['wait'],
            'uncertain'=>['check_marketplace_result','start_new_review'],
            'rejected'=>['edit_and_retry'], 'succeeded'=>['view_mapping'], default=>[],
        };
    }
    private function nextAction(string $code): string
    {
        return match ($code) {
            'credentials_unavailable'=>'reauthorize_store','review_outdated'=>'review_product',
            'image_validation_failed'=>'review_images','marketplace_validation_failed'=>'review_attributes',default=>'review_task',
        };
    }
}
