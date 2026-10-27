<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingRevision,MarketingAiRun,MarketingAsset,CmsMediaAsset};
use Illuminate\Support\Facades\{DB,Storage,Cache};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Services\ManagedMediaIngest;
class MarketingMaterialImages
{
 public const CONTRACT='openai-story-frames-v2';
 public function project(string $id,int $actor):MarketingProject{return MarketingProject::where('owner_id',$actor)->findOrFail($id);}
 public function fingerprint(MarketingProject $p):string{
  $r=$p->currentRevision;
  abort_unless($r&&($r->context['director_review']['plan_digest']??'')===hash('sha256',json_encode($r->document['director_plan']??null,JSON_THROW_ON_ERROR)),422,'请先审阅并采用当前导演稿。');
  $package=app(MarketingMaterialPackage::class)->assertFresh($p,$r);
  abort_if($package['status']==='awaiting_inputs',422,'请先保存必要真实参考。');
  abort_if(($p->concepts[$p->selected_concept]['video_type']??'')==='presenter'&&!collect($package['requirements'])->contains(fn($n)=>in_array($n['kind'],['real_person','synthetic_person'])),422,'选定故事是人物口播，但当前分镜与素材计划没有安排人物。请先修正分镜并重新审阅导演稿，不能用商品照片替代主持人。');
  $parts=[$package['source_digest'],$package['requirements']];$t=$r->document['transition_plan']??null;if($t&&($r->context['transition_adoption']['plan_digest']??'')===hash('sha256',json_encode($t,JSON_THROW_ON_ERROR)))$parts[]=hash('sha256',json_encode($t,JSON_THROW_ON_ERROR));return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
 }
 public function latest(MarketingProject $p):?MarketingAiRun{return MarketingAiRun::where('project_id',$p->id)->where('action','material_images')->where('usage->provider','openai')->latest('created_at')->latest('id')->first();}
 public function quote(string $id,int $actor,int $version,string $request,string $feedback='',bool $automatic=false,float $spendStop=2.0):MarketingAiRun{
  $configuration=app(MarketingMaterialImageClient::class)->configuration();
  if($automatic){abort_unless($spendStop>=.6&&$spendStop<=100,422,'自动制备预算停止线应在US$0.60至100之间。');$configuration['auto_review']=app(MarketingMaterialAutoReview::class)->policy()+['spend_stop_usd'=>round($spendStop,2)];$configuration['spent_usd']=0;}
  return DB::transaction(function()use($id,$actor,$version,$request,$feedback,$configuration){
   $p=$this->project($id,$actor);$p=MarketingProject::whereKey($id)->lockForUpdate()->firstOrFail();
   $old=MarketingAiRun::where('project_id',$id)->where('request_id',$request)->first();if($old){abort_unless($old->action==='material_images'&&$old->feedback===$feedback,409);return $old;}
   abort_unless($p->version===$version&&!$p->busy_run_id,409,'项目已变化，请重新读取。');
   $last=$this->latest($p);$lastCurrent=$last&&($last->usage['design_version']??'')===MarketingMaterialImageDesign::VERSION;abort_if($lastCurrent&&in_array($last->status,['running','quoted'])&&($last->status==='running'||(now()->lt($last->usage['expires_at'])&&$this->fingerprint($p)===$last->usage['fingerprint'])),409,'请先处理现有素材报价或任务。');
   if($last)abort_if(collect($last->usage['outputs']??[])->contains(fn($o)=>in_array($o['status'],['unknown','submitting','submitted','qa_unknown','qa_submitting','qa_pending','repair_pending','ai_passed'])||($lastCurrent&&$o['status']==='review')),409,'请先检查或审阅上一批图片；结果未知时不能重复生成。');
   $fingerprint=$this->fingerprint($p);$r=$p->currentRevision;$pkg=app(MarketingMaterialPackage::class)->assertFresh($p,$r);
   $outputs=[];foreach($pkg['outputs'] as $output){if(($output['status']??'')==='approved')continue;
    $built=app(MarketingMaterialImageDesign::class)->build($p,$pkg,$output,$feedback);
    $outputs[]=['id'=>$output['id'],'scene'=>$output['scene_number'],'role'=>$output['role'],'prompt'=>$built['prompt'],'design'=>$built['design'],'postproduction'=>$built['postproduction'],'status'=>'planned','task_id'=>null,'asset_id'=>null];
   }
   abort_unless(count($outputs)>0,422,'所有图片已通过审阅。');
   $ratio=$p->concepts[$p->selected_concept]['ratio']??'9:16';abort_unless(in_array($ratio,['9:16','16:9','1:1','4:3','3:4','2:3','3:2']),422,'图片比例暂不支持。');
   $size=app(MarketingMaterialImageClient::class)->size($ratio);
   return MarketingAiRun::create(['project_id'=>$id,'request_id'=>$request,'action'=>'material_images','feedback'=>$feedback,'status'=>'quoted','reserved_usd'=>0,'usage'=>$configuration+['contract'=>self::CONTRACT,'design_version'=>MarketingMaterialImageDesign::VERSION,'fingerprint'=>$fingerprint,'source_revision'=>$r->id,'ratio'=>$ratio,'size'=>$size,'unit_usd'=>null,'total_usd'=>null,'rates_per_million'=>MarketingImagePricing::RATES,'pricing_url'=>'https://developers.openai.com/api/docs/pricing','pricing_checked_at'=>'2026-10-22','expires_at'=>now()->addMinutes(30)->toIso8601String(),'outputs'=>$outputs]]);
  });
 }
 private function configurationMatches(array $usage):bool{
  foreach(app(MarketingMaterialImageClient::class)->configuration() as $key=>$value)if(($usage[$key]??null)!==$value)return false;
  return true;
 }
 public function authorize(string $id,int $actor,string $run):MarketingAiRun{
  return DB::transaction(function()use($id,$actor,$run){$p=$this->project($id,$actor);$p=MarketingProject::whereKey($id)->lockForUpdate()->firstOrFail();$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);
   abort_unless(($r->usage['provider']??'')==='openai',422,'旧Nano Banana报价已停用；应使用AI Connect的GPT Image 2重新报价。');
   abort_unless(($r->usage['design_version']??'')===MarketingMaterialImageDesign::VERSION,409,'图片设计规则已更新，请按无字幕规则重新确认费用。');
   if($r->status!=='quoted')return $r;
   if(isset($r->usage['repair_context']))app(MarketingMaterialRepair::class)->assertCurrent($p,$r);
   abort_unless(now()->lt($r->usage['expires_at'])&&now()->lt('2026-10-27')&&$this->fingerprint($p)===$r->usage['fingerprint']&&!$p->busy_run_id,409,'报价过期或项目已变化，请重新核算。');
   abort_unless($this->configurationMatches($r->usage)&&($r->usage['contract']??'')===self::CONTRACT,409,'AI Connect图片模型或连接已变化，请重新核算。');
   $u=$r->usage;if(!empty($u['auto_review']))abort_unless(app(MarketingMaterialAutoReview::class)->compatible($u['auto_review']),409,'视觉质检连接已变化，请重新核算。');$u['authorized_by']=$actor;$u['authorized_at']=now()->toIso8601String();$r->update(['status'=>'running','usage'=>$u]);return $r;
  });
 }
 // One persisted claim per image; synchronous response from OpenAI, never an automatic resubmission.
 public function advance(string $id,int $actor,string $run):MarketingAiRun{
  @set_time_limit(330);$lock=Cache::lock('material-image-run:'.$run,360);abort_unless($lock->get(),409,'图片正在生成，请勿重复提交。');
  try{
   $p=$this->project($id,$actor);$r=MarketingAiRun::where('project_id',$id)->findOrFail($run);$u=$r->usage;
   abort_unless(($u['provider']??'')==='openai'&&($u['contract']??'')===self::CONTRACT,422,'旧图片批次已停止；请按AI Connect重新查看费用。');
   if(($u['design_version']??'')!==MarketingMaterialImageDesign::VERSION){$r->update(['status'=>'needs_attention','error'=>'旧图片设计可能包含字幕，已停止后续生成；请按无字幕规则重新确认费用。']);return $r;}
   if($r->status!=='running')return $r;
   abort_unless($this->fingerprint($p)===$u['fingerprint']&&$this->configurationMatches($u),409,'故事、参考或AI Connect设置已改变，后续生成已停止。');
   if(isset($u['repair_context']))app(MarketingMaterialRepair::class)->assertCurrent($p,$r);
   if(!empty($u['auto_review']))return app(MarketingMaterialAutoReview::class)->advance($p,$r);
   foreach($u['outputs'] as $i=>$out){
    if(in_array($out['status'],['review','approved','rejected','failed']))continue;
    if(in_array($out['status'],['submitting','unknown'])){$u['outputs'][$i]['status']='unknown';$r->update(['status'=>'needs_attention','usage'=>$u,'error'=>'上次生成结果未知；没有重复请求，请核对OpenAI用量。']);return $r;}
    $paths=[];$pkg=$p->currentRevision->document['material_package'];
    // Use exact role ordering from the saved frame design. No reference required for fictional content.
    foreach($out['design']['reference_roles'] as $role){$need=collect($pkg['requirements'])->firstWhere('id',$role['need_id']);$ref=$need['reference']??null;abort_unless($ref,409,'真实参考已改变。');
     if($ref['type']==='catalog_image')$paths[]=app(MarketingMaterialPackage::class)->catalogPath($p,$ref['index']);
     elseif($ref['type']==='project_asset')$paths[]=Storage::disk('local')->path($p->assets()->findOrFail($ref['id'])->path);
    }
    // Prefer this scene's opening frame for ending/extra images. It is a continuity aid, not the story source.
    $anchor=collect($u['outputs'])->first(fn($o)=>$o['scene']===$out['scene']&&$o['role']==='start_frame'&&!empty($o['asset_id']));
    if(!$anchor)$anchor=collect($u['outputs'])->first(fn($o)=>!empty($o['asset_id']));
    if($anchor)$paths[]=Storage::disk('local')->path($p->assets()->findOrFail($anchor['asset_id'])->path);
    abort_if(count($paths)>16,422,'真实参考超过模型上限。');
    $u['outputs'][$i]['status']='submitting';$r->update(['usage'=>$u]);
    try{
     $result=app(MarketingMaterialImageClient::class)->generate($out['prompt'],$u['size'],$paths);
     $u['outputs'][$i]['supplier_usage']=$result['usage'];$u['outputs'][$i]['actual_usd']=$result['actual_usd'];$u['outputs'][$i]['provider_request_id']=$result['request_id'];$r->update(['usage'=>$u]);
     $path='marketing/'.$id.'/generated-'.$r->id.'-'.$i.'.png';abort_unless(Storage::disk('local')->put($path,$result['bytes']),409);
     $asset=MarketingAsset::firstOrCreate(['project_id'=>$id,'path'=>$path],['name'=>'GPT Image 2 '.$out['id'],'mime'=>'image/png','size'=>strlen($result['bytes']),'authorized'=>true]);
     $u['outputs'][$i]=array_replace($u['outputs'][$i],['asset_id'=>$asset->id,'sha256'=>hash('sha256',$result['bytes']),'status'=>'review','supplier_usage'=>$result['usage'],'actual_usd'=>$result['actual_usd'],'provider_request_id'=>$result['request_id']]);
     $r->update(['status'=>'awaiting_review','usage'=>$u,'error'=>null]);return $r;
    }catch(\Throwable $e){
     $reason=$e instanceof \App\Services\AI\ProviderFailure?$e->reason:'unknown';$known=in_array($reason,['balance','authentication','quota','schema','endpoint']);$u['outputs'][$i]['status']=$known?'failed':'unknown';$u['outputs'][$i]['failure_reason']=$reason;
     $r->update(['status'=>'needs_attention','usage'=>$u,'error'=>match($reason){'balance'=>'OpenAI余额不足，请top up。没有自动重试。','authentication'=>'AI Connect连接授权失效，请检查OpenAI设置。','schema'=>'OpenAI拒绝了图片请求格式；未自动重试，请修复后重新核算。','quota'=>'OpenAI限流或额度不足，请检查账户。未自动重试。',default=>'生成结果或用量未能完整确认；请核对OpenAI用量，不会自动重试。'}]);return $r;
    }
   }
   $r->update(['status'=>'completed']);return $r;
  }finally{$lock->release();}
 }
 public function review(string $id,int $actor,string $run,string $output,bool $approve):void{
  DB::transaction(function()use($id,$actor,$run,$output,$approve){$p=$this->project($id,$actor);$p=MarketingProject::whereKey($id)->lockForUpdate()->firstOrFail();$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);$u=$r->usage;
   abort_if(!empty($u['auto_review']),422,'自动质检批次需要整套确认，不能逐张跳过质检。');
   abort_unless(($u['provider']??'')==='openai'&&($u['contract']??'')===self::CONTRACT,422,'旧模型图片不作为本次GPT Image 2生产素材采用。');
   abort_unless($this->fingerprint($p)===$u['fingerprint'],409,'素材来源已改变。');$i=collect($u['outputs'])->search(fn($o)=>$o['id']===$output);abort_if($i===false,404);$out=$u['outputs'][$i];if($out['status']===($approve?'approved':'rejected'))return;
   abort_if($approve&&($out['design']['version']??'')!==MarketingMaterialImageDesign::VERSION,422,'该图片使用旧版含字幕风险设计，不能批准为视频输入，请重新制备干净画面。');
   abort_unless($out['status']==='review',422,'请先等待图片生成完成。');$asset=$p->assets()->findOrFail($out['asset_id']);$path=Storage::disk('local')->path($asset->path);abort_unless(is_file($path)&&hash_file('sha256',$path)===$out['sha256'],422,'生成文件已失效。');
   $doc=$p->currentRevision->document;$pkg=$doc['material_package'];$j=collect($pkg['outputs'])->search(fn($o)=>$o['id']===$output);abort_if($j===false,409);
   $media=null;if($approve){$mime=$asset->mime;$ext=match($mime){'image/jpeg'=>'jpg','image/webp'=>'webp',default=>'png'};$media=app(ManagedMediaIngest::class)->ingest(new UploadedFile($path,'AI-'.$output.'.'.$ext,$mime,null,true),$actor);$media->update(['orphaned_at'=>null]);}
   $pkg['outputs'][$j]=array_replace($pkg['outputs'][$j],['status'=>$approve?'approved':'rejected','asset_id'=>$asset->id,'media_library_id'=>$media?->id,'generation_run_id'=>$r->id,'approved_at'=>$approve?now()->toIso8601String():null,'approved_by'=>$approve?$actor:null,'sha256'=>$out['sha256']]);$doc['material_package']=$pkg;
   $source=$p->currentRevision;$new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$doc,'context'=>$source->context,'parent_id'=>$source->id,'feedback'=>$approve?'已审阅生产图片，保存至Media；未授权视频制作':'图片需调整，原生成记录保留']);
   $p->update(['current_revision_id'=>$new->id,'confirmed_revision_id'=>null,'version'=>$p->version+1]);$u['outputs'][$i]['status']=$approve?'approved':'rejected';$remaining=collect($u['outputs'])->contains('status','planned');$rejected=collect($u['outputs'])->contains('status','rejected');$r->update(['usage'=>$u,'status'=>$rejected?'completed':($remaining?'running':'completed')]);
  });
 }
 public function approveBatch(string $id,int $actor,string $run):MarketingAiRun{
  return DB::transaction(function()use($id,$actor,$run){
   $this->project($id,$actor);$p=MarketingProject::whereKey($id)->lockForUpdate()->firstOrFail();$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);$u=$r->usage;
   abort_unless(!empty($u['auto_review']),422);
   if($r->status==='completed'&&collect($u['outputs'])->every(fn($o)=>$o['status']==='approved'))return $r;
   abort_unless($r->status==='awaiting_batch_review'&&$this->fingerprint($p)===$u['fingerprint'],409,'请先完成当前批次的视觉质检。');
   if(isset($u['repair_context']))app(MarketingMaterialRepair::class)->assertFinal($p,$r);
   foreach($u['outputs'] as $out){abort_unless($out['status']==='ai_passed'&&($out['qa']['verdict']??'')==='pass'&&($out['qa']['candidate_sha256']??'')===$out['sha256']&&($out['qa']['design_sha256']??'')===hash('sha256',json_encode($out['design'],JSON_THROW_ON_ERROR)),422,'存在未通过或已变化的质检图片。');}
   $doc=$p->currentRevision->document;$pkg=$doc['material_package'];$files=[];
   // Check the entire set before importing any member. All DB changes share this transaction.
   foreach($u['outputs'] as $i=>$out){$asset=$p->assets()->where('authorized',true)->findOrFail($out['asset_id']);$path=Storage::disk('local')->path($asset->path);abort_unless(is_file($path)&&hash_file('sha256',$path)===$out['sha256'],422,'生成文件已失效，整套未采用。');$j=collect($pkg['outputs'])->search(fn($o)=>$o['id']===$out['id']);abort_if($j===false,409);$files[]=[$i,$j,$asset,$path];}
   foreach($files as [$i,$j,$asset,$path]){$out=$u['outputs'][$i];$media=app(ManagedMediaIngest::class)->ingest(new UploadedFile($path,'AI-'.$out['id'].'.png',$asset->mime,null,true),$actor);$media->update(['orphaned_at'=>null]);$pkg['outputs'][$j]=array_replace($pkg['outputs'][$j],['status'=>'approved','asset_id'=>$asset->id,'media_library_id'=>$media->id,'generation_run_id'=>$r->id,'approved_at'=>now()->toIso8601String(),'approved_by'=>$actor,'sha256'=>$out['sha256']]);$u['outputs'][$i]['status']='approved';}
   if(isset($u['repair_context']))$r->update(['usage'=>$u]);
   $doc['material_package']=$pkg;$source=$p->currentRevision;$new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$doc,'context'=>$source->context,'parent_id'=>$source->id,'feedback'=>'已整套确认AI质检素材并保存至Media；未授权视频制作']);if(isset($u['repair_context']))app(MarketingMaterialRepair::class)->refreshRevision($p,$new,$r);$p->update(['current_revision_id'=>$new->id,'confirmed_revision_id'=>null,'version'=>$p->version+1]);$u['batch_approved_by']=$actor;$u['batch_approved_at']=now()->toIso8601String();$r->update(['usage'=>$u,'status'=>'completed']);return $r;

  });
 }
 public function resumeBudget(string $id,int $actor,string $run,float $stop):MarketingAiRun{
  $lock=Cache::lock('material-image-run:'.$run,360);abort_unless($lock->get(),409);
  try{return DB::transaction(function()use($id,$actor,$run,$stop){$this->project($id,$actor);$p=MarketingProject::whereKey($id)->lockForUpdate()->firstOrFail();$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);$u=$r->usage;
   abort_unless($r->status==='needs_attention'&&($u['pause_reason']??'')==='budget'&&!($u['unknown_cost']??false)&&$this->fingerprint($p)===$u['fingerprint'],409,'该暂停不能通过提高预算恢复。');
   abort_unless($stop>($u['auto_review']['spend_stop_usd']??0)&&$stop<=100&&app(MarketingMaterialAutoReview::class)->compatible($u['auto_review']),422,'请输入更高且不超过US$100的预算停止线。');
   $u['budget_changes'][]=['by'=>$actor,'at'=>now()->toIso8601String(),'from'=>$u['auto_review']['spend_stop_usd'],'to'=>$stop];$u['auto_review']['spend_stop_usd']=$stop;unset($u['pause_reason']);$r->update(['usage'=>$u,'status'=>'running','error'=>null]);return $r;
  });}finally{$lock->release();}
 }
 public function abandon(string $id,int $actor,string $run):MarketingAiRun{
  $lock=Cache::lock('material-image-run:'.$run,360);abort_unless($lock->get(),409);
  try{return DB::transaction(function()use($id,$actor,$run){$this->project($id,$actor);$r=MarketingAiRun::where('project_id',$id)->lockForUpdate()->findOrFail($run);$u=$r->usage;
   abort_unless(!empty($u['auto_review'])&&(in_array($r->status,['needs_attention','awaiting_batch_review'])||(isset($u['repair_context'])&&$r->status==='quoted'))&&!($u['unknown_cost']??false)&&!collect($u['outputs'])->contains(fn($o)=>in_array($o['status'],['submitting','qa_submitting','unknown','qa_unknown'])),409,'存在结果或用量未知的请求，请先人工核对，不能发起新批次。');
   foreach($u['outputs'] as &$out){if($out['status']!=='approved'){$out['abandoned_status']=$out['status'];$out['status']='rejected';}}unset($out);$u['abandoned_by']=$actor;$u['abandoned_at']=now()->toIso8601String();$r->update(['usage'=>$u,'status'=>'completed','error'=>null]);return $r;
  });}finally{$lock->release();}
 }
}
