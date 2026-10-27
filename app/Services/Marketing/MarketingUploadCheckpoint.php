<?php
namespace App\Services\Marketing;
use App\Models\{MarketingAsset,MarketingProductionPlan,MarketingUploadCheckpoint as Record};
use Illuminate\Support\Facades\{DB,Http,Storage};

/** One explicit click = at most one POST. Claims survive crashes; unknown costs never retry. */
class MarketingUploadCheckpoint {
 public function grant():array {
  $g=config('marketing.production.upload_checkpoint',[]);
  if(!$g&&app()->environment('local')){$path=storage_path('app/private/marketing/upload-checkpoint-grant.json');$g=is_file($path)?json_decode(file_get_contents($path),true):[];}
  return is_array($g)?$g:[];
 }
 public function visible(string $project):bool {$g=$this->grant();return ($g['project']??'')===$project&&isset($g['expires_at'])&&now()->lt($g['expires_at']);}
 private function balance():array {
  $r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/credits');
  abort_unless($r->successful(),409,'积分查询失败，已暂停，不会重试上传。');$d=$r->json('data');
  foreach([$d['user']['remaining_credits']??null,$d['user']['used_credits']??null,$d['token']['used_credits']??null] as $v)abort_unless(is_numeric($v)&&is_finite((float)$v),409,'积分证据不完整，已暂停。');return $d;
 }
 public function uploadOne(string $project,int $owner,string $planId,string $role='start_frame'):array {
  app(MarketingStoryboardWorkspace::class)->project($project,$owner);$g=$this->grant();
  abort_unless($this->visible($project)&&in_array($role,['start_frame','end_frame'],true)&&($g['max_posts']??0)===3&&($g['cap_microusd']??0)===1500000&&($g['credit_usd']??0)>0,423,'单张上传范围或预算尚未批准。');
  $plan=MarketingProductionPlan::where('project_id',$project)->where('prepared_by',$owner)->findOrFail($planId);$s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$owner);
  abort_unless($s['source_fresh']&&$s['story_version']===($plan->manifest['story_version']??null),409,'导演稿版本已变化，请重新交接。');
  $h=app(MarketingStoryboardHandoff::class)->build($project,$owner,$s['story_id']);abort_unless($h===($plan->manifest['handoff']??null)&&($plan->manifest['scope']['scene_ids']??[])===[$g['scene']],409,'上传范围与当前交接不一致。');
  $clip=$plan->manifest['clips'][0];$ref=$clip['frames'][$role]['asset']??null;abort_unless($ref&&($clip['scene_id']??'')===$g['scene'],422,'该帧尚未准备。');
  $connection=app(MarketingEvolinkAdapter::class)->connectionFingerprint();$claim=hash('sha256',json_encode([$project,$g['reference'],$g['scene'],$role,$ref['asset_id'],$ref['sha256'],$connection]));
  $old=Record::where('claim',$claim)->first();if($old)return $this->state($old);
  $asset=MarketingAsset::where('project_id',$project)->where('authorized',true)->findOrFail($ref['asset_id']);$path=Storage::disk('local')->path($asset->path);abort_unless(is_file($path)&&hash_file('sha256',$path)===$ref['sha256'],409,'图片内容已改变。');
  $before=$this->balance();abort_unless((float)$before['user']['remaining_credits']>=81,423,'积分不足以预留本段视频，未上传。');
  $record=DB::transaction(function()use($project,$owner,$plan,$g,$clip,$role,$claim,$connection,$ref,$before){
   app(MarketingStoryboardWorkspace::class)->project($project,$owner,true);$old=Record::where('claim',$claim)->first();if($old)return $old;
   $rows=Record::where('project_id',$project)->where('grant_reference',$g['reference'])->get();abort_if($rows->contains(fn($r)=>$r->status!=='measured'),409,'已有上传结果或费用待核对，不继续调用。');
   abort_unless($rows->count()<2&&$rows->sum('actual_microusd')+(int)ceil(81*$g['credit_usd']*1000000)<=$g['cap_microusd'],423,'调用次数或剩余预算不足。');
   if($role==='end_frame')abort_unless($rows->contains(fn($r)=>$r->role==='start_frame'&&$r->status==='measured'),409,'请先完成首帧上传费用核对。');
   return Record::create(['project_id'=>$project,'plan_id'=>$plan->id,'scene_id'=>$clip['scene_id'],'role'=>$role,'claim'=>$claim,'grant_reference'=>$g['reference'],'connection'=>$connection,'sha256'=>$ref['sha256'],'asset_id'=>$ref['asset_id'],'status'=>'claimed','evidence'=>['before'=>$before,'claimed_at'=>now()->toIso8601String()]]);
  });
  // Only the process that created this durable claim may transmit it.
  if(!$record->wasRecentlyCreated)return $this->state($record);
  try {
   $upload=app(MarketingClipProvider::class)->upload($path);$e=$record->evidence;$e['upload']=$upload;$record->update(['evidence'=>$e,'status'=>'uploaded']);
   $after=$this->balance();$e['after']=$after;$e['checked_at']=now()->toIso8601String();
   $delta=(float)$before['user']['remaining_credits']-(float)$after['user']['remaining_credits'];$used=(float)$after['user']['used_credits']-(float)$before['user']['used_credits'];$token=(float)$after['token']['used_credits']-(float)$before['token']['used_credits'];
   $known=$delta>=0&&abs($delta-$used)<0.00001&&abs($delta-$token)<0.00001;$cost=$known?(int)ceil(round($delta,6)*$g['credit_usd']*1000000):null;$e['observed_credits']=$known?round($delta,6):null;
   $record->update(['evidence'=>$e,'status'=>$known?'measured':'unknown','actual_microusd'=>$cost]);
   if($known&&($cost+Record::where('project_id',$project)->where('grant_reference',$g['reference'])->whereKeyNot($record->id)->sum('actual_microusd')+(int)ceil(81*$g['credit_usd']*1000000)>$g['cap_microusd']))$record->update(['status'=>'over_budget']);
  }catch(\Throwable $err){$e=$record->fresh()->evidence;$e['error']='上传或积分核对未完成；结果保留，不自动重试。';$record->update(['evidence'=>$e,'status'=>'unknown']);}
  return $this->state($record->fresh());
 }
 public function state(Record $r):array{return ['id'=>$r->id,'role'=>$r->role,'status'=>$r->status,'actual_microusd'=>$r->actual_microusd,'before'=>$r->evidence['before']??null,'after'=>$r->evidence['after']??null,'observed_credits'=>$r->evidence['observed_credits']??null,'upload'=>$r->evidence['upload']??null,'message'=>$r->status==='measured'?'本张已上传并暂停；已记录积分变化，不会自动继续上传或生成。':'上传结果或费用待核对，已暂停，不会重试。'];}
 public function reuse(string $project,string $scene,string $role,string $sha):?array {
  $connection=app(MarketingEvolinkAdapter::class)->connectionFingerprint();$r=Record::where('project_id',$project)->where('scene_id',$scene)->where('role',$role)->where('sha256',$sha)->where('connection',$connection)->where('status','measured')->latest()->first();$u=$r?($r->evidence['upload']??null):null;
  if(!$r){$call=\App\Models\MarketingStoryboardProviderCall::where('project_id',$project)->where('role','upload')->where('status','completed')->where('input->scene_id',$scene)->where('input->frame_role',$role)->where('input->image_sha256',$sha)->where('input->connection',$connection)->latest()->first();if(!$call||!($call->result['measured_upload']??false))return null;$u=$call->result;}
  abort_unless($u&&now()->addMinutes(20)->lt($u['expires_at']),409,'已上传链接过期，不会自动重传。');return $u;
 }
 public function activateVideo(array $h):bool {
  if(app(MarketingStoryboardBatchApproval::class)->activate($h))return true;
  if(app(MarketingEconomyTrialGrant::class)->activate($h))return true;
  $g=$this->grant();$scenes=$h['scene_map']??[];
  if(!$this->visible($g['project']??'')||count($scenes)!==1||($scenes[0]['scene_id']??'')!==($g['scene']??null)||($scenes[0]['seconds']??0)!==6||($h['profile']['voice']['mode']??'auto')!=='auto')return false;
  $rows=Record::where('project_id',$g['project'])->where('grant_reference',$g['reference'])->get();if($rows->count()!==2||$rows->contains(fn($r)=>$r->status!=='measured'))return false;
  foreach(['start_frame','end_frame'] as $role){$ref=$scenes[0]['frames'][$role]['asset']??null;if(!$ref||!$this->reuse($g['project'],$g['scene'],$role,$ref['sha256']))return false;}
  $spent=$rows->sum('actual_microusd');$cap=(int)ceil(81*$g['credit_usd']*1000000);if($spent+$cap>$g['cap_microusd'])return false;
  $version='scene-trial-upload-checkpoint-v2';$video=['model'=>'seedance-2.0-image-to-video','version'=>$version,'cap_microusd'=>$cap,'credit_usd'=>$g['credit_usd'],'access_verified_at'=>'2026-10-23','pricing_verified_at'=>'2026-10-23','pricing_valid_until'=>$g['expires_at'],'native_trial_grant'=>$g['reference']];
  config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['mode'=>'scoped_upload_trial','reference'=>$g['reference'],'project_id'=>$g['project'],'owner_id'=>\App\Models\MarketingProject::findOrFail($g['project'])->owner_id,'roles'=>['upload','video'],'expires_at'=>$g['expires_at'],'max_total_microusd'=>$g['cap_microusd']-$spent,'max_calls'=>1],
   'marketing.storyboard.roles.video'=>$video,'marketing.storyboard.video_models'=>[],
   'marketing.storyboard.roles.upload'=>['model'=>'evolink-file-reuse','version'=>$version,'cap_microusd'=>1,'fee_microusd'=>0,'pricing_verified_at'=>'observed-upload-checkpoints','reuse_only'=>true],
   'marketing.storyboard.audio.native'=>['model'=>$video['model'],'version'=>$version,'verified_at'=>'2026-10-23-native-audio-api-docs','languages'=>[$h['source_context']['language']??$h['source_context']['brief']['language']??'English'],'exact_script'=>false,'lip_sync'=>false,'scoped_trial'=>$g['reference']]]);
  return true;
 }
 public function activateGrant(string $project):void {
  if(!$this->visible($project))return;$g=$this->grant();$p=\App\Models\MarketingProject::findOrFail($project);$s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$p->owner_id);$h=app(MarketingStoryboardHandoff::class)->build($project,$p->owner_id,$s['story_id']);$h=app(MarketingProductionScope::class)->select($h,$g['scene']);$this->activateVideo($h);
 }
}
