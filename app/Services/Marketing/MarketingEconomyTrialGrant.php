<?php
namespace App\Services\Marketing;

use Illuminate\Support\Facades\{Cache,Http};

/** Explicit local trial only; activation performs GETs, never generation or upload. */
class MarketingEconomyTrialGrant
{
 private static bool $activating=false;
 public function grant():array {
  $g=config('marketing.storyboard.economy_trial');
  if($g===null&&app()->environment('local')){$p=storage_path('app/private/marketing/economy-trial-grant.json');$g=is_file($p)?json_decode(file_get_contents($p),true):[];}
  return is_array($g)?$g:[];
 }
 public function activate(array $h):bool {
  $g=$this->grant();$project=$h['source_context']['project_id']??'';$scenes=$h['scene_map']??[];
  if(!$g||($g['project']??null)!==$project||empty($g['expires_at'])||now()->gte($g['expires_at'])||($g['story_version']??null)!==($h['story_version']??null)||count($scenes)!==1||($scenes[0]['scene_id']??null)!==($g['scene']??null)||($scenes[0]['seconds']??null)!==6||($h['profile']['voice']['mode']??'auto')!=='auto')return false;
  if(($g['cap_microusd']??null)!==450000||($g['max_posts']??null)!==4||($g['model']??null)!==MarketingEconomyVideoPolicy::PRO||($g['connection']??null)!==app(MarketingEvolinkAdapter::class)->connectionFingerprint())return false;
  if(($g['scene_digest']??null)!==hash('sha256',json_encode($scenes[0],JSON_THROW_ON_ERROR)))return false;
  foreach(['start_frame','end_frame'] as $role){$f=$scenes[0]['frames'][$role]['asset']??null;if(!$f||!app(MarketingUploadCheckpoint::class)->reuse($project,$g['scene'],$role,$f['sha256']))return false;}
  $e=Cache::remember('economy-trial-account:'.$g['connection'],now()->addMinutes(2),function(){
   $out=[];foreach(['models','credits'] as $ep){$r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/'.$ep);abort_unless($r->successful(),423,'模型或余额核对失败，不发送生成请求。');$out[$ep]=$r->json('data');}return $out;
  });
  abort_unless(in_array(MarketingEconomyVideoPolicy::PRO,array_column($e['models']??[],'id'),true)&&is_numeric($e['credits']['user']['remaining_credits']??null)&&$e['credits']['user']['remaining_credits']>=21.3408,423,'1.5 Pro账户访问或余额不足，未生成。');
  $version='economy-trial-v1:'.$g['reference'];
  $video=['model'=>MarketingEconomyVideoPolicy::PRO,'version'=>$version,'cap_microusd'=>400000,'credit_usd'=>10/650,'access_verified_at'=>$g['verified_at'],'pricing_verified_at'=>$g['verified_at'],'pricing_valid_until'=>$g['expires_at'],'native_trial_grant'=>$g['reference'],'adapt_prompt'=>true,'tiers'=>['ordinary']];
  $text=['model'=>'gpt-5.6-luna','version'=>$version,'cap_microusd'=>50000,'input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2,'cached_usd_per_million'=>0.02,'cache_write_usd_per_million'=>0.25,'cache_write_12h_usd_per_million'=>0.5,'pricing_verified_at'=>$g['verified_at']];
  config(['marketing.storyboard.live_enabled'=>true,'marketing.storyboard.live_grant'=>['mode'=>'economy_trial','reference'=>$g['reference'],'project_id'=>$project,'owner_id'=>\App\Models\MarketingProject::findOrFail($project)->owner_id,'roles'=>['semantic','upload','video'],'expires_at'=>$g['expires_at'],'max_total_microusd'=>450000,'max_calls'=>2],
   'marketing.storyboard.roles.video'=>$video,'marketing.storyboard.video_models'=>[], 'marketing.storyboard.roles.semantic'=>$text,
   'marketing.storyboard.roles.upload'=>['model'=>'evolink-file-reuse','version'=>$version,'cap_microusd'=>1,'fee_microusd'=>0,'pricing_verified_at'=>'observed-upload-checkpoints','reuse_only'=>true],
   'marketing.storyboard.audio.native'=>['model'=>$video['model'],'version'=>$version,'verified_at'=>$g['verified_at'],'languages'=>[$h['source_context']['language']??'English'],'exact_script'=>false,'lip_sync'=>false,'scoped_trial'=>$g['reference']]]);
  return true;
 }
 public function activateProject(string $project):bool {
  $g=$this->grant();if(($g['project']??null)!==$project)return false;
  if(self::$activating||empty($g['expires_at'])||now()->gte($g['expires_at']))return false;
  self::$activating=true;
  try {
  $p=\App\Models\MarketingProject::findOrFail($project);$s=app(MarketingStoryboardWorkspace::class)->snapshot($project,$p->owner_id);
  $h=app(MarketingStoryboardHandoff::class)->build($project,$p->owner_id,$s['story_id']);
  return $this->activate(app(MarketingProductionScope::class)->select($h,$g['scene']));
  } finally {self::$activating=false;}
 }
}
