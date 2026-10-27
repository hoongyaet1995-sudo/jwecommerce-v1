<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingProduction;
use App\Services\Marketing\{MarketingNativePresenter,MarketingNativeProduction};
use Illuminate\Http\Request;

class MarketingNativePresenterController extends Controller
{
    public function store(Request $r,MarketingNativePresenter $service)
    {
        abort_unless($r->user()?->is_super_admin,403);
        $d=$r->validate(['facts'=>'required|string|min:10|max:1000','script'=>'required|string|min:5|max:180','facts_confirmation'=>'required|accepted']);
        $p=$service->create($r->user(),$d['facts'],$d['script']);
        return redirect()->route('marketing.show',$p->id)->with('message','Supplied-script native presenter pilot saved. Review and confirm this draft. No AI request or paid generation was made.');
    }

    public function check(Request $r,string $project,string $production,MarketingNativeProduction $service)
    {
        abort_unless($r->user()?->is_super_admin,403);
        $task=\App\Models\MarketingProduction::where('project_id',$project)->where('owner_id',$r->user()->id)->findOrFail($production);
        if((\App\Models\MarketingProductionPlan::findOrFail($task->plan_id)->manifest['execution_profile']??null)===\App\Services\Marketing\MarketingHybridProduction::PROFILE)app(\App\Services\Marketing\MarketingHybridProduction::class)->queueCheck($project,$production,$r->user()->id);
        else $service->queueCheck($project,$production,$r->user()->id);
        return redirect(route('marketing.production.show',$project).'#production')->with('message','A status check for the existing provider task was queued. No new generation request was submitted.');
    }
}
