<?php
namespace App\Services\Marketing;
class MarketingTargetGrounding {
 public static function box(mixed $b):bool{return is_array($b)&&count($b)===4&&count(array_filter($b,fn($v)=>is_numeric($v)&&is_finite((float)$v)))===4&&$b[0]>=0&&$b[1]>=0&&$b[2]>0&&$b[3]>0&&$b[0]+$b[2]<=1.00000001&&$b[1]+$b[3]<=1.00000001;}
 public static function iou(array $a,array $b):float {$i=max(0,min($a[0]+$a[2],$b[0]+$b[2])-max($a[0],$b[0]))*max(0,min($a[1]+$a[3],$b[1]+$b[3])-max($a[1],$b[1]));return $i/max(.0000001,$a[2]*$a[3]+$b[2]*$b[3]-$i);}
 public function validate(array $target,array $scene,array $samples):?array {
  if(($target['target_id']??'')!=='product'||!in_array($target['mode']??'', ['static','moving'])||!is_int($target['start_frame']??null)||!is_int($target['end_frame']??null)||$target['start_frame']<0||$target['end_frame']>$scene['frames']||$target['end_frame']-$target['start_frame']<36)return null;
  $source=array_values(array_filter($samples,fn($s)=>$s['scene_number']===$scene['number']));$byId=array_column($source,null,'id');$observations=$target['observations']??[];if(count($observations)!==count($source)||count($source)<3||count($source)>9)return null;$seen=[];$points=[];
  foreach($observations as $o){$s=$byId[$o['sample_id']??'']??null;if(!$s||isset($seen[$s['id']])||$s['raw_sha256']!==$scene['raw_sha256'])return null;$seen[$s['id']]=true;$inside=$s['frame']>=$target['start_frame']&&$s['frame']<$target['end_frame'];
   $valid=($o['confidence']??'')==='high'&&($o['visible']??false)&&($o['identity_match']??false)&&self::box($o['bbox']??null);
   if(!$valid){if($inside||$target['mode']==='static')return null;continue;}
   $people=$o['people']??[];if(count($people)>12||array_filter($people,fn($b)=>!self::box($b)))return null;
   $points[]=['frame'=>$s['frame'],'bbox'=>array_map('floatval',$o['bbox']),'people'=>$people,'sample_id'=>$s['id']];
  }
  usort($points,fn($a,$b)=>$a['frame']<=>$b['frame']);if(count($points)<3||$points[0]['frame']>$target['start_frame']||end($points)['frame']<$target['end_frame']-1)return null;
  if($target['mode']==='static'){
   $frames=array_column($points,'frame');foreach([0,(int)floor(($scene['frames']-1)/2),$scene['frames']-1] as $anchor)if(!in_array($anchor,$frames,true))return null;
   foreach($points as $p)foreach($points as $q){$a=$p['bbox'];$b=$q['bbox'];if(self::iou($a,$b)<.8-1e-8||abs($a[0]+$a[2]/2-$b[0]-$b[2]/2)>.02+1e-8||abs($a[1]+$a[3]/2-$b[1]-$b[3]/2)>.02+1e-8)return null;}
  }else{
   $points=array_values(array_filter($points,fn($p)=>$p['frame']>=$target['start_frame']&&$p['frame']<$target['end_frame']));if(count($points)<4)return null;
   for($i=1;$i<count($points);$i++){$a=$points[$i-1]['bbox'];$b=$points[$i]['bbox'];$dx=($a[0]+$a[2]/2-$b[0]-$b[2]/2)*720;$dy=($a[1]+$a[3]/2-$b[1]-$b[3]/2)*1280;if($points[$i]['frame']-$points[$i-1]['frame']>12||hypot($dx,$dy)/hypot(720,1280)>.1+1e-8||abs($b[2]*$b[3]/($a[2]*$a[3])-1)>.25+1e-8)return null;}
  }
  return ['version'=>MarketingSemanticEvidence::VERSION,'mode'=>$target['mode'],'target_id'=>'product','source_sha256'=>$scene['raw_sha256'],'scene_number'=>$scene['number'],'points'=>$points,'intervals'=>[[$target['start_frame'],$target['end_frame']]]];
 }
}
