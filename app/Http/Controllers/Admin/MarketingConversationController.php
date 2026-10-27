<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingConversation;
use App\Services\Marketing\MarketingPlanningConfirmation;
use App\Services\Marketing\MarketingWorkspaceView;
use Illuminate\Http\Request;

class MarketingConversationController extends Controller
{
    private function project(Request $r, string $id): MarketingProject
    {
        abort_unless($r->user()?->is_super_admin, 403);

        return MarketingProject::where('owner_id', $r->user()->id)->findOrFail($id);
    }

    public function show(Request $r, string $project, MarketingConversation $chat)
    {
        $p = $this->project($r, $project);
        $d = $r->validate(['before' => 'nullable|integer|min:1','screen'=>'nullable|in:'.implode(',',MarketingConversation::SCREENS)]);

        $out=['messages' => $chat->messages($p, $d['before'] ?? null), 'version' => $p->version];
        $screen=$r->input('screen','guided');
        if(in_array($screen,['guided','brief'],true)){
            $data=app(MarketingWorkspaceView::class)->data($p,$screen);$data['live']=true;
            $data['missing']=app(MarketingPlanningConfirmation::class)->missing($p);
            $data['link']=fn($screen)=>route('marketing.workspace.show',['project'=>$p->id,'screen'=>$screen]);
            $data['guide']=app(\App\Services\Marketing\MarketingPlanningGuide::class)->data($p);
            $out+=['guide'=>$data['guide'],'summary_html'=>view('marketing.workspace.summary',$data)->render(),'confirmation_html'=>view('marketing.workspace.planning-confirmation-action',$data)->render(),'planning_html'=>view('marketing.workspace.planning-guide',$data)->render(),'stepper_html'=>view('marketing.workspace.stepper',$data)->render()];
        }
        return response()->json($out)->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $r, string $project, MarketingConversation $chat)
    {
        $p = $this->project($r, $project);
        $d = $r->validate(['request_id' => 'required|uuid', 'screen' => 'required|in:'.implode(',', MarketingConversation::SCREENS), 'message' => 'required_without:choice|nullable|string|max:2000','choice'=>'sometimes|array:field,value','choice.field'=>'required_with:choice|string','choice.value'=>'required_with:choice|string','version'=>'required_with:choice|integer|min:0','film_id'=>'nullable|uuid','allow_head_trim'=>'nullable|boolean']);
        if(isset($d['choice']))$chat->choose($p->id,$r->user()->id,$d['request_id'],$d['screen'],(int)$d['version'],$d['choice']);
        elseif($d['screen']==='finished'&&!empty($d['film_id']))app(\App\Services\Marketing\MarketingEditingConversation::class)->enqueue($p,$d['request_id'],trim($d['message']),$d['film_id'],(bool)($d['allow_head_trim']??false));
        else $chat->send($p->id, $r->user()->id, $d['request_id'], $d['screen'], trim($d['message']));

        return $this->show($r, $project, $chat);
    }

    public function reconcile(Request $r, string $project, MarketingConversation $chat)
    {
        $this->project($r, $project);
        $chat->reconcile($project, $r->user()->id);

        return $this->show($r, $project, $chat);
    }

    public function decide(Request $r, string $project, string $message, MarketingConversation $chat)
    {
        $this->project($r, $project);
        $d = $r->validate(['decision' => 'required|in:accept,reject', 'screen' => 'nullable|in:'.implode(',', MarketingConversation::SCREENS)]);
        $chat->decide($project, $r->user()->id, $message, $d['decision']);
        $p = $this->project($r, $project);
        $data = app(MarketingWorkspaceView::class)->data($p, $r->input('screen', 'brief'));
        $data['live'] = true;
        $data['missing'] = app(MarketingPlanningConfirmation::class)->missing($p);
        $data['link'] = fn ($screen) => route('marketing.workspace.show', ['project' => $p->id, 'screen' => $screen]);

        return response()->json(['messages' => $chat->messages($p), 'version' => $p->version, 'confirmation_ready' => count($data['missing']) === 0 && empty($p->planning_state['proposed_changes']), 'confirmation_html' => view('marketing.workspace.planning-confirmation-action', $data)->render(), 'summary_html' => view('marketing.workspace.summary', $data)->render(), 'stepper_html' => view('marketing.workspace.stepper', $data)->render()])->header('Cache-Control', 'private, no-store');
    }
}
