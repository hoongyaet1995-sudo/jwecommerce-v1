<?php

namespace App\Services\Marketing;

use App\Models\{AiConnection, MarketingScene, MarketingSceneRevision, MarketingStoryboardSemanticRun, MarketingStoryboardProviderCall, MarketingStoryboardWorkspace as Workspace};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Step 3 closure operations: previews never dispatch images; all writes are version-bound. */
class MarketingStoryboardClosure
{
    private function context(string $project, int $owner, int $version): array
    {
        $s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$owner);
        abort_unless(($s['source_fresh']??false)&&$s['story_version']===$version,409,'故事已变化，请刷新后重新预览。');
        abort_if(($s['preparation_progress']['active']??0)||($s['preparation_progress']['unknown']??0),409,'任务仍在处理或费用待核对，请先完成核对。');
        return $s;
    }

    private function key(array $s, array $extra=[]): string
    {
        return hash('sha256',json_encode([$s['story_id'],$s['story_version'],$s['scenes'],$s['profile'],$extra],JSON_THROW_ON_ERROR));
    }

    private function revise(array $scene, array $profile): void
    {
        $row=MarketingScene::findOrFail($scene['scene_id']);
        $scene=app(MarketingStoryboardPackage::class)->content($scene);
        // Snapshot-only metadata is never persisted as director data.
        foreach($scene['frames'] as &$f) unset($f['existing_asset_review_required'],$f['adoption_technical_issue'],$f['qa_override_required'],$f['adoption_issue'],$f['continuity_ack_required'],$f['framing_sync_preview']);
        unset($f,$scene['ordinal'],$scene['revision_id'],$scene['image_signature']);
        $r=MarketingSceneRevision::create(['scene_id'=>$row->id,'parent_id'=>$row->current_revision_id,'content'=>$scene,'signature'=>app(MarketingStoryboardContract::class)->signature($scene,$profile)]);
        $row->update(['current_revision_id'=>$r->id]);
    }

    public function timingPreview(string $project,int $owner,int $version): array
    {
        $s=$this->context($project,$owner,$version);$rows=[];
        foreach($s['scenes'] as $scene){
            $needed=app(MarketingStoryboardSaveScope::class)->speechSeconds($scene['voiceover']);
            if($needed<=$scene['seconds'])continue;
            $rows[]=['scene_id'=>$scene['scene_id'],'ordinal'=>$scene['ordinal'],'revision_id'=>$scene['revision_id'],'seconds'=>$scene['seconds'],'suggested_seconds'=>$needed,'can_extend'=>$needed<=15,'voiceover'=>$scene['voiceover'],'subtitle'=>$scene['subtitle']];
        }
        $analysis=app(MarketingStoryboardDecisions::class)->planningInfo($project);
        return ['key'=>$this->key($s,['timing-v1',$analysis,config('marketing.storyboard.roles.semantic'),AiConnection::where('provider','openai')->value('version')]),'scenes'=>$rows,'before_seconds'=>$s['timing']['content_seconds'],'after_seconds'=>$s['timing']['content_seconds']+array_sum(array_map(fn($r)=>$r['suggested_seconds']-$r['seconds'],$rows)),'analysis'=>$analysis];
    }

    public function extendTiming(string $project,int $owner,string $request,int $version,string $key): array
    {
        return app(MarketingStoryboardWorkspace::class)->transact($project,$owner,$request,['extend-timing',$version,$key],function()use($project,$owner,$version,$key){
            $s=$this->context($project,$owner,$version);$plan=$this->timingPreview($project,$owner,$version);
            abort_unless(hash_equals($plan['key'],$key)&&$plan['scenes'],409,'时长预览已过期。');
            abort_if(collect($plan['scenes'])->contains(fn($r)=>!$r['can_extend']),422,'完整口播超过单段15秒，请精简或拆分。');
            $w=Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();$by=collect($plan['scenes'])->keyBy('scene_id');
            foreach($s['scenes'] as $scene){if(!isset($by[$scene['scene_id']]))continue;
                $n=$by[$scene['scene_id']]['suggested_seconds'];$ratio=$n/$scene['seconds'];
                foreach($scene['director']['beats'] as &$beat){$beat['start']=round($beat['start']*$ratio,3);$beat['end']=round($beat['end']*$ratio,3);}unset($beat);
                $scene['seconds']=$n;$scene['timing_decision']=['actor_id'=>$owner,'at'=>now()->toIso8601String(),'origin'=>'employee_accepted_speech_estimate','preview_key'=>$key];
                $this->revise($scene,$w->profile);
            }
            $w->update(['version'=>$w->version+1,'last_confirmation'=>null]);
        });
    }

    public function shortenPreview(string $project,int $owner,string $request,int $version,string $quoteKey): array
    {
        $s=$this->context($project,$owner,$version);$plan=$this->timingPreview($project,$owner,$version);
        abort_unless(hash_equals($plan['key'],$quoteKey),409,'文字分析模型或费用已变化，请重新查看时长安排。');
        abort_unless($plan['scenes'],422,'目前没有需要精简的口播。');
        $provider=app(MarketingStoryboardProvider::class);
        abort_unless($provider->allowed('semantic',$project),423,'文字分析尚未授权。');
        [$connection,$cfg]=app(MarketingStoryboardOpenAi::class)->connection('semantic');
        $fingerprint=$this->key($s,['shorten-v1',$connection->version,$cfg]);
        $key=hash('sha256',$project.':'.$owner.':'.$fingerprint);
        $run=DB::transaction(function()use($project,$owner,$key){
            app(MarketingStoryboardWorkspace::class)->project($project,$owner,true);
            $old=MarketingStoryboardSemanticRun::find($key);
            if($old){abort_unless($old->status==='completed',409,'上次分析未完成或结果未知，不会自动重试。');return $old;}
            return MarketingStoryboardSemanticRun::create(['id'=>$key,'status'=>'requesting']);
        });
        if($run->status==='completed')return $run->result;
        try{
            $auth=app(MarketingPreparationBudget::class)->authorize($project,$owner,$request,$version,array_column($s['scenes'],'revision_id'),$cfg['cap_microusd']);
            $answer=app(MarketingStoryboardOpenAi::class)->shorten($project,$auth['id'],$plan['scenes']);
            $rows=$answer['answer']['scenes']??[];$expected=array_column($plan['scenes'],'scene_id');
            abort_unless(is_array($rows)&&count($rows)===count($expected)&&count(array_unique(array_column($rows,'scene_id')))===count($expected)&&!array_diff($expected,array_column($rows,'scene_id')),422,'AI未完整返回口播建议。');
            $mapped=collect($rows)->keyBy('scene_id');
            foreach($plan['scenes'] as &$row){$a=$mapped[$row['scene_id']];
                abort_unless(is_string($a['voiceover']??null)&&trim($a['voiceover'])!==''&&mb_strlen($a['voiceover'])<=4000&&is_string($a['subtitle']??null)&&mb_strlen($a['subtitle'])<=1000&&is_string($a['reason']??null)&&mb_strlen($a['reason'])<=2000&&app(MarketingStoryboardSaveScope::class)->speechSeconds($a['voiceover'])<=$row['seconds'],422,'精简建议仍超出目标时长，未修改原稿。');
                $row['after_voiceover']=$a['voiceover'];$row['after_subtitle']=$a['subtitle'];$row['reason']=$a['reason'];
            }unset($row);
            $result=['key'=>$key,'project_id'=>$project,'owner_id'=>$owner,'fingerprint'=>$fingerprint,'scenes'=>$plan['scenes']];
            $run->update(['status'=>'completed','result'=>$result]);return $result;
        }catch(\Throwable $e){$run->update(['status'=>isset($auth)&&MarketingStoryboardProviderCall::where('authorization_id',$auth['id'])->exists()?'unknown':'blocked']);throw $e;}
    }

    public function applyShortening(string $project,int $owner,string $request,int $version,string $key): array
    {
        return app(MarketingStoryboardWorkspace::class)->transact($project,$owner,$request,['apply-shortening',$version,$key],function()use($project,$owner,$version,$key){
            $s=$this->context($project,$owner,$version);[$conn,$cfg]=app(MarketingStoryboardOpenAi::class)->connection('semantic');
            $run=MarketingStoryboardSemanticRun::findOrFail($key);$r=$run->result;
            abort_unless($run->status==='completed'&&($r['project_id']??'')===$project&&($r['owner_id']??0)===$owner&&hash_equals($r['fingerprint']??'',$this->key($s,['shorten-v1',$conn->version,$cfg])),409,'口播建议已过期。');
            $w=Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();$by=collect($r['scenes'])->keyBy('scene_id');
            foreach($s['scenes'] as $scene){if(!isset($by[$scene['scene_id']]))continue;$a=$by[$scene['scene_id']];
                $scene['voiceover']=$a['after_voiceover'];$scene['subtitle']=$a['after_subtitle'];$scene['timing_decision']=['actor_id'=>$owner,'at'=>now()->toIso8601String(),'origin'=>'employee_accepted_ai_shortening','preview_key'=>$key];$this->revise($scene,$w->profile);
            }
            $w->update(['version'=>$w->version+1,'last_confirmation'=>null]);
        });
    }

    public function missingPreview(string $project,int $owner,int $version): array
    {
        $s=$this->context($project,$owner,$version);$frames=[];$blockers=[];$pricing=[];
        foreach($s['scenes'] as $scene)foreach($scene['frames'] as $role=>$f){
            if(!$f['required']||!empty($f['candidate_asset'])||(!empty($f['asset'])&&in_array($f['readiness'],['qa_passed','employee_declared_local_validated'],true)))continue;
            $frames[]=['scene_id'=>$scene['scene_id'],'revision_id'=>$scene['revision_id'],'ordinal'=>$scene['ordinal'],'role'=>$role,'instruction'=>$f['instruction'],'replaces_stale_image'=>!empty($f['asset'])];
            if(!empty($scene['director_needs_detail']))$blockers[]='第'.$scene['ordinal'].'段详细导演设计待补齐。';
            if($role!=='start_frame'&&!in_array($scene['frames']['start_frame']['readiness'],['qa_passed','employee_declared_local_validated'],true))$blockers[]='请先确认第'.$scene['ordinal'].'段首帧候选，再准备后续图片。';
            if(($f['readiness']??'')==='invalid')$blockers[]='第'.$scene['ordinal'].'段参考已失效，请先更新人物或商品参考。';
        }
        try{
            foreach(['image','qa'] as $role){
                abort_unless(app(MarketingGenerationQuotes::class)->enabled()||app(MarketingStoryboardProvider::class)->allowed($role,$project),423);
                $cfg=app(MarketingStoryboardProvider::class)->role($role);
                $connection=$role==='image'?app(MarketingMaterialImageClient::class)->configuration():app(MarketingMaterialVisionClient::class)->configuration();
                abort_unless($connection['model']===$cfg['model'],423);
                $pricing[$role]=['model'=>$cfg['model'],'cap_microusd'=>$cfg['cap_microusd'],'config'=>$cfg,'connection'=>$connection];
            }
        }catch(\Throwable){$blockers[]='图片或检查模型、费率、调用授权尚未就绪，未开始生成。';}
        $cap=count($frames)*array_sum(array_column($pricing,'cap_microusd'));
        if($cap<=0||$cap>100000000)$blockers[]='本次费用上限无法核定。';
        return ['key'=>$this->key($s,['missing-v1',$pricing,AiConnection::where('provider','openai')->value('version')]),'frames'=>$frames,'blockers'=>array_values(array_unique($blockers)),'pricing'=>$pricing,'cap_microusd'=>$cap,'max_posts'=>count($frames)*2,'automatic_retries'=>0];
    }

    public function prepareMissing(string $project,int $owner,string $request,int $version,string $key,bool $consent): array
    {
        abort_unless($consent,422,'请先确认本次图片范围与费用上限。');
        $s=app(MarketingStoryboardWorkspace::class)->transact($project,$owner,$request,['missing-images',$version,$key],function()use($project,$owner,$request,$version,$key){
            $q=$this->missingPreview($project,$owner,$version);
            abort_unless(hash_equals($q['key'],$key)&&$q['frames']&&!$q['blockers'],409,'准备范围、模型、费用或素材已变化，请重新查看。');
            $s=$this->context($project,$owner,$version);$w=Workspace::where('project_id',$project)->lockForUpdate()->firstOrFail();
            foreach($s['scenes'] as $scene){$roles=array_column(array_filter($q['frames'],fn($f)=>$f['scene_id']===$scene['scene_id']),'role');if(!$roles)continue;
                $scene['generation_roles']=$roles;$scene['missing_image_preparation']=['request_id'=>$request,'preview_key'=>$key,'cap_microusd'=>$q['cap_microusd'],'max_posts'=>$q['max_posts'],'pricing'=>$q['pricing']];$this->revise($scene,$w->profile);
            }
            $w->update(['version'=>$w->version+1,'last_confirmation'=>null]);
        });
        // Stable IDs plus firstOrCreate keep a replay from dispatching twice after a transport interruption.
        $scope=[];$cap=null;$pricing=[];
        foreach($s['scenes'] as $scene)if(($scene['missing_image_preparation']['request_id']??'')===$request){$scope[]=$scene['revision_id'];$cap=$scene['missing_image_preparation']['cap_microusd'];$pricing=$scene['missing_image_preparation']['pricing'];}
        abort_unless($scope&&$cap,409,'本次准备记录不可用。');
        $authorization=app(MarketingPreparationBudget::class)->authorize($project,$owner,(string)\Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL,'missing:'.$project.':'.$request),$s['story_version'],$scope,$cap);
        $tasks=app(MarketingStoryboardPreparation::class)->prepare($project,$authorization['id'],['max_repairs'=>0,'pricing'=>$pricing]);
        return ['snapshot'=>app(MarketingStoryboardWorkspace::class)->snapshot($project,$owner),'preparation'=>$tasks];
    }
}
