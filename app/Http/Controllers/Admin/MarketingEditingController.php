<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingEditPlanService;
use Illuminate\Http\Request;
class MarketingEditingController extends Controller {
 public function reanalyze(Request $request,string $project,string $message,\App\Services\Marketing\MarketingEditingConversation $service){abort_unless($request->user()?->is_super_admin,403);$d=$request->validate(['request_id'=>'required|uuid','consent'=>'required|accepted']);$film=$service->retry($project,$request->user()->id,$message,$d['request_id']);return redirect()->route('marketing.workspace.show',['project'=>$project,'screen'=>'finished','film'=>$film]);}
 public function adopt(Request $request,string $project,string $plan,MarketingEditPlanService $service){
  abort_unless($request->user()?->is_super_admin,403);$d=$request->validate(['request_id'=>'required|uuid','base_film_id'=>'required|uuid','base_sha256'=>'required|string|size:64|regex:/^[a-f0-9]+$/']);
  $film=$service->adopt($project,$request->user()->id,$plan,$d['request_id'],$d['base_film_id'],$d['base_sha256']);$url=route('marketing.workspace.show',['project'=>$project,'screen'=>'finished','film'=>$film->id]);return $request->expectsJson()?response()->json(['film_id'=>$film->id,'url'=>$url]):redirect($url);
 }
 public function reject(Request $request,string $project,string $plan,MarketingEditPlanService $service){abort_unless($request->user()?->is_super_admin,403);$p=$service->reject($project,$request->user()->id,$plan);return response()->json(['plan_id'=>$p->id,'status'=>$p->status]);}
}
