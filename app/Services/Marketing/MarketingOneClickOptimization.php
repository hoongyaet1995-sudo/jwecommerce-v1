<?php
namespace App\Services\Marketing;

use App\Jobs\ProcessMarketingOptimization;
use App\Models\{MarketingAiRun,MarketingProject,MarketingVideoFilm};
use Illuminate\Support\Facades\{Cache,DB};
use Illuminate\Support\Str;

/** A source-bound user click authorizes safe selection and local composition, never publication. */
class MarketingOneClickOptimization {
 public function start(string $project,int $owner,string $film,string $hash,string $request,?string $runId=null):MarketingAiRun {
  if(config('marketing.optimization.semantic_enabled',true))return app(MarketingSemanticOptimization::class)->start($project,$owner,$film,$hash,$request);
  abort_unless(MarketingEditingConversation::enabled(),403);
  $optimization=app(MarketingOptimization::class);$base=$optimization->film($project,$owner,$film);
  abort_unless(hash_equals($base->output_sha256,$hash),409,'成片版本已变化，请重新读取。');
  return DB::transaction(function()use($project,$owner,$film,$hash,$request,$runId,$optimization,$base){
   MarketingProject::whereKey($project)->lockForUpdate()->firstOrFail();
   $run=$optimization->read($project,$owner,$film,$runId);
   $existing=MarketingAiRun::where('project_id',$project)->where('action',MarketingOptimization::ACTION)->where('usage->film_id',$film)->where('usage->base_hash',$hash)->whereNotNull('usage->one_click')->latest()->first();
   $run=$existing??$run;
   $newAnalysis=false;if(!$run){$run=$optimization->enqueue($project,$owner,$film,$hash,$request);$newAnalysis=true;}
   $run=MarketingAiRun::lockForUpdate()->findOrFail($run->id);
   abort_unless(hash_equals($run->usage['recipe_digest'],MarketingEditingDigest::hash($base->input)),409,'配方已变化，请重新读取。');
   if(isset($run->usage['one_click']))return $run;
   $failed=in_array($run->status,['failed','unknown']);
   $this->state($run,['request_id'=>$request,'adoption_id'=>(string)Str::uuid(),'status'=>$failed?'failed':'queued','requested_at'=>now()->toIso8601String(),'error'=>$failed?'已有检查未完成，原版保留；不会自动重新分析。':null]);
   // Fresh analysis already has one after-commit job. Cached results only need local application.
   if(!$newAnalysis&&$run->status==='completed')ProcessMarketingOptimization::dispatch($run->id);
   return $run->fresh();
  });
 }
 private function state(MarketingAiRun $run,array $changes):void {
  DB::transaction(function()use($run,$changes){$locked=MarketingAiRun::lockForUpdate()->findOrFail($run->id);$usage=$locked->usage;$usage['one_click']=array_replace($usage['one_click']??[],$changes);$locked->update(['usage'=>$usage]);});
 }
 public function complete(string $id):void {
  $lock=Cache::lock('optimization-one-click:'.$id,1200);if(!$lock->get())return;
  try{
   $run=MarketingAiRun::findOrFail($id);$click=$run->usage['one_click']??null;
   if(!$click||!in_array($click['status'],['queued','applying']))return;
   if($run->updated_at->lt(now()->subMinutes(20))){$this->failed($run->id);return;}
   if(in_array($run->status,['failed','unknown'])){$this->state($run,['status'=>'failed','error'=>'检查未完成，原版保留；不会自动重新分析。']);return;}
   if($run->status!=='completed')return;
   $this->state($run,['status'=>'applying']);
   $base=app(MarketingOptimization::class)->film($run->project_id,$run->usage['owner_id'],$run->usage['film_id']);
   $selected=$this->select($base,$run->usage['answer']['recommendations']);
   if(!$selected){$this->state($run,['status'=>'unchanged','selected'=>[],'error'=>null]);return;}
   DB::transaction(function()use($run,$click,$base,$selected){
    $new=app(MarketingOptimization::class)->adopt($run,$click['adoption_id'],$selected,false);abort_if($new->id===$base->id,409);
    $new=MarketingVideoFilm::lockForUpdate()->findOrFail($new->id);$result=$new->result??[];$result['ai_optimization']=['run_id'=>$run->id,'source_film_id'=>$base->id,'source_sha256'=>$base->output_sha256,'selected'=>$selected];$new->update(['result'=>$result]);$this->state($run,['status'=>'rendering','film_id'=>$new->id,'selected'=>$selected,'error'=>null]);
   });
  }catch(\Throwable $e){if(isset($run))$this->state($run,['status'=>'failed','error'=>'这次优化未完成，原版保留；没有自动重新分析。']);report($e);}finally{$lock->release();}
 }
 public function failed(string $id):void {$run=MarketingAiRun::find($id);if($run&&isset($run->usage['one_click'])&&!isset($run->usage['one_click']['film_id']))$this->state($run,['status'=>'failed','error'=>'优化任务已中断，原版保留；没有自动重新分析。']);}
 public function select(MarketingVideoFilm $base,array $recommendations):array {
  $plan=app(MarketingOptimizationPlan::class);$state=$plan->baseline($base);$initial=MarketingEditingDigest::hash($state);$rows=app(MarketingOptimizationEvidence::class)->rows($base);$project=MarketingProject::findOrFail($base->project_id);$selected=[];
  foreach($recommendations as $r){
   if(!($r['selectable']??false)||($r['confidence']??'')!=='high'||($r['needs_speech_optin']??false)||($r['action']??'keep')==='keep')continue;
   try{$candidate=$plan->validateState($plan->apply($state,[$r],[$r['id']]),$rows,$project);}catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){continue;}
   if(MarketingEditingDigest::hash($state)!==MarketingEditingDigest::hash($candidate)){$selected[]=$r['id'];$state=$candidate;}
  }
  return MarketingEditingDigest::hash($state)===$initial?[]:$selected;
 }
 public function present(MarketingAiRun $run):?array {
  $click=$run->usage['one_click']??null;if(!$click)return null;
  $out=['status'=>$click['status'],'film_id'=>$click['film_id']??null,'error'=>$click['error']??null,'url'=>null];
  if(!$out['film_id']&&in_array($out['status'],['queued','applying'])&&$run->updated_at->lt(now()->subMinutes(20))){$out['status']='failed';$out['error']='优化任务已中断，原版保留；没有自动重新分析。';}
  if($out['film_id']){$film=MarketingVideoFilm::where('project_id',$run->project_id)->where('owner_id',$run->usage['owner_id'])->findOrFail($out['film_id']);$out['status']=match($film->status){'completed'=>'completed','failed','unknown'=>'failed',default=>'rendering'};$out['error']=$film->error_message;$out['url']=route('marketing.workspace.show',['project'=>$film->project_id,'screen'=>'finished','film'=>$film->id]);}
  return $out;
 }
}
