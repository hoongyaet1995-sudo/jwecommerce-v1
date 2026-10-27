<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingAsset};
use Illuminate\Support\Facades\{Validator,Storage};
use Illuminate\Validation\ValidationException;
class MarketingOverlays {
 public function image(MarketingProject $p,string $id):array{
  if($id==='product'){$a=app(MarketingPackagingAssets::class)->product($p);abort_unless($a,422,'当前项目没有可用产品图。');$path=$a['path'];$name='现有产品参考图';}
  else{$a=MarketingAsset::where('project_id',$p->id)->where('authorized',true)->findOrFail($id);$path=Storage::disk('local')->path($a->path);$name=$a->name;}
  abort_unless(is_file($path)&&filesize($path)<=10485760,422,'图片文件不可用。');$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$size=@getimagesize($path);abort_unless(in_array($mime,['image/png','image/jpeg','image/webp'])&&$size&&$size[0]>0&&$size[1]>0&&$size[0]*$size[1]<=40000000,422,'请选择有效的JPG、PNG或WebP图片。');
  return ['id'=>$id,'path'=>$path,'name'=>$name,'sha256'=>hash_file('sha256',$path),'ratio'=>$size[0]/$size[1],'mime'=>$mime,'extension'=>['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]];
 }
 public function catalogue(MarketingProject $p):array{
  $out=[];if(app(MarketingPackagingAssets::class)->product($p))$out[]=['id'=>'product','name'=>'现有产品参考图','url'=>route('marketing.workspace.packaging.image',[$p->id,'product'])];
  foreach(MarketingAsset::where('project_id',$p->id)->where('authorized',true)->whereIn('mime',['image/png','image/jpeg','image/webp'])->latest()->limit(30)->get() as $a)$out[]=['id'=>$a->id,'name'=>$a->name,'url'=>route('marketing.workspace.packaging.image',[$p->id,'overlay-'.$a->id])];return $out;
 }
 public function normalize(array $layers,array $rows,MarketingProject $p):array{
  Validator::make(['overlays'=>$layers],['overlays'=>'present|array|max:3','overlays.*'=>'array:id,image_id,enabled,scene_number,x,y,width,radius,border,color,start_frame,end_frame,ratio','overlays.*.id'=>'required|string|max:80|distinct|regex:/^[a-zA-Z0-9_-]+$/','overlays.*.image_id'=>'required|string|max:80','overlays.*.enabled'=>'required|boolean','overlays.*.scene_number'=>'required|integer|min:1|max:12','overlays.*.x'=>'required|numeric|between:0,100','overlays.*.y'=>'required|numeric|between:0,100','overlays.*.width'=>'required|numeric|between:15,55','overlays.*.radius'=>'required|integer|between:0,48','overlays.*.border'=>'required|integer|between:0,8','overlays.*.color'=>'required|regex:/^#[a-fA-F0-9]{6}$/','overlays.*.start_frame'=>'required|integer|min:0','overlays.*.end_frame'=>'required|integer|min:36'])->validate();$out=[];
  foreach($layers as $l){$r=collect($rows)->firstWhere('number',(int)$l['scene_number']);if(!$r||$l['end_frame']>$r['frames']||$l['end_frame']-$l['start_frame']<36)throw ValidationException::withMessages(['overlays'=>'图片显示区间须在真实片段内且至少1.50秒。']);$a=$this->image($p,$l['image_id']);$l['ratio']=$a['ratio'];$h=$l['width']*720/$a['ratio']/1280;abort_unless($h<=100&&$l['x']>=$l['width']/2-.01&&$l['x']<=100-$l['width']/2+.01&&$l['y']>=$h/2-.01&&$l['y']<=100-$h/2+.01,422,'请把叠加图片完整放在画面内。');foreach(['scene_number','radius','border','start_frame','end_frame'] as $k)$l[$k]=(int)$l[$k];$l['enabled']=(bool)$l['enabled'];$out[]=$l;}return $out;
 }
}
