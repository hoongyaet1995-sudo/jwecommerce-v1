<?php
namespace App\Services\Marketing;
use App\Models\MarketingVideoClip;
use Illuminate\Support\Facades\Storage;

/** Local probe/ASR/frame evidence, independent from LLM decisions and durable claims. */
class MarketingEditingLocalMedia {
 /** Independent acoustic envelope: missing ASR words cannot make audible material disposable. */
 public static function audioProtection(array $e):array {
  if(!($e['audio_present']??false))return [];$n=(int)($e['frame_count']??0);$cursor=0;$open=null;$last=0;$active=[];
  foreach($e['silence_intervals']??[] as $s){$at=max(0,min($n,(int)($s['frame']??0)));if($at<$last)return [['in_frame'=>0,'out_frame'=>$n,'reason'=>'uncertain_audio']];$last=$at;
   if(($s['kind']??'')==='start'&&$open===null){if($at>$cursor)$active[]=[$cursor,$at];$open=$at;}
   elseif(($s['kind']??'')==='end'&&$open!==null){$cursor=$at;$open=null;}
   else return [['in_frame'=>0,'out_frame'=>$n,'reason'=>'uncertain_audio']];
  }
  if($open===null&&$cursor<$n)$active[]=[$cursor,$n];
  return array_map(fn($a)=>['in_frame'=>max(0,$a[0]-5),'out_frame'=>min($n,$a[1]+9),'reason'=>'non_silent_audio_with_handles'],$active);
 }
 public function analyze(MarketingVideoClip $clip,string $directory):array {
  $tools=app(MarketingMediaTools::class);$disk=Storage::disk('local');$disk->makeDirectory($directory);$path=app(MarketingVideoFiles::class)->file($clip->raw_path,$clip->raw_sha256);$probe=$tools->probe($path);
  $v=collect($probe['streams']??[])->firstWhere('codec_type','video');$audio=collect($probe['streams']??[])->contains('codec_type','audio');$rawFrames=(int)($v['nb_read_frames']??0);$frames=$clip->input['seconds']*24;
  abort_unless($audio===MarketingClipSource::hasAudio($clip),422,'实际片段音轨与已确认声音安排不一致。');
  abort_unless($v&&in_array($v['r_frame_rate']??'', ['24/1','24'])&&$rawFrames>=$frames&&$rawFrames-$frames<=3,422,'实际视频帧数或帧率与片段不一致。');
  $words=[];$silences=[];$similarity=1.0;$transcript='';
  if($audio&&trim($clip->input['spoken_text']??'')!==''){
   $python=(string)config('marketing.editing.python_binary');$models=(string)config('marketing.editing.whisper_model_dir');
   if(!is_file($python)||!is_file($models.'/small.pt'))throw new MarketingProductionFailure('editing_asr_not_configured','blocked');
   $wav=$disk->path($directory.'/speech.wav');$json=$disk->path($directory.'/speech.json');
   try{
    $diag=$tools->runCaptured([(string)config('marketing.production.ffmpeg_binary'),'-hide_banner','-y','-i',$path,'-vn','-af','silencedetect=noise=-35dB:d=0.25','-ar','16000','-ac','1','-c:a','pcm_s16le',$wav],60);
    preg_match_all('/silence_(start|end): ([0-9.]+)/',$diag['stderr'],$matches,PREG_SET_ORDER);foreach($matches as $match)$silences[]=['kind'=>$match[1],'frame'=>(int)round((float)$match[2]*24)];
    $tools->run([$python,base_path('resources/marketing-renderer/workspace-video/analyze.py'),'--input',$wav,'--output',$json,'--model-dir',$models,'--script',$clip->input['spoken_text']],300,true);
    $asr=json_decode(file_get_contents($json),true,32,JSON_THROW_ON_ERROR);$words=$asr['words'];$similarity=(float)$asr['script_similarity'];$transcript=$asr['transcript'];
   }finally{if(is_file($wav))unlink($wav);}
  }
  $intervals=[];foreach($words as $w){$a=max(0,(int)floor($w['start']*24)-5);$b=min($frames,(int)ceil($w['end']*24)+9);if($b>$a)$intervals[]=['in_frame'=>$a,'out_frame'=>$b,'reason'=>'speech_with_handles'];}
  $acoustic=self::audioProtection(['audio_present'=>$audio,'frame_count'=>$frames,'silence_intervals'=>$silences]);$intervals=array_merge($intervals,$acoustic);
  $speechEnd=$words?min($frames,(int)ceil(max(array_column($words,'end'))*24)):0;
  if($acoustic)$speechEnd=max($speechEnd,max(array_column($acoustic,'out_frame'))-9);
  $hasScript=trim($clip->input['spoken_text']??'')!=='';$safeSpeech=!$hasScript||($words&&$similarity>=.6);
  $reading=min($frames,max(24,(int)ceil(mb_strwidth($clip->input['subtitle']??'')/16*24)));
  $candidate=$safeSpeech?min($frames,max($reading,$speechEnd+9)): $frames;
  $candidateIn=$safeSpeech&&$words?max(0,(int)floor(min(array_column($words,'start'))*24)-5):0;if($acoustic)$candidateIn=min($candidateIn,min(array_column($acoustic,'in_frame')));
  $points=array_unique([0,max(0,$candidateIn-4),$candidateIn,min($frames-1,$candidateIn+4),max(0,$candidate-5),max(0,$candidate-1),min($frames-1,$candidate+5),$frames-1]);sort($points);$samples=[];
  foreach($points as $frame){$relative=$directory.'/frame-'.$frame.'.jpg';$tools->run([(string)config('marketing.production.ffmpeg_binary'),'-v','error','-y','-ss',number_format($frame/24,6,'.',''),'-i',$path,'-frames:v','1',$disk->path($relative)],60,true);$samples[]=['frame'=>$frame,'path'=>$relative,'sha256'=>hash_file('sha256',$disk->path($relative))];}
  return ['fps'=>24,'frame_count'=>$frames,'width'=>$v['width'],'height'=>$v['height'],'audio_present'=>$audio,'speech_intervals'=>$intervals,'speech_end_frame'=>$speechEnd,'words'=>$words,'silence_intervals'=>$silences,'transcript'=>$transcript,'script_similarity'=>$similarity,'safe_speech_timing'=>(bool)$safeSpeech,'candidate_in_frame'=>$candidateIn,'candidate_out_frame'=>$candidate,'frame_samples'=>$samples,'protected_intervals'=>$intervals,'minimum_frames'=>$reading,'method'=>'ffprobe+silencedetect+local-whisper-small','limitations'=>['ASR仅作时间定位，不替换已确认台词或判定同音发音错误。','画面仅抽样检查，不代表逐帧观看。']];
 }
}
