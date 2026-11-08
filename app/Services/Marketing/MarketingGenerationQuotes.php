<?php
namespace App\Services\Marketing;
use App\Models\{MarketingGenerationQuote as Quote,MarketingPreparationAuthorization,MarketingProductionPlan,MarketingStoryboardProviderCall as Call};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** One user-approved operation; a quote is not execution authority until atomically claimed. */
class MarketingGenerationQuotes
{
    private static bool $activating=false;
    public const ACTIONS=['changes','missing-images-prepare','material-plan-preview','timing-shorten-preview','start-production','retry-production'];
    public function enabled():bool {return app(MarketingGenerationPricing::class)->enabled();}
    /** Free saves never grant generation authority or extend the rate card. */
    public function freeManual(string $action,array $payload):bool {
        $c=$payload['command']??[];
        if($action!=='changes'||($c['mode']??'manual')!=='manual'||!empty($c['refine_manual'])||!empty($c['rewrite']))return false;
        if(in_array($c['type']??'', ['move','remove','restore'],true))return true;
        if(($c['type']??'')==='edit')return in_array($c['update_scope']['images']??null,[false,0,'0'],true)&&!empty($c['update_scope']['director']);
        return ($c['type']??'')==='insert'&&!isset($c['update_scope'])&&Str::isUuid($c['asset_id']??'');
    }
    private function digest(array $snapshot):string {return hash('sha256',json_encode([$snapshot['story_id'],$snapshot['story_version'],$snapshot['source_revision_id'],$snapshot['profile'],$snapshot['scenes']],JSON_THROW_ON_ERROR));}
    public function quote(string $project,int $owner,int $version,string $action,array $payload):Quote {
        abort_unless(in_array($action,self::ACTIONS,true),422,'这个操作不属于制作报价范围。');
        $ws=app(MarketingStoryboardWorkspace::class);$ws->project($project,$owner);$ws->assertEditable($project,$owner);$s=$ws->snapshot($project,$owner);
        abort_unless($s['source_fresh']&&$s['story_version']===$version,409,'故事已更新，请重新核价。');
        $pricing=app(MarketingGenerationPricing::class);$rates=$pricing->configuration();$included=false;$cap=0;$posts=0;
        if(in_array($action,['material-plan-preview','timing-shorten-preview'],true)){$included=true;$cap=50000;$posts=1;}
        elseif($action==='retry-production'){
            $spec=app(MarketingProductionRecovery::class)->retrySpec($project,$owner,$payload['production_id']??'',$payload['clip_id']??'');$cap=$spec['cap_microusd'];$posts=$spec['posts'];$payload=['production_id'=>$spec['production_id'],'clip_id'=>$spec['clip_id']];
        }
        elseif($action==='start-production'){
            $plan=MarketingProductionPlan::where('project_id',$project)->where('prepared_by',$owner)->findOrFail($payload['plan_id']??'');
            app(MarketingStoryboardProduction::class)->assertPlan($plan,$owner);
            app(MarketingProductionQuoteEvidence::class)->assertAffordable($plan,$owner);
            $cap=$plan->manifest['budget']['cap_microusd'];$posts=$plan->manifest['budget']['total_posts'];
            $payload=['plan_id'=>$plan->id,'consent'=>true];
        }elseif($action==='missing-images-prepare'){
            $count=0;foreach($s['scenes'] as $scene)foreach($scene['frames'] as $f)if($f['required']&&!in_array($f['readiness'],['qa_passed','employee_declared_local_validated'],true))$count++;
            $cap=$count*550000;$posts=$count*2;$payload['consent']=true;
        }else{
            $command=$payload['command']??[];$scope=app(MarketingStoryboardSaveScope::class)->validate($command,collect($s['scenes'])->firstWhere('scene_id',$command['scene_id']??'')??[]);
            $images=$scope['images']??true;$analysis=($command['mode']??'manual')==='ai'||!empty($command['refine_manual']);
            $count=!empty($command['rewrite'])?12:1;
            $frames=count($scope['frames']??['start_frame','end_frame','extra_image']);
            $cap=($analysis?50000:0)+($images?$count*$frames*550000:0);$posts=($analysis?1:0)+($images?$count*$frames*2:0);$included=!$images;
        }
        abort_unless($cap>=0&&$cap<=100000000&&$posts>=0&&($cap>0||$action==='start-production'),422,'当前没有需要收费准备的内容。');
        $cap=(int)(ceil($cap/10000)*10000); // Displayed upper limit covers the exact internal reservation.
        if($included)$this->includedAllowance($project,$cap);
        return Quote::create(['project_id'=>$project,'owner_id'=>$owner,'story_version'=>$version,'action'=>$action,'payload'=>$payload,'source_digest'=>$this->digest($s),'pricing_digest'=>$pricing->fingerprint(),'pricing'=>$rates,'cap_microusd'=>$cap,'max_posts'=>$posts,'included'=>$included,'status'=>'quoted','expires_at'=>now()->addMinutes(30)]);
    }
    private function includedAllowance(string $project,int $next):void {
        app(MarketingPlanningAllowance::class)->assertAvailable($project,$next);
    }
    public function claim(string $project,int $owner,string $id,bool $consent):array {
        abort_unless($consent,422,'请先确认本次内容与费用。');
        return DB::transaction(function()use($project,$owner,$id){
            app(MarketingStoryboardWorkspace::class)->project($project,$owner,true);
            $q=Quote::where('project_id',$project)->where('owner_id',$owner)->lockForUpdate()->findOrFail($id);
            if($q->result!==null)return ['quote'=>$q,'replay'=>$q->result];
            app(MarketingStoryboardWorkspace::class)->assertEditable($project,$owner);
            abort_unless($q->status==='quoted'&&$q->expires_at->isFuture(),409,'本次确认已提交或报价已过期，请核对原结果；不会重复生成。');
            $s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$owner);
            abort_unless(hash_equals($q->source_digest,$this->digest($s))&&hash_equals($q->pricing_digest,app(MarketingGenerationPricing::class)->fingerprint()),409,'内容、模型连接或价格已变化，请重新核价确认。');
            abort_if(Call::where('project_id',$project)->whereIn('status',['unknown','requesting','external_pending'])->exists(),409,'上一笔结果或费用待核对，暂不继续收费制作。');
            abort_if(Quote::where('project_id',$project)->where('id','!=',$q->id)->where('status','executing')->exists(),409,'本项目正在提交制作，请勿重复操作。');
            if($q->included)$this->includedAllowance($project,$q->cap_microusd);
            $q->update(['status'=>'executing','confirmed_at'=>now()]);return ['quote'=>$q,'replay'=>null];
        });
    }
    public function finish(Quote $q,array $result):void {$q->update(['status'=>'accepted','result'=>$result]);}
    public function fail(Quote $q):void {$q->update(['status'=>'paused']);}
    public function activate(Quote $q):bool {
        if(!$this->enabled()||!in_array($q->status,['executing','accepted'],true)||$q->expires_at->isPast())return false;
        abort_unless(hash_equals($q->pricing_digest,app(MarketingGenerationPricing::class)->fingerprint()),409,'已确认费率或模型连接已变化，请重新核价。');
        app(MarketingGenerationPricing::class)->install($q->pricing);
        $roles=in_array($q->action,['start-production','retry-production'],true)?['semantic','upload','video']:($q->included?['semantic']:['semantic','image','qa']);
        if(isset($q->pricing['voice'])&&in_array($q->action,['start-production','retry-production'],true))$roles[]='voice';
        config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['mode'=>'customer_quote','quote_id'=>$q->id,'reference'=>'quote:'.$q->id,'project_id'=>$q->project_id,'owner_id'=>$q->owner_id,'roles'=>$roles,'expires_at'=>$q->expires_at->toIso8601String(),'max_total_microusd'=>$q->cap_microusd,'max_calls'=>$q->max_posts]]);
        return true;
    }
    public function activateAuthorization(string $id):bool {
        if(!$this->enabled())return false;
        $a=MarketingPreparationAuthorization::find($id);
        if(!$a?->billing_quote_id)return false;
        config(['marketing.billing.execution_quote_id'=>$a->billing_quote_id]);
        return $this->activate(Quote::findOrFail($a->billing_quote_id));
    }
    public function activateProject(string $project):bool {
        if(!$this->enabled()||self::$activating)return false;
        self::$activating=true;
        try{$q=Quote::where('project_id',$project)->whereKey(config('marketing.billing.execution_quote_id',''))->first();return $q?$this->activate($q):false;}finally{self::$activating=false;}
    }
    public function receipt(string $project,int $owner):?array {
        if(!$this->enabled())return null;
        $q=Quote::where('project_id',$project)->where('owner_id',$owner)->where('included',false)->whereNotNull('confirmed_at')->latest('confirmed_at')->first();
        if(!$q)return null;
        $calls=Call::where('project_id',$project)->where('grant_reference','quote:'.$q->id)->get();
        $unknown=$calls->contains(fn($c)=>in_array($c->status,['unknown','requesting'],true));
        $pending=$calls->contains(fn($c)=>$c->status==='external_pending')||\App\Models\MarketingStoryboardTask::whereIn('authorization_id',MarketingPreparationAuthorization::where('billing_quote_id',$q->id)->select('id'))->whereIn('status',['queued','requesting'])->exists();
        if($q->action==='start-production')$pending=$pending||\App\Models\MarketingProduction::where('project_id',$project)->where('plan_id',$q->payload['plan_id'])->whereIn('status',['queued','preparing'])->exists();
        return ['upper_limit'=>number_format($q->cap_microusd/1000000,2,'.',''),'actual'=>$unknown?null:number_format($calls->where('status','completed')->sum('actual_microusd')/1000000,2,'.',''),'status'=>$unknown?'费用待核对，已暂停':($pending?'正在生成，费用记录更新中':($q->status==='paused'?'本次已暂停，已有结果保留':'本次费用已记录'))];
    }
    public function view(Quote $q):array {return ['id'=>$q->id,'included'=>$q->included,'amount'=>number_format($q->included?0:$q->cap_microusd/1000000,2,'.',''),'currency'=>'USD','summary'=>match($q->action){'start-production'=>(\App\Models\MarketingProductionPlan::find($q->payload['plan_id'])?->manifest['scope']['mode']??'')==='scene_trial'?'仅生成本次所选分镜，不合成为整片':'按本次确认方案生成视频并合成成片','changes'=>($q->payload['command']['update_scope']['director']??true)?'按本次要求更新导演稿及所选图片':'仅更新所选图片，导演稿保持原样','missing-images-prepare'=>'准备当前分镜所需的缺失图片',default=>'AI 整理本次策划建议'},'expires_at'=>$q->expires_at->toIso8601String()];}
}
