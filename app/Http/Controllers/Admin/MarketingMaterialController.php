<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingMaterialReferences;
class MarketingMaterialController extends Controller
{
 public function repair(Request $request,string $project,string $operation,\App\Services\Marketing\MarketingMaterialRepair $repair){
  abort_unless($request->user()?->is_super_admin,403);$actor=$request->user()->id;$images=app(\App\Services\Marketing\MarketingMaterialImages::class);$images->project($project,$actor);abort_unless(in_array($operation,['plan','authorize','advance','apply','resume-budget','abandon']),404);
  $d=$request->validate(['version'=>'nullable|integer','request_id'=>'nullable|uuid','run'=>'nullable|uuid','spend_stop_usd'=>'nullable|numeric|min:0.6|max:100']);
  if($operation==='plan'){$request->validate(['version'=>'required|integer','request_id'=>'required|uuid']);$run=$repair->plan($project,$actor,$d['version'],$d['request_id']);}
  else{$request->validate(['run'=>'required|uuid']);$saved=\App\Models\MarketingAiRun::where('project_id',$project)->findOrFail($d['run']);abort_unless(isset($saved->usage['repair_context']),422);if(in_array($operation,['authorize','apply','resume-budget','abandon']))$request->validate(['consent'=>'required|accepted']);$run=match($operation){'authorize'=>$repair->authorize($project,$actor,$d['run'],(float)($d['spend_stop_usd']??2)),'apply'=>$images->approveBatch($project,$actor,$d['run']),'resume-budget'=>$images->resumeBudget($project,$actor,$d['run'],(float)($d['spend_stop_usd']??2)),'abandon'=>$images->abandon($project,$actor,$d['run']),default=>$images->advance($project,$actor,$d['run'])};}
  return response()->json(['reload'=>$run->status!=='running','status'=>$run->status,'run'=>$run->id,'next_operation'=>$run->status==='running'?'advance':null,'progress'=>$repair->progress($run)]);
 }
 public function reference(Request $request,string $project,string $need,MarketingMaterialReferences $references)
 {
  abort_unless($request->user()?->is_super_admin,403);MarketingProject::where('owner_id',$request->user()->id)->findOrFail($project);
  $data=$request->validate(['version'=>'required|integer|min:1','request_id'=>'required|uuid','source'=>'nullable|string|max:100','answer'=>'nullable|string|max:2000','permission'=>'required|accepted','image'=>'nullable|file|mimes:jpg,jpeg,png,webp|max:10240']);
  $references->save($project,$request->user()->id,$data['version'],$need,$data['request_id'],['source'=>$data['source']??'','answer'=>$data['answer']??'','permission'=>true],$request->file('image'));
  return redirect()->route('marketing.workspace.show',['project'=>$project,'screen'=>'materials'])->with('message','参考资料已保存。仍需AI制备、审阅生产图片，未开始收费制作。');
 }
 public function images(Request $request,string $project,string $operation,\App\Services\Marketing\MarketingMaterialImages $images)
 {
  abort_unless($request->user()?->is_super_admin,403);$actor=$request->user()->id;$images->project($project,$actor);
  abort_unless(in_array($operation,['quote','authorize','advance','review','facts','approve-batch','resume-budget','abandon']),404);
  $data=$request->validate(['version'=>'nullable|integer','request_id'=>'nullable|uuid','run'=>'nullable|uuid','output'=>'nullable|string|max:100','approve'=>'nullable|boolean','feedback'=>'nullable|string|max:1000','auto_review'=>'nullable|boolean','spend_stop_usd'=>'nullable|numeric|min:0.6|max:100']);
  if($operation==='facts'){$d=$request->validate(['version'=>'required|integer|min:1','facts'=>'required|array|min:1|max:12','facts.*'=>'required|string|size:64','consent'=>'required|accepted']);app(\App\Services\Marketing\MarketingRequirementReview::class)->acknowledge($project,$actor,$d['version'],$d['facts']);return response()->json(['reload'=>true]);}
  if($operation==='quote'){$request->validate(['version'=>'required|integer','request_id'=>'required|uuid']);$run=$images->quote($project,$actor,(int)$data['version'],$data['request_id'],$data['feedback']??'',(bool)($data['auto_review']??false),(float)($data['spend_stop_usd']??2));}
  else{$request->validate(['run'=>'required|uuid']);if($operation==='authorize'){$request->validate(['consent'=>'required|accepted']);$run=$images->authorize($project,$actor,$data['run']);}
   elseif($operation==='approve-batch'){$request->validate(['consent'=>'required|accepted']);$run=$images->approveBatch($project,$actor,$data['run']);}
   elseif($operation==='resume-budget'){$request->validate(['consent'=>'required|accepted','spend_stop_usd'=>'required|numeric|min:0.6|max:100']);$run=$images->resumeBudget($project,$actor,$data['run'],(float)$data['spend_stop_usd']);}
   elseif($operation==='abandon'){$request->validate(['consent'=>'required|accepted']);$run=$images->abandon($project,$actor,$data['run']);}
   elseif($operation==='advance')$run=$images->advance($project,$actor,$data['run']);
   else{$request->validate(['output'=>'required|string','approve'=>'required|boolean']);$images->review($project,$actor,$data['run'],$data['output'],(bool)$data['approve']);return response()->json(['reload'=>true]);}}
  return response()->json(['reload'=>true,'status'=>$run->status]);
 }
}
