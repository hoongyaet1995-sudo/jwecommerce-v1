<?php
namespace App\Services\Marketing;

/** Server-owned route boundaries; AI proposes intent, never pricing or commands. */
class MarketingSceneExecutionPolicy
{
 public const VERSION='scene-execution-v1';
 public function decide(array $scene):array{
  $plan=$scene['material_plan']??[];$intent=app(MarketingVideoRoutes::class)->intent($scene);
  $requested=$plan['production_route']??'auto';$motion=$plan['motion_kind']??'auto';
  $text=$scene['director']['subject']??'';
  $text=preg_replace('/(?:不加入|不展示|不含|不出现|不显示|没有|无|no |without )[^。；;.]*/iu','',$text);
  $exact=($plan['fidelity']??'standard')==='exact_brand'||preg_match('/包装|标签|品牌文字|商标|\blogo\b|\bpackaging\b|\blabel\b|\btin\b/iu',$text);
  $action=$motion==='action'||!empty($scene['presenter_required']);
  $motionText=preg_replace('/(?:不加入|不安排|不可|不得|不要|no |without )[^。；;.]*/iu','',$scene['director']['motion']??'');
  if(preg_match('/拿起|放下|走动|打开|开盖|旋转商品|拆解|倾倒|倒茶|漂浮|翻转|\b(?:pick up|pour|open lid|walk|explode|rotate product)\b/iu',$motionText))$action=true;$blockers=[];if($motion==='camera_only'&&$action)$blockers[]='camera_only_action_conflict';$route='generative';
  if($exact){if($action)$blockers[]='exact_brand_action_conflict';else $route='source_locked';}
  elseif(in_array($requested,['source_locked','conservative_motion'],true)){if($action||$motion!=='camera_only')$blockers[]='local_route_requires_camera_only_intent';else $route=$requested;}
  elseif($motion==='camera_only')$route='conservative_motion';
  if($route!=='generative'&&($intent['mode']!=='single_image'||$intent['roles']!==['start_frame']))$blockers[]='local_route_cannot_drop_required_references';
  if($blockers)$route='needs_review';
  $audio=trim($scene['voiceover']??'')===''?'silent':(!empty($scene['presenter_required'])?'native_or_verified_lipsync':'independent');
  $reason=match($route){'source_locked'=>'包装与精确文字沿用原图合成，不交给生成模型重绘。','conservative_motion'=>'沿用原图做最多2%的整图推进，保留物体形状；不包含真实液体或人物动作。','needs_review'=>'当前动作、精确保真或必要参考存在冲突，保留导演稿，需调整制作安排。',default=>'保留导演稿中的动态需求，使用已核验生成路线；输出仍需检查。'};
  return ['version'=>self::VERSION,'route'=>$route,'audio'=>$audio,'reason'=>$reason,'blockers'=>$blockers,'external_video_posts'=>in_array($route,['source_locked','conservative_motion'],true)?0:null,'max_zoom'=>$route==='conservative_motion'?1.02:1,'quality_verified'=>false];
 }
 public function impact(array $before,array $after,array $profile):array{
  $images=[];foreach(MarketingStoryboardPackage::ROLES as $role){if(!isset($before['frames'][$role])&&!isset($after['frames'][$role]))continue;if(!hash_equals(app(MarketingStoryboardImages::class)->reuseSignature($before,$role,$profile),app(MarketingStoryboardImages::class)->reuseSignature($after,$role,$profile)))$images[]=$role;}
  $voice=($before['voiceover']??'')!==($after['voiceover']??'');$animation=($before['director']['motion']??null)!==($after['director']['motion']??null)||($before['director']['beats']??null)!==($after['director']['beats']??null)||($before['material_plan']??null)!==($after['material_plan']??null);
  return ['version'=>self::VERSION,'images'=>$images,'reuse_images'=>!$images,'voice'=>$voice,'animation'=>$animation,'composition'=>$voice||$animation||(bool)$images||($before['subtitle']??'')!==($after['subtitle']??'')||($before['seconds']??0)!==($after['seconds']??0)||($before['rhythm']??null)!==($after['rhythm']??null),'requires_image_confirmation'=>(bool)$images,'pricing'=>'derive_after_scope_not_permission_to_charge'];
 }
 public function instruction():string{return ' Choose material_plan production_route auto/source_locked/conservative_motion/generative, fidelity standard/exact_brand, motion_kind auto/camera_only/action. Exact packaging/logo/label shots use original-image composition; do not redraw precise print. Camera-only images may use max2% whole-image motion. Never replace required physical actions, changing views or references with a slide; incompatible exact-brand action needs a revised feasible arrangement. Off-camera narration uses independently priced audio and measured speech duration, not native video audio. Narration/subtitle/pacing edits reuse unaffected images; ask only for materially affected visuals and new fees. No new tool or price may be invented.';}
}
