<?php

namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingRevision,MarketingProduction,MarketingProductionPlan};
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessMarketingProduction;

class MarketingNativeProduction
{
    public function submit(MarketingProduction $task,MarketingProductionPlan $plan): void
    {
        DB::transaction(function()use($task,$plan){
            $p=MarketingProject::lockForUpdate()->findOrFail($task->project_id);$t=MarketingProduction::lockForUpdate()->findOrFail($task->id);
            try{app(MarketingProductionPreparation::class)->validateForProduction($task->project_id,$task->owner_id,$plan->project_version,$plan->id);}catch(\Throwable){throw new MarketingProductionFailure('approval_no_longer_valid','blocked');}
            $r=MarketingRevision::where('project_id',$p->id)->findOrFail($plan->revision_id);$grant=app(MarketingNativePresenter::class)->grant($p,$r,$plan->manifest['native']['payload']);
            if(!config('marketing.native.live_enabled')||!config('marketing.native.api_key'))throw new MarketingProductionFailure('native_live_disabled','blocked');
            if(!$grant||$grant!==($plan->manifest['native']['authorization']??null)||$t->status!=='preparing'||($t->result['provider_submission_claimed']??false)||MarketingProduction::where('result->evolink_authorization',$grant['id'])->exists())throw new MarketingProductionFailure('native_authorization_unavailable','blocked');
            $t->update(['status'=>'external_submitting','new_reserved_microusd'=>$grant['cap_microusd'],'result'=>['provider_submission_claimed'=>true,'evolink_authorization'=>$grant['id'],'reserved_usd'=>$grant['cap_microusd']/1000000,'actual_usd'=>null,'provider'=>'evolink','publication_authorized'=>false]]);
        });
        $data=app(MarketingEvolinkClient::class)->submit($plan->manifest['native']['payload']);
        $task->refresh()->update(['status'=>'external_checking','result'=>array_replace($task->result,['provider_task_id'=>$data['id'],'provider_usage'=>$data['usage']])]);
        $this->check($task->fresh());
    }

    public function queueCheck(string $project,string $production,int $actor): void
    {
        DB::transaction(function()use($project,$production,$actor){
            MarketingProject::where('owner_id',$actor)->lockForUpdate()->findOrFail($project);
            $t=MarketingProduction::where('project_id',$project)->where('owner_id',$actor)->lockForUpdate()->findOrFail($production);
            abort_unless(($t->result['provider']??null)==='evolink'&&!empty($t->result['provider_task_id']),409,'There is no known provider task to check. Do not resubmit generation.');
            if(in_array($t->status,['external_check_queued','external_checking','completed'],true))return;
            abort_unless(in_array($t->status,['external_pending','unknown'],true),409,'Review the terminal provider outcome before taking further action.');
            $t->update(['status'=>'external_check_queued']);ProcessMarketingProduction::dispatch($t->id);
        });
    }

    public function processCheck(string $id): void
    {
        $task=DB::transaction(function()use($id){$t=MarketingProduction::lockForUpdate()->findOrFail($id);if($t->status!=='external_check_queued')return null;$t->update(['status'=>'external_checking']);return $t;});
        if($task)$this->check($task);
    }

    private function check(MarketingProduction $task): void
    {
        try{
            $data=app(MarketingEvolinkClient::class)->query($task->result['provider_task_id']);
            $result=array_replace($task->result,['provider_status'=>$data['status'],'provider_usage'=>array_replace($task->result['provider_usage']??[],$data['usage'])]);
            if(in_array($data['status'],['failed','cancelled'],true)){$task->update(['status'=>'failed','error_code'=>'native_provider_failed','result'=>$result,'finished_at'=>now()]);return;}
            if($data['status']!=='completed'){$task->update(['status'=>'external_pending','result'=>$result,'error_code'=>null]);return;}
            if(!is_array($data['results'])||count($data['results'])!==1||!is_string($data['results'][0]))throw new MarketingProductionFailure('native_result_invalid','unknown');
            $media=app(MarketingEvolinkMedia::class)->store($task,$data['results'][0]);
            $task->update(['status'=>'completed','output_path'=>'marketing-productions/'.$task->id.'/output.mp4','output_sha256'=>$media['sha256'],'result'=>array_replace($result,$media),'finished_at'=>now(),'error_code'=>null]);
        }catch(MarketingProductionFailure $error){$task->refresh()->update(['status'=>'unknown','error_code'=>$error->reason]);}
    }
}
