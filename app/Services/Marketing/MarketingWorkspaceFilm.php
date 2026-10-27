<?php
namespace App\Services\Marketing;
use App\Models\{MarketingVideoFilm,MarketingVideoClip,MarketingProject};
use App\Jobs\ProcessMarketingWorkspaceVideo;
use Illuminate\Support\Facades\{DB,Cache};

class MarketingWorkspaceFilm {
 public const VERSION='workspace-film-cuts-v1';
 public function __construct(private MarketingClipSource $source,private MarketingVideoFiles $files){}
 public function inputs(MarketingProject $p,array $ids):array{
  $doc=$p->currentRevision->document;$n=count($doc['scenes']);abort_unless(count($ids)===$n&&count(array_unique($ids))===$n,422,'请先完成每一段视频，再确认整片合成。');$found=MarketingVideoClip::where('project_id',$p->id)->where('owner_id',$p->owner_id)->whereIn('id',$ids)->get()->keyBy('id');$scenes=[];
  foreach($ids as $i=>$id){$c=$found->get($id);abort_unless($c&&$c->scene_number===$i+1&&$c->status==='completed',422,'片段缺少、未完成或顺序不一致。');$this->source->assertCurrent($c);$this->files->file($c->raw_path,$c->raw_sha256);$this->files->file($c->output_path,$c->output_sha256);$scenes[]=['clip_id'=>$c->id,'source_fingerprint'=>$c->fingerprint,'raw_path'=>$c->raw_path,'raw_sha256'=>$c->raw_sha256,'output_sha256'=>$c->output_sha256,'seconds'=>$c->input['seconds'],'subtitle'=>$c->input['subtitle'],'with_audio'=>MarketingClipSource::hasAudio($c)];}
  $joins=$doc['transition_plan']['boundaries']??[];abort_unless(count($joins)===max(0,$n-1),422,'请先补齐相邻段落的衔接设计。');foreach($joins as $i=>$e)abort_unless($e['from']===$i+1&&$e['to']===$i+2&&in_array($e['method'],['cut','cutaway','match_action'],true)&&$e['transition_ms']===0&&in_array($e['audio']['strategy'],['cut','pause','continuous'],true),422,'当前本地执行支持直接切镜与片段原声衔接；淡化、提前入声或延后出声需要先完成对应适配。');
  abort_unless(array_sum(array_column($scenes,'seconds'))===$doc['duration']&&$doc['duration']<=120,422,'片段合计时长与分镜不一致。');$cta=($p->preferences['subtitles']??true)?($doc['cta']??''):'';abort_if(mb_strwidth($cta,'UTF-8')>80,422,'结尾行动提示过长，请先调整。');
  $input=['version'=>self::VERSION,'project_id'=>$p->id,'scenes'=>$scenes,'joins'=>$joins,'cta'=>$cta,'duration'=>$doc['duration'],'publication_authorized'=>false];return ['input'=>$input,'fingerprint'=>hash('sha256',json_encode($input,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
 }
 public function prepare(string $project,int $owner,int $version,array $ids):MarketingVideoFilm{
  return DB::transaction(function()use($project,$owner,$version,$ids){$this->source->project($project,$owner);$p=app(MarketingWorkflow::class)->locked($project,$owner,$version);$built=$this->inputs($p,$ids);$old=MarketingVideoFilm::where('project_id',$project)->where('fingerprint',$built['fingerprint'])->first();if($old)return $old;$film=MarketingVideoFilm::create(['project_id'=>$project,'owner_id'=>$owner,'revision_id'=>$p->current_revision_id,'fingerprint'=>$built['fingerprint'],'status'=>'queued','input'=>$built['input'],'result'=>['composition_external_api_cost'=>0,'new_generation_requests'=>0,'publication_authorized'=>false,'scope_confirmed_at'=>now()->toIso8601String()]]);app(MarketingToolTasks::class)->enqueue($film);return $film;});
 }
 public function assertCurrent(MarketingVideoFilm $film):MarketingProject{
  if(\App\Models\MarketingStoryboardWorkspace::where('project_id',$film->project_id)->exists()){
   $snapshot=app(MarketingStoryboardWorkspace::class)->snapshot($film->project_id,$film->owner_id);
   abort_unless(app(MarketingStoryboardVersions::class)->filmBinding($film,$snapshot)['current'],409,'此成片属于历史工作稿；原片可查看，新稿需重新交接制作。');
  }
  if(($film->input['version']??'')===MarketingStoryboardProduction::FILM)return app(MarketingStoryboardProduction::class)->assertFilmCurrent($film);
  if(($film->input['version']??'')===MarketingFilmPackaging::VERSION)return app(MarketingFilmPackaging::class)->assertCurrent($film);
  if(($film->input['version']??'')===MarketingEditPlanService::VERSION)return app(MarketingEditPlanService::class)->assertFilmCurrent($film);
  abort_unless(($film->input['version']??'')===self::VERSION,409,'无法识别成片配方。');
  $p=$this->source->project($film->project_id,$film->owner_id);try{$fresh=$this->inputs($p,array_column($film->input['scenes'],'clip_id'));}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){abort(409,'整片使用的分镜、片段或授权已变化，请重新准备整片。');}abort_unless(hash_equals($film->fingerprint,$fresh['fingerprint']),409,'整片来源或衔接安排已变化，旧版本保留。');return $p;
 }
 public function process(string $id):void{
  $lock=Cache::lock('workspace-film:'.$id,1000);if(!$lock->get())return;
  try{$f=MarketingVideoFilm::findOrFail($id);if($f->status!=='queued')return;$this->assertCurrent($f);$f->update(['status'=>'rendering']);$media=app(MarketingToolTasks::class)->execute($f);$this->assertCurrent($f);$f->update(['status'=>'completed','output_path'=>$media['path'],'output_sha256'=>$media['sha256'],'result'=>array_replace($f->result??[],['render'=>$media]),'error_message'=>null]);}
  catch(\Throwable $e){report($e);if(isset($f))$f->update(['status'=>'failed','error_message'=>'本地整片合成未完成或输入已变化。已有片段保留；仅重试本地合成，不重新收费生成。']);}
  finally{$lock->release();}
 }
 public function retry(string $project,int $owner,string $id):MarketingVideoFilm{
  return DB::transaction(function()use($project,$owner,$id){$this->source->project($project,$owner);$f=MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->lockForUpdate()->findOrFail($id);abort_unless(in_array($f->status,['failed','unknown']),409,'当前成片不需要重试。');$this->assertCurrent($f);$f->update(['status'=>'queued','error_message'=>null]);app(MarketingToolTasks::class)->enqueue($f,true);return $f;});
 }
 public function approve(string $project,int $owner,string $id,string $hash):MarketingVideoFilm{
  return DB::transaction(function()use($project,$owner,$id,$hash){$this->source->project($project,$owner);$f=MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->lockForUpdate()->findOrFail($id);$this->assertCurrent($f);abort_unless($f->status==='completed'&&hash_equals($f->output_sha256??'',$hash),409,'成片版本已变化，请播放当前版本后确认。');$this->files->file($f->output_path,$f->output_sha256);if(!$f->approved_at)$f->update(['approved_at'=>now(),'approved_sha256'=>$hash]);return $f;});
 }
 public function preview(string $project,int $owner,string $id):string{
  $p=$this->source->project($project,$owner);$f=MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->findOrFail($id);abort_unless($f->status==='completed',404);foreach($f->input['scenes'] as $s){$c=MarketingVideoClip::where('project_id',$project)->findOrFail($s['clip_id']);$this->source->path($p,$c->input['image']['asset_id'],$c->input['image']['sha256']);}return $this->files->file($f->output_path,$f->output_sha256);
 }
 public function download(string $project,int $owner,string $id):string{
  $this->source->project($project,$owner);$f=MarketingVideoFilm::where('project_id',$project)->where('owner_id',$owner)->findOrFail($id);abort_unless($f->status==='completed'&&$f->approved_at&&hash_equals($f->approved_sha256??'',$f->output_sha256??''),409,'请先审阅并批准当前成片版本。');return $this->files->file($f->output_path,$f->output_sha256);
 }
}
