<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingAiRun,MarketingVideoClip,MarketingVideoFilm};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Read-only page state; rendering this data never starts a job or a supplier request. */
class MarketingVideoWorkbench
{
 public const LABELS=['quoted'=>'费用待确认','upload_pending'=>'等待后台处理','uploading'=>'准备生产首帧','submit_pending'=>'等待提交本段','submitting'=>'提交中','external_pending'=>'供应商制作中','download_pending'=>'等待收纳视频','downloading'=>'正在收纳视频','render_pending'=>'等待合成字幕','rendering'=>'本地合成中','completed'=>'可播放','failed'=>'需要处理','unknown'=>'结果待核对','queued'=>'等待本地合成'];
 public function data(MarketingProject $p,?int $selected=null,?string $filmId=null):array
 {
  return app(MarketingClipSource::class)->readSnapshot(fn($source)=>$this->read($p,$selected,$source,$filmId));
 }
 /** Finished-page adapter for the new storyboard contract, with no legacy source preparation. */
 public function storyboardFilmData(MarketingProject $p,?string $filmId=null):array {
  $history=MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->latest('created_at')->latest('id')->limit(30)->get();
  $film=$filmId?MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->findOrFail($filmId):$history->first();
  $snapshot=app(MarketingStoryboardWorkspace::class)->snapshot($p->id,$p->owner_id);
  if(!$filmId){$film=$history->first(fn($candidate)=>$candidate->status==='completed'&&app(MarketingStoryboardVersions::class)->filmBinding($candidate,$snapshot)['current'])??$film;}
  $binding=$film?app(MarketingStoryboardVersions::class)->filmBinding($film,$snapshot):['current'=>false,'story_version'=>null,'label'=>'暂无成片'];
  $current=false;$error=null;
  if($film&&$binding['current']){try{app(MarketingWorkspaceFilm::class)->assertCurrent($film);$current=true;}catch(HttpExceptionInterface){$error='此版本素材已变化，请回到当前分镜核对；原片保留。';}}
  elseif($film)$error='历史成片，非当前工作稿的制作结果；原片与历史批次保留。';
  $rows=[];$completed=[];foreach($film?->input['scenes']??[] as $i=>$scene){$clip=MarketingVideoClip::where('project_id',$p->id)->where('owner_id',$p->owner_id)->findOrFail($scene['clip_id']);$rows[$i+1]=['number'=>$i+1,'clip'=>$clip];if($clip->status==='completed')$completed[]=$clip->id;}
  return ['story_snapshot'=>$snapshot,'film_binding'=>$binding,'rows'=>$rows,'selected'=>1,'current'=>$rows[1]??null,'completed'=>$completed,'assembly'=>null,'assembly_error'=>null,'film'=>$film,'film_current'=>$current,'film_error'=>$error,'film_history'=>$history,'labels'=>self::LABELS,'pending'=>[],'film_pending'=>$film&&in_array($film->status,['queued','rendering'],true)];
 }
 private function read(MarketingProject $p,?int $selected,MarketingClipSource $source,?string $filmId):array
 {
  $rows=[];$completed=[];
  $records=MarketingVideoClip::where('project_id',$p->id)->latest('created_at')->latest('id')->get();
  $pilots=MarketingAiRun::where('project_id',$p->id)->where('action','single_clip_pilot')->where('status','completed')->latest('created_at')->get();
  foreach($p->currentRevision->document['scenes'] as $i=>$scene){
   $number=$i+1;$row=['number'=>$number,'scene'=>$scene,'options'=>[],'clip'=>null,'pilot'=>null,'input'=>null,'error'=>null];
   try{
    $row['options']=$source->candidates($p,$number);$built=[];
    foreach($row['options'] as $option)$built[$option['asset_id']]=$source->build($p,$number,$option['asset_id']);
    $valid=$records->filter(fn($c)=>$c->scene_number===$number&&isset($built[$c->input['image']['asset_id']])&&hash_equals($c->fingerprint,$built[$c->input['image']['asset_id']]['fingerprint']));
    $row['clip']=$valid->firstWhere('status','completed')??$valid->first(fn($c)=>in_array($c->status,array_merge(MarketingClipProduction::ACTIVE,['unknown']),true))??$valid->first();
    if($row['clip']?->status==='quoted'&&($row['clip']->expires_at->isPast()||now()->gte($row['clip']->quote['rate_valid_until'])||!hash_equals($row['clip']->connection_fingerprint,$source->connection())))$row['clip']=null;
    $row['input']=$row['clip']?->input??reset($built)['input'];
    if($row['clip']?->status==='completed')$completed[]=$row['clip']->id;
    if(!$row['clip'])foreach($pilots as $pilot){$u=$pilot->usage;$r=$p->currentRevision;if(($u['scene_number']??0)===$number&&($u['source_revision']??null)===$r->id&&($u['source_digest']??'')===hash('sha256',json_encode([$r->id,$r->document,$p->brief,$p->preferences],JSON_THROW_ON_ERROR))&&isset($built[$u['source_image']['asset_id']??''])){$row['pilot']=$pilot;$row['input']=$built[$u['source_image']['asset_id']]['input'];break;}}
   }catch(HttpExceptionInterface $e){$row['error']=$e->getMessage()?:'本段资料尚未准备好，请回到分镜与素材。';}
   $rows[$number]=$row;
  }
  $selected=isset($rows[$selected??0])?$selected:null;
  if(!$selected)foreach($rows as $n=>$row)if($row['pilot']){$selected=$n;break;}
  if(!$selected)foreach($rows as $n=>$row)if($row['clip']?->status!=='completed'){$selected=$n;break;}
  $selected??=1;$assembly=null;$assemblyError=null;$film=null;
  if(count($completed)===count($rows)&&count($rows)>0){try{$assembly=app(MarketingWorkspaceFilm::class)->inputs($p,$completed);$film=MarketingVideoFilm::where('project_id',$p->id)->where('fingerprint',$assembly['fingerprint'])->first();}catch(HttpExceptionInterface $e){$assemblyError=$e->getMessage();}}
  $history=MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->latest('created_at')->latest('id')->limit(30)->get();
  if($filmId)$film=MarketingVideoFilm::where('project_id',$p->id)->where('owner_id',$p->owner_id)->findOrFail($filmId);
  $filmCurrent=false;$filmError=null;if($film){try{app(MarketingWorkspaceFilm::class)->assertCurrent($film);$filmCurrent=true;}catch(HttpExceptionInterface $e){$filmError='此历史版本的来源已变化，可追溯查看；继续制作需回到当前分镜。';}}
  return ['rows'=>$rows,'selected'=>$selected,'current'=>$rows[$selected],'completed'=>$completed,'assembly'=>$assembly,'assembly_error'=>$assemblyError,'film'=>$film,'film_current'=>$filmCurrent,'film_error'=>$filmError,'film_history'=>$history,'labels'=>self::LABELS,
   'pending'=>array_values(array_map(fn($c)=>['kind'=>'clips','id'=>$c->id],$records->filter(fn($c)=>in_array($c->status,MarketingClipProduction::ACTIVE,true))->all())),
   'film_pending'=>$film&&in_array($film->status,['queued','rendering'],true)];
 }
}
