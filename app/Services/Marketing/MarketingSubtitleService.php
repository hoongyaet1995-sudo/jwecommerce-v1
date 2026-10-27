<?php
namespace App\Services\Marketing;

use App\Models\{MarketingProject,MarketingVideoFilm,MarketingVideoClip};
use App\Jobs\PrepareMarketingSubtitles;
use Illuminate\Support\Facades\{DB,Storage,Validator};

/** Confirmed copy + local audio alignment. Browser settings never supply timing or paths. */
class MarketingSubtitleService {
 public const VERSION='dynamic-subtitles-v2';
 public function settings(array $s):array{
  return Validator::make($s,['mode'=>'required|in:plain,highlight,phrase','size'=>'required|in:small,medium,large','position'=>'required|in:lower,raised','color'=>'required|in:#d2f36b,#ffdc78,#9edbf8','scope'=>'required|in:all,scene','scene'=>'required|integer|min:1|max:12'])->validate();
 }
 public function content(MarketingVideoFilm $f):MarketingVideoFilm{return app(MarketingFilmPackaging::class)->content($f);}
 public function scenes(MarketingVideoFilm $f):array{
  $content=$this->content($f);$rows=[];$start=0;
  foreach($content->input['scenes'] as $i=>$s){
   $c=MarketingVideoClip::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->findOrFail($s['clip_id']);
   $scene=$c->input['scene'];$title=trim($scene['title']??$scene['name']??$c->input['director']['purpose']??'');if($title==='')$title='第'.($i+1).'段';elseif(mb_strlen($title)>18)$title=mb_substr($title,0,18).'…';
   $in=(int)($s['in_frame']??0);$frames=(int)($s['frames']??$s['seconds']*24);
   $rows[]=['number'=>$i+1,'title'=>$title,'clip_id'=>$c->id,'raw_sha256'=>$s['raw_sha256'],'in_frame'=>$in,'out_frame'=>$in+$frames,'frames'=>$frames,'start_frame'=>$start,'end_frame'=>$start+$frames,'spoken_text'=>trim($c->input['spoken_text']??''),'original_subtitle'=>$s['subtitle'],'raw_url'=>route('marketing.workspace.subtitles.raw',[$f->project_id,$c->id]),'original_url'=>route('marketing.workspace.clips.preview',[$f->project_id,$c->id]),'cta'=>$i===count($content->input['scenes'])-1?($content->input['cta']??''):'','cta_hold_frames'=>$content->input['cta_hold_frames']??72];$start+=$frames;
  }return $rows;
 }
 private function sourceDigest(MarketingVideoFilm $f):string{$rows=$this->scenes($f);foreach($rows as &$r)unset($r['raw_url'],$r['original_url']);unset($r);return MarketingEditingDigest::hash([self::VERSION,$this->content($f)->fingerprint,$rows]);}
 private function alignment(MarketingVideoFilm $f):array{
  $digest=$this->sourceDigest($f);$state=$f->result['subtitle_alignment']??[];
  if(($state['source_digest']??'')===$digest)return $state;
  $plan=$f->input['subtitles']??[];
  if(($plan['version']??'')===self::VERSION&&($plan['source_digest']??'')===$digest)return ['status'=>'ready','source_digest'=>$digest,'scenes'=>$plan['scenes'],'method'=>'reused_verified_subtitle_plan'];
  return ['status'=>'missing'];
 }
 public function config(MarketingVideoFilm $f):array{
  $rows=$this->scenes($f);$state=$this->alignment($f);
  return ['config_url'=>route('marketing.workspace.subtitles.config',[$f->project_id,$f->id]),'film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'comparison_url'=>route('marketing.workspace.films.preview',[$f->project_id,$f->id]),'scenes'=>$rows,'alignment'=>$state??['status'=>'missing'],'settings'=>$f->input['subtitles']['settings']??['mode'=>'highlight','size'=>'medium','position'=>'lower','color'=>'#d2f36b','scope'=>'all','scene'=>1],'content_frames'=>array_sum(array_column($rows,'frames')),'total_frames'=>(int)round($f->input['duration']*24),'fps'=>24,'prepare_url'=>route('marketing.workspace.subtitles.prepare',[$f->project_id,$f->id]),'adopt_url'=>route('marketing.workspace.subtitles.adopt',[$f->project_id,$f->id])];
 }
 public function prepare(MarketingVideoFilm $f):void{
  app(MarketingWorkspaceFilm::class)->assertCurrent($f);$digest=$this->sourceDigest($f);
  DB::transaction(function()use($f,$digest){$locked=MarketingVideoFilm::lockForUpdate()->findOrFail($f->id);$state=$locked->result['subtitle_alignment']??[];
   if(($state['source_digest']??'')===$digest&&in_array($state['status']??'',['queued','running','ready'],true))return;
   $r=$locked->result??[];$r['subtitle_alignment']=['status'=>'queued','source_digest'=>$digest,'queued_at'=>now()->toIso8601String()];$locked->update(['result'=>$r]);PrepareMarketingSubtitles::dispatch($f->id,$digest)->afterCommit();
  });
 }
 private function state(string $id,array $state):void{DB::transaction(function()use($id,$state){$f=MarketingVideoFilm::lockForUpdate()->findOrFail($id);$r=$f->result??[];$r['subtitle_alignment']=array_replace($r['subtitle_alignment']??[],$state);$f->update(['result'=>$r]);});}
 public function align(string $id,string $digest):void{
  $f=MarketingVideoFilm::findOrFail($id);if(($f->result['subtitle_alignment']['status']??'')!=='queued')return;
  try{
   abort_unless(hash_equals($digest,$this->sourceDigest($f)),409);app(MarketingWorkspaceFilm::class)->assertCurrent($f);$this->state($id,['status'=>'running','started_at'=>now()->toIso8601String(),'completed_scenes'=>0,'total_scenes'=>count($this->scenes($f)),'error'=>null]);
   $tools=app(MarketingMediaTools::class);$disk=Storage::disk('local');$rows=$this->scenes($f);$dir='marketing-subtitles/'.$id;$disk->makeDirectory($dir);$aligned=[];
   foreach($rows as $row){
    $c=MarketingVideoClip::findOrFail($row['clip_id']);$path=app(MarketingVideoFiles::class)->file($c->raw_path,$row['raw_sha256']);$words=[];
    if($row['spoken_text']!==''){
     $wav=$disk->path($dir.'/'.$row['number'].'.wav');$json=$disk->path($dir.'/'.$row['number'].'.json');
     try{
      $tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-i',$path,'-t',(string)$c->input['seconds'],'-vn','-ar','16000','-ac','1','-c:a','pcm_s16le',$wav],60,true);
      $tools->run([(string)config('marketing.editing.python_binary'),base_path('resources/marketing-renderer/subtitles/align.py'),'--input',$wav,'--output',$json,'--model-dir',(string)config('marketing.editing.whisper_model_dir'),'--script',$row['spoken_text'],'--language',$this->language($c->input)],300,true);
      $data=json_decode(file_get_contents($json),true,32,JSON_THROW_ON_ERROR);$words=$data['words'];
     }finally{if(is_file($wav))unlink($wav);}
    }
    $last=0;foreach($words as $word){abort_unless(is_string($word['text'])&&$word['start_frame']>=$last&&$word['end_frame']>$word['start_frame']&&$word['end_frame']<=($c->input['seconds']*24),422,'语音时间无法可靠定位。');$last=$word['start_frame'];}
    abort_unless($row['spoken_text']===''||count($words)>0,422,'没有可靠口播时间。');$row['words']=$words;$aligned[]=$row;$this->state($id,['completed_scenes'=>count($aligned)]);
   }
   $fresh=$f->fresh();abort_unless(hash_equals($digest,$this->sourceDigest($fresh)),409);app(MarketingWorkspaceFilm::class)->assertCurrent($fresh);
   $this->state($id,['status'=>'ready','scenes'=>$aligned,'method'=>'local-whisper-cross-attention-dtw-confirmed-script','completed_at'=>now()->toIso8601String(),'error'=>null]);
  }catch(\Throwable $e){report($e);$this->state($id,['status'=>'failed','error'=>'字幕同步准备未完成，原片保留。请检查口播与素材后重新准备。','automatic_retry'=>false,'error_code'=>$e instanceof MarketingProductionFailure?$e->getMessage():'subtitle_alignment_failed','failed_at'=>now()->toIso8601String()]);}
 }
 private function language(array $input):string{
  $language=strtolower($input['configuration']['audio']['language']??$input['language']??'');
  if(str_starts_with($language,'zh'))return 'zh';
  if(str_starts_with($language,'ms')||$language==='malay')return 'ms';
  return 'en';
 }
 public function plan(MarketingVideoFilm $f,array $settings):array{
  $s=$this->settings($settings);$state=$this->alignment($f);
  abort_unless(($state['status']??'')==='ready'&&($state['source_digest']??'')===$this->sourceDigest($f),409,'请先完成当前版本的字幕同步准备。');
  $rows=$state['scenes'];abort_unless(isset($rows[$s['scene']-1]),422,'段落不存在。');
  return ['version'=>self::VERSION,'reference_film_id'=>$f->id,'reference_sha256'=>$f->output_sha256,'content_film_id'=>$this->content($f)->id,'content_sha256'=>$this->content($f)->output_sha256,'source_digest'=>$state['source_digest'],'settings'=>$s,'scenes'=>$rows];
 }
 public function assertPlan(MarketingVideoFilm $f,array $plan):void{
  $this->settings($plan['settings']??[]);$content=$this->content($f);$reference=MarketingVideoFilm::where('project_id',$f->project_id)->where('owner_id',$f->owner_id)->where('status','completed')->findOrFail($plan['reference_film_id']??'');app(MarketingVideoFiles::class)->file($reference->output_path,$plan['reference_sha256']??'');
  abort_unless(($plan['version']??'')===self::VERSION&&($plan['content_film_id']??'')===$content->id&&($plan['content_sha256']??'')===$content->output_sha256&&($plan['source_digest']??'')===$this->sourceDigest($f),409,'字幕来源已变化，请重新准备。');
 }
 public function adopt(MarketingVideoFilm $f,string $request,string $hash,array $settings):MarketingVideoFilm{
  abort_unless(hash_equals($f->output_sha256??'',$hash),409,'所选成片已变化。');app(MarketingWorkspaceFilm::class)->assertCurrent($f);$plan=$this->plan($f,$settings);
  $p=MarketingProject::findOrFail($f->project_id);$a=app(MarketingPackagingAssets::class);try{$brand=$a->brand($p);}catch(\Throwable){$brand=['name'=>app(\App\Support\ShopBrand::class)->name(),'sha256'=>null];}
  $packaging=$f->input['settings']??['enabled'=>false,'template'=>'minimal','duration'=>3,'company'=>$brand['name'],'cta'=>$f->input['cta']??'','track'=>null,'volume'=>18,'duck'=>true,'fade'=>true,'logo_sha256'=>$brand['sha256']];
  return app(MarketingFilmPackaging::class)->prepare($p->id,$p->owner_id,$request,$f->id,$hash,$packaging,$plan);
 }
}
