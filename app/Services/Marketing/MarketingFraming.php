<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
/** Controlled visual transforms over raw clips; no command or media paths accepted. */
class MarketingFraming {
 public const VERSION='frame-composition-v1';
 public function normalize(array $effects,array $rows):array{
  Validator::make(['framing'=>$effects],['framing'=>'present|array|max:12','framing.*'=>'array:scene_number,mode,zoom,x,y,start_frame,end_frame','framing.*.scene_number'=>'required|integer|min:1|max:12|distinct','framing.*.mode'=>'required|in:none,fixed,push','framing.*.zoom'=>'required|numeric|between:1,1.3','framing.*.x'=>'required|integer|between:0,100','framing.*.y'=>'required|integer|between:0,100','framing.*.start_frame'=>'required|integer|min:0','framing.*.end_frame'=>'required|integer|min:36'])->validate();$out=[];
  foreach($effects as $c){$row=collect($rows)->firstWhere('number',(int)$c['scene_number']);if(!$row||$c['end_frame']>$row['frames']||$c['end_frame']-$c['start_frame']<36)throw ValidationException::withMessages(['framing'=>'构图区间须在对应真实片段内，且至少1.50秒。']);foreach(['scene_number','x','y','start_frame','end_frame'] as $k)$c[$k]=(int)$c[$k];$c['zoom']=round((float)$c['zoom'],2);$out[]=$c;}
  usort($out,fn($a,$b)=>$a['scene_number']<=>$b['scene_number']);return $out;
 }
}
