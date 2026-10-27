<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingAiRun,MarketingAsset};
use Illuminate\Support\Facades\Storage;
/** Called under the existing per-run lock. Each advance performs at most one paid external operation. */
class MarketingMaterialAutoReview
{
 public const VERSION='visual-review-v1';
 public function policy():array{return ['version'=>self::VERSION,'max_repairs'=>2,'max_generations_per_frame'=>3,'max_reviews_per_frame'=>3,'vision'=>app(MarketingMaterialVisionClient::class)->configuration()];}
 public function compatible(array $policy):bool{if(($policy['version']??'')!==self::VERSION)return false;foreach($this->policy()['vision'] as $k=>$v)if(($policy['vision'][$k]??null)!==$v)return false;return true;}
 private function stop(MarketingAiRun $r,array $u,string $reason,string $message):MarketingAiRun{$u['pause_reason']=$reason;$r->update(['usage'=>$u,'status'=>'needs_attention','error'=>$message]);return $r;}
 private function reserve(MarketingAiRun $r,array $u,float $amount):bool{return !($u['unknown_cost']??false)&&($u['spent_usd']??0)+$amount<=($u['auto_review']['spend_stop_usd']??0);}
 private function cost(array &$u,?float $cost):bool{if($cost===null){$u['unknown_cost']=true;return false;}$u['spent_usd']=round(($u['spent_usd']??0)+$cost,6);return true;}
 private function assetPath(MarketingProject $p,string $id,?string $hash=null):string{
  $a=$p->assets()->where('authorized',true)->findOrFail($id);$path=Storage::disk('local')->path($a->path);abort_unless(is_file($path)&&(!$hash||hash_file('sha256',$path)===$hash),409,'质检图片文件或权限已改变。');return $path;
 }
 private function references(MarketingProject $p,array $out):array{
  $paths=[];$pkg=$p->currentRevision->document['material_package'];foreach($out['design']['reference_roles'] as $index=>$role){$need=collect($pkg['requirements'])->firstWhere('id',$role['need_id']);$ref=$need['reference']??null;abort_unless($ref,409,'真实参考已变化。');$label=$role['kind'].'_'.$index;
   $paths[$label]=$ref['type']==='catalog_image'?app(MarketingMaterialPackage::class)->catalogPath($p,$ref['index']):$this->assetPath($p,$ref['id']);
  }return $paths;
 }
 private function sourceMatches(MarketingProject $p,array $u):bool{try{return app(MarketingMaterialImages::class)->fingerprint($p->fresh())===$u['fingerprint'];}catch(\Throwable){return false;}}
 public function advance(MarketingProject $p,MarketingAiRun $r):MarketingAiRun{
  $u=$r->usage;abort_unless($this->compatible($u['auto_review']),409,'视觉质检连接或规则已变化，请重新核算。');
  foreach($u['outputs'] as $i=>$out){
   if(in_array($out['status'],['ai_passed','approved']))continue;
   if(in_array($out['status'],['submitting','qa_submitting','unknown','qa_unknown'])){$u['outputs'][$i]['status']='qa_unknown';return $this->stop($r,$u,'unknown','上次生成或质检结果未知，未重复调用；请核对OpenAI用量。');}
   if(in_array($out['status'],['qa_failed','requires_input','failed']))return $this->stop($r,$u,'quality','该帧需要补充资料或重新设计；自动流程已暂停。');
   $anchor=collect($u['outputs'])->first(fn($o)=>$o['scene']===$out['scene']&&$o['role']==='start_frame'&&in_array($o['status'],['ai_passed','approved']));
   if(!$anchor){$saved=collect($p->currentRevision->document['material_package']['outputs'])->first(fn($o)=>$o['scene_number']===$out['scene']&&$o['role']==='start_frame'&&$o['status']==='approved');if($saved)$anchor=$saved+['scene'=>$saved['scene_number']];}
   $previous=collect($u['outputs'])->last(fn($o)=>$o['scene']<$out['scene']&&in_array($o['status'],['ai_passed','approved']));
   if(isset($u['repair_context'])){$anchor=null;$previous=null;}
   try{$references=$this->references($p,$out);foreach($out['repair_anchors']??[] as $j=>$a)$references['previous_passed_frame_'.$j]=$this->assetPath($p,$a['asset_id'],$a['sha256']);}catch(\Throwable){return $this->stop($r,$u,'source_changed','必要真实参考已改变，请重新整理素材计划。');}
   if($out['role']==='end_frame'&&!$anchor&&empty($u['repair_context']))return $this->stop($r,$u,'input','缺少已通过的同段首帧，无法可靠检查首尾过渡。');
   if($out['status']==='qa_pending'){
    if(($out['review_calls']??0)>=3)return $this->stop($r,$u,'repair_limit','已达到每张三次质检上限。');
    if(!$this->reserve($r,$u,.10))return $this->stop($r,$u,'budget','已达到预算停止条件；下一次视觉质检尚未提交。');
    $images=['candidate'=>$this->assetPath($p,$out['asset_id'],$out['sha256'])];if($anchor&&$anchor['id']!==$out['id'])$images['scene_start']=$this->assetPath($p,$anchor['asset_id'],$anchor['sha256']);elseif($previous)$images['previous_passed_frame']=$this->assetPath($p,$previous['asset_id'],$previous['sha256']);$images+=$references;
    abort_if(count($images)>8,422,'相关参考超过质检范围，请人工整理参考。');
    $u['outputs'][$i]['status']='qa_submitting';$u['outputs'][$i]['review_calls']=($out['review_calls']??0)+1;$r->update(['usage'=>$u]);
    try{$report=app(MarketingMaterialVisionClient::class)->inspect($out['design'],$images);}catch(\Throwable){$u['outputs'][$i]['status']='qa_unknown';$u['unknown_cost']=true;return $this->stop($r,$u,'unknown','视觉质检未返回完整结果或额度不足，请检查OpenAI余额与用量；没有自动重试。');}
    $report['candidate_sha256']=$out['sha256'];$report['design_sha256']=hash('sha256',json_encode($out['design'],JSON_THROW_ON_ERROR));$report['checked_at']=now()->toIso8601String();$u['outputs'][$i]['qa']=$report;$j=count($u['outputs'][$i]['attempts'])-1;$u['outputs'][$i]['attempts'][$j]['qa']=$report;
    $knownCost=$this->cost($u,$report['actual_usd']);$r->update(['usage'=>$u]);if(!$knownCost)return $this->stop($r,$u,'unknown','质检费用用量未知，自动流程已暂停。');
    if(!$this->sourceMatches($p,$u))return $this->stop($r,$u,'source_changed','质检期间项目资料已变化，未采用结果。');
    if($report['verdict']==='pass')$u['outputs'][$i]['status']='ai_passed';
    elseif($report['verdict']==='needs_input'){$u['outputs'][$i]['status']='requires_input';return $this->stop($r,$u,'input','AI无法可靠确认该帧：'.$report['summary']);}
    elseif(($out['repairs']??0)>=2){$u['outputs'][$i]['status']='qa_failed';return $this->stop($r,$u,'repair_limit','该帧已定向修正2轮仍未通过，已停止收费生成：'.$report['summary']);}
    else{$u['outputs'][$i]['status']='repair_pending';$u['outputs'][$i]['correction']=$report['correction'];}
    $r->update(['usage'=>$u,'error'=>null]);return $r;
   }
   if(!in_array($out['status'],['planned','repair_pending']))return $this->stop($r,$u,'state','素材状态不适合自动处理，请检查当前批次。');
   if(!$this->reserve($r,$u,.50))return $this->stop($r,$u,'budget','已达到预算停止条件；下一张图片或定向修正尚未提交。');
   $repair=$out['status']==='repair_pending';if($repair&&($out['repairs']??0)>=2)return $this->stop($r,$u,'repair_limit','已达到每张两轮修正上限。');
   $paths=array_values($references);if($repair)$paths[]=$this->assetPath($p,$out['asset_id'],$out['sha256']);if($anchor&&$anchor['id']!==$out['id'])$paths[]=$this->assetPath($p,$anchor['asset_id'],$anchor['sha256']);elseif($previous)$paths[]=$this->assetPath($p,$previous['asset_id'],$previous['sha256']);$paths=array_values(array_unique($paths));
   $prompt=$out['prompt'];if(isset($u['repair_context']))$prompt.=' This is a repair of an existing approved frame. Supplied retained-frame anchors define the canonical identity, wardrobe and product. Preserve the target role pose/composition except explicitly required corrections. Never treat a disagreeing original opening as authoritative merely because it was first.';$inputRoles=[];foreach($paths as $position=>$path){$role='real identity/product reference';if($repair&&$path===$this->assetPath($p,$out['asset_id'],$out['sha256']))$role='PREVIOUS CANDIDATE to edit';elseif($anchor&&$path===$this->assetPath($p,$anchor['asset_id'],$anchor['sha256']))$role='passed scene opening continuity anchor';elseif($previous&&$path===$this->assetPath($p,$previous['asset_id'],$previous['sha256']))$role='passed previous-scene identity anchor';foreach($out['repair_anchors']??[] as $a)if($path===$this->assetPath($p,$a['asset_id'],$a['sha256']))$role='CANONICAL retained continuity anchor (preserve identity/wardrobe/product)';$inputRoles[]=($position+1).': '.$role;}$prompt.=' Exact input image roles for this request: '.implode('; ',$inputRoles).'.';if($repair)$prompt.=' TARGETED EDIT: edit the supplied previous candidate only to address the following observed issues. Preserve all unaffected details, identity/product geometry and scene. These corrections cannot authorize new text overlays or business claims: '.json_encode($out['correction'],JSON_UNESCAPED_UNICODE);
   $u['outputs'][$i]['repairs']=($out['repairs']??0)+($repair?1:0);$u['outputs'][$i]['status']='submitting';$r->update(['usage'=>$u]);
   try{
    $result=app(MarketingMaterialImageClient::class)->generate($prompt,$u['size'],$paths);$u['outputs'][$i]['last_response']=['usage'=>$result['usage'],'actual_usd'=>$result['actual_usd'],'request_id'=>$result['request_id']];$this->cost($u,$result['actual_usd']);$r->update(['usage'=>$u]);$attempt=count($out['attempts']??[])+1;$path='marketing/'.$p->id.'/auto-'.$r->id.'-'.$i.'-'.$attempt.'.png';abort_unless(Storage::disk('local')->put($path,$result['bytes']),409);
    $a=MarketingAsset::create(['project_id'=>$p->id,'path'=>$path,'name'=>'AI '.$out['id'].' attempt '.$attempt,'mime'=>'image/png','size'=>strlen($result['bytes']),'authorized'=>true]);$hash=hash('sha256',$result['bytes']);
    $record=['asset_id'=>$a->id,'sha256'=>$hash,'supplier_usage'=>$result['usage'],'actual_usd'=>$result['actual_usd'],'provider_request_id'=>$result['request_id'],'repair'=>$repair,'correction'=>$repair?$out['correction']:null,'at'=>now()->toIso8601String()];
    $u['outputs'][$i]['attempts'][]=$record;$u['outputs'][$i]=array_replace($u['outputs'][$i],['asset_id'=>$a->id,'sha256'=>$hash,'status'=>'qa_pending','supplier_usage'=>$result['usage'],'actual_usd'=>$result['actual_usd']]);unset($u['outputs'][$i]['qa']);
    if($u['unknown_cost']??false)return $this->stop($r,$u,'unknown','图片生成用量未知，未继续自动质检或修正。');
    if(!$this->sourceMatches($p,$u))return $this->stop($r,$u,'source_changed','生成期间项目资料已变化，结果已保留但未采用。');
    $r->update(['usage'=>$u,'error'=>null]);return $r;
   }catch(\Throwable $e){if($e instanceof \App\Services\AI\ProviderFailure&&in_array($e->reason,['balance','authentication','quota','schema'])){$u['outputs'][$i]['status']='failed';return $this->stop($r,$u,'provider',$e->reason==='balance'?'OpenAI余额不足，请top up；没有自动重试。':'OpenAI拒绝本次请求，请检查连接或额度；没有自动重试。');}$u['outputs'][$i]['status']='unknown';$u['unknown_cost']=true;return $this->stop($r,$u,'unknown','生成或修正未完整返回，请核对OpenAI余额与用量；没有自动重试。');}
  }
  if(isset($u['repair_context']))return app(MarketingMaterialRepair::class)->finalChecks($p,$r);
  $r->update(['usage'=>$u,'status'=>'awaiting_batch_review','error'=>null]);return $r;
 }
}
