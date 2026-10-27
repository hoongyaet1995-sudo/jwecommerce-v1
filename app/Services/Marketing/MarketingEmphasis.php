<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class MarketingEmphasis {
 public const VERSION='content-emphasis-v1';
 public function normalize(array $effects,array $rows):array{
 Validator::make(['emphasis'=>$effects],['emphasis'=>'present|array|max:12','emphasis.*'=>'array:scene_number,kind,x,y,size,angle,color,strength,start_frame,end_frame,grounding','emphasis.*.scene_number'=>'required|integer|min:1|max:12|distinct','emphasis.*.kind'=>'required|in:arrow,circle,spot','emphasis.*.x'=>'required|integer|between:5,95','emphasis.*.y'=>'required|integer|between:5,95','emphasis.*.size'=>'required|integer|between:10,60','emphasis.*.angle'=>'required|integer|between:0,360','emphasis.*.color'=>'required|regex:/^#[0-9a-fA-F]{6}$/','emphasis.*.strength'=>'required|integer|between:20,85','emphasis.*.start_frame'=>'required|integer|min:0','emphasis.*.end_frame'=>'required|integer|min:36'])->validate();$out=[];
 foreach($effects as $c){$row=collect($rows)->firstWhere('number',(int)$c['scene_number']);if(!$row||$c['end_frame']>$row['frames']||$c['end_frame']-$c['start_frame']<36)throw ValidationException::withMessages(['emphasis'=>'强调区间须在真实片段内，且至少1.50秒。']);if(isset($c['grounding'])&&((!is_array($c['grounding'])||!MarketingSemanticProof::valid($c['grounding']))||($c['grounding']['source_sha256']??null)!==($row['raw_sha256']??null)||($c['grounding']['scene_number']??null)!==$c['scene_number']))throw ValidationException::withMessages(['emphasis'=>'物品定位凭据已变化。']);foreach(['scene_number','x','y','size','angle','strength','start_frame','end_frame'] as $k)$c[$k]=(int)$c[$k];$out[]=$c;}usort($out,fn($a,$b)=>$a['scene_number']<=>$b['scene_number']);return $out;
 }
}
