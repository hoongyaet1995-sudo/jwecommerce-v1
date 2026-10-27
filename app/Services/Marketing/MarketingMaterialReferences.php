<?php
namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingRevision;
use App\Models\MarketingAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** Reference collection only. Uploaded files cannot populate production outputs. */
class MarketingMaterialReferences
{
 public function save(string $id,int $actor,int $version,string $need,string $request,array $input,?UploadedFile $file=null):MarketingRevision
 {
  $stored=null;
  try{return DB::transaction(function()use($id,$actor,$version,$need,$request,$input,$file,&$stored){
   $p=MarketingProject::where('owner_id',$actor)->lockForUpdate()->findOrFail($id);
   $existing=MarketingRevision::where('project_id',$id)->where('context->material_reference_request',$request)->first();
   $fingerprint=hash('sha256',json_encode([$need,$input,$file?hash_file('sha256',$file->getRealPath()):null],JSON_THROW_ON_ERROR));
   if($existing){abort_unless(($existing->context['material_reference_fingerprint']??'')===$fingerprint,409,'请求内容已改变。');return $existing;}
   $p=app(MarketingWorkflow::class)->locked($id,$actor,$version);
   $r=$p->currentRevision;abort_unless($r,422);$package=app(MarketingMaterialPackage::class)->assertFresh($p,$r);
   $index=collect($package['requirements'])->search(fn($n)=>$n['id']===$need);abort_if($index===false,404);
   $item=$package['requirements'][$index];abort_unless($item['owner']==='employee',422,'AI负责的素材不需要员工上传。');
   abort_unless(($input['permission']??false),422,'请确认资料真实且有权用于AI制备。');
   if($item['kind']==='factual_detail'){
    $answer=trim($input['answer']??'');abort_unless($answer!==''&&mb_strlen($answer)<=2000,422,'请补充该项真实资料。');
    $reference=['type'=>'confirmed_fact','value'=>$answer,'confirmed_by'=>$actor,'confirmed_at'=>now()->toIso8601String()];
   }else{
    $source=$input['source']??'';
    if($file){
     Validator::make(['image'=>$file],['image'=>'required|file|mimes:jpg,jpeg,png,webp|max:10240'])->validate();
     $size=@getimagesize($file->getRealPath());abort_unless($size&&$size[0]<=8000&&$size[1]<=8000&&$size[0]*$size[1]<=40000000&&in_array($file->getMimeType(),['image/jpeg','image/png','image/webp'],true),422,'参考图无效或尺寸过大。');
     $stored=$file->store('marketing/'.$id,'local');
     $asset=MarketingAsset::create(['project_id'=>$id,'name'=>mb_substr(basename($file->getClientOriginalName()),0,180),'path'=>$stored,'mime'=>$file->getMimeType(),'size'=>$file->getSize(),'authorized'=>true]);$source='upload:'.$asset->id;
    }
    if(str_starts_with($source,'upload:')){
     $asset=$p->assets()->where('authorized',true)->findOrFail(substr($source,7));abort_unless(str_starts_with($asset->mime,'image/')&&Storage::disk('local')->exists($asset->path),422,'参考素材文件不可用。');
     $reference=['type'=>'project_asset','id'=>$asset->id,'sha256'=>hash_file('sha256',Storage::disk('local')->path($asset->path))];
    }elseif(preg_match('/^catalog:(\d+)$/',$source,$m)){
     $image=$p->snapshot['images'][(int)$m[1]]??null;abort_unless($image&&!empty($image['path']),422,'目录参考已不存在。');
     $path=$image['path'];$candidate=realpath(public_path(ltrim($path,'/')));$public=realpath(public_path());
     if(!$candidate||!str_starts_with(strtolower($candidate),strtolower($public.DIRECTORY_SEPARATOR))){
      $root=realpath(Storage::disk('public')->path(''));$candidate=realpath(Storage::disk('public')->path($path));
      if(!$root||!$candidate||!str_starts_with(strtolower($candidate),strtolower($root.DIRECTORY_SEPARATOR)))$candidate=false;
     }
     abort_unless($candidate&&@getimagesize($candidate),422,'目录参考文件不可用。');
     $reference=['type'=>'catalog_image','index'=>(int)$m[1],'path'=>$path,'sha256'=>hash_file('sha256',$candidate)];
    }else{abort(422,'请选择现有参考图或上传所需照片。');}
    $reference+=['confirmed_by'=>$actor,'confirmed_at'=>now()->toIso8601String()];
   }
   $package['requirements'][$index]['reference']=$reference;$package['requirements'][$index]['status']='reference_provided';
   $package=app(MarketingMaterialPackage::class)->status($package);
   foreach($package['outputs'] as &$output){$output['status']='planned';$output['asset_id']=null;$output['generation_run_id']=null;$output['approved_at']=null;$output['media_library_id']=null;$output['source_reference_ids']=array_column(array_values(array_filter($package['requirements'],fn($n)=>in_array($n['id'],$output['requirement_ids'],true)&&$n['reference']!==null)),'id');}unset($output);
   $package['image_authorization']=null;
   $doc=$r->document;$doc['material_package']=$package;$context=$r->context;unset($context['production_strategy']);$context['material_reference_request']=$request;$context['material_reference_fingerprint']=$fingerprint;$context['production_recipe']='pending-tool-routing';
   $new=MarketingRevision::create(['project_id'=>$id,'actor_id'=>$actor,'number'=>(int)$p->revisions()->max('number')+1,'document'=>$doc,'context'=>$context,'parent_id'=>$r->id,'feedback'=>'已补充参考资料；未生成生产图片，未授权制作']);
   $p->update(['current_revision_id'=>$new->id,'confirmed_revision_id'=>null,'status'=>'needs_confirmation','stage'=>'storyboard','version'=>$p->version+1]);return $new;
  });}catch(\Throwable $error){if($stored)Storage::disk('local')->delete($stored);throw $error;}
 }
}
