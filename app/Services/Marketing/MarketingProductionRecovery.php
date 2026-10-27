<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProduction,MarketingProductionPlan,MarketingPreparationAuthorization,MarketingStoryboardProviderCall as Call,MarketingVideoClip,MarketingGenerationQuote as Quote};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Safe continuation is separate from approval for a new, independently bounded paid attempt. */
class MarketingProductionRecovery
{
    private function run(string $project,int $owner,string $id):MarketingProduction {
        app(MarketingStoryboardWorkspace::class)->project($project,$owner);
        return MarketingProduction::where('project_id',$project)->where('owner_id',$owner)->findOrFail($id);
    }
    public function retrySpec(string $project,int $owner,string $id,string $clipId):array {
        $run=$this->run($project,$owner,$id);
        abort_unless($run->status==='blocked',409,'制作仍在处理，或费用尚待核对。');
        abort_if(Call::where('project_id',$project)->whereIn('status',['unknown','requesting','external_pending'])->exists(),409,'上次结果或费用尚未核清，不提供收费重试。');
        $plan=MarketingProductionPlan::findOrFail($run->plan_id);
        app(MarketingStoryboardProduction::class)->assertPlan($plan,$owner);
        abort_unless(in_array($clipId,$run->result['clip_ids']??[],true),409,'重试片段不属于本次制作。');
        $clip=MarketingVideoClip::where('project_id',$project)->where('owner_id',$owner)->findOrFail($clipId);
        abort_unless(!$clip->raw_path&&!$clip->output_path&&$clip->provider_task_id,409,'已有结果应直接恢复，不重新收费生成。');
        $call=Call::where('project_id',$project)->where('authorization_id',$clip->result['authorization_id']??'')->findOrFail($clip->result['video_call_id']??'');
        abort_unless($call->status==='completed'&&is_int($call->actual_microusd)&&in_array($call->result['provider_status']??'', ['failed','cancelled'],true)&&($call->result['id']??null)===$clip->provider_task_id,409,'上次任务状态与费用尚未核清，不提供收费重试。');
        $video=$clip->input['configuration']['video'];$upload=$clip->input['configuration']['upload'];
        $frames=count(app(MarketingVideoRoutes::class)->inputs($clip->input,$clip->input['configuration']));
        $cap=(int)ceil(($video['cap_microusd']+$frames*$upload['cap_microusd'])/10000)*10000;
        $account=app(MarketingProductionQuoteEvidence::class)->refresh($project,$owner);
        abort_unless(($account['account_status']??'')==='model_list_and_balance_read'&&in_array($video['model'],$account['model_ids']??[],true)&&is_numeric($account['remaining_credits']??null)&&$account['remaining_credits']*10/650*1000000>=$cap,409,'重试模型访问、余额或费用尚未核清，未发送请求。');
        $original=MarketingPreparationAuthorization::findOrFail($run->result['authorization_id']);
        $extra=Quote::where('project_id',$project)->where('action','retry-production')->where('payload->production_id',$id)->whereNotNull('confirmed_at')->sum('cap_microusd');
        return ['production_id'=>$id,'clip_id'=>$clipId,'scene'=>$clip->scene_number,'previous_actual_microusd'=>$call->actual_microusd,'cap_microusd'=>$cap,'posts'=>1+$frames,'total_cap_microusd'=>$original->cap_microusd+$extra+$cap,'reason'=>'第'.$clip->scene_number.'段供应商任务未完成，上一笔费用已核清。仅重新生成本段，其余结果保留。'];
    }
    public function progress(string $project,int $owner,string $id):array {
        $run=$this->run($project,$owner,$id);$svc=app(MarketingStoryboardProduction::class);
        if(!in_array($run->status,['blocked','unknown'],true))return ['mode'=>'progress'];
        // These are queries of durable task IDs, never a second generation submission.
        foreach($run->result['clip_ids']??[] as $clipId){$clip=MarketingVideoClip::findOrFail($clipId);if($clip->provider_task_id&&isset($clip->result['video_call_id'])){
            $call=Call::find($clip->result['video_call_id']);
            if($call&&$call->status==='external_pending')try{$svc->reconcileVideo($clip,MarketingProductionPlan::findOrFail($run->plan_id));}catch(\Throwable){/* keep receipts for platform review */}
        }}
        if(Call::where('project_id',$project)->whereIn('status',['unknown','requesting'])->exists())return ['mode'=>'platform','message'=>'制作暂停，系统正在核对上次结果和费用，已有结果保留。'];
        foreach($run->result['clip_ids']??[] as $clipId){$clip=MarketingVideoClip::findOrFail($clipId);$call=isset($clip->result['video_call_id'])?Call::find($clip->result['video_call_id']):null;
            if($call&&$call->status==='completed'&&in_array($call->result['provider_status']??'', ['failed','cancelled'],true)){
                if($run->status==='unknown'){$run->update(['status'=>'blocked']);}
                try{$spec=$this->retrySpec($project,$owner,$id,$clipId);}catch(\Throwable){return ['mode'=>'platform','message'=>'制作暂停，系统正在核对当前方案与费用，已有结果保留。'];}
                $q=Quote::where('project_id',$project)->where('action','retry-production')->where('payload->production_id',$id)->where('payload->clip_id',$clipId)->latest()->first();
                if($q&&$q->status==='declined')return ['mode'=>'declined','message'=>'你未批准新增费用，制作已暂停，已有结果保留。'];
                if(!$q)$q=app(MarketingGenerationQuotes::class)->quote($project,$owner,MarketingPreparationAuthorization::findOrFail($run->result['authorization_id'])->story_version,'retry-production',['production_id'=>$id,'clip_id'=>$clipId]);
                if($q->status!=='quoted'||$q->expires_at->isPast())return ['mode'=>'platform','message'=>'重试报价需由系统重新核对，未发起收费请求。'];
                return ['mode'=>'approval','quote'=>['id'=>$q->id,'reason'=>$spec['reason'],'previous_actual'=>number_format($spec['previous_actual_microusd']/1e6,2,'.',''),'additional_limit'=>number_format($q->cap_microusd/1e6,2,'.',''),'total_limit'=>number_format($spec['total_cap_microusd']/1e6,2,'.','')]];
            }
        }
        // At most two safe recoveries per saved run. No unchecked recursive retry loop.
        if(($run->result['safe_recoveries']??0)>=2)return ['mode'=>'platform','message'=>'制作暂停，平台正在排查，已有结果保留。'];
        try{$svc->assertPlan(MarketingProductionPlan::findOrFail($run->plan_id),$owner);}catch(\Throwable){return ['mode'=>'platform','message'=>'制作暂停，平台正在核对执行环境与当前方案，已有结果保留。'];}
        DB::transaction(function()use($run){$locked=MarketingProduction::lockForUpdate()->findOrFail($run->id);if(!in_array($locked->status,['blocked','unknown'],true))return;$r=$locked->result;$r['safe_recoveries']=($r['safe_recoveries']??0)+1;$locked->update(['result'=>$r]);});
        try{$svc->resume($project,$owner,$id);return ['mode'=>'recovering','message'=>'正在安全恢复制作，已有结果将直接复用。'];}
        catch(\Throwable){return ['mode'=>'platform','message'=>'制作暂停，平台正在核对已有任务，已有结果保留。'];}
    }
    public function decline(string $project,int $owner,string $quoteId):array {
        return DB::transaction(function()use($project,$owner,$quoteId){app(MarketingStoryboardWorkspace::class)->project($project,$owner,true);$q=Quote::where('project_id',$project)->where('owner_id',$owner)->where('action','retry-production')->lockForUpdate()->findOrFail($quoteId);abort_unless(in_array($q->status,['quoted','declined'],true),409,'本次决定已处理，请核对已有结果。');$q->update(['status'=>'declined']);return ['mode'=>'declined'];});
    }
    public function approve(Quote $q):array {
        abort_unless($q->action==='retry-production'&&$q->status==='executing'&&$q->confirmed_at&&$q->expires_at->isFuture()&&config('marketing.billing.execution_quote_id')===$q->id,409,'新增收费请求必须先取得本次明确批准。');
        return DB::transaction(function()use($q){$spec=$this->retrySpec($q->project_id,$q->owner_id,$q->payload['production_id'],$q->payload['clip_id']);abort_unless($spec['cap_microusd']===$q->cap_microusd,409,'重试费用已变化，请重新确认。');
            $run=MarketingProduction::lockForUpdate()->findOrFail($spec['production_id']);$old=MarketingVideoClip::findOrFail($spec['clip_id']);
            $authorization=app(MarketingPreparationBudget::class)->authorize($q->project_id,$q->owner_id,(string)\Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL,'production-retry:'.$q->id),$q->story_version,[$old->input['revision_id']],$q->cap_microusd);
            $clip=MarketingVideoClip::create(['project_id'=>$old->project_id,'owner_id'=>$old->owner_id,'revision_id'=>$old->revision_id,'request_id'=>$q->id,'scene_number'=>$old->scene_number,'fingerprint'=>$old->fingerprint,'status'=>'upload_pending','input'=>$old->input,'quote'=>['retry_quote'=>$q->id],'connection_fingerprint'=>$old->connection_fingerprint,'authorized_at'=>now(),'expires_at'=>$q->expires_at,'result'=>['authorization_id'=>$authorization['id'],'retry_of'=>$old->id]]);
            $r=$run->result;$r['clip_ids']=array_map(fn($id)=>$id===$old->id?$clip->id:$id,$r['clip_ids']);$r['retry_quotes'][]=$q->id;unset($r['message'],$r['failure']);$run->update(['status'=>'preparing','error_code'=>null,'result'=>$r]);\App\Jobs\ProcessMarketingStoryboardProduction::dispatch($run->id);return ['id'=>$run->id];
        });
    }
}
