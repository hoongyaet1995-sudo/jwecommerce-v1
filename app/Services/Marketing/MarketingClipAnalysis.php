<?php
namespace App\Services\Marketing;
use App\Models\{MarketingClipAnalysis as ClipAnalysisRecord,MarketingVideoClip,MarketingAiRun};
use Illuminate\Support\Facades\{DB,Storage};
use App\Services\AI\ProviderFailure;

class MarketingClipAnalysis {
 public const VERSION='editing-evidence-v2';
 public function fingerprint(MarketingVideoClip $clip):string {
  app(MarketingClipSource::class)->assertCurrent($clip);$files=app(MarketingVideoFiles::class);$files->file($clip->raw_path,$clip->raw_sha256);$files->file($clip->output_path,$clip->output_sha256);
  $configuration=app(MarketingEditingVisionClient::class)->configuration();$model=(string)config('marketing.editing.whisper_model_dir').'/small.pt';
  return MarketingEditingDigest::hash([self::VERSION,$clip->id,$clip->fingerprint,$clip->raw_sha256,$clip->output_sha256,$configuration,config('marketing.editing.python_binary'),is_file($model)?[filesize($model),filemtime($model)]:null]);
 }
 public function ensure(MarketingVideoClip $clip):ClipAnalysisRecord {
  abort_unless(MarketingEditingConversation::enabled(),403);$fingerprint=$this->fingerprint($clip);
  return ClipAnalysisRecord::firstOrCreate(['project_id'=>$clip->project_id,'fingerprint'=>$fingerprint],['owner_id'=>$clip->owner_id,'clip_id'=>$clip->id,'status'=>'queued']);
 }
 public function evidence(ClipAnalysisRecord $analysis):array {
  $clip=MarketingVideoClip::where('project_id',$analysis->project_id)->where('owner_id',$analysis->owner_id)->findOrFail($analysis->clip_id);
  abort_unless($analysis->status==='completed'&&hash_equals($analysis->fingerprint,$this->fingerprint($clip)),409,'素材分析已过期或尚未完成，请重新核对。');
  $e=$analysis->evidence;$digest=$e['digest']??'';unset($e['digest']);abort_unless(hash_equals($digest,MarketingEditingDigest::hash($e)),409,'分析证据已变化，不能继续采用。');return $analysis->evidence;
 }
 public function process(string $id):void {
  $a=DB::transaction(function()use($id){$a=ClipAnalysisRecord::lockForUpdate()->findOrFail($id);if($a->status!=='queued')return null;$a->update(['status'=>'analyzing','started_at'=>now(),'error_message'=>null]);return $a;});if(!$a)return;
  $run=null;
  try{
   abort_unless(MarketingEditingConversation::enabled(),403);
   $clip=MarketingVideoClip::where('project_id',$a->project_id)->where('owner_id',$a->owner_id)->findOrFail($a->clip_id);abort_unless(hash_equals($a->fingerprint,$this->fingerprint($clip)),409);
   $local=app(MarketingEditingLocalMedia::class)->analyze($clip,'marketing-editing/'.$a->id);
   abort_unless(($local['frame_count']??0)===$clip->input['seconds']*24&&($local['fps']??0)===24,422);
   $run=MarketingAiRun::create(['project_id'=>$a->project_id,'request_id'=>(string)\Illuminate\Support\Str::uuid(),'action'=>'editing_vision','status'=>'running','reserved_usd'=>.02,'usage'=>['analysis_id'=>$a->id,'request_claimed_at'=>now()->toIso8601String(),'generation_requests'=>0]]);$a->update(['run_id'=>$run->id]);
   $images=array_map(fn($frame)=>$frame+['absolute_path'=>app(MarketingVideoFiles::class)->file($frame['path'],$frame['sha256'])],$local['frame_samples']);
   $vision=app(MarketingEditingVisionClient::class)->inspect(['frame_count'=>$local['frame_count'],'candidate_in_frame'=>$local['candidate_in_frame']??0,'candidate_out_frame'=>$local['candidate_out_frame'],'director'=>$clip->input['director'],'authoritative_script'=>$clip->input['spoken_text']??'','subtitle'=>$clip->input['subtitle']??'','audio_scope'=>'independent local timing; do not infer heard speech'],$images);
   $run->update(['status'=>'completed','usage'=>array_merge($run->usage??[],['response'=>$vision])]);
   $canTrim=($local['safe_speech_timing']??false)&&$vision['can_trim_tail']&&$vision['confidence']==='high';
   $canHead=($local['safe_speech_timing']??false)&&($vision['can_trim_head']??false)&&$vision['confidence']==='high';
   $protected=$local['protected_intervals'];$protected[]=['in_frame'=>$canHead?min($vision['protected_from_frame']??0,$local['candidate_in_frame']??0):0,'out_frame'=>$canTrim?$vision['protected_until_frame']:$local['frame_count'],'reason'=>$canTrim?'sampled_visual_action':'uncertain_retain_full_shot'];
   $e=$local+['version'=>self::VERSION,'clip_id'=>$clip->id,'raw_sha256'=>$clip->raw_sha256,'output_sha256'=>$clip->output_sha256,'vision'=>$vision,'can_trim_tail'=>$canTrim,'can_trim_head'=>$canHead];$e['protected_intervals']=$protected;
   $e['digest']=MarketingEditingDigest::hash($e);
   abort_unless(hash_equals($a->fingerprint,$this->fingerprint($clip->fresh())),409);
   $a->update(['status'=>'completed','evidence'=>$e,'usage'=>array_replace($a->usage??[],['actual_usd'=>$vision['actual_usd']??0,'provider_usage'=>$vision['usage']??[]])]);
  }catch(\Throwable $error){
   if($run&&$run->fresh()->status==='running')$run->update(['status'=>'unknown','usage'=>array_merge($run->usage??[],['provider_usage'=>request()->attributes->get('ai_provider_usage',[]),'automatic_retry'=>false])]);
   $blocked=$error instanceof MarketingProductionFailure&&$error->state==='blocked';$balance=$error instanceof ProviderFailure&&$error->reason==='balance';
   $a->update(['status'=>$run?'unknown':($blocked?'blocked':'failed'),'error_message'=>$balance?'AI余额不足，请先 topup。没有自动重试或生成新视频。':($blocked?'本地语音分析尚未配置完整，请配置Python及已缓存的Whisper small模型后重试本地分析。':'素材分析未完成或来源已变化；原片保留，未自动重试。')]);
  }
 }
}
