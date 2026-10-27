<?php
namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Supplier-independent shot design. Never grants production permission. */
class MarketingDirectorContract
{
 public const VERSION='director-v1';
 public function schema(array $facts=[]):array
 {
  $text=fn($max=1000)=>['type'=>'string','minLength'=>1,'maxLength'=>$max];
  $object=fn($p)=>['type'=>'object','properties'=>$p,'required'=>array_keys($p),'additionalProperties'=>false];
  $list=fn($i,$min=0,$max=12)=>['type'=>'array','items'=>$i,'minItems'=>$min,'maxItems'=>$max];
  $number=['type'=>'integer','minimum'=>0,'maximum'=>180];
  $shot=['scene_number'=>['type'=>'integer','minimum'=>1,'maximum'=>12],'start'=>$number,'end'=>$number];
  foreach(['purpose','subject','framing','motion','lighting','continuity'] as $field)$shot[$field]=$text();
  $shot['voiceover']=['type'=>'string','maxLength'=>1500];$shot['subtitle']=['type'=>'string','maxLength'=>1000];
  $shot['beats']=$list($object(['start'=>$number,'end'=>$number,'action'=>$text()]),1,12);
  $shot['checks']=$list($text(500),1,8);
  return $object(['summary'=>$text(),'shots'=>$list($object($shot),1,12),'materials'=>$list($object(['scene_number'=>['type'=>'integer','minimum'=>1,'maximum'=>12],'name'=>$text(200),'owner'=>['type'=>'string','enum'=>['ai','employee','system']],'status'=>['type'=>'string','enum'=>collect($facts['assets']??[])->contains('authorized',true)?['ready','missing','optional']:['missing','optional']],'asset_id'=>['type'=>'string','maxLength'=>64],'evidence'=>$text(),'alternative'=>$text()]),0,24),'notes'=>$list($text(),0,12)]);
 }
 public function validate(array $data,array $facts):array
 {
  $rules=['plan'=>'required|array:summary,shots,materials,notes','plan.summary'=>'required|string|max:1000','plan.shots'=>'required|array|list|min:1|max:12','plan.shots.*'=>'required|array:scene_number,start,end,purpose,subject,framing,motion,lighting,continuity,voiceover,subtitle,beats,checks','plan.shots.*.scene_number'=>'required|integer|min:1|max:12','plan.shots.*.start'=>'required|integer|min:0|max:180','plan.shots.*.end'=>'required|integer|min:1|max:180','plan.shots.*.voiceover'=>'present|string|max:1500','plan.shots.*.subtitle'=>'present|string|max:1000','plan.shots.*.beats'=>'required|array|list|min:1|max:12','plan.shots.*.beats.*'=>'required|array:start,end,action','plan.shots.*.beats.*.start'=>'required|integer|min:0|max:180','plan.shots.*.beats.*.end'=>'required|integer|min:1|max:180','plan.shots.*.beats.*.action'=>'required|string|max:1000','plan.shots.*.checks'=>'required|array|list|min:1|max:8','plan.shots.*.checks.*'=>'required|string|max:500','plan.materials'=>'present|array|list|max:24','plan.materials.*'=>'required|array:scene_number,name,owner,status,asset_id,evidence,alternative','plan.materials.*.scene_number'=>'required|integer|min:1|max:12','plan.materials.*.name'=>'required|string|max:200','plan.materials.*.owner'=>'required|in:ai,employee,system','plan.materials.*.status'=>'required|in:ready,missing,optional','plan.materials.*.asset_id'=>'present|string|max:64','plan.materials.*.evidence'=>'required|string|max:1000','plan.materials.*.alternative'=>'required|string|max:1000','plan.notes'=>'present|array|list|max:12','plan.notes.*'=>'required|string|max:1000'];
  foreach(['purpose','subject','framing','motion','lighting','continuity'] as $f)$rules['plan.shots.*.'.$f]='required|string|max:1000';
  $data=Validator::make(['plan'=>$data],$rules)->validate()['plan'];
  $fail=fn($message)=>throw ValidationException::withMessages(['director'=>$message]);
  // One design unit per saved narrative scene. Multiple beats never imply multiple API tasks.
  if(count($data['shots'])!==count($facts['scenes']))$fail('导演稿必须完整覆盖原分镜，不能增加或遗漏叙事段落。');
  $cursor=0;
  foreach($data['shots'] as $i=>$shot){
   $scene=$facts['scenes'][$i];
   if($shot['scene_number']!==$i+1||$shot['start']!==$cursor||$shot['end']!==$cursor+(int)$scene['duration'])$fail('镜头时间必须连续并保持原分镜时长。');
   if($shot['voiceover']!==$scene['voiceover']||$shot['subtitle']!==$scene['subtitle'])$fail('导演稿不能更改已保存口播或字幕，请先修改叙事分镜。');
   $beatCursor=$cursor;foreach($shot['beats'] as $beat){if($beat['start']!==$beatCursor||$beat['end']<=$beatCursor||$beat['end']>$shot['end'])$fail('镜头内部节拍有重叠、空缺或越界。');$beatCursor=$beat['end'];}
   if($beatCursor!==$shot['end'])$fail('节拍必须覆盖整个镜头。');$cursor=$shot['end'];
  }
  if($cursor!==(int)$facts['duration'])$fail('导演稿总时长与原分镜不一致。');
  $assets=array_column($facts['assets'],null,'id');
  foreach($data['materials'] as $material){
   if(!isset($facts['scenes'][$material['scene_number']-1]))$fail('素材引用了不存在的分镜。');
   if($material['asset_id']!==''){
    $asset=$assets[$material['asset_id']]??null;
    if(!$asset||!$asset['authorized']||!in_array($material['asset_id'],$facts['scenes'][$material['scene_number']-1]['asset_ids'],true))$fail('素材必须属于当前分镜且已获授权。');
   }elseif($material['status']==='ready')$fail('已就绪素材需要可核对的已授权资产，不能声称尚未生成的素材存在。');
  }
  $data['pacing_review']=app(MarketingSpeechRhythm::class)->review($facts['scenes']);
  return $data+['schema_version'=>self::VERSION,'source_revision_id'=>$facts['source_revision_id'],'source_digest'=>$facts['source_digest'],'duration'=>(int)$facts['duration'],'review_status'=>collect($data['materials'])->contains('status','missing')?'needs_materials':'needs_review'];
 }
}
