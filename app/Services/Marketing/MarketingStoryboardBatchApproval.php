<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoClip};
use Illuminate\Support\Facades\Cache;

/** Bounded owner-approved scope; prices and permissions are never supplied by creative AI. */
class MarketingStoryboardBatchApproval {
 private static bool $inside=false;
 public function grant():array {
  $g=config('marketing.storyboard.batch_approval');
  if($g===null&&app()->environment('local')){$p=storage_path('app/private/marketing/storyboard-batch-approval.json');$g=is_file($p)?json_decode(file_get_contents($p),true):[];}
  return is_array($g)?$g:[];
 }
 public function clip(string $project,int $owner,array $scene,string $id,string $model):array {
  $c=MarketingVideoClip::where('project_id',$project)->where('owner_id',$owner)->where('status','completed')->findOrFail($id);
  app(MarketingStoryboardProduction::class)->assertClip($c);
  abort_unless(($c->input['scene_id']??null)===$scene['scene_id']&&($c->input['revision_id']??null)===$scene['revision_id']&&($c->input['seconds']??null)===$scene['seconds']&&($c->input['payload']['model']??null)===$model&&($c->input['payload']['quality']??null)==='720p'&&($c->input['voiceover']??'')===($scene['voiceover']??'')&&($c->input['subtitle']??'')===($scene['subtitle']??'')&&(!trim($scene['voiceover']??'')||($c->input['payload']['generate_audio']??false)),409,'待复用片段与当前导演稿、声音或模型不一致。');
  app(MarketingVideoFiles::class)->file($c->raw_path,$c->raw_sha256);app(MarketingVideoFiles::class)->file($c->output_path,$c->output_sha256);
  return ['id'=>$c->id,'fingerprint'=>$c->fingerprint,'raw_sha256'=>$c->raw_sha256,'output_sha256'=>$c->output_sha256];
 }
 public function activate(array $h):bool {
  $g=$this->grant();$project=$h['source_context']['project_id']??'';$scenes=$h['scene_map']??[];
  if(!$g||($g['project']??null)!==$project||empty($g['expires_at'])||now()->gte($g['expires_at'])||($g['story_version']??null)!==($h['story_version']??null)||!$scenes||($h['profile']['voice']['mode']??'auto')!=='auto')return false;
  if(($g['connection']??null)!==app(MarketingEvolinkAdapter::class)->connectionFingerprint())return false;
  $owner=MarketingProject::findOrFail($project)->owner_id;$caps=[];$reused=[];$requiredCredits=0;
  foreach($scenes as $s){$bound=$g['scenes'][$s['scene_id']]??null;if(!$bound||($bound['digest']??null)!==hash('sha256',json_encode($s,JSON_THROW_ON_ERROR)))return false;
   if(!is_int($s['seconds'])||$s['seconds']<4||$s['seconds']>12||!is_int($bound['video_cap_microusd']??null)||$bound['video_cap_microusd']<=0)return false;
   if(isset($caps[$s['seconds']])&&$caps[$s['seconds']]!==$bound['video_cap_microusd'])return false;$caps[$s['seconds']]=$bound['video_cap_microusd'];
   if(!empty($bound['reuse_clip']))$reused[$s['scene_id']]=$this->clip($project,$owner,$s,$bound['reuse_clip'],MarketingEconomyVideoPolicy::PRO);else $requiredCredits+=$s['seconds']*3.5568;
  }
  abort_unless(is_int($g['cap_microusd']??null)&&$g['cap_microusd']>0&&is_int($g['max_posts']??null)&&$g['max_posts']>0,423,'本次批次预算和请求次数尚未确认。');
  Cache::remember('storyboard-batch-account:'.$g['reference'].':'.$g['connection'],now()->addMinutes(2),function()use($requiredCredits){$d=app(MarketingClipProvider::class)->credits($requiredCredits);$r=\Illuminate\Support\Facades\Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/models');abort_unless($r->successful()&&in_array(MarketingEconomyVideoPolicy::PRO,array_column($r->json('data')??[],'id'),true),423,'账户模型核对失败。');return $d;});
  ksort($caps);$version='storyboard-batch-v1:'.$g['reference'];
  $video=['model'=>MarketingEconomyVideoPolicy::PRO,'version'=>$version,'cap_microusd'=>max($caps),'duration_caps'=>$caps,'credit_usd'=>10/650,'access_verified_at'=>$g['verified_at'],'pricing_verified_at'=>$g['verified_at'],'pricing_valid_until'=>$g['expires_at'],'native_trial_grant'=>$g['reference'],'batch_approved'=>true,'adapt_prompt'=>true,'tiers'=>['ordinary']];
  $text=['model'=>'gpt-5.6-luna','version'=>$version,'cap_microusd'=>50000,'input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2,'cached_usd_per_million'=>0.02,'cache_write_usd_per_million'=>0.25,'cache_write_12h_usd_per_million'=>0.5,'pricing_verified_at'=>$g['verified_at']];
  $restore=['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['mode'=>'approved_batch','reference'=>$g['reference'],'project_id'=>$project,'owner_id'=>$owner,'roles'=>['semantic','upload','video'],'expires_at'=>$g['expires_at'],'max_total_microusd'=>$g['cap_microusd'],'max_calls'=>$g['max_posts']], 'marketing.storyboard.roles.video'=>$video,'marketing.storyboard.video_models'=>[],'marketing.storyboard.roles.semantic'=>$text,'marketing.storyboard.batch_reuse'=>$reused,
   'marketing.storyboard.roles.upload'=>['model'=>'evolink-measured-upload','version'=>$version,'cap_microusd'=>25000,'fee_microusd'=>0,'credit_usd'=>10/650,'pricing_verified_at'=>'measured-per-request','measure_balance'=>true],
   'marketing.storyboard.audio.native'=>['model'=>$video['model'],'version'=>$version,'verified_at'=>$g['verified_at'],'languages'=>[$h['source_context']['language']??'English'],'exact_script'=>false,'lip_sync'=>false,'scoped_trial'=>$g['reference'],'multi_scene_trial'=>true]];
  config($restore);config(['marketing.storyboard.batch_restore'=>['digest'=>hash('sha256',json_encode($g)),'config'=>$restore]]);return true;
 }
 public function activateProject(string $project):bool {
  $g=$this->grant();if(($g['project']??null)!==$project||self::$inside||empty($g['expires_at'])||now()->gte($g['expires_at']))return false;
  $saved=config('marketing.storyboard.batch_restore',[]);if(($saved['digest']??null)===hash('sha256',json_encode($g))){config($saved['config']);return true;}
  self::$inside=true;try{$p=MarketingProject::findOrFail($project);$s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$p->owner_id);$h=app(MarketingStoryboardHandoff::class)->build($project,$p->owner_id,$s['story_id']);return $this->activate($h);}finally{self::$inside=false;}
 }
}
