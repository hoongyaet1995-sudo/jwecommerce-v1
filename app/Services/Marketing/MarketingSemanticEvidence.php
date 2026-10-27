<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoFilm};use Illuminate\Support\Facades\Storage;
class MarketingSemanticEvidence {
 public const VERSION='semantic-one-click-v1';
 public function identity(MarketingVideoFilm $film):array {$facts=app(MarketingSemanticFacts::class)->catalogue($film);$rows=app(MarketingSubtitleService::class)->scenes($film);foreach($rows as &$row)unset($row['raw_url'],$row['original_url']);unset($row);$out=['contract'=>self::VERSION,'film_id'=>$film->id,'source_sha256'=>$film->output_sha256,'recipe_digest'=>MarketingEditingDigest::hash($film->input),'facts_digest'=>$facts['digest'],'timeline_digest'=>MarketingEditingDigest::hash($rows)];$out['digest']=MarketingEditingDigest::hash($out);return $out;}
 public function points(array $rows):array {
  abort_unless(count($rows)>0&&count($rows)<=12,422);$out=[];$extra=[];
  foreach($rows as $r){abort_unless($r['frames']>=3,422);$n=$r['number'];$anchors=[0,(int)floor(($r['frames']-1)/2),$r['frames']-1];foreach(array_unique($anchors) as $frame)$out[]=['scene_number'=>$n,'frame'=>$frame,'raw_frame'=>$frame+($r['in_frame']??0)];$start=min(24,max(0,$r['frames']-37));$points=[];foreach([0,12,24,36] as $offset){$frame=min($r['frames']-1,$start+$offset);if(!in_array($frame,$anchors,true))$points[]=['scene_number'=>$n,'frame'=>$frame,'raw_frame'=>$frame+($r['in_frame']??0)];}$extra[]=$points;}
  for($i=0;$i<4&&count($out)<36;$i++)foreach($extra as $list)if(isset($list[$i])&&count($out)<36)$out[]=$list[$i];
  usort($out,fn($a,$b)=>[$a['scene_number'],$a['frame']]<=>[$b['scene_number'],$b['frame']]);return $out;
 }
 public function collect(MarketingVideoFilm $film,string $runId):array {
  $disk=Storage::disk('local');$dir='marketing-semantic/'.$runId;$disk->makeDirectory($dir);$rows=app(MarketingSubtitleService::class)->scenes($film);$content=app(MarketingFilmPackaging::class)->content($film);$samples=[];$tools=app(MarketingMediaTools::class);$files=app(MarketingVideoFiles::class);$bin=(string)config('marketing.production.speed_ffmpeg_binary');
  foreach($this->points($rows) as $n=>$point){$source=$content->input['scenes'][$point['scene_number']-1];$raw=$files->file($source['raw_path'],$source['raw_sha256']);$path=$dir.'/sample-'.($n+1).'.jpg';$tools->run([$bin,'-v','error','-y','-ss',sprintf('%.8f',$point['raw_frame']/24),'-i',$raw,'-frames:v','1','-vf','scale=576:1024',$disk->path($path)],45,true);$samples[]=$point+['id'=>'S'.($n+1),'clip_id'=>$source['clip_id'],'raw_sha256'=>$source['raw_sha256'],'path'=>$path,'sha256'=>hash_file('sha256',$disk->path($path))];}
  $references=[];$p=MarketingProject::findOrFail($film->project_id);
  // Only the confirmed product reference is used, not arbitrary employee uploads or presenters.
  if(app(MarketingPackagingAssets::class)->product($p)){$image=app(MarketingOverlays::class)->image($p,'product');$relative=$dir.'/reference-product.'.$image['extension'];abort_unless(copy($image['path'],$disk->path($relative)),409);$references[]=['id'=>'product','path'=>$relative,'sha256'=>$image['sha256'],'mime'=>$image['mime']];}
  foreach($rows as &$row)unset($row['raw_url'],$row['original_url']);unset($row);
  $e=['identity'=>$this->identity($film),'facts'=>app(MarketingSemanticFacts::class)->catalogue($film),'scenes'=>$rows,'samples'=>$samples,'references'=>$references,'current'=>app(MarketingOptimizationPlan::class)->baseline($film),'limits'=>'RAW unframed samples; local SOURCE frames at24fps. Not whole-video viewing or listening. Visual observations do not authorize commercial facts.'];
  $disk->put($dir.'/evidence.json',json_encode($e,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));return $e;
 }
}
