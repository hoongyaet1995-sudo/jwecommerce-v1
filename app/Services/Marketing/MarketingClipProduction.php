<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoClip,MarketingAiRun};
use App\Jobs\ProcessMarketingWorkspaceVideo;
use Illuminate\Support\Facades\{DB,Cache,Storage};
use Illuminate\Support\Str;

class MarketingClipProduction {
 public const ACTIVE=['upload_pending','uploading','submit_pending','submitting','external_pending','downloading','download_pending','render_pending','rendering'];
 public function __construct(private MarketingClipSource $source){}
 public function quote(string $id,int $owner,int $version,int $scene,string $request,?string $asset=null):MarketingVideoClip{
  return DB::transaction(function()use($id,$owner,$version,$scene,$request,$asset){$this->source->project($id,$owner);$p=app(MarketingWorkflow::class)->locked($id,$owner,$version);$built=$this->source->build($p,$scene,$asset);$existing=MarketingVideoClip::where('project_id',$id)->where('request_id',$request)->first();
   if($existing){abort_unless($existing->scene_number===$scene&&hash_equals($existing->fingerprint,$built['fingerprint']),409,'同一核价请求的内容已变化。');return $existing;}
   $reuse=MarketingVideoClip::where('project_id',$id)->where('fingerprint',$built['fingerprint'])->whereIn('status',array_merge(self::ACTIVE,['completed','unknown']))->latest('created_at')->first();if($reuse)return $reuse;
   abort_unless(now()->gte(MarketingEvolinkAdapter::CHECKED)&&now()->lt(MarketingEvolinkAdapter::VALID_UNTIL),409,'供应商公开费率需要重新核验。');$seconds=$built['input']['seconds'];
   return MarketingVideoClip::create(['project_id'=>$id,'owner_id'=>$owner,'revision_id'=>$p->current_revision_id,'request_id'=>$request,'scene_number'=>$scene,'fingerprint'=>$built['fingerprint'],'status'=>'quoted','input'=>$built['input'],'connection_fingerprint'=>$this->source->connection(),'quote'=>['credits'=>$seconds*13.5,'estimate_microusd'=>$seconds*199000,'pricing_kind'=>'public_estimate','checked_at'=>MarketingEvolinkAdapter::CHECKED,'rate_valid_until'=>MarketingEvolinkAdapter::VALID_UNTIL,'project_version'=>$version],'expires_at'=>now()->addMinutes(30)]);
  });
 }
 public function authorize(string $id,int $owner,int $version,string $clipId):MarketingVideoClip{
  return DB::transaction(function()use($id,$owner,$version,$clipId){$this->source->project($id,$owner);app(MarketingWorkflow::class)->locked($id,$owner,$version);$c=MarketingVideoClip::where('project_id',$id)->where('owner_id',$owner)->lockForUpdate()->findOrFail($clipId);$this->source->assertCurrent($c);
   if($c->authorized_at)return $c;
   abort_unless($c->status==='quoted'&&$c->expires_at->isFuture()&&now()->lt($c->quote['rate_valid_until'])&&hash_equals($c->connection_fingerprint,$this->source->connection()),409,'费用方案或连接已变化，请重新核价。');
   $same=MarketingVideoClip::where('project_id',$id)->where('fingerprint',$c->fingerprint)->where('id','!=',$c->id)->whereIn('status',array_merge(self::ACTIVE,['completed','unknown']))->first();abort_if($same,409,'相同输入已有任务，请查看已保存进度，避免重复收费。');
   $c->update(['authorized_at'=>now(),'status'=>'upload_pending','result'=>['authorization'=>['owner_id'=>$owner,'at'=>now()->toIso8601String(),'scope'=>'this_scene_native_video_and_local_subtitles','fingerprint'=>$c->fingerprint],'publication_authorized'=>false]]);ProcessMarketingWorkspaceVideo::dispatch('clip',$c->id);return $c->fresh();
  });
 }
 private function checkpoint(MarketingVideoClip $c,array $values):void{$c->update($values);}
 /** One checkpoint per job. Persist claim BEFORE any upload or generation POST. */
 public function advance(string $id):bool{
  $lock=Cache::lock('workspace-clip:'.$id,1000);if(!$lock->get())return false;
  try{$c=MarketingVideoClip::findOrFail($id);if(!in_array($c->status,self::ACTIVE,true))return false;$this->source->project($c->project_id,$c->owner_id);$result=$c->result??[];
   if(in_array($c->status,['uploading','submitting','downloading','rendering'],true)){$c->update(['status'=>'unknown','error_code'=>'interrupted_checkpoint','error_message'=>'上次操作结果尚未确认；已保存记录，未重复提交。']);return false;}
   if(in_array($c->status,['upload_pending','submit_pending'],true)){
    $p=$this->source->assertCurrent($c);abort_unless($c->authorized_at&&$c->expires_at->isFuture()&&now()->lt($c->quote['rate_valid_until'])&&hash_equals($c->connection_fingerprint,$this->source->connection()),409,'制作连接、费用方案或授权已变化。');
    if($c->status==='upload_pending'){$result['credits_before']=app(MarketingClipProvider::class)->credits($c->quote['credits']);$this->checkpoint($c,['status'=>'uploading','result'=>$result]);$file=app(MarketingClipProvider::class)->upload($this->source->path($p,$c->input['image']['asset_id'],$c->input['image']['sha256']));$result['upload']=$file;$c->update(['status'=>'submit_pending','result'=>$result]);return true;}
    abort_unless(now()->addMinutes(2)->lt($result['upload']['expires_at']),409,'临时首帧链接已过期，尚未提交视频生成。');
    abort_if(isset($result['submission_claimed']),409,'生成请求已有记录，不会重复提交。');app(MarketingClipProvider::class)->credits($c->quote['credits']);$result['submission_claimed']=now()->toIso8601String();$c->update(['status'=>'submitting','result'=>$result]);
    $payload=$c->input['payload']+['image_urls'=>[$result['upload']['file_url']]];$submitted=app(MarketingEvolinkClient::class)->submit($payload);$result['provider_usage']=$submitted['usage'];$c->update(['status'=>'external_pending','provider_task_id'=>$submitted['id'],'result'=>$result]);return true;
   }
   if($c->status==='external_pending'){
    abort_unless($c->provider_task_id,409,'没有可查询的供应商任务号，不能重新生成。');$result['polls']=($result['polls']??0)+1;
    if($result['polls']>90){$c->update(['status'=>'unknown','result'=>$result,'error_code'=>'status_wait_limit','error_message'=>'供应商任务仍未完成；保留任务号，可稍后重新查询，不会重复生成。']);return false;}
    $data=app(MarketingEvolinkClient::class)->query($c->provider_task_id);$result['provider_usage']=array_replace($result['provider_usage']??[],$data['usage']);$result['last_provider_status']=$data['status'];
    if(in_array($data['status'],['failed','cancelled'],true)){$c->update(['status'=>'failed','result'=>$result,'error_code'=>'provider_failed','error_message'=>'供应商返回生成失败，原素材与任务记录保留；没有自动重试收费生成。']);return false;}
    if($data['status']!=='completed'){$c->update(['result'=>$result]);return true;}
    abort_unless(is_array($data['results'])&&count($data['results'])===1&&is_string($data['results'][0]),422,'供应商结果缺少唯一视频文件。');$result['provider_results']=$data['results'];$c->update(['status'=>'download_pending','result'=>$result]);return true;
   }
   if($c->status==='download_pending'){$c->update(['status'=>'downloading']);$media=app(MarketingVideoFiles::class)->download($result['provider_results'][0],'marketing-clips/'.$c->id.'/raw.mp4',$c->input['seconds'],$c->input['payload']['generate_audio']);$result['raw_media']=$media;$c->update(['status'=>'render_pending','raw_path'=>$media['path'],'raw_sha256'=>$media['sha256'],'result'=>$result]);return true;}
   if($c->status==='render_pending'){$c->update(['status'=>'rendering']);$media=app(MarketingVideoCompositor::class)->clip($c);$result['render']=$media;$c->update(['status'=>'completed','output_path'=>$media['path'],'output_sha256'=>$media['sha256'],'result'=>$result,'error_code'=>null,'error_message'=>null]);return false;}
   return false;
  }catch(\Throwable $e){
   if(isset($c)){
    $c->refresh();$reason=$e instanceof MarketingProductionFailure?$e->reason:'source_or_media_changed';
    $rejected=$e instanceof MarketingProductionFailure&&$reason==='native_request_rejected'&&$e->state!=='unknown';
    $unknown=!$rejected&&(($e instanceof MarketingProductionFailure&&$e->state==='unknown')||in_array($c->status,['uploading','submitting'],true));
    $message=match($reason){
     'balance_topup'=>'Evolink余额不足，请top up。未重新提交生成。',
     'connection_rejected'=>'Evolink连接被拒绝，请检查连接；没有重试。',
     'native_request_rejected'=>'供应商明确拒绝本次请求，未取得视频任务。请检查输入与连接，再重新核价确认；没有自动重发。',
     default=>in_array($c->status,['rendering','downloading'],true)?'本地处理未完成，已有供应商视频保留；可只重试本地处理，不会再次收费生成。':($unknown?'请求结果暂时未知，已保留记录。请重新查询已知任务；不会再次收费生成。':'当前输入、授权或供应商检查未通过，尚未继续生成；请核对来源和连接。'),
    };
    $c->update(['status'=>$unknown?'unknown':'failed','error_code'=>$reason,'error_message'=>$message]);
   }
   return false;
  }finally{$lock->release();}
 }
 public function retry(string $project,int $owner,string $id):void{
  $this->source->project($project,$owner);$lock=Cache::lock('workspace-clip:'.$id,1000);abort_unless($lock->get(),409,'任务仍在处理。');
  try{$c=MarketingVideoClip::where('project_id',$project)->where('owner_id',$owner)->findOrFail($id);abort_unless(in_array($c->status,['failed','unknown']),409,'当前任务不需要恢复。');$r=$c->result??[];
   if($c->raw_path){app(MarketingVideoFiles::class)->file($c->raw_path,$c->raw_sha256);$status='render_pending';}
   elseif(!empty($r['provider_results']))$status='download_pending';
   elseif($c->provider_task_id){$status='external_pending';$r['polls']=0;}
   else abort(409,'没有已知视频任务，不能自动重发收费生成。请先核对供应商记录。');
   $c->update(['status'=>$status,'result'=>$r,'error_code'=>null,'error_message'=>null]);ProcessMarketingWorkspaceVideo::dispatch('clip',$c->id);
  }finally{$lock->release();}
 }
 public function completed(MarketingProject $p,int $scene,?string $asset=null):?MarketingVideoClip{
  $s=$this->source->build($p,$scene,$asset);return MarketingVideoClip::where('project_id',$p->id)->where('fingerprint',$s['fingerprint'])->where('status','completed')->latest('created_at')->first();
 }
 public function importPilot(string $project,int $owner,int $version,string $runId):MarketingVideoClip{
  return DB::transaction(function()use($project,$owner,$version,$runId){$this->source->project($project,$owner);$p=app(MarketingWorkflow::class)->locked($project,$owner,$version);$run=MarketingAiRun::where('project_id',$project)->where('action','single_clip_pilot')->where('status','completed')->findOrFail($runId);$u=$run->usage;$r=$p->currentRevision;
   abort_unless(($u['profile']??'')==='operator-single-shot-probe-v1'&&($u['source_revision']??null)===$r->id&&($u['source_digest']??null)===hash('sha256',json_encode([$r->id,$r->document,$p->brief,$p->preferences],JSON_THROW_ON_ERROR)),409,'小样来源已变化，不能直接关联到当前分镜。');
   $built=$this->source->build($p,$u['scene_number'],$u['source_image']['asset_id']);$input=$built['input'];abort_unless(($u['source_image']['sha256']??'')===$input['image']['sha256']&&($u['spoken_text']??null)===$input['spoken_text']&&($u['payload']['model']??'')===MarketingClipSource::MODEL&&($u['payload']['duration']??null)===$input['seconds']&&($u['payload']['aspect_ratio']??null)==='9:16'&&($u['payload']['quality']??null)==='720p'&&($u['payload']['generate_audio']??null)===$input['payload']['generate_audio']&&($u['acceptance']['speech_matches_ignoring_punctuation']??false),409,'小样与当前片段的素材、对白或生成路线不一致。');
   $existing=MarketingVideoClip::where('project_id',$project)->where('origin_run_id',$runId)->first();if($existing)return $existing;
   abort_if(MarketingVideoClip::where('project_id',$project)->where('fingerprint',$built['fingerprint'])->whereIn('status',array_merge(self::ACTIVE,['completed','unknown']))->exists(),409,'本段已有任务或成片，请使用已有记录。');
   $raw=realpath($u['media']['path']??'');$roots=[realpath(base_path('work')),realpath(Storage::disk('local')->path(''))];$safe=false;foreach($roots as $root)if($root&&$raw&&str_starts_with(strtolower($raw),strtolower($root.DIRECTORY_SEPARATOR)))$safe=true;
   abort_unless($safe&&is_file($raw)&&hash_file('sha256',$raw)===($u['media']['sha256']??null),409,'小样原片校验失败，未关联。');$meta=app(MarketingVideoFiles::class)->verify($raw,$input['seconds'],$input['payload']['generate_audio']);
   $clip=MarketingVideoClip::create(['project_id'=>$project,'owner_id'=>$owner,'revision_id'=>$r->id,'request_id'=>(string)Str::uuid(),'scene_number'=>$input['scene_number'],'fingerprint'=>$built['fingerprint'],'status'=>'render_pending','input'=>$input,'quote'=>['estimate_microusd'=>0,'credits'=>0,'pricing_kind'=>'reuse_existing_video','historical_credits'=>$u['provider_result']['usage']['credits_used']??null],'connection_fingerprint'=>$this->source->connection(),'origin_run_id'=>$runId,'provider_task_id'=>$u['provider_task_id']??null,'authorized_at'=>now(),'expires_at'=>now()->addMinutes(30),'result'=>['imported_from'=>$runId,'provider_usage'=>$u['provider_result']['usage']??[],'new_generation_requests'=>0,'publication_authorized'=>false]]);
   $path='marketing-clips/'.$clip->id.'/raw.mp4';Storage::disk('local')->makeDirectory(dirname($path));abort_unless(copy($raw,Storage::disk('local')->path($path))&&hash_file('sha256',Storage::disk('local')->path($path))===$u['media']['sha256'],409,'原片收纳未完成。');$clip->update(['raw_path'=>$path,'raw_sha256'=>$u['media']['sha256'],'result'=>$clip->result+['raw_media'=>$meta]]);ProcessMarketingWorkspaceVideo::dispatch('clip',$clip->id);return $clip->fresh();
  });
 }
}
