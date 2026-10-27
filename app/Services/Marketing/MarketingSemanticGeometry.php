<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoFilm;use Illuminate\Support\Facades\Storage;
class MarketingSemanticGeometry {
 public function audit(MarketingVideoFilm $base,array $compiled,string $runId):array {
  $effects=array_values(array_filter(($compiled['state']['emphasis']??[]),fn($e)=>isset($e['grounding'])));if(!$effects)return $compiled;
  foreach($effects as &$e){$e['grounding']['avoidance']=app(MarketingSemanticCompiler::class)->avoidance($compiled['state'],$e['scene_number']);}unset($e);
  $file='marketing-semantic/'.$runId.'/geometry.json';Storage::disk('local')->put($file,json_encode(['effects'=>$effects,'scenes'=>app(MarketingSubtitleService::class)->scenes($base),'framing'=>$compiled['state']['framing']],JSON_THROW_ON_ERROR));
  $out=app(MarketingMediaTools::class)->run([(string)config('marketing.production.node_binary'),base_path('resources/marketing-packaging/audit-grounding.mjs'),Storage::disk('local')->path($file)],30);$checked=array_column(json_decode($out,true,64,JSON_THROW_ON_ERROR),null,'scene_number');$dropped=[];
  foreach($compiled['state']['emphasis'] as &$effect)if(isset($effect['grounding'])){$ranges=$checked[$effect['scene_number']]['intervals']??[];if(!$ranges){$dropped[]=$effect['scene_number'];$compiled['skipped'][]=['scene_number'=>$effect['scene_number'],'reason'=>'最终构图下无法持续避开人物、字幕和卡片，未添加强调'];}else{$g=$effect['grounding'];$g['avoidance']=app(MarketingSemanticCompiler::class)->avoidance($compiled['state'],$effect['scene_number']);$g['intervals']=$ranges;$effect['grounding']=MarketingSemanticProof::seal($g);}}unset($effect);
  $compiled['state']['emphasis']=array_values(array_filter(($compiled['state']['emphasis']??[]),fn($e)=>!in_array($e['scene_number'],$dropped,true)));foreach($compiled['fallback_emphasis']??[] as $old)if(in_array($old['scene_number'],$dropped,true))$compiled['state']['emphasis'][]=$old;$compiled['applied']=array_values(array_filter($compiled['applied'],fn($a)=>$a['kind']!=='grounded_emphasis'||!in_array($a['scene_number'],$dropped,true)));return $compiled;
 }
}
