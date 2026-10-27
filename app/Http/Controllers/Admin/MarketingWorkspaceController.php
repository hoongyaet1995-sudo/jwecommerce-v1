<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingConversation;
use App\Services\Marketing\MarketingPlanningAgent;
use App\Services\Marketing\MarketingPlanningConfirmation;
use App\Services\Marketing\MarketingPlanningContract;
use App\Services\Marketing\MarketingWorkflow;
use App\Services\Marketing\MarketingWorkspaceCreative;
use App\Services\Marketing\MarketingWorkspacePreparation;
use App\Services\Marketing\MarketingWorkspaceView;
use Illuminate\Http\Request;

class MarketingWorkspaceController extends Controller
{
    private function owner(Request $r): void
    {
        abort_unless($r->user()?->is_super_admin || ($r->isMethod('GET') && \App\Domain\Marketing\MarketingPermission::allows($r->user())), 403);
    }

    private function project(Request $r, string $id): MarketingProject
    {
        $this->owner($r);

        return MarketingProject::where('owner_id', $r->user()->id)->findOrFail($id);
    }

    private function go(MarketingProject $p, string $screen)
    {
        return redirect()->route('marketing.workspace.show', ['project' => $p->id, 'screen' => $screen])->with('message', '已保存。尚未授权制作或发布。');
    }

    public function show(Request $r, MarketingWorkspaceView $view, MarketingPlanningConfirmation $planning)
    {
        $this->owner($r);
        $d = $r->validate(['project' => 'nullable|uuid', 'screen' => 'nullable|string', 'scene'=>'nullable|integer|min:1|max:12','film'=>'nullable|uuid','handoff'=>'nullable|uuid']);
        $s = $d['screen'] ?? 'source';
        abort_unless(in_array($s, ['source', 'preferences', 'guided', 'brief', 'concepts', 'materials', 'confirmation', 'finished'], true), 404);
        $p = isset($d['project']) ? $this->project($r, $d['project']) : null;
        if($p?->template_id===\App\Services\Marketing\MarketingTemplateProjects::ID){return response()->view('marketing.workspace.template',['templateBoot'=>app(\App\Services\Marketing\MarketingTemplateProjects::class)->view($p)])->header('Cache-Control','private, no-store');}
        if (! $p && $s !== 'source') {
            return redirect()->route('marketing.workspace.show');
        }
        if ($p && in_array($s, ['guided', 'brief']) && (! ($p->preferences['complete'] ?? false) || ! $p->planning_state)) {
            return $this->go($p, 'preferences');
        }
        $data = $view->data($p, $s);
        if($p&&$s==='confirmation'&&(\App\Models\MarketingStoryboardWorkspace::where('project_id',$p->id)->exists()||$r->boolean('storyboard'))){$data['storyboardPreflight']=app(\App\Services\Marketing\MarketingProductionPreparation::class)->storyboardPreview($p->id,$r->user()->id);$newReady=$data['storyboardPreflight']['handoff_current'];$data['progressCurrent']=$newReady?4:3;$data['completedThrough']=$newReady?3:2;}
        if($p&&$s==='finished'&&config('marketing.workspace_video.enabled')&&\App\Models\MarketingStoryboardWorkspace::where('project_id',$p->id)->exists()){
            $data['clipWorkbench']=app(\App\Services\Marketing\MarketingVideoWorkbench::class)->storyboardFilmData($p,$d['film']??null);$data['progressCurrent']=5;$filmStory=$data['clipWorkbench']['story_snapshot'];$data['completedThrough']=$data['clipWorkbench']['film_current']?4:((($filmStory['last_confirmation']['snapshot']['story_version']??null)===$filmStory['story_version'])?3:2);
        }
        if(!isset($data['clipWorkbench'])&&!isset($data['storyboardPreflight'])&&$p&&config('marketing.workspace_video.enabled')&&in_array($s,['confirmation','finished'],true)&&$p->currentRevision&&app(\App\Services\Marketing\MarketingProductionHandoff::class)->applies($p->currentRevision)){
            $data['clipWorkbench']=app(\App\Services\Marketing\MarketingVideoWorkbench::class)->data($p,isset($d['scene'])?(int)$d['scene']:null,$d['film']??null);
            $data['progressCurrent']=$s==='finished'?5:4;
        }
        if(!isset($data['storyboardPreflight'])&&!isset($data['clipWorkbench'])&&$p&&$s==='confirmation'&&$p->currentRevision&&app(\App\Services\Marketing\MarketingProductionHandoff::class)->applies($p->currentRevision)){$data['preflight']=app(\App\Services\Marketing\MarketingProductionHandoff::class)->preview($p);$data['preflightPlan']=\App\Models\MarketingProductionPlan::where('project_id',$p->id)->where('revision_id',$p->current_revision_id)->where('project_version',$p->version)->where('manifest->recipe',\App\Services\Marketing\MarketingProductionHandoff::VERSION)->latest('created_at')->latest('id')->first();}
        if(isset($data['preflight'])){
            $data['supplierModels']=app(\App\Services\Marketing\MarketingEvolinkAdapter::class)->models();
            $data['supplierQuotePlan']=\App\Models\MarketingProductionPlan::where('project_id',$p->id)->whereNotNull('manifest->supplier_quote')->latest('created_at')->latest('id')->first();
            $q=$data['supplierQuotePlan'];$quote=$q?->manifest['supplier_quote']??null;
            $data['supplierQuoteFresh']=$quote&&$data['preflight']['handoff']&&$q->revision_id===$p->current_revision_id&&$q->project_version===$p->version&&$q->expires_at->isFuture()&&now()->lt($quote['pricing_valid_until'])&&($quote['version']??'')===\App\Services\Marketing\MarketingEvolinkAdapter::VERSION&&($quote['handoff_digest']??'')===$data['preflight']['handoff']['digest']&&($quote['connection_fingerprint']??'')===app(\App\Services\Marketing\MarketingEvolinkAdapter::class)->connectionFingerprint();
        }
        $data['live'] = true;
        if($p&&$s==='materials'&&!empty($d['handoff'])){
            $message=\App\Models\MarketingConversationMessage::where('project_id',$p->id)->where('actor_id',$p->owner_id)->where('role','assistant')->findOrFail($d['handoff']);$handoff=$message->context['handoff']??null;abort_unless($handoff,404);
            $film=\App\Models\MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->findOrFail($handoff['base_film_id']);$handoff['current']=false;
            try{app(\App\Services\Marketing\MarketingWorkspaceFilm::class)->assertCurrent($film);$handoff['current']=hash_equals($handoff['base_sha256'],$film->output_sha256??'');}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){}
            $data['editingHandoff']=$handoff;
        }
        $data['conversationMessages'] = $p && in_array($s, MarketingConversation::SCREENS, true) ? app(MarketingConversation::class)->messages($p) : [];
        $data['question'] = $p && $p->planning_state ? $planning->question($p) : null;
        $data['missing'] = $p && $p->planning_state ? $planning->missing($p) : [];
        $data['textReady'] = $p ? app(MarketingPlanningAgent::class)->ready($p) : false;
        $data['conversationReady'] = $p ? app(MarketingConversation::class)->available($p) : false;
        if($s==='finished')$data['conversationReady']=$data['conversationReady']&&\App\Services\Marketing\MarketingEditingConversation::enabled();

        return response()->view('marketing.workspace.preview', $data)->header('Cache-Control', 'private, no-store');
    }

    public function preflight(Request $r,string $project,\App\Services\Marketing\MarketingProductionPreparation $service)
    {
        $this->project($r,$project);$d=$r->validate(['version'=>'required|integer|min:1','revision_id'=>'required|uuid','request_id'=>'required|uuid']);
        $p=$this->project($r,$project);abort_unless($p->currentRevision&&app(\App\Services\Marketing\MarketingProductionHandoff::class)->applies($p->currentRevision),422,'请先完成新版分镜与素材准备。');
        $service->prepare($project,$r->user()->id,$d['version'],$d['revision_id'],$d['request_id']);
        return redirect()->route('marketing.workspace.show',['project'=>$project,'screen'=>'confirmation'])->with('message','逐段素材交接与本地预检已保存；未调用收费API，未授权制作。');
    }

    public function supplierQuote(Request $r,string $project,\App\Services\Marketing\MarketingProductionPreparation $service)
    {
        $this->project($r,$project);$d=$r->validate(['version'=>'required|integer|min:1','revision_id'=>'required|uuid','request_id'=>'required|uuid','model'=>'required|in:'.implode(',',array_keys(app(\App\Services\Marketing\MarketingEvolinkAdapter::class)->models()))]);
        $service->prepare($project,$r->user()->id,$d['version'],$d['revision_id'],$d['request_id'],$d['model']);
        return redirect()->route('marketing.workspace.show',['project'=>$project,'screen'=>'confirmation'])->with('message','供应商输入方案与视频估价已保存；未上传素材或提交收费视频任务。');
    }

    public function transitionReview(Request $r,string $project,\App\Services\Marketing\MarketingTransitionPlanning $service)
    {
        $this->project($r,$project);$d=$r->validate(['version'=>'required|integer|min:1','revision_id'=>'required|uuid','consent'=>'required|accepted','edges'=>'nullable|array|max:11','edges.*'=>'required|string|regex:/^[1-9][0-2]?-[1-9][0-2]?$/','adopt_only'=>'nullable|boolean']);
        $service->confirm($project,$r->user()->id,$d['version'],$d['revision_id'],$d['edges']??[],!($d['adopt_only']??false));
        return redirect()->route('marketing.workspace.show',['project'=>$project,'screen'=>'materials'])->with('message','跨段衔接审阅已保存；原图片保留，未授权重画或制作。');
    }

    public function source(Request $r, MarketingWorkspacePreparation $prep, ?string $project = null)
    {
        $this->owner($r);
        if ($project) {
            abort_if($this->project($r, $project)->template_id,409,'请在模板项目页面修改资料。');
        }
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => $project ? 'required|integer|min:1' : 'nullable|integer', 'source_kind' => 'required|in:product,service,theme', 'product_id' => 'required_unless:source_kind,theme|nullable|integer|exists:products,id', 'theme' => 'required_if:source_kind,theme|nullable|string|min:5|max:2000', 'video_type' => 'required|in:'.implode(',', array_merge(MarketingPlanningContract::TYPES, ['auto']))]);
        $request = $d['request_id'];
        $version = $d['version'] ?? null;
        unset($d['request_id'],$d['version']);
        if ($d['source_kind'] === 'theme') {
            unset($d['product_id']);
        } else {
            unset($d['theme']);
        }

        return $this->go($prep->source($r->user(), $d, $request, $project, $version), 'preferences');
    }

    public function preferences(Request $r, string $project, MarketingWorkspacePreparation $prep)
    {
        abort_if($this->project($r, $project)->template_id,409,'请在模板项目页面修改制作偏好。');
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'language' => 'required|in:English,zh-MY,ms-MY', 'platforms' => 'required|array|list|min:1|max:4', 'platforms.*' => 'required|distinct|in:Facebook,Instagram,TikTok,YouTube', 'duration' => 'required|in:auto,15,30,60', 'subtitles' => 'required|boolean', 'narration' => 'required|boolean', 'voice' => 'required|in:ai,none', 'music' => 'required|in:later,none', 'production_tier'=>'sometimes|in:ordinary,advanced,professional']);
        $request = $d['request_id'];
        $version = $d['version'];
        unset($d['request_id'],$d['version']);
        $d['subtitles'] = (bool) $d['subtitles'];
        $d['narration'] = (bool) $d['narration'];
        $d['voice'] = $d['narration'] ? 'ai' : 'none';
        $d['duration'] = (string) $d['duration'];

        return $this->go($prep->preferences($project, $r->user()->id, $version, $request, $d), 'guided');
    }

    public function answer(Request $r, string $project, MarketingPlanningConfirmation $planning)
    {
        $this->project($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'field' => 'required|string', 'value' => 'present']);
        $p = $planning->answer($project, $r->user()->id, $d['version'], $d['request_id'], $d['field'], $d['value']);

        return $this->go($p, $planning->question($p) ? 'guided' : 'brief');
    }

    public function confirm(Request $r, string $project, MarketingPlanningConfirmation $planning)
    {
        $this->project($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1']);

        try {
            $p = app(\App\Services\Marketing\MarketingPlanningHandoff::class)->confirm($project, $r->user()->id, $d['version'], $d['request_id']);
        } catch (\Illuminate\Validation\ValidationException $error) {
            if (!isset($error->errors()['generation'])) throw $error;
            return redirect()->route('marketing.workspace.show', ['project' => $project, 'screen' => 'concepts'])->withErrors($error->errors());
        }

        return $this->go($p, 'concepts');
    }

    public function creative(Request $r, string $project, MarketingWorkflow $flow)
    {
        $p = $this->project($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'action' => 'required|in:concepts,hook,select,storyboard,confirm_storyboard,director,confirm_director,material_plan,transitions', 'index' => 'required_if:action,hook,select|nullable|integer|min:0|max:2', 'feedback' => 'nullable|string|max:2000']);
        abort_unless(($p->planning_state['status'] ?? '') === 'confirmed', 422, '请先重新确认策划摘要。');
        $action = $d['action'];
        if (in_array($action,['director','material_plan','transitions'],true)) {
            app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->draft($project, $r->user()->id, $d['version'], $p->current_revision_id ?? '', $d['request_id'], $action, $d['feedback'] ?? '');
        } elseif ($action === 'confirm_director') {
            app(\App\Services\Marketing\MarketingDirectorWorkflow::class)->confirm($project, $r->user()->id, $d['version'], $p->current_revision_id ?? '');
        } elseif ($action === 'confirm_storyboard') {
            $flow->confirm($project, $r->user()->id, $d['version'], $p->current_revision_id ?? '');
        } elseif ($action === 'select') {
            try {
                app(\App\Services\Marketing\MarketingConceptHandoff::class)->select($project, $r->user()->id, $d['version'], $d['index'], $d['request_id']);
            } catch (\Illuminate\Validation\ValidationException $error) {
                return redirect()->route('marketing.workspace.show', ['project' => $project, 'screen' => 'materials'])
                    ->withErrors(['generation' => implode(' ', array_map(fn ($messages) => $messages[0] ?? '', $error->errors()))]);
            }
        } elseif ($action === 'hook') {
            app(MarketingWorkspaceCreative::class)->hook($project, $r->user()->id, $d['version'], $d['index'], $d['request_id'], $d['feedback'] ?? '');
        } else {
            $flow->generate($project, $r->user()->id, $d['version'], $action, $d['feedback'] ?? '', $d['request_id'], null, null, true);
        }

        if($action==='storyboard'){
            $fresh=$p->fresh();$existing=\App\Models\MarketingStoryboardWorkspace::where('project_id',$project)->exists();
            if($fresh->current_revision_id&&!$existing)app(\App\Services\Marketing\MarketingStoryboardDirector::class)->initialize($project,$r->user()->id,(string)\Illuminate\Support\Str::uuid(),$fresh->version);
        }
        return $this->go($p->fresh(), $action === 'confirm_storyboard' ? 'confirmation' : (in_array($action, ['select', 'storyboard', 'director', 'confirm_director', 'material_plan','transitions']) ? 'materials' : 'concepts'));
    }

    public function conceptHistory(Request $r,string $project)
    {
        $p=$this->project($r,$project);
        $events=\App\Models\MarketingPlanningEvent::where('project_id',$project)->where('kind','concept_snapshot')->latest('created_at')->latest('id')->paginate(8);
        return response()->view('marketing.workspace.concept-history',['project'=>$p,'events'=>$events,'currentContext'=>app(\App\Services\Marketing\MarketingConceptHistory::class)->context($p)])->header('Cache-Control','private, no-store');
    }

    public function captureConceptHistory(Request $r,string $project)
    {
        $p=$this->project($r,$project);$d=$r->validate(['version'=>'required|integer|min:1']);
        \Illuminate\Support\Facades\DB::transaction(function()use($p,$r,$d){
            $current=app(\App\Services\Marketing\MarketingWorkflow::class)->locked($p->id,$r->user()->id,$d['version']);
            app(\App\Services\Marketing\MarketingConceptHistory::class)->capture($current,'baseline');
        });
        return $this->conceptHistory($r,$project);
    }

    public function restoreConcept(Request $r,string $project,string $history)
    {
        $p=$this->project($r,$project);
        $d=$r->validate(['version'=>'required|integer|min:1','request_id'=>'required|uuid','index'=>'nullable|integer|min:0|max:2']);
        app(\App\Services\Marketing\MarketingConceptHistory::class)->restore($project,$r->user()->id,$d['version'],$history,$d['request_id'],isset($d['index'])?(int)$d['index']:null);
        return $this->go($p->fresh(),isset($d['index'])?'materials':'concepts');
    }

    public function suggest(Request $r, string $project, MarketingPlanningAgent $agent)
    {
        $this->project($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'feedback' => 'nullable|string|max:2000']);
        $p = $agent->suggest($project, $r->user()->id, $d['version'], $d['request_id'], $d['feedback'] ?? '');

        return $this->go($p, 'guided');
    }
}
