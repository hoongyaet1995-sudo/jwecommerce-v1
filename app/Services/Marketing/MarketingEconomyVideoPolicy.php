<?php
namespace App\Services\Marketing;

/** Public recommendations only. Neither AI intent nor published prices authorizes a supplier call. */
class MarketingEconomyVideoPolicy {
 public const VERSION='ordinary-seedance-v1';
 public const PRO='seedance-1.5-pro';
 public const FAST='doubao-seedance-1.0-pro-fast';
 public const MODELS=[self::PRO,self::FAST];
 public function recommend(array $scene,array $profile=[]):array {
  $intent=app(MarketingVideoRoutes::class)->intent($scene);$seconds=$scene['seconds']??0;$roles=$intent['roles'];
  $base=['policy_version'=>self::VERSION,'quality'=>'720p','image_roles'=>$roles,'mode'=>$intent['mode'],'generation_authorized'=>false,'cap_microusd'=>null,'pricing_kind'=>'public_estimate_not_execution_grant','pricing_checked_at'=>'2026-10-23'];
  $speech=trim($scene['voiceover']??'')!=='';$voice=$profile['voice']['mode']??'auto';
  $absent=true;foreach($roles as $role)$absent=$absent&&($scene['frames'][$role]['person_presence']??'unknown')==='absent';
  $kind=$intent['shot_kind']??null;
  $broll=!($scene['presenter_required']??true)&&!$speech&&$absent&&$intent['mode']==='single_image'&&in_array($kind,[null,'product_broll'],true);
  $preferred=$intent['preferred_model']??'auto';$model=$broll&&$preferred!==self::PRO?self::FAST:self::PRO;
  $min=$model===self::FAST?2:4;
  if(!is_int($seconds)||$seconds<$min||$seconds>12||!in_array($intent['mode'],['single_image','first_last_frames'],true))return $base+['status'=>'needs_review','model'=>null,'reason'=>'普通档位当前候选模型不支持本段时长或必要参考图；保留导演稿，请确认调整路线或档位。','public_estimate_microusd'=>null,'estimated_credits'=>null];
  $native=$model===self::PRO&&$speech&&$voice==='auto';$independent=$speech&&$voice==='preset';
  $credits=$model===self::FAST?.9:($native?3.5568:1.7784);$usd=$model===self::FAST?14000:($native?53000:27000);
  $reason=$model===self::FAST?'沿用AI导演设计的无人物、无口播商品空镜，仅需首帧，普通档位选用1.0 Pro Fast降低视频费用。':'普通档位选用1.5 Pro，保留导演稿要求的人物、动作与必要首尾帧。';
  if($preferred===self::FAST&&!$broll)$reason.=' AI提出的Fast候选不满足当前人物、口播或关键帧要求，已保留这些要求并改选1.5 Pro。';
  if($preferred===self::PRO&&$broll)$reason.=' 沿用AI明确推荐的1.5 Pro。';
  return $base+['status'=>'estimated','model'=>$model,'reason'=>$reason,'ai_reason'=>$intent['reason']??'','selection_origin'=>isset($intent['preferred_model'])?'saved_ai_material_plan':'saved_director_requirements','seconds'=>$seconds,'public_estimate_microusd'=>$seconds*$usd,'estimated_credits'=>round($seconds*$credits,6),'credits_per_second'=>$credits,'audio_route'=>$native?'native_candidate':($independent?'independent_voice_unpriced':'silent'),'voice_description'=>$native?'原生声音候选：按已保存台词和语言请求；普通话、台词、口型和音色尚待真实验收。':($independent?'保留用户指定声线，独立配音费用需单独核对。':'无口播；视频不会自动添加人声。'),'pricing_source'=>$model===self::FAST?'https://evolink.ai/seedance-1-pro-fast':'https://evolink.ai/seedance-1-5-pro','voice_requests'=>$independent?1:0];
 }
 public function isModel(string $model):bool{return in_array($model,self::MODELS,true);}
}
