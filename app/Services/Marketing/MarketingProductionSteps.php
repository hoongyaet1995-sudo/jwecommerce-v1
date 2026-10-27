<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProduction,MarketingProductionPlan,MarketingProductionStep};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Local-only serial executor. External-generation nodes are deliberately not executable. */
class MarketingProductionSteps
{
    public function process(string $id):void
    {
        $task=MarketingProduction::findOrFail($id);$plan=MarketingProductionPlan::findOrFail($task->plan_id);$m=$plan->manifest;
        $hybrid=($m['execution_profile']??null)===MarketingHybridProduction::PROFILE&&app(MarketingHybridProduction::class)->enabled($task->project_id);
        if(!app(MarketingToolPlanning::class)->executionEnabled($task->project_id)||(!$hybrid&&(($m['execution_profile']??null)!=='local-only-v1'||$m['budget']['cap_microusd']!==0||$m['budget']['ai_video_requests']!==0))||!($m['audio']['reuse_only']??false))throw new MarketingProductionFailure('dynamic_execution_not_authorized','blocked');
        $nodes=$m['strategy']['nodes'];
        if(count(array_filter($nodes,fn($n)=>!in_array($n['kind'],$hybrid?['reuse_media','generate_video','compose']:['reuse_media','compose'],true)))||count(array_unique(array_column($nodes,'key')))!==count($nodes)||count(array_filter($nodes,fn($n)=>$n['kind']==='compose'))!==1)throw new MarketingProductionFailure('dynamic_generation_disabled','blocked');
        if($hybrid&&MarketingProductionStep::where('production_id',$id)->where('kind','generate_video')->where('status','completed')->count()!==1)throw new MarketingProductionFailure('hybrid_dependency_incomplete','blocked');
        DB::transaction(function()use($task,$nodes,$plan){
            $current=MarketingProduction::lockForUpdate()->findOrFail($task->id);
            if($current->status!=='preparing')throw new MarketingProductionFailure('dynamic_step_outcome_unknown','unknown');
            app(MarketingProductionPreparation::class)->validateForProduction($task->project_id,$task->owner_id,$plan->project_version,$plan->id);
            foreach($nodes as $n){$signature=hash('sha256',json_encode([$plan->fingerprint,$n],JSON_THROW_ON_ERROR));$row=MarketingProductionStep::firstOrCreate(['production_id'=>$task->id,'step_key'=>$n['key']],['kind'=>$n['kind'],'input_signature'=>$signature,'depends_on'=>$n['depends_on'],'status'=>'queued']);if(!hash_equals($row->input_signature,$signature))throw new MarketingProductionFailure('dynamic_step_changed','blocked');}
        });
        foreach($nodes as $n){
            $step=DB::transaction(function()use($task,$n){
                MarketingProduction::lockForUpdate()->findOrFail($task->id);$s=MarketingProductionStep::where('production_id',$task->id)->where('step_key',$n['key'])->lockForUpdate()->firstOrFail();
                if($s->status==='completed')return null;
                if(!in_array($s->status,['queued','failed'],true))throw new MarketingProductionFailure('dynamic_step_outcome_unknown','unknown');
                $ready=MarketingProductionStep::where('production_id',$task->id)->whereIn('step_key',$s->depends_on)->where('status','completed')->count();if($ready!==count($s->depends_on))throw new MarketingProductionFailure('dynamic_dependency_incomplete','blocked');
                $s->update(['status'=>'running','claim_token'=>(string)Str::uuid(),'attempts'=>$s->attempts+1,'error_code'=>null]);return $s;
            });
            if(!$step)continue;
            try{
                if($n['kind']==='reuse_media'){
                    foreach($n['media'] as $file)app(MarketingProductionCompiler::class)->sourcePath($file);
                    $step->update(['status'=>'completed','result'=>['reused'=>true,'media_hashes'=>array_column($n['media'],'sha256')]]);continue;
                }
                // Recheck current role, confirmed plan, authorizations, exact media and voice before composing.
                DB::transaction(fn()=>app(MarketingProductionPreparation::class)->validateForProduction($task->project_id,$task->owner_id,$plan->project_version,$plan->id));
                $renderer=app(MarketingVideoRenderer::class);$renderer->assertReady();$workspace=app(MarketingProductionWorkspace::class);$directory=$workspace->stage($task,$plan);$voice=null;
                if($m['audio']['requests']){$voice=app(MarketingVoiceLibrary::class)->obtain($task,$plan);$workspace->stageVoice($directory,$voice);}
                $task->update(['status'=>'rendering']);$result=$renderer->render($task,$plan,$directory);
                DB::transaction(function()use($step,$task,$result,$voice,$hybrid){
                    $step->update(['status'=>'completed','output_sha256'=>$result['sha256'],'result'=>$result]);
                    $task->refresh()->update(['status'=>'completed','output_path'=>'marketing-productions/'.$task->id.'/output.mp4','output_sha256'=>$result['sha256'],'finished_at'=>now(),'error_code'=>null,'result'=>array_replace($task->result??[],$result,['composition'=>$hybrid?'dynamic_single_generated_shot':'dynamic_saved_assets','voice_source'=>$voice?->source,'voice_reused'=>$voice!==null,'new_provider_requests'=>$hybrid?1:0,'actual_new_supplier_usd'=>$hybrid?null:0,'publication_authorized'=>false])]);
                });
            }catch(\Throwable $e){$step->update(['status'=>$e instanceof MarketingProductionFailure&&$e->state!=='unknown'?'failed':'unknown','error_code'=>$e instanceof MarketingProductionFailure?$e->reason:'dynamic_step_interrupted']);throw $e;}
        }
    }
}
