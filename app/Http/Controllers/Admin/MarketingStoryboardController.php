<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingPreparationAuthorization;
use App\Services\Marketing\MarketingCreativeProfiles;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingPresenterLibrary;
use App\Services\Marketing\MarketingProductionPreparation;
use App\Services\Marketing\MarketingStoryboardDirector;
use App\Services\Marketing\MarketingStoryboardHandoff;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardProvider;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardVoice;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use Illuminate\Http\Request;

class MarketingStoryboardController extends Controller
{
    private function owner(Request $r, string $project): int
    {
        abort_unless($r->user()?->is_admin, 403);
        app(MarketingStoryboardWorkspace::class)->project($project, $r->user()->id);

        return $r->user()->id;
    }

    public function show(Request $r, string $project)
    {
        $owner = $this->owner($r, $project);

        if(app(\App\Services\Marketing\MarketingGenerationQuotes::class)->enabled())app(\App\Services\Marketing\MarketingGenerationPricing::class)->preview($project);
        return response()->json(['billing_receipt'=>app(\App\Services\Marketing\MarketingGenerationQuotes::class)->receipt($project,$owner),'billing_enabled'=>app(\App\Services\Marketing\MarketingGenerationQuotes::class)->enabled(),'snapshot' => app(\App\Services\Marketing\MarketingStoryboardDirectorReadout::class)->snapshot(app(MarketingStoryboardWorkspace::class)->snapshot($project, $owner)), 'options' => app(MarketingCreativeProfiles::class)->options($project, $owner), 'generation_enabled' => app(\App\Services\Marketing\MarketingGenerationQuotes::class)->enabled()||app(MarketingStoryboardProvider::class)->allowed('semantic', $project)])->header('Cache-Control', 'private, no-store');
    }

    public function write(Request $r, string $project, string $action)
    {
        $owner = $this->owner($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:0', 'command' => 'sometimes|array', 'preview_key'=>'sometimes|string|size:64|regex:/^[a-f0-9]{64}$/D', 'profile' => 'sometimes|array', 'scope' => 'sometimes|array|max:12', 'scope.*' => 'uuid', 'cap_microusd' => 'sometimes|integer|min:1|max:100000000', 'authorization_id' => 'sometimes|uuid', 'name' => 'sometimes|string|max:80', 'kind' => 'sometimes|in:synthetic,real', 'asset_id' => 'sometimes|uuid', 'consent' => 'sometimes|accepted', 'scene_id' => 'sometimes|uuid','plan_id'=>'sometimes|uuid','frame_role'=>'sometimes|in:start_frame,end_frame','production_id'=>'sometimes|uuid','operation'=>'sometimes|string','payload'=>'sometimes|array','quote_id'=>'sometimes|uuid']);
        $s = app(MarketingStoryboardWorkspace::class);
        $v = (int) $d['version'];
        $id = $d['request_id'];
        $billing=app(\App\Services\Marketing\MarketingGenerationQuotes::class);
        $billingQuote=null;
        if($billing->enabled()){
            app(\App\Services\Marketing\MarketingGenerationPricing::class)->preview($project);
            if($action==='billing-quote')return response()->json(['quote'=>$billing->view($billing->quote($project,$owner,$v,$d['operation']??'', $d['payload']??[]))]);
            if($action==='billing-confirm'){
                $claim=$billing->claim($project,$owner,$d['quote_id']??'',(bool)($d['consent']??false));
                if($claim['replay']!==null)return response()->json($claim['replay']);
                $billingQuote=$claim['quote'];try{abort_unless($billing->activate($billingQuote),409,'本次费用确认已失效，请重新核价。');}catch(\Throwable $error){$billing->fail($billingQuote);throw $error;}
                config(['marketing.billing.execution_quote_id'=>$billingQuote->id]);
                $action=$billingQuote->action;$d=$billingQuote->payload;$v=$billingQuote->story_version;$id=$billingQuote->id;
            }elseif(in_array($action,$billing::ACTIONS,true)){
                $freeManual=$action==='changes'&&($d['command']['mode']??'manual')==='manual'&&empty($d['command']['refine_manual'])&&isset($d['command']['update_scope']['images'])&&!$d['command']['update_scope']['images'];
                $freeManual=$freeManual||($action==='changes'&&($d['command']['mode']??'manual')==='manual'&&in_array($d['command']['type']??'', ['move','remove','restore'],true));
                abort_unless($freeManual,409,'请先查看本次内容与费用，再确认生成。');
            }
        }
        try{$result = match ($action) {
            'production-recover'=>app(\App\Services\Marketing\MarketingProductionRecovery::class)->progress($project,$owner,$d['production_id']??''),
            'retry-decline'=>app(\App\Services\Marketing\MarketingProductionRecovery::class)->decline($project,$owner,$d['quote_id']??''),
            'retry-production'=>app(\App\Services\Marketing\MarketingProductionRecovery::class)->approve($billingQuote),
            'production-entry'=>$this->productionEntry($project,$owner,$v),
            'upload-checkpoint'=>['checkpoint'=>app(\App\Services\Marketing\MarketingUploadCheckpoint::class)->uploadOne($project,$owner,$d['plan_id']??'', $d['frame_role']??'start_frame')],
            'sync-accepted-framing'=>['snapshot'=>app(\App\Services\Marketing\MarketingStoryboardDecisions::class)->syncAccepted($project,$owner,$id,$v,$d['command']??[])],
            'timing-preview'=>['timing_preview'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->timingPreview($project,$owner,$v)],
            'timing-extend'=>['snapshot'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->extendTiming($project,$owner,$id,$v,$d['preview_key']??'')],
            'timing-shorten-preview'=>['shortening_preview'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->shortenPreview($project,$owner,$id,$v,$d['preview_key']??'')],
            'timing-shorten-apply'=>['snapshot'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->applyShortening($project,$owner,$id,$v,$d['preview_key']??'')],
            'missing-images-preview'=>['missing_preview'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->missingPreview($project,$owner,$v)],
            'missing-images-prepare'=>app(\App\Services\Marketing\MarketingStoryboardClosure::class)->prepareMissing($project,$owner,$id,$v,$d['preview_key']??'',(bool)($d['consent']??false)),
            'material-plan-preview'=>['material_preview'=>app(\App\Services\Marketing\MarketingStoryboardDecisions::class)->preview($project,$owner,$id,$v)],
            'material-plan-apply'=>['snapshot'=>app(\App\Services\Marketing\MarketingStoryboardDecisions::class)->applyPlan($project,$owner,$id,$v,$d['preview_key']??'')],
            'adopt-candidate'=>['snapshot'=>app(\App\Services\Marketing\MarketingStoryboardDecisions::class)->adopt($project,$owner,$id,$v,$d['command']??[])],
            'video-settings'=>['snapshot'=>app(\App\Services\Marketing\MarketingVideoSettings::class)->save($project,$owner,$id,$v,$d['command']??[])],
            'tier'=>['snapshot'=>app(\App\Services\Marketing\MarketingProductionPreferences::class)->save($project,$owner,$id,$v,(string)($d['profile']['production_tier']??''))],
            'start-production'=>app(\App\Services\Marketing\MarketingStoryboardProduction::class)->state($project,$owner,app(\App\Services\Marketing\MarketingStoryboardProduction::class)->start($project,$owner,$v,$d['plan_id']??'',(bool)($d['consent']??false))->id),'resume-production'=>['id'=>app(\App\Services\Marketing\MarketingStoryboardProduction::class)->resume($project,$owner,$d['production_id']??'')->id], 'presenters' => ['presenter' => app(MarketingPresenterLibrary::class)->register($project, $owner, $id, $d['name'] ?? '', $d['kind'] ?? '', $d['asset_id'] ?? '', (bool) ($d['consent'] ?? false)), 'options' => app(MarketingCreativeProfiles::class)->options($project, $owner)], 'voice' => abort(409,'正式配音仅在Step 4确认制作费用后执行。'), 'initialize' => app(MarketingStoryboardDirector::class)->initialize($project,$owner,$id,$v),'changes' => app(MarketingStoryboardDirector::class)->change($project, $owner, $id, $v, $d['command'] ?? []),'profile' => $this->profile($project, $owner, $id, $v, $d['profile'] ?? []),'authorizations' => ['authorization' => app(MarketingPreparationBudget::class)->authorize($project, $owner, $id, $v, $d['scope'] ?? [], (int) ($d['cap_microusd'] ?? 0))],'preparations' => $this->prepare($project, $d['authorization_id'] ?? ''),'production' => $this->production($project, $owner, $id, $v, $d['scene_id']??null),
            'confirm' => ['snapshot' => app(MarketingStoryboardHandoff::class)->confirm($project, $owner, $id, $v)],default => abort(404)
        };

        }catch(\Throwable $error){if($billingQuote)$billing->fail($billingQuote);throw $error;}
        if(isset($result['snapshot']))$result['snapshot']=app(\App\Services\Marketing\MarketingStoryboardDirectorReadout::class)->snapshot($result['snapshot']);
        if($billingQuote)$billing->finish($billingQuote,$result);
        return response()->json($result+['billing_receipt'=>$billing->receipt($project,$owner),'generation_enabled'=>app(MarketingStoryboardProvider::class)->allowed('semantic',$project)])->header('Cache-Control', 'private, no-store');
    }

    public function productionState(Request $r,string $project,string $production) {
        $owner=$this->owner($r,$project);return response()->json(app(\App\Services\Marketing\MarketingStoryboardProduction::class)->state($project,$owner,$production))->header('Cache-Control','private, no-store');
    }

    private function productionEntry(string $project,int $owner,int $version):array
    {
        $preview=app(MarketingProductionPreparation::class)->storyboardPreview($project,$owner);
        abort_unless($preview['snapshot']['story_version']===$version&&$preview['handoff_current'],409,'请先确认当前故事与素材。');
        return ['preview_url'=>route('marketing.workspace.show',['project'=>$project,'screen'=>'confirmation','storyboard'=>1])];
    }

    private function profile(string $project, int $owner, string $id, int $version, array $profile): array
    {
        $s = app(MarketingStoryboardWorkspace::class);
        $duplicate = \App\Models\MarketingStoryboardCommand::where('project_id',$project)->where('request_id',$id)->exists();
        $snapshot = $s->transact($project, $owner, $id, ['profile', $version, $profile], fn () => app(MarketingCreativeProfiles::class)->save($project, $owner, $version, $profile));
        $out = ['snapshot' => $snapshot, 'options' => app(MarketingCreativeProfiles::class)->options($project, $owner)];
        if (! $duplicate) return app(MarketingStoryboardDirector::class)->prepare($project,$owner,$out);

        return $out;
    }

    private function production(string $project, int $owner, string $request, int $version, ?string $sceneId=null): array
    {
        if(!app()->environment('testing'))app(\App\Services\Marketing\MarketingProductionQuoteEvidence::class)->refresh($project,$owner);
        $plan = app(MarketingProductionPreparation::class)->prepareStoryboard($project, $owner, $request, $version, $sceneId);

        return ['plan_id' => $plan->id, 'preview_url' => route('marketing.workspace.show', ['project' => $project, 'screen' => 'confirmation', 'storyboard' => 1])];
    }

    private function prepare(string $project, string $authorization): array
    {
        abort_unless(MarketingPreparationAuthorization::whereKey($authorization)->where('project_id', $project)->exists(), 404);

        return app(MarketingStoryboardPreparation::class)->prepare($project, $authorization);
    }

    public function upload(Request $r, string $project)
    {
        $owner = $this->owner($r, $project);
        $r->validate(['file' => 'required|file|max:30720', 'authorized' => 'required|accepted']);

        return response()->json(['asset' => app(MarketingStoryboardUpload::class)->store($project,$owner,$r->file('file'),true)]);
    }
}
