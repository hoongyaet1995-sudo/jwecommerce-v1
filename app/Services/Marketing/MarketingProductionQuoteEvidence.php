<?php
namespace App\Services\Marketing;

use Illuminate\Support\Facades\{Cache,Http};

/** Read-only account discovery. Public estimates never become a paid execution grant. */
class MarketingProductionQuoteEvidence
{
 public function assertAffordable(\App\Models\MarketingProductionPlan $plan,int $owner):void
 {
  app(MarketingStoryboardWorkspace::class)->project($plan->project_id,$owner);
  $remote=array_filter($plan->manifest['configuration']['clip_routes']??[],fn($r)=>empty($r['local']));
  if(!$remote&&($plan->manifest['budget']['voice_requests']??0)===0)return;
  abort_if(!$remote,423,'独立配音账户费用与授权待核验；不会用视频账户余额替代声音授权。');
  $e=$this->refresh($plan->project_id,$owner);
  abort_unless(($e['account_status']??'')==='model_list_and_balance_read',409,'模型连接或余额未能核对，尚未开始生成。');
  foreach($remote as $route)abort_unless(in_array($route['video']['model'],$e['model_ids']??[],true),409,'当前账户不可用所选模型，请重新核价。');
  $remaining=$e['remaining_credits']??null;
  abort_unless(is_numeric($remaining)&&$remaining*10/650*1000000>=($plan->manifest['budget']['cap_microusd']??PHP_INT_MAX),409,'账户余额不足以覆盖本次费用上限，未开始生成；不会自动充值。');
 }
 public function refresh(string $project,int $owner):array
 {
  app(MarketingStoryboardWorkspace::class)->project($project,$owner);
  $key=$this->key($project);$old=Cache::get($key);
  if($old)return $old;
  $out=['checked_at'=>now()->toIso8601String(),'generation_requests'=>0,'model_ids'=>[],'account_status'=>'unverified'];
  if(!filled(config('marketing.native.api_key'))){$out['account_status']='missing_connection';Cache::put($key,$out,now()->addMinutes(10));return $out;}
  try{
   foreach(['models','credits'] as $endpoint){
    $r=Http::withToken((string)config('marketing.native.api_key'))->acceptJson()->connectTimeout(8)->timeout(25)->withoutRedirecting()->get('https://api.evolink.ai/v1/'.$endpoint);
    $out[$endpoint.'_status']=$r->status();
    if(!$r->successful()){$out['account_status']=in_array($r->status(),[401,403])?'authentication_blocked':'lookup_failed';break;}
    if($endpoint==='models')$out['model_ids']=array_values(array_filter(array_column($r->json('data')??[],'id'),'is_string'));
    else{$d=$r->json('data');$out['remaining_credits']=$d['user']['remaining_credits']??null;$out['account_status']='model_list_and_balance_read';}
   }
  }catch(\Throwable){$out['account_status']='lookup_failed';}
  Cache::put($key,$out,now()->addMinutes(30));return $out;
 }
 private function key(string $project):string{return 'storyboard-account-quote:'.hash('sha256',$project.':'.app(MarketingEvolinkAdapter::class)->connectionFingerprint());}
 public function candidate(string $project,array $handoff,array $clips):array
 {
  $e=Cache::get($this->key($project),['account_status'=>'not_checked','model_ids'=>[]]);
  if(($handoff['profile']['production_tier']??'ordinary')==='ordinary')return $this->economyCandidate($e,$handoff,$clips);
  $model='seedance-2.0-image-to-video';$seconds=array_sum(array_column($clips,'seconds'));$uploads=0;$supported=true;
  foreach($clips as $clip){$intent=app(MarketingVideoRoutes::class)->intent($clip);$supported=$supported&&in_array($intent['mode'],['single_image','first_last_frames'],true)&&$clip['seconds']>=4&&$clip['seconds']<=15;$uploads+=count($intent['roles']);}
  $native=($handoff['profile']['voice']['mode']??'auto')==='auto'&&count($clips)===1;
  $blockers=[];
  if(!$supported)$blockers[]='所选镜头的输入方式或时长不适合当前视频适配器。';
  if(!in_array($model,$e['model_ids']??[],true))$blockers[]='账户尚未确认可列出该视频模型，请重新核价读取连接状态。';
  $blockers[]='尚缺账户 USD/credit 换算与临时素材上传费用凭据，公开估价不能作为实际费用上限。';
  if(!$native)$blockers[]='多段声音一致性或指定声线仍需有效独立配音配置。';
  $roles=$native?0:count(array_filter($clips,fn($c)=>trim($c['voiceover'])!==''));
  return ['provider'=>'evolink','model'=>$model,'quality'=>'720p','account'=>$e,'audio_route'=>$native?'native_trial':'needs_voice_configuration','voice_description'=>$native?'原生音画：按已保存台词请求普通话女声；声线和嘴型等待实测，不沿用已失效的独立配音连接。':'独立配音连接与声线待核验；不会静默替换已指定声线。','native_audio_incremental_cost'=>0,'seconds'=>$seconds,'video_requests'=>count($clips),'upload_requests'=>$uploads,'voice_requests'=>$roles,'total_posts'=>$uploads+count($clips)+$roles,'estimated_credits'=>$seconds*13.5,'public_estimate_microusd'=>$seconds*199000,'cap_microusd'=>null,'pricing_kind'=>'public_estimate_not_account_cap','pricing_checked_at'=>'2026-10-23','pricing_source'=>'https://evolink.ai/seedance-2-0','audio_source'=>'https://evolink.ai/docs/en/api-manual/video-series/seedance2.0/seedance-2.0-image-to-video','generation_authorized'=>false,'blockers'=>$blockers];
 }
 private function economyCandidate(array $account,array $handoff,array $clips):array {
  $rows=[];$uploads=0;$credits=0;$usd=0;$voices=0;$blocked=[];
  foreach($clips as $clip){$r=app(MarketingEconomyVideoPolicy::class)->recommend($clip,$handoff['profile']??[]);$r['scene_id']=$clip['scene_id'];$r['ordinal']=$clip['ordinal']??count($rows)+1;$rows[]=$r;$uploads+=count($r['image_roles']);$voices+=$r['voice_requests']??0;
   if($r['status']!=='estimated'){$blocked[]='第'.$r['ordinal'].'段：'.$r['reason'];continue;}$credits+=$r['estimated_credits'];$usd+=$r['public_estimate_microusd'];
   if(!in_array($r['model'],$account['model_ids']??[],true))$blocked[]='第'.$r['ordinal'].'段模型尚待核对账户可用性。';
  }
  if($voices)$blocked[]='用户指定声线已保留，独立配音费用尚待核对。';
  $blocked[]='当前为公开费率估价；真实测试范围、账户费率、上传费用和预算尚未确认，不会生成。';
  $models=array_values(array_unique(array_filter(array_column($rows,'model'))));
  return ['provider'=>'evolink','model'=>count($models)===1?$models[0]:(count($models)?'按分镜分别选型':'暂无兼容模型'),'quality'=>'720p','account'=>$account,'scene_models'=>$rows,'audio_route'=>'per_scene_candidate','voice_description'=>'声音按各段导演稿安排；原生普通话、台词及口型待真实验收，指定声线保留。','audio_price_note'=>'1.5 Pro已区分有声与无声费率；独立配音和上传费用另行核对。','seconds'=>array_sum(array_column($clips,'seconds')),'video_requests'=>count($clips),'upload_requests'=>$uploads,'voice_requests'=>$voices,'total_posts'=>count($clips)+$uploads+$voices,'estimated_credits'=>$credits,'public_estimate_microusd'=>$usd,'cap_microusd'=>null,'pricing_kind'=>'public_estimate_not_account_cap','generation_authorized'=>false,'blockers'=>$blocked];
 }
}
