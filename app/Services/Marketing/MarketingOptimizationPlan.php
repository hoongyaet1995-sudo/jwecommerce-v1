<?php
namespace App\Services\Marketing;
use Illuminate\Validation\ValidationException;
/** Only whitelisted local edits; source facts, people and asset identity are immutable. */
class MarketingOptimizationPlan {
 public const FIELDS=[
 'overlays'=>['enabled','x','y','width','radius','border','color','start_frame','end_frame','image_id'],
 'emphasis'=>['kind','x','y','size','angle','color','strength','start_frame','end_frame'],
 'framing'=>['mode','zoom','x','y','start_frame','end_frame'],
 'information_cards'=>['position','start_frame','end_frame'],
 'subtitles'=>['mode','size','position','color','scope','scene'],
 'music'=>['volume','duck','fade'], 'outro'=>['enabled','template','duration'],
 'speed'=>['start_frame','end_frame','rate','protect']];
 private function fail():never{throw ValidationException::withMessages(['optimization'=>'建议包含无法采用的目标、冲突或未支持的操作，请重新检查方案。']);}
 public function apply(array $state,array $recs,array $selected):array {
  if(count($selected)!==count(array_unique($selected)))$this->fail();$seen=[];
  foreach($selected as $id){$r=collect($recs)->firstWhere('id',$id);if(!$r)$this->fail();$cap=$r['capability'];$action=$r['action'];$target=(string)$r['target_id'];$patch=array_filter($r['patch']??[],fn($v)=>$v!==null);$scene=(int)$r['scene_number'];
   if(!isset(self::FIELDS[$cap])||array_diff(array_keys($patch),self::FIELDS[$cap])||!in_array($action,['add','adjust','remove','keep'],true))$this->fail();if($action==='keep')continue;
   $key=$cap.':'.$target;if(isset($seen[$key]))$this->fail();$seen[$key]=true;
   if(in_array($cap,['overlays','information_cards','framing','emphasis'])){
    $keyfield=in_array($cap,['overlays','information_cards'])?'id':'scene_number';$index=null;foreach($state[$cap] as $n=>$v)if((string)$v[$keyfield]===$target&&$v['scene_number']===$scene)$index=$n;
    if($action==='add'){
     if($index!==null||$scene<1||(in_array($cap,['framing','emphasis'])&&$target!==(string)$scene)||!in_array($cap,['framing','emphasis','overlays']))$this->fail();
     $v=['scene_number'=>$scene]+$patch;if($cap==='overlays')$v+=['id'=>'opt-'.$r['id'],'enabled'=>true];$state[$cap][]=$v;
    }else{if($index===null)$this->fail();if($action==='remove'){if($patch)$this->fail();array_splice($state[$cap],$index,1);}elseif($action==='adjust'){if(!$patch)$this->fail();$state[$cap][$index]=array_replace($state[$cap][$index],$patch);}}
   }elseif($cap==='speed'){
    if($target!=='speed')$this->fail();if($action==='remove')$state['speed']=null;
    elseif(in_array($action,['add','adjust'])){if(!$patch)$this->fail();$state['speed']=array_replace($state['speed']??[],['scene_number'=>$scene],$patch);if((float)($state['speed']['rate']??1)!==1.0)$state['speed']['protect']=true;}else $this->fail();
   }else{
    if($target!==$cap||$scene!==0)$this->fail();if($cap==='subtitles'){$field='subtitles';if($action!=='adjust')$this->fail();}
    else{$field='settings';if($action==='remove')$patch=$cap==='music'?['track'=>null]:['enabled'=>false];elseif($action!=='adjust')$this->fail();}
    if(!$patch)$this->fail();$state[$field]=array_replace($state[$field],$patch);
   }
  }return $state;
 }
 public function baseline(\App\Models\MarketingVideoFilm $f):array {
  $config=app(MarketingSubtitleService::class)->config($f);return ['settings'=>$f->input['settings']??['enabled'=>false,'template'=>'minimal','duration'=>3,'company'=>app(\App\Support\ShopBrand::class)->name(),'cta'=>$f->input['cta']??'','track'=>null,'volume'=>18,'duck'=>true,'fade'=>true,'logo_sha256'=>null],'subtitles'=>$config['settings'],'information_cards'=>$f->input['information_cards']??[],'overlays'=>$f->input['overlays']??[],'emphasis'=>$f->input['emphasis']??[],'framing'=>$f->input['framing']??[],'speed'=>$f->input['speed']??null];
 }
 public function validateState(array $s,array $rows,\App\Models\MarketingProject $p):array {
  $s['settings']=app(MarketingFilmPackaging::class)->settings($s['settings']);$s['subtitles']=app(MarketingSubtitleService::class)->settings($s['subtitles']);
  $s['overlays']=app(MarketingOverlays::class)->normalize($s['overlays'],$rows,$p);$s['emphasis']=app(MarketingEmphasis::class)->normalize($s['emphasis'],$rows);$s['framing']=app(MarketingFraming::class)->normalize($s['framing'],$rows);$s['information_cards']=app(MarketingInformationCards::class)->normalize($s['information_cards'],$rows);$s['speed']=app(MarketingSpeed::class)->normalize($s['speed'],$rows);return $s;
 }
 public static function schema():array {
  $strings=['image_id','kind','mode','color','position','scope','template'];$booleans=['enabled','duck','fade','protect'];$props=[];
  foreach(array_unique(array_merge(...array_values(self::FIELDS))) as $k)$props[$k]=['type'=>[in_array($k,$strings)?'string':(in_array($k,$booleans)?'boolean':(in_array($k,['zoom','rate','width'])?'number':'integer')),'null']];
  $p=['capability'=>['type'=>'string','enum'=>array_keys(self::FIELDS)],'action'=>['type'=>'string','enum'=>['keep','remove','adjust','add']],'target_id'=>['type'=>'string'],'scene_number'=>['type'=>'integer'],'patch'=>['type'=>'object','additionalProperties'=>false,'properties'=>$props,'required'=>array_keys($props)],'title'=>['type'=>'string'],'reason'=>['type'=>'string'],'evidence_ids'=>['type'=>'array','items'=>['type'=>'string']],'confidence'=>['type'=>'string','enum'=>['high','uncertain']],'user_check'=>['type'=>'string']];
  return ['type'=>'object','additionalProperties'=>false,'required'=>['summary','scope','recommendations','preserve','unknowns'],'properties'=>['summary'=>['type'=>'string'],'scope'=>['type'=>'string'],'recommendations'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>$p,'required'=>array_keys($p)]],'preserve'=>['type'=>'array','items'=>['type'=>'string']],'unknowns'=>['type'=>'array','items'=>['type'=>'string']]]];
 }
}
