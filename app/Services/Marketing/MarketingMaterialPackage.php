<?php
namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Our preparation manifest; never a supplier payload or permission to generate images. */
class MarketingMaterialPackage
{
 public const VERSION='material-package-v1';
 public const REAL=['factual_detail','product_view','real_person','brand_reference'];
 public const KINDS=['factual_detail','product_view','real_person','brand_reference','synthetic_person','fictional_scene','graphic'];
 public function schema():array
 {
  $text=fn($max=800,$min=1)=>['type'=>'string','minLength'=>$min,'maxLength'=>$max];
  $object=fn($fields)=>['type'=>'object','properties'=>$fields,'required'=>array_keys($fields),'additionalProperties'=>false];
  $list=fn($item,$min,$max)=>['type'=>'array','items'=>$item,'minItems'=>$min,'maxItems'=>$max];
  return $object(['summary'=>$text(),'requirements'=>$list($object(['key'=>$text(64),'kind'=>['type'=>'string','enum'=>self::KINDS],'name'=>$text(200),'scene_numbers'=>$list(['type'=>'integer','minimum'=>1,'maximum'=>12],1,12),'reason'=>$text(),'evidence'=>$text(1000,0),'question'=>$text(500,0),'preparation'=>$text(1500),'required'=>['type'=>'boolean']]),0,24)]);
 }
 public function digest(MarketingProject $p,MarketingRevision $r):string
 {
  return hash('sha256',json_encode([$r->document['scenes'],$r->document['director_plan']??null,$p->snapshot,$p->brief,$p->preferences,$p->concepts[$p->selected_concept]??null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }
 public function build(array $output,array $facts,?array $previous=null):array
 {
  $o=Validator::make(['p'=>$output],['p'=>'required|array:summary,requirements','p.summary'=>'required|string|max:800','p.requirements'=>'present|array|list|max:24','p.requirements.*'=>'required|array:key,kind,name,scene_numbers,reason,evidence,question,preparation,required','p.requirements.*.key'=>'required|string|max:64|distinct','p.requirements.*.kind'=>'required|in:'.implode(',',self::KINDS),'p.requirements.*.name'=>'required|string|max:200','p.requirements.*.scene_numbers'=>'required|array|list|min:1|max:12','p.requirements.*.scene_numbers.*'=>'required|integer|min:1|max:12','p.requirements.*.reason'=>'required|string|max:800','p.requirements.*.evidence'=>'present|string|max:1000','p.requirements.*.question'=>'present|string|max:500','p.requirements.*.preparation'=>'required|string|max:1500','p.requirements.*.required'=>'required|boolean'])->validate()['p'];
  foreach($o['requirements'] as &$need){
   if(count(array_unique($need['scene_numbers']))!==count($need['scene_numbers']))$this->fail('同一需求的段落引用重复。');
   foreach($need['scene_numbers'] as $number)if(!isset($facts['scenes'][$number-1]))$this->fail('素材需求引用了不存在的段落。');
   $real=in_array($need['kind'],self::REAL,true);
   if($real){
    if(trim($need['question'])===''||trim($need['evidence'])==='')$this->fail('向员工索取真实资料必须说明问题及分镜依据。');
    $evidenceFound=false;
    foreach($need['scene_numbers'] as $number){$scene=$facts['scenes'][$number-1];$shot=$facts['director_plan']['shots'][$number-1]??[];foreach(array_merge(array_values($scene),array_values($shot)) as $value)if(is_string($value)&&str_contains($value,$need['evidence']))$evidenceFound=true;}
    if(!$evidenceFound)$this->fail('员工需补的资料没有对应已保存分镜或导演稿依据。');
   }elseif($need['question']!=='')$this->fail('虚拟人物、虚构场景和排版由AI准备，不应向员工索取文件。');
   $need+=['id'=>'need-'.substr(hash('sha256',$need['key']),0,16),'owner'=>$real?'employee':'ai','status'=>$real?'awaiting_reference':'ai_planned','reference'=>null];   if($real&&($previous['source_digest']??null)===$facts['source_digest']){
    foreach($previous['requirements']??[] as $old){
     $same=true;foreach(['key','kind','name','evidence','question'] as $field)if(($old[$field]??null)!==$need[$field])$same=false;
     if($same&&($old['reference']??null)!==null){$need['reference']=$old['reference'];$need['status']='reference_provided';break;}
    }
   }
  }unset($need);
  $outputs=[];
  foreach($facts['scenes'] as $i=>$scene){
   $shot=$facts['director_plan']['shots'][$i]??[];
   $ids=array_column(array_values(array_filter($o['requirements'],fn($n)=>in_array($i+1,$n['scene_numbers'],true)&&$n['kind']!=='graphic')),'id');
   foreach(['start_frame','end_frame','extra_image'] as $role){
    $intent=match($role){'start_frame'=>'开场关键帧：固定主体、身份、场景与开始构图。','end_frame'=>'结束关键帧：同一主体与场景，表现动作落点和结束构图。',default=>'补充参考图：保持主体外观和场景一致，不凭空补真实商品不可见结构。'};
    $outputs[]=['id'=>'scene-'.($i+1).'-'.$role,'scene_number'=>$i+1,'role'=>$role,'status'=>'planned','requirement_ids'=>$ids,'preparation'=>$intent.' '.implode(' ',array_intersect_key($shot,array_flip(['subject','framing','motion','lighting','continuity']))),'source_reference_ids'=>array_column(array_values(array_filter($o['requirements'],fn($n)=>in_array($n['id'],$ids,true)&&$n['reference']!==null)),'id'),'asset_id'=>null,'media_library_id'=>null,'generation_run_id'=>null,'approved_at'=>null];
   }
  }
  return $this->status($o+['schema_version'=>self::VERSION,'source_revision_id'=>$facts['source_revision_id'],'source_digest'=>$facts['source_digest'],'source_policy'=>'reference_only','production_policy'=>'approved_ai_prepared_images_only','outputs'=>$outputs,'image_authorization'=>null]);
 }
 public function status(array $package):array
 {
  $missing=collect($package['requirements'])->contains(fn($n)=>$n['required']&&$n['owner']==='employee'&&$n['reference']===null);
  $package['status']=$missing?'awaiting_inputs':'awaiting_image_budget';
  // This stage never has generated/approved files; downstream must not treat references as finished production assets.
  return $package;
 }
 public function assertFresh(MarketingProject $p,MarketingRevision $r):array
 {
  $package=$r->document['material_package']??null;
  abort_unless($package&&($package['schema_version']??'')===self::VERSION,422,'请先整理结构化素材计划。');
  abort_unless($package['source_digest']===$this->digest($p,$r),409,'故事、导演稿或商品资料已变化，请重新整理素材计划。');return $this->inspectReferences($p,$package);
 }
 public function catalogPath(MarketingProject $p,int $index):?string
 {
  $path=$p->snapshot['images'][$index]['path']??null;if(!is_string($path)||$path==='')return null;
  foreach([public_path(),\Illuminate\Support\Facades\Storage::disk('public')->path('')] as $root){
   $base=realpath($root);$candidate=realpath($root.DIRECTORY_SEPARATOR.ltrim($path,'/\\'));
   if($base&&$candidate&&str_starts_with(strtolower($candidate),strtolower($base.DIRECTORY_SEPARATOR))&&is_file($candidate)&&@getimagesize($candidate))return $candidate;
  }return null;
 }
 public function inspectReferences(MarketingProject $p,array $package):array
 {
  $assets=$p->assets()->where('authorized',true)->get()->keyBy('id');$changed=false;
  foreach($package['requirements'] as &$need){
   $ref=$need['reference'];if($ref===null)continue;
   $valid=(int)($ref['confirmed_by']??0)===(int)$p->owner_id;$path=null;
   if(($ref['type']??'')==='confirmed_fact')$valid=$valid&&trim($ref['value']??'')!=='';
   elseif(($ref['type']??'')==='project_asset'){
    $asset=$assets->get($ref['id']??'');$root=realpath(\Illuminate\Support\Facades\Storage::disk('local')->path(''));
    if($asset&&str_starts_with($asset->mime,'image/')){$path=realpath(\Illuminate\Support\Facades\Storage::disk('local')->path($asset->path));if(!$root||!$path||!str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR)))$path=null;}
    $valid=$valid&&$path&&is_file($path)&&hash_file('sha256',$path)===($ref['sha256']??null);
   }elseif(($ref['type']??'')==='catalog_image'){
    $index=$ref['index']??-1;$path=$this->catalogPath($p,(int)$index);
    $valid=$valid&&($p->snapshot['images'][$index]['path']??null)===($ref['path']??null)&&$path&&hash_file('sha256',$path)===($ref['sha256']??null);
   }else $valid=false;
   if(!$valid){$need['reference']=null;$need['status']='reference_invalid';$changed=true;}
  }unset($need);
  if($changed){$package['image_authorization']=null;foreach($package['outputs'] as &$output){$output['status']='planned';$output['asset_id']=null;$output['media_library_id']=null;$output['generation_run_id']=null;$output['approved_at']=null;$output['source_reference_ids']=array_column(array_values(array_filter($package['requirements'],fn($n)=>in_array($n['id'],$output['requirement_ids'],true)&&$n['reference']!==null)),'id');}unset($output);}
  foreach($package['outputs'] as &$output){
   if(($output['status']??'')!=='approved')continue;
   $asset=$assets->get($output['asset_id']??'');$run=\App\Models\MarketingAiRun::where('project_id',$p->id)->where('action','material_images')->find($output['generation_run_id']??'');
   $record=collect($run?->usage['outputs']??[])->first(fn($o)=>$o['id']===$output['id']);
   $media=\App\Models\CmsMediaAsset::find($output['media_library_id']??'');
   $path=$asset?\Illuminate\Support\Facades\Storage::disk('local')->path($asset->path):null;
   $valid=$path&&is_file($path)&&($run?->usage['provider']??'')==='openai'&&($run?->usage['contract']??'')===MarketingMaterialImages::CONTRACT&&$record&&$record['status']==='approved'&&($record['design']['version']??'')===MarketingMaterialImageDesign::VERSION&&$record['asset_id']===$asset->id&&($output['approved_by']??null)===$p->owner_id&&hash_file('sha256',$path)===($output['sha256']??'')&&$media&&$media->checksum===$output['sha256']&&\Illuminate\Support\Facades\Storage::disk($media->disk)->exists($media->path)&&hash_file('sha256',\Illuminate\Support\Facades\Storage::disk($media->disk)->path($media->path))===$output['sha256'];
   if(!$valid){$output['status']='invalid';$output['approved_at']=null;}
  }unset($output);
  return $this->status($package);
 } private function fail(string $message):never{throw ValidationException::withMessages(['materials'=>$message]);}
}
