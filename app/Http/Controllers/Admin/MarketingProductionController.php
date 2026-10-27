<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingProductionPlan;
use App\Models\MarketingProduction;
use App\Models\MarketingProductionApproval;
use App\Models\MarketingVoiceAsset;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingProductionPreparation;
use App\Services\Marketing\MarketingProductionRunner;
use App\Services\Marketing\MarketingProductionDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingProductionController extends Controller
{
    private function project(Request $r, string $id): MarketingProject
    {
        abort_unless($r->user()?->is_super_admin, 403);

        return MarketingProject::where('owner_id', $r->user()->id)->with('currentRevision')->findOrFail($id);
    }

    public function show(Request $r, string $project)
    {
        $p = $this->project($r, $project);
        $plan = MarketingProductionPlan::where('project_id', $project)->latest('created_at')->latest('id')->first();
        $current = $p->current_revision_id && $p->confirmed_revision_id === $p->current_revision_id && $p->status === 'prepared' && ! $p->busy_run_id;
        $canConfirm = $current && $plan && $plan->revision_id === $p->current_revision_id && $plan->project_version === $p->version && $plan->expires_at->isFuture() && $plan->status === 'awaiting_budget';

        $production = $plan ? MarketingProduction::where('plan_id', $plan->id)->first() : null;
        $canRetryRender = $production && $production->status === 'failed' && in_array($production->error_code, ['render_failed', 'media_processing_failed', 'render_result_invalid'], true) && (in_array(($plan->manifest['execution_profile']??null),['local-only-v1',\App\Services\Marketing\MarketingHybridProduction::PROFILE],true)||($production->voice_asset_id && MarketingVoiceAsset::whereKey($production->voice_asset_id)->where('status', 'ready')->exists()));

        $approval = $production ? MarketingProductionApproval::where('production_id', $production->id)->first() : null;
        $canApprove = $current && $plan && $plan->revision_id === $p->current_revision_id && $plan->project_version === $p->version && $production?->status === 'completed';
        $change=$p->currentRevision?->context['production_change']??null;
        if($change){$change['impact']['brand_changed']=!app(\App\Services\Marketing\MarketingVideoChange::class)->brandMatches($p->currentRevision);if($change['impact']['brand_changed'])$canApprove=false;}
        $history=MarketingProduction::where('project_id',$project)->where('status','completed')->whereIn('id',MarketingProductionApproval::select('production_id'))->latest('created_at')->get();
        return view('marketing.production', ['project' => $p, 'plan' => $plan, 'current' => $current, 'canConfirm' => $canConfirm, 'production' => $production, 'canRetryRender' => $canRetryRender, 'approval' => $approval, 'canApprove' => $canApprove,'change'=>$change,'history'=>$history,'textReady'=>app(\App\Services\Marketing\MarketingWriter::class)->ready($p->id)]);
    }

    public function prepare(Request $r, string $project, MarketingProductionPreparation $service)
    {
        $p=$this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer|min:1', 'revision_id' => 'required|uuid', 'request_id' => 'required|uuid']);
        $modern=$p->currentRevision&&app(\App\Services\Marketing\MarketingProductionHandoff::class)->applies($p->currentRevision);
        if(!$modern&&app(\App\Services\Marketing\MarketingHybridProduction::class)->enabled($project)&&(($p->currentRevision?->context['production_strategy']['proposal']['route']??null)!=='hybrid'||($p->currentRevision?->context['hybrid_request']??null)===$d['request_id'])){
            app(\App\Services\Marketing\MarketingHybridProduction::class)->draft($project,$r->user()->id,$d['version'],$d['revision_id'],$d['request_id']);
            return redirect()->route('marketing.show',$project)->with('message','A separate optional third-scene draft was saved. Review it before confirming content. No AI, voice or video request was made.');
        }
        if(!$modern&&app(\App\Services\Marketing\MarketingToolPlanning::class)->executionEnabled($project)&&(($p->currentRevision?->context['production_recipe']??null)!==\App\Services\Marketing\MarketingToolPlanning::RECIPE||($p->currentRevision?->context['dynamic_source_request']??null)===$d['request_id'])){
            app(\App\Services\Marketing\MarketingWorkflow::class)->dynamicDraft($project,$r->user()->id,$d['version'],$d['revision_id'],$d['request_id']);
            return redirect()->route('marketing.show',$project)->with('message','A separate dynamic production draft was saved using the same story and brand. No AI request was made. Confirm this version before preparing its budget.');
        }
        $service->prepare($project, $r->user()->id, $d['version'], $d['revision_id'], $d['request_id']);

        return redirect()->route('marketing.production.show', $project)->with('message', 'Production checklist saved. No paid request has been sent. Review the materials and separate budget confirmation.');
    }

    public function confirm(Request $r, string $project, string $plan, MarketingProductionPreparation $service)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer|min:1', 'budget_confirmation' => 'required|accepted']);
        $service->confirm($project, $r->user()->id, $d['version'], $plan);

        return redirect()->route('marketing.production.show', $project)->with('message', 'Budget confirmation saved for this plan only. Production has not started; publishing is not authorized.');
    }

    public function start(Request $r, string $project, string $plan, MarketingProductionRunner $runner)
    {
        $this->project($r, $project);
        $d = $r->validate(['version' => 'required|integer|min:1']);
        $runner->start($project, $r->user()->id, $d['version'], $plan);

        return redirect(route('marketing.production.show', $project).'#production')->with('message', 'Production task saved. Refresh to check its actual status. Nothing is published.');
    }

    public function approve(Request $r, string $project, string $production, MarketingProductionDelivery $service)
    {
        $this->project($r, $project);
        $data = $r->validate(['output_sha256' => 'required|regex:/^[a-f0-9]{64}$/', 'final_confirmation' => 'required|accepted']);
        $service->approve($project, $production, $r->user()->id, $data['output_sha256']);
        return redirect(route('marketing.production.show', $project).'#production')->with('message', 'Final video approved and saved for this exact version. You can download it privately. Publishing is not authorized.');
    }

    public function change(Request $r, string $project, string $production, \App\Services\Marketing\MarketingWorkflow $workflow)
    {
        $this->project($r,$project);
        $data=$r->validate(['version'=>'required|integer|min:1','request_id'=>'required|uuid','feedback'=>'required|string|max:2000','text_confirmation'=>'required|accepted']);
        $workflow->generate($project,$r->user()->id,$data['version'],'revise',$data['feedback'],$data['request_id'],null,$production);
        return redirect(route('marketing.production.show',$project).'#production')->with('message','Video change draft saved. Review its impact and confirm the revised storyboard before a separate production budget. The previous video and approval remain saved.');
    }

    public function restoreBrand(Request $r,string $project,\App\Services\Marketing\MarketingVideoChange $service)
    {
        $this->project($r,$project);
        $data=$r->validate(['version'=>'required|integer','revision_id'=>'required|uuid','restore_confirmation'=>'required|accepted']);
        $service->restoreBrand($project,$r->user()->id,$data['version'],$data['revision_id']);
        return redirect(route('marketing.production.show',$project).'#production')->with('message','Original brand restored as a new draft. Previous versions remain saved. No paid request was made. Confirm this new draft before production.');
    }

    public function download(Request $r, string $project, string $production, MarketingProductionDelivery $service)
    {
        $this->project($r, $project);
        $task = MarketingProduction::where('project_id', $project)->where('owner_id', $r->user()->id)->findOrFail($production);
        return response()->download($service->approvedFile($task), 'marketing-video-'.$task->id.'.mp4', ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(Request $r, string $project, string $production)
    {
        $this->project($r, $project);
        $task = MarketingProduction::where('project_id', $project)->findOrFail($production);
        abort_unless($task->status === 'completed' && $task->output_path && Storage::disk('local')->exists($task->output_path), 404);

        return response()->file(Storage::disk('local')->path($task->output_path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
