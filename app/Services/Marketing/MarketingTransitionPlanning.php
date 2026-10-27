<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingRevision,MarketingAiRun};
use Illuminate\Support\Facades\DB;
class MarketingTransitionPlanning
{
 public function referenceEvidence(MarketingProject $p,MarketingRevision $r):array{
  $pkg=$r->document['material_package']??null;if(!$pkg)return [];$pkg=app(MarketingMaterialPackage::class)->inspectReferences($p,$pkg);
  return array_map(function($n){$ref=$n['reference'];return ['need_id'=>$n['id'],'kind'=>$n['kind'],'name'=>$n['name'],'scene_numbers'=>$n['scene_numbers'],'required'=>$n['required'],'status'=>$n['status'],'verified'=>$ref!==null,'reference_type'=>$ref['type']??null,'reference_sha256'=>$ref['sha256']??null,'confirmed_value'=>($ref['type']??'')==='confirmed_fact'?mb_substr($ref['value'],0,1000):null];},$pkg['requirements']);
 }
 public function digest(MarketingProject $p,MarketingRevision $r):string{return hash('sha256',json_encode([$r->document['scenes'],$r->document['director_plan']??null,$p->brief,$p->snapshot,$p->preferences,$p->concepts[$p->selected_concept]??null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
 public function inventory(MarketingProject $p,MarketingRevision $r):array{
  $pkg=$r->document['material_package']??null;if(!$pkg)return [];$pkg=app(MarketingMaterialPackage::class)->inspectReferences($p,$pkg);$frames=[];
  foreach($pkg['outputs'] as $o){if(!in_array($o['role'],['start_frame','end_frame'],true))continue;$run=MarketingAiRun::where('project_id',$p->id)->find($o['generation_run_id']??'');$record=collect($run?->usage['outputs']??[])->firstWhere('id',$o['id']);$frames[$o['scene_number'].'-'.$o['role']]=['scene'=>$o['scene_number'],'role'=>$o['role'],'status'=>$o['status'],'asset_id'=>$o['asset_id']??null,'sha256'=>$o['sha256']??null,'saved_design'=>array_intersect_key($record['design']??[],array_flip(['subject','composition','frame_state','continuity']))];}
  ksort($frames);return $frames;
 }
 public function frameDigest(array $inventory):string{return hash('sha256',json_encode(array_map(fn($f)=>array_intersect_key($f,array_flip(['scene','role','status','asset_id','sha256'])),$inventory),JSON_THROW_ON_ERROR));}
 public function state(MarketingProject $p,MarketingRevision $r):array{
  $frames=$this->inventory($p,$r);$plan=$r->document['transition_plan']??null;$base=['adopted'=>false,'plan'=>$plan,'frames'=>$frames,'frame_digest'=>$this->frameDigest($frames),'ready'=>false];
  if(count($r->document['scenes'])<2)return array_replace($base,['status'=>'not_required','ready'=>true]);
  if(!$plan)return $base+['status'=>'missing'];
  if(($plan['schema_version']??'')!==MarketingTransitionContract::VERSION||($plan['source_digest']??'')!==$this->digest($p,$r))return $base+['status'=>'stale'];
  $base['adopted']=($r->context['transition_adoption']['plan_digest']??'')===hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));
  foreach($r->document['scenes'] as $i=>$scene){$review=$plan['pair_reviews'][$i+1]??null;if(($review['verdict']??'')!=='pass'||($review['source_digest']??'')!==app(MarketingFramePairReview::class)->digest($p,$r,$i+1))return $base+['status'=>'needs_materials'];}
  foreach($plan['boundaries'] as $edge){foreach(['outgoing_end'=>[$edge['from'],'end_frame'],'incoming_start'=>[$edge['to'],'start_frame']] as $side=>[$n,$role]){if(($frames[$n.'-'.$role]['status']??'')!=='approved')return $base+['status'=>'needs_materials'];$review=collect($edge['frame_reviews'])->firstWhere('side',$side);if(($review['status']??'')!=='reuse_candidate'){$old=$plan['assessed_frames'][$n.'-'.$role]??['asset_id'=>null,'sha256'=>null];$changed=($old['asset_id']??null)!==$frames[$n.'-'.$role]['asset_id']||($old['sha256']??null)!==$frames[$n.'-'.$role]['sha256'];if(($review['status']??'')!=='needs_redesign'||!$changed)return $base+['status'=>'needs_materials'];}}}
  $review=$r->context['transition_review']??[];$ready=$base['adopted']&&($review['plan_digest']??'')===hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))&&($review['frame_digest']??'')===$base['frame_digest'];return array_replace($base,['status'=>$ready?'ready':'needs_review','ready'=>$ready]);
 }
 public function confirm(string $id,int $actor,int $version,string $revision,array $edges,bool $reuse=true):void{
  DB::transaction(function()use($id,$actor,$version,$revision,$edges,$reuse){$p=app(MarketingWorkflow::class)->locked($id,$actor,$version);abort_unless($p->current_revision_id===$revision,409);$r=$p->currentRevision;$state=$this->state($p,$r);abort_unless($reuse?in_array($state['status'],['needs_review','ready']):!in_array($state['status'],['missing','stale','not_required']),422,'先准备有效跨段方案，并补齐或重新制备标记的问题素材。');$expected=array_map(fn($e)=>$e['from'].'-'.$e['to'],$state['plan']['boundaries']);sort($expected);sort($edges);abort_unless(!$reuse||$edges===$expected,422,'请逐一对照相邻首尾图并确认复用。');$c=$r->context;$c['transition_adoption']=['actor_id'=>$actor,'at'=>now()->toIso8601String(),'plan_digest'=>hash('sha256',json_encode($state['plan'],JSON_THROW_ON_ERROR))];if($reuse)$c['transition_review']=['actor_id'=>$actor,'at'=>now()->toIso8601String(),'plan_digest'=>hash('sha256',json_encode($state['plan'],JSON_THROW_ON_ERROR)),'frame_digest'=>$state['frame_digest']];$r->update(['context'=>$c]);$p->update(['version'=>$p->version+1,'confirmed_revision_id'=>null,'status'=>'needs_confirmation']);});
 }
 public function constraints(MarketingProject $p,MarketingRevision $r,int $scene,string $role):array{
  $plan=$r->document['transition_plan']??null;if(!$plan||($plan['source_digest']??'')!==$this->digest($p,$r)||($r->context['transition_adoption']['plan_digest']??'')!==hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR)))return [];$result=[];
  foreach($plan['boundaries'] as $e){if($scene===$e['from']&&$role==='end_frame')$result[]=['side'=>'outgoing_end','state'=>$e['outgoing_end'],'instruction'=>$e['api']['outgoing']];if($scene===$e['to']&&$role==='start_frame')$result[]=['side'=>'incoming_start','state'=>$e['incoming_start'],'instruction'=>$e['api']['incoming']];}return $result;
 }
}
