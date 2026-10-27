<?php
namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MarketingPlanningContract
{
 public const ACTIONS=['suggest','concepts','refresh','hooks','storyboard','tools'];
 public const TYPES=['presenter','product_showcase','scenario_story','tutorial','feature_demo','service_intro'];
 public function profile(array $p):array
 {
  return Validator::make(['p'=>$p],['p'=>'required|array:offer,audience,goal,platforms,duration_seconds,pain_point,differentiator,tone','p.offer'=>'required|string|max:800','p.audience'=>'required|string|max:500','p.goal'=>'required|in:awareness,introduction,promotion,enquiry,appointment,purchase','p.platforms'=>'required|array|list|min:1|max:4','p.platforms.*'=>'required|distinct|in:Facebook,Instagram,TikTok,YouTube','p.duration_seconds'=>'required|integer|min:5|max:60','p.pain_point'=>'required|string|max:800','p.differentiator'=>'required|string|max:800','p.tone'=>'required|string|max:300'])->validate()['p'];
 }
 private function object(array $p):array{return ['type'=>'object','properties'=>$p,'required'=>array_keys($p),'additionalProperties'=>false];}
 private function text(int $max=800):array{return ['type'=>'string','minLength'=>1,'maxLength'=>$max];}
 private function list(array $items,int $min=0,int $max=6):array{return ['type'=>'array','items'=>$items,'minItems'=>$min,'maxItems'=>$max];}
 public function schema(string $action):array
 {
  $profile=$this->object(['offer'=>$this->text(),'audience'=>$this->text(500),'goal'=>['type'=>'string','enum'=>['awareness','introduction','promotion','enquiry','appointment','purchase']],'platforms'=>$this->list(['type'=>'string','enum'=>['Facebook','Instagram','TikTok','YouTube']],1,4),'duration_seconds'=>['type'=>'integer','minimum'=>5,'maximum'=>60],'pain_point'=>$this->text(),'differentiator'=>$this->text(),'tone'=>$this->text(300)]);
  if($action==='suggest')return $this->object(['profile'=>$profile,'questions'=>$this->list($this->text(500),0,6),'reason'=>$this->text()]);
  if($action==='hooks')return $this->object(['hook'=>$this->text(300),'reason'=>$this->text()]);
  if($action==='tools')return app(MarketingToolPlanning::class)->schema();
  if($action==='storyboard'){$s=app(MarketingDraftContract::class)->storyboardSchema();unset($s['properties']['production']);$s['properties']['scenes']['minItems']=1;$s['properties']['scenes']['maxItems']=6;$s['required']=array_keys($s['properties']);return $s;}
  $ref=$this->object(['source_id'=>$this->text(64),'basis'=>['type'=>'string','enum'=>['text','visual_notes']],'adaptation'=>$this->text(600)]);
  $c=$this->object(['title'=>$this->text(120),'approach'=>['type'=>'string','enum'=>['use_scenario','feature_detail','question_answer','brand_story','step_by_step','comparison']],'hook'=>$this->text(300),'story'=>$this->text(1800),'materials'=>$this->text(),'duration'=>['type'=>'integer','minimum'=>5,'maximum'=>60],'ratio'=>['type'=>'string','enum'=>['9:16']],'recommended'=>['type'=>'boolean'],'reason'=>$this->text(),'video_type'=>['type'=>'string','enum'=>self::TYPES],'visual_treatment'=>$this->text(),'references'=>$this->list($ref,1,3)]);
  return $this->object(['concepts'=>$this->list($c,3,3)]);
 }
 public function validate(array $data,string $action,array $facts):array
 {
  if($action==='suggest'){$v=Validator::make($data,['profile'=>'required|array','questions'=>'present|array|max:6','questions.*'=>'required|string|max:500','reason'=>'required|string|max:800'])->validate();$v['profile']=$this->profile($v['profile']);return $v;}
  if($action==='hooks')return Validator::make($data,['hook'=>'required|string|max:300','reason'=>'required|string|max:800'])->validate();
  if($action==='storyboard'){
   $d=app(MarketingWriter::class)->validateOutput($data,'storyboard',$facts+['draft_contract'=>MarketingDraftContract::VERSION]);
   if(count($d['scenes'])>6||$d['duration']!==$facts['profile']['duration_seconds'])throw ValidationException::withMessages(['agent'=>'The storyboard duration or scene count changed the confirmed brief.']);
   return $d;
  }
  if($action==='tools'){
   $p=app(MarketingToolPlanning::class)->validateProposal($data);$doc=$facts['storyboard'];
   if(count($p['scenes'])!==count($doc['scenes']))throw ValidationException::withMessages(['agent'=>'Every storyboard scene needs exactly one tool proposal.']);
   foreach($p['scenes'] as $i=>$s){if($s['scene_id']!=='scene-'.($i+1)||$s['asset_ids']!==$doc['scenes'][$i]['asset_ids'])throw ValidationException::withMessages(['agent'=>'The tool proposal changed scene or material identity.']);}
   if(($facts['concept']['video_type']??null)==='presenter'&&($p['route']!=='native_video'||count($p['scenes'])!==1||$p['scenes'][0]['capability']!=='native_presenter'))throw ValidationException::withMessages(['agent'=>'A pure presenter uses the native video route; it is not converted into a local slideshow.']);
   return $p;
  }
  $v=Validator::make($data,['concepts'=>'required|array|list|size:3','concepts.*'=>'required|array:title,approach,hook,story,materials,duration,ratio,recommended,reason,video_type,visual_treatment,references','concepts.*.title'=>'required|string|max:120','concepts.*.approach'=>'required|in:use_scenario,feature_detail,question_answer,brand_story,step_by_step,comparison','concepts.*.hook'=>'required|string|max:300','concepts.*.story'=>'required|string|max:1800','concepts.*.materials'=>'required|string|max:800','concepts.*.duration'=>'required|integer|min:5|max:60','concepts.*.ratio'=>'required|in:9:16','concepts.*.recommended'=>'required|boolean','concepts.*.reason'=>'required|string|max:800','concepts.*.video_type'=>'required|in:'.implode(',',self::TYPES),'concepts.*.visual_treatment'=>'required|string|max:800','concepts.*.references'=>'required|array|list|min:1|max:3','concepts.*.references.*'=>'required|array:source_id,basis,adaptation','concepts.*.references.*.source_id'=>'required|string|max:64','concepts.*.references.*.basis'=>'required|in:text,visual_notes','concepts.*.references.*.adaptation'=>'required|string|max:600'])->validate();
  $items=collect($v['concepts']);if($items->pluck('approach')->unique()->count()!==3||$items->pluck('title')->map(fn($s)=>mb_strtolower(trim($s)))->unique()->count()!==3||$items->pluck('hook')->unique()->count()!==3||$items->where('recommended',true)->count()!==1)throw ValidationException::withMessages(['agent'=>'Three genuinely different directions and one recommendation are required.']);
  $sources=array_column($facts['references'],null,'id');$used=[];
  foreach($v['concepts'] as $c){
   if($c['duration']!==$facts['profile']['duration_seconds'])throw ValidationException::withMessages(['agent'=>'A concept changed the confirmed duration.']);
   foreach($c['references'] as $r){if(!isset($sources[$r['source_id']])||($r['basis']==='visual_notes'&&empty($sources[$r['source_id']]['visual_notes'])))throw ValidationException::withMessages(['agent'=>'A concept cites unsupported visual evidence.']);$used[]=$r['source_id'];}
  }
  if(count(array_unique($used))<2)throw ValidationException::withMessages(['agent'=>'Use at least two distinct references across the concepts.']);
  if($action==='refresh'&&count(array_intersect($items->pluck('hook')->all(),array_column($facts['previous_concepts'],'hook'))))throw ValidationException::withMessages(['agent'=>'Refresh repeated a previous opening. No automatic retry was made.']);
  return $v;
 }
}
