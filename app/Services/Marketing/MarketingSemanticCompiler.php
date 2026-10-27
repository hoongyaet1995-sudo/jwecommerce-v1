<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;use Illuminate\Validation\ValidationException;
class MarketingSemanticCompiler {
 public function compile(MarketingVideoFilm $base,array $evidence,array $answer):array {
  app(MarketingSemanticFacts::class)->assertFresh($base,$evidence['facts']);$state=app(MarketingOptimizationPlan::class)->baseline($base);$rows=app(MarketingSubtitleService::class)->scenes($base);$known=array_column($evidence['facts']['atoms'],null,'id');$applied=[];$skipped=[];$seen=[];$provenance=['version'=>MarketingSemanticEvidence::VERSION,'facts'=>$evidence['facts'],'evidence_digest'=>MarketingEditingDigest::hash($evidence),'source_film_id'=>$base->id,'source_sha256'=>$base->output_sha256,'decisions'=>[]];
  foreach($answer['scenes']??[] as $decision){$n=$decision['scene_number']??0;$row=collect($rows)->firstWhere('number',$n);$fail=function(string $why)use(&$skipped,$n){$skipped[]=['scene_number'=>$n,'reason'=>$why];};
   if(!$row||isset($seen[$n])){$fail('分镜不存在或重复');continue;}$seen[$n]=true;$ids=$decision['fact_ids']??[];
   if(($decision['confidence']??'')!=='high'||!$ids||array_filter($ids,fn($id)=>!isset($known[$id])||($known[$id]['scene_number']!==0&&$known[$id]['scene_number']!==$n))){$fail('卖点缺少可靠的已确认事实');continue;}
   $provenance['decisions'][]=['scene_number'=>$n,'fact_ids'=>$ids,'reason'=>mb_substr($decision['reason']??'',0,1200)];$target=$decision['target']??null;$grounding=null;
   if($target&&array_intersect($ids,$target['fact_ids']??[])&&!array_diff($target['fact_ids']??[],$ids)&&!array_diff($target['reference_ids']??[],array_column($evidence['references'],'id'))&&in_array('product',$target['reference_ids']??[],true))$grounding=app(MarketingTargetGrounding::class)->validate($target,$row,$evidence['samples']);
   if($target&&!$grounding)$fail('物品身份、位置或连续证据不足，未添加强调');
   $candidate=$decision['card']??null;
   if($candidate){try{
     if(!in_array($candidate['body_fact_id']??'',$ids,true))throw ValidationException::withMessages(['semantic'=>'卡片与本段卖点不一致']);
     $copy=app(MarketingSemanticFacts::class)->card($candidate,$evidence['facts'],$n);$start=$candidate['start_frame'];$end=$candidate['end_frame'];
     if(!is_int($start)||!is_int($end)||$start<0||$end>$row['frames']||$end-$start<36||count($state['information_cards'])>=6)throw ValidationException::withMessages(['semantic'=>'卡片时间或数量不可靠']);
     foreach($state['information_cards'] as $old)if($old['scene_number']===$n&&(max($start,$old['start_frame'])<min($end,$old['end_frame'])||$old['body']===$copy['body']))throw ValidationException::withMessages(['semantic'=>'保留已有合适卡片，避免重叠或重复']);
     $layout=$this->layout($decision['layout_observations']??[],$row,$evidence['samples']);
     if($layout===null)throw ValidationException::withMessages(['semantic'=>'缺少人物遮挡证据']);
     $position=$this->position($candidate['position'],$start,$end,$grounding,$layout,$copy,collect($state['framing'])->firstWhere('scene_number',$n),$row['frames'],$this->avoidance($state,$n));if(!$position)throw ValidationException::withMessages(['semantic'=>'没有可靠的不遮挡信息卡位置']);
     $card=['id'=>'semantic-'.$n.'-'.substr($evidence['facts']['digest'],0,10),'type'=>($candidate['title_key']==='cta'?'contact':'feature'),'scene_number'=>$n,'start_frame'=>$start,'end_frame'=>$end,'title'=>$copy['title'],'body'=>$copy['body'],'step'=>1,'channel'=>'none','contact'=>'','position'=>$position,'replace_product_info'=>false,'source'=>$copy['source'],'source_refs'=>$copy['source_refs']];
     $card['semantic']=MarketingSemanticProof::seal(['version'=>MarketingSemanticEvidence::VERSION,'facts_digest'=>$evidence['facts']['digest'],'fact_ids'=>$copy['fact_ids'],'source_sha256'=>$row['raw_sha256'],'scene_number'=>$n,'title'=>$card['title'],'body'=>$card['body']]);$state['information_cards'][]=$card;$applied[]=['scene_number'=>$n,'kind'=>'information_card','fact_ids'=>$copy['fact_ids']];
    }catch(ValidationException){$fail('信息卡未通过完整事实、时间或遮挡校验');}}
   if($grounding){$grounding['facts_digest']=$evidence['facts']['digest'];$grounding['fact_ids']=$ids;$grounding['avoidance']=$this->avoidance($state,$n);$grounding=MarketingSemanticProof::seal($grounding);$box=$grounding['points'][0]['bbox'];$state['emphasis']=array_values(array_filter($state['emphasis'],fn($v)=>$v['scene_number']!==$n));$state['emphasis'][]=['scene_number'=>$n,'kind'=>$target['kind'],'x'=>(int)max(5,min(95,round(($box[0]+$box[2]/2)*100))),'y'=>(int)max(5,min(95,round(($box[1]+$box[3]/2)*100))),'size'=>12,'angle'=>0,'color'=>'#ffdc78','strength'=>45,'start_frame'=>$target['start_frame'],'end_frame'=>$target['end_frame'],'grounding'=>$grounding];$applied[]=['scene_number'=>$n,'kind'=>'grounded_emphasis','fact_ids'=>$ids];}
  }
  return ['state'=>$state,'provenance'=>$provenance,'applied'=>$applied,'skipped'=>$skipped,'fallback_emphasis'=>app(MarketingOptimizationPlan::class)->baseline($base)['emphasis']];
 }
 private function layout(array $layout,array $scene,array $samples):?array {
  $samples=array_values(array_filter($samples,fn($s)=>$s['scene_number']===$scene['number']));if(!$samples||count($layout)!==count($samples))return null;$map=array_column($samples,null,'id');$out=[];
  foreach($layout as $v){$sample=$map[$v['sample_id']??'']??null;if(!$sample||($v['confidence']??'')!=='high')return null;unset($map[$sample['id']]);foreach($v['people']??[] as $box){if(!MarketingTargetGrounding::box($box))return null;$out[]=$box;}}return $out;
 }
 private function position(string $preferred,int $start,int $end,?array $grounding,array $people,array $copy,?array $framing=null,int $frames=0,array $obstacles=[]):?string {
  $raw=[...$people,...array_column($grounding['points']??[],'bbox')];
  foreach(array_unique([$preferred,'top-left','top-center','top-right']) as $position){
   if(!in_array($position,['top-left','top-center','top-right']))continue;$b=$this->cardBox($position,$copy);$clear=true;
   foreach($obstacles as $o)if(max($start,$o['start_frame'])<min($end,$o['end_frame'])&&MarketingTargetGrounding::iou($b,$o['bbox'])>0)$clear=false;
   for($frame=$start;$clear&&$frame<$end;$frame++)foreach($raw as $box)if(MarketingTargetGrounding::iou($b,$this->framedBox($box,$framing,$frame,$frames))>0){$clear=false;break;}
   if($clear)return $position;
  }return null;
 }
 // Same source-to-screen transform as framing-core.mjs; conservative union of observed protected boxes.
 private function framedBox(array $box,?array $c,int $frame,int $frames):array {
  if(!$c||$c['mode']==='none'||$frame<$c['start_frame']||$frame>=$c['end_frame'])return $box;
  $clamp=fn($v,$a,$b)=>max($a,min($b,$v));$ease=fn($v)=>$v*$v*(3-2*$v);$duration=max(1,$c['end_frame']-$c['start_frame']);$edge=max(1,min(8,floor($duration/4)));
  $entrance=$c['mode']==='fixed'?$ease($clamp(($frame-$c['start_frame'])/$edge,0,1)):$ease($clamp(($frame-$c['start_frame'])/max(1,$duration-1),0,1));$exit=$c['end_frame']<$frames?$ease($clamp(($c['end_frame']-1-$frame)/$edge,0,1)):1;
  $scale=1+($clamp($c['zoom'],1,1.3)-1)*$entrance*$exit;$x=$clamp(.5-$scale*$clamp($c['x']/100,0,1),1-$scale,0);$y=$clamp(.5-$scale*$clamp($c['y']/100,0,1),1-$scale,0);
  return [$box[0]*$scale+$x,$box[1]*$scale+$y,$box[2]*$scale,$box[3]*$scale];
 }
 public function cardBox(string $position,array $copy=[]):array {$height=56+max(1,ceil(mb_strlen($copy['title']??'')/9))*32*1.45+7+ceil(mb_strlen($copy['body']??'')/13)*24*1.45+(($copy['type']??'')==='step'?44:0)+(($copy['channel']??'none')!=='none'&&!empty($copy['contact'])?12+ceil(mb_strlen($copy['contact'])/12)*25*1.45:0);return [match($position){'top-right'=>.36,'top-center'=>.25,default=>.075},66/1280,406/720,min(.45,$height/1280)];}
 public function avoidance(array $state,int $scene):array {$out=[['start_frame'=>0,'end_frame'=>2880,'bbox'=>[0,.70,1,.30]]];foreach($state['information_cards'] as $c)if($c['scene_number']===$scene)$out[]=['start_frame'=>$c['start_frame'],'end_frame'=>$c['end_frame'],'bbox'=>$this->cardBox($c['position'],$c)];foreach($state['overlays'] as $o)if($o['scene_number']===$scene&&($o['enabled']??true)){$w=$o['width']/100;$h=$w*720/($o['ratio']??1.5)/1280;$out[]=['start_frame'=>$o['start_frame'],'end_frame'=>$o['end_frame'],'bbox'=>[$o['x']/100-$w/2,$o['y']/100-$h/2,$w,$h]];}return $out;}
}
