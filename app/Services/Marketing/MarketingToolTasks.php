<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;
use App\Jobs\ProcessMarketingWorkspaceVideo;
use Illuminate\Support\Facades\{DB,Cache,Log};
use Illuminate\Support\Str;

/** Unified local task envelope backed by the existing owner-scoped film, queue and frozen recipe. */
class MarketingToolTasks {
 public const VERSION='local-film-tool-task-v1';
 private function sourceDigest(MarketingVideoFilm $f):string{return MarketingEditingDigest::hash(['film_id'=>$f->id,'fingerprint'=>$f->fingerprint,'input'=>$f->input]);}
 private function adapter(string $capability):\App\Services\Marketing\Contracts\MarketingToolAdapter{
  $cap=app(MarketingToolCapabilities::class)->requireExecutable($capability);$class=$cap['adapter'];abort_unless(is_string($class)&&is_subclass_of($class,\App\Services\Marketing\Contracts\MarketingToolAdapter::class),423,'工具适配器尚未验证。');return app($class);
 }
 private function spec(MarketingVideoFilm $f):array{
  $registry=app(MarketingToolCapabilities::class);$cap=$registry->filmCapability($f);$contract=$registry->requireExecutable($cap);
  return ['version'=>self::VERSION,'id'=>'film:'.$f->id,'film_id'=>$f->id,'subject'=>['type'=>'film','id'=>$f->id,'project_id'=>$f->project_id,'owner_id'=>$f->owner_id],'capability'=>$cap,'capability_version'=>$contract['version'],'adapter_version'=>$contract['adapter_version'],'operations'=>$registry->operations($f),'source_digest'=>$this->sourceDigest($f),'cost'=>$contract['cost'],'quality_verified'=>false];
 }
 private function store(string $id,array $receipt):void{
  DB::transaction(function()use($id,$receipt){$locked=MarketingVideoFilm::lockForUpdate()->findOrFail($id);abort_unless(hash_equals($receipt['source_digest'],$this->sourceDigest($locked))||(($receipt['state']??'')==='failed'&&($locked->result['tool_task']['source_digest']??null)===$receipt['source_digest']),409,'工具任务来源已变化，不能覆盖旧版本。');$result=$locked->result??[];$result['tool_task']=$receipt;$locked->update(['result'=>$result]);});
 }
 public function describe(string $project,int $owner,string $id):array{
  $f=MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->findOrFail($id);$r=$f->result['tool_task']??null;
  $status=match($f->status){'rendering'=>'running',default=>$f->status};
  return ['id'=>'film:'.$f->id,'film_id'=>$f->id,'status'=>$status,'phase'=>$status==='completed'?'completed':(($r['state']??'')==='completed'?'finalizing':($r['phase']??$status)),'capability'=>$r['capability']??app(MarketingToolCapabilities::class)->filmCapability($f),'evidence'=>$r?'task_receipt':'legacy_record','attempts'=>$r['attempts']??null,'cost'=>$r['cost']??app(MarketingToolCapabilities::class)->catalog()[app(MarketingToolCapabilities::class)->filmCapability($f)]['cost'],'output_sha256'=>$f->status==='completed'?$f->output_sha256:null,'error_reference'=>$r['error_reference']??null,'quality_verified'=>false];
 }
 /** Called only after existing film prepare/retry validated scope; never creates another film or paid request. */
 public function enqueue(MarketingVideoFilm $film,bool $explicitLocalRetry=false):void{
  DB::transaction(function()use($film,$explicitLocalRetry){$f=MarketingVideoFilm::lockForUpdate()->findOrFail($film->id);abort_unless($f->status==='queued',409,'仅已确认且排队的本地成片任务可执行。');$spec=$this->spec($f);$old=$f->result['tool_task']??null;
   if($old){abort_unless(hash_equals($old['source_digest'],$spec['source_digest']),409,'工具任务来源已变化，旧结果保留。');if(!$explicitLocalRetry&&in_array($old['state'],['queued','running','completed'],true))return;}
   $this->store($f->id,$spec+['state'=>'queued','phase'=>'queued','attempts'=>$old['attempts']??0,'queued_at'=>now()->toIso8601String(),'automatic_retry'=>false]);ProcessMarketingWorkspaceVideo::dispatch('film',$f->id);
  });$film->refresh();
 }
 public function execute(MarketingVideoFilm $film):array{
  $lock=Cache::lock('marketing-tool-task:'.$film->id,1100);abort_unless($lock->get(),409,'本地工具任务仍在处理，不会重复执行。');
  try{
   $film->refresh();abort_unless($film->status==='rendering',409,'本地工具只能执行已承接的成片任务。');app(MarketingWorkspaceFilm::class)->assertCurrent($film);$spec=$this->spec($film);$old=$film->result['tool_task']??null;
   if($old){abort_unless(hash_equals($old['source_digest'],$spec['source_digest'])&&$old['capability']===$spec['capability'],409,'工具任务来源或能力已变化，旧结果保留。');if($old['state']==='completed'){try{$this->adapter($spec['capability'])->verify($old['output']);}catch(\Throwable $e){$this->store($film->id,array_replace($old,['state'=>'failed','phase'=>'failed','failed_phase'=>'reuse_validation','error_code'=>'output_verification_failed','error_reference'=>(string)Str::uuid(),'automatic_retry'=>false]));throw $e;}return $old['output'];}}
   $r=$spec+['tool_identity'=>app(MarketingToolCapabilities::class)->executionIdentity(),'state'=>'running','phase'=>'render','attempts'=>($old['attempts']??0)+1,'started_at'=>now()->toIso8601String(),'automatic_retry'=>false];$this->store($film->id,$r);
   try{
    $adapter=$this->adapter($spec['capability']);$output=$adapter->execute($r);$r['phase']='validate';$this->store($film->id,$r);$adapter->verify($output);app(MarketingWorkspaceFilm::class)->assertCurrent($film);
    $r=array_replace($r,['state'=>'completed','phase'=>'completed','completed_at'=>now()->toIso8601String(),'output'=>$output]);$this->store($film->id,$r);$film->refresh();return $output;
   }catch(\Throwable $e){$reference=(string)Str::uuid();$this->store($film->id,array_replace($r,['state'=>'failed','phase'=>'failed','failed_phase'=>$r['phase'],'failed_at'=>now()->toIso8601String(),'error_code'=>'local_tool_failed','error_reference'=>$reference]));Log::warning('Local marketing tool task failed',['film_id'=>$film->id,'reference'=>$reference,'exception'=>get_class($e)]);throw $e;}
  }finally{$lock->release();}
 }
}
