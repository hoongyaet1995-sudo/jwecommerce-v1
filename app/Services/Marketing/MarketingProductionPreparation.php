<?php

namespace App\Services\Marketing;

use App\Models\MarketingProductionPlan;
use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketingProductionPreparation
{
    public function __construct(private MarketingProductionCompiler $compiler, private MarketingWorkflow $workflow) {}

    public function prepare(string $id, int $actor, int $version, string $revision, string $request, ?string $supplierModel=null): MarketingProductionPlan
    {
        return DB::transaction(function () use ($id, $actor, $version, $revision, $request, $supplierModel) {
            $p = MarketingProject::where('owner_id', $actor)->lockForUpdate()->findOrFail($id);
            $existing = MarketingProductionPlan::where('project_id', $id)->where('request_id', $request)->first();
            if ($existing) {
                abort_unless($existing->revision_id === $revision && $existing->project_version === $version && ($existing->manifest['supplier_quote']['model']??null)===$supplierModel, 409, 'This preparation request has changed. Reload the project.');

                $r=$this->current($p,$revision);[,,$fresh]=$this->inputs($p,$r,$supplierModel);abort_unless(hash_equals($existing->fingerprint,$fresh)&&$existing->expires_at->isFuture(),409,'制作预检已失效，请重新检查当前资料。');
                return $existing;
            }
            $p = $this->workflow->locked($id, $actor, $version);
            $r = $this->current($p, $revision);
            [$snapshot, $manifest, $fingerprint] = $this->inputs($p, $r, $supplierModel);
            $existing = MarketingProductionPlan::where('project_id', $id)->where('fingerprint', $fingerprint)->where('expires_at', '>', now())->latest()->first();
            if ($existing) {
                return $existing;
            }

            return MarketingProductionPlan::create(['project_id' => $id, 'revision_id' => $r->id, 'prepared_by' => $actor, 'request_id' => $request, 'project_version' => $p->version, 'snapshot' => $snapshot, 'manifest' => $manifest, 'fingerprint' => $fingerprint, 'status' => $manifest['blockers'] ? 'blocked' : 'awaiting_budget', 'expires_at' => $supplierModel!==null?now()->addMinutes(30):now()->addDay()]);
        });
    }

    /** New storyboard contract only: freezes offline inputs, never authorizes video or reuses legacy pricing. */
    public function prepareStoryboard(string $project,int $owner,string $request,int $storyVersion,?string $sceneId=null):MarketingProductionPlan
    {
        abort_unless(\Illuminate\Support\Str::isUuid($request),422,'请求编号无效。');
        return DB::transaction(function()use($project,$owner,$request,$storyVersion,$sceneId){
            $service=app(MarketingStoryboardWorkspace::class);$p=$service->project($project,$owner,true);$s=$service->snapshot($project,$owner);
            abort_unless($s['story_version']===$storyVersion&&($s['last_confirmation']['snapshot']['story_version']??null)===$storyVersion,409,'请先确认当前故事。');
            $handoff=app(MarketingStoryboardHandoff::class)->build($project,$owner,$s['story_id']);
            abort_unless($handoff===$s['last_confirmation']['snapshot'],409,'当前资料与交接快照不一致，请重新确认故事。');
            $execution=app(MarketingProductionScope::class)->select($handoff,$sceneId);
            $clips=app(MarketingClipSource::class)->storyboard($execution);
            $manifest=app(MarketingStoryboardProduction::class)->manifest($execution,$clips,$project);$manifest['story_id']=$s['story_id'];
            if($sceneId!==null){$manifest['handoff']=$handoff;$manifest['execution_handoff']=$execution;$manifest['scope']=['mode'=>'scene_trial','scene_ids'=>[$sceneId]];}
            $fingerprint=hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR));$old=MarketingProductionPlan::where('project_id',$project)->where('request_id',$request)->first();if($old){abort_unless(hash_equals($old->fingerprint,$fingerprint),409,'预检请求内容已变化。');return $old;}
            return MarketingProductionPlan::create(['project_id'=>$project,'revision_id'=>$s['source_revision_id'],'prepared_by'=>$owner,'request_id'=>$request,'project_version'=>$p->version,'snapshot'=>$handoff,'manifest'=>$manifest,'fingerprint'=>$fingerprint,'status'=>$manifest['blockers']?'blocked':'awaiting_budget','expires_at'=>now()->addDay()]);
        });
    }

    public function storyboardPreview(string $project,int $owner):array
    {
        $s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$owner);
        $handoff=null;
        if(($s['source_fresh']??false)&&($s['readiness']??false)&&($s['last_confirmation']['snapshot']['story_version']??null)===$s['story_version']){
            $built=app(MarketingStoryboardHandoff::class)->build($project,$owner,$s['story_id']);
            if($built===$s['last_confirmation']['snapshot'])$handoff=$built;
        }
        $history=MarketingProductionPlan::where('project_id',$project)->where('prepared_by',$owner)->latest('created_at')->latest('id')->limit(30)->get();
        $plan=$handoff?MarketingProductionPlan::where('project_id',$project)->where('prepared_by',$owner)->where('manifest->recipe','storyboard-production-v1')->where('manifest->story_id',$s['story_id'])->where('manifest->story_version',$s['story_version'])->latest('created_at')->latest('id')->get()->first(fn($p)=>($p->manifest['handoff']??null)===$handoff):null;
        $batches=\App\Models\MarketingProduction::where('project_id',$project)->where('owner_id',$owner)->whereIn('plan_id',$history->pluck('id'))->latest('created_at')->get();
        // An unpriced navigation snapshot cannot erase a settled result for the same handoff.
        if($handoff&&(!$plan||($plan->manifest['execution_profile']??'')==='offline_preflight_only')){
            $completed=$history->first(fn($candidate)=>($candidate->manifest['handoff']??null)===$handoff&&$batches->contains(fn($batch)=>$batch->plan_id===$candidate->id&&$batch->status==='completed'));
            if($completed)$plan=$completed;
        }
        $production=$plan?$batches->firstWhere('plan_id',$plan->id):null;
        $settlement=null;
        if($production?->status==='completed'){
            $authorizations=\App\Models\MarketingPreparationAuthorization::where('project_id',$project)->where(function($q)use($production){$q->whereKey($production->result['authorization_id']??'')->orWhereIn('billing_quote_id',$production->result['retry_quotes']??[]);})->get();
            $calls=\App\Models\MarketingStoryboardProviderCall::where('project_id',$project)->whereIn('authorization_id',$authorizations->pluck('id'))->get();
            $known=$calls->isNotEmpty()&&$calls->every(fn($c)=>$c->status==='completed'&&$c->actual_microusd!==null);
            $settlement=['posts'=>$calls->count(),'known'=>$known,'actual_microusd'=>$known?$calls->sum('actual_microusd'):null,'cap_microusd'=>$authorizations->sum('cap_microusd')];
        }
        $quoteCurrent=false;$quoteIssue=null;
        if($plan&&$production?->status!=='completed'){
            if($plan->expires_at->isPast())$quoteIssue='报价已过期，请重新核价。';
            elseif($plan->manifest['blockers']??[])$quoteIssue=implode('；',$plan->manifest['blockers']);
            else{try{app(MarketingStoryboardProduction::class)->assertPlan($plan,$owner);$quoteCurrent=true;}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){$quoteIssue=$e->getMessage()?:'模型、素材或费率已变化，请重新核价。';}}
        }
        return ['snapshot'=>$s,'plan'=>$plan,'production'=>$production,'settlement'=>$settlement,'fresh'=>$s['source_fresh']??false,'handoff_current'=>$handoff!==null,'quote_current'=>$quoteCurrent,'quote_issue'=>$quoteIssue,'history'=>$history,'batches'=>$batches];
    }

    public function confirm(string $id, int $actor, int $version, string $planId): void
    {
        DB::transaction(function () use ($id, $actor, $version, $planId) {
            $p = $this->workflow->locked($id, $actor, $version);
            $plan = MarketingProductionPlan::where('project_id', $id)->lockForUpdate()->findOrFail($planId);
            $r = $this->current($p, $plan->revision_id);
            abort_if($plan->expires_at->lte(now()), 409, 'This production budget expired. Prepare a fresh checklist.');
            [, , $fingerprint] = $this->inputs($p, $r, $plan->manifest['supplier_quote']['model']??null);
            abort_unless(hash_equals($plan->fingerprint, $fingerprint), 409, 'The project, materials or budget profile changed. Prepare a fresh checklist before confirming.');
            abort_if($plan->manifest['blockers'] || ! in_array($plan->status, ['awaiting_budget', 'budget_confirmed'], true), 422, 'Resolve the production checklist blockers first.');
            if ($plan->status === 'budget_confirmed') {
                return;
            }
            $plan->update(['status' => 'budget_confirmed', 'confirmed_by' => $actor, 'confirmed_at' => now()]);
            // P2-A deliberately ends here. No queue, provider call or publication side effect.
        });
    }

    /** Caller holds the project transaction; used again immediately before worker execution. */
    public function validateForProduction(string $id, int $actor, int $version, string $planId): MarketingProductionPlan
    {
        abort_unless(User::whereKey($actor)->where('is_admin', true)->where('is_super_admin', true)->exists(), 403, 'Current administrator access is required for production.');
        $p = $this->workflow->locked($id, $actor, $version);
        $plan = MarketingProductionPlan::where('project_id', $id)->lockForUpdate()->findOrFail($planId);
        $r = $this->current($p, $plan->revision_id);
        abort_unless($plan->status === 'budget_confirmed' && $plan->confirmed_at && $plan->confirmed_by === $actor, 422, 'Confirm this production budget separately before starting.');
        abort_if($plan->expires_at->lte(now()), 409, 'This production budget expired. Prepare a fresh checklist.');
        [, , $fingerprint] = $this->inputs($p, $r, $plan->manifest['supplier_quote']['model']??null);
        abort_unless(hash_equals($plan->fingerprint, $fingerprint) && ! $plan->manifest['blockers'], 409, 'The approved production inputs changed. Prepare and confirm a fresh checklist.');

        return $plan;
    }

    private function current(MarketingProject $p, string $revision): MarketingRevision
    {
        abort_unless($p->current_revision_id === $revision && $p->confirmed_revision_id === $revision && $p->status === 'prepared', 409, 'Confirm the current script and storyboard before preparing production.');
        $r = MarketingRevision::where('project_id', $p->id)->findOrFail($revision);
        abort_unless($r->confirmed_at && $r->confirmed_by && empty(app(MarketingRequirementReview::class)->pending($p,$r)['gaps']) && ($r->context['brief'] ?? null) === $p->brief && ($r->context['snapshot'] ?? null) === $p->snapshot && ($r->context['concept'] ?? null) === ($p->concepts[$p->selected_concept] ?? null), 409, 'The confirmed facts or creative direction changed. Review and confirm a current storyboard.');

        return $r;
    }

    private function inputs(MarketingProject $p, MarketingRevision $r, ?string $supplierModel=null): array
    {
        $snapshot = ['project_id' => $p->id, 'project_version' => $p->version, 'revision_id' => $r->id, 'revision_number' => $r->number, 'document' => $r->document, 'source' => $p->snapshot, 'brief' => $p->brief, 'concept' => $r->context['concept']];
        $manifest = $this->compiler->compile($p, $r);

        if($supplierModel!==null){abort_unless(isset($manifest['handoff']),422,'先完成新版素材交接。');$quote=app(MarketingEvolinkAdapter::class)->quote($p,$manifest['handoff'],$supplierModel);$manifest['supplier_quote']=$quote;$manifest['execution_profile']='quotation-only';$manifest['budget']['supplier_estimate_usd']=$quote['video_estimate_microusd']/1000000;$manifest['budget']['kind']='public_video_only_estimate';$manifest['budget']['ai_video_requests']=count($quote['scenes']);$manifest['blockers']=['供应商视频输入与公开估价已整理；临时素材传输、实际模型访问、音频/唇形对齐及合成费用尚未完成，不授权制作。'];}
        return [$snapshot, $manifest, hash('sha256', json_encode([$snapshot, $manifest], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }
}
