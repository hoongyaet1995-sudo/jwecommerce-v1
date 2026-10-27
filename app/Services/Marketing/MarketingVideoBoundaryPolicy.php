<?php
namespace App\Services\Marketing;

/** Shared creative boundaries and deterministic execution checks. Never certifies generated quality. */
class MarketingVideoBoundaryPolicy
{
 public const VERSION='video-boundaries-v1';
 public function globals():array{return [
  'identity'=>'Preserve approved product/person identity, geometry, object count, packaging, logos and text. Only explicit user-directed creative transformations may vary appearance; never imply a new purchasable variant.',
  'facts'=>'Invent story situations, not commercial facts, capabilities, testimonials, prices, results or guarantees. Unknown facts are omitted; missing optional research never blocks ideation.',
  'exact_content'=>'Exact packaging/brand/legal text needs source-locked material or deterministic composition. Generative preservation is best effort, not a guarantee. If the needed route is unavailable, report the gap; do not promise it or silently substitute.',
  'motion'=>'Separate subject, object and camera motion; name stationary objects, contact/support relationships, and start/action/end states. Liquid motion must not move its container. Keep explicit surreal intent; otherwise use plausible causes and restrained actions.',
  'timing'=>'Use LOCAL speech windows and purposeful silence/action/reading beats. Estimates are not measured audio. Preserve frozen copy and duration; later compare actual speech and picture, never infer success from valid timestamps.',
  'execution'=>'Use only supported route inputs, duration and audio capabilities. Do not drop required references, change approved intent, auto-retry paid requests or add fees. Creative QA is advisory; authorization, unknown costs and invalid inputs remain execution guards.',
 ];}
 public function catalog():array{return ['version'=>self::VERSION,'global'=>$this->globals(),'forms'=>app(MarketingVideoFormPolicy::class)->catalog(),'routes'=>app(MarketingVideoRoutes::class)->catalog(),'tools'=>app(MarketingToolCapabilities::class)->catalog()];}
 public function globalInstruction():string{return ' SHARED BOUNDARIES '.self::VERSION.'. '.implode(' ',$this->globals()).app(MarketingToolCapabilities::class)->instruction().app(MarketingSceneExecutionPolicy::class)->instruction().' '.app(MarketingVideoFormPolicy::class)->coordinationInstruction();}
 public function instruction(string $form='auto'):string{return $this->globalInstruction().' '.app(MarketingVideoFormPolicy::class)->instruction($form);}
 public function assess(array $scene,?array $capability=null,bool $nativeAudio=false):array{
  $blockers=[];$rhythm=app(MarketingSpeechRhythm::class)->analyze($scene);$advice=$rhythm['warnings'];
  if(isset($scene['rhythm'])){try{app(MarketingSpeechRhythm::class)->validate($scene);}catch(\Illuminate\Validation\ValidationException){$blockers[]='rhythm_invalid';}}
  if($capability){$intent=app(MarketingVideoRoutes::class)->intent($scene);
   if(!in_array($intent['mode'],$capability['modes'],true))$blockers[]='reference_mode_unsupported';
   if(count($intent['roles'])>$capability['max_images'])$blockers[]='reference_count_unsupported';
   if(!in_array($scene['seconds']??0,$capability['durations'],true))$blockers[]='duration_unsupported';
   if($nativeAudio&&!$capability['audio_control'])$blockers[]='native_audio_unsupported';
  }
  if(!empty($scene['scene_design']['product_reference_indexes']))$advice[]='product_fidelity_requires_review';
  return ['version'=>self::VERSION,'blockers'=>array_values(array_unique($blockers)),'advisories'=>array_values(array_unique($advice)),'speech'=>$rhythm,'quality_verified'=>false];
 }
 public function assertExecutable(array $scene,?array $capability=null,bool $nativeAudio=false):void{
  $r=$this->assess($scene,$capability,$nativeAudio);abort_if($r['blockers'],422,'制作边界不匹配：'.implode(', ',$r['blockers']).'。原稿与素材保留，不会自动改要求或增加费用。');
 }
 public function executionDirection():string{return ' Keep approved identity, object count, geometry and support/contact; separate camera, container and liquid motion. Allow only explicitly directed transformations. Add no text; exact branding uses approved source/composition.';}
 public function timingDirection(array $rhythm):string{
  // Rationale is retained in the frozen source, not repeated in bounded execution instructions.
  return ' LOCAL timing: '.json_encode(array_intersect_key($rhythm,array_flip(['speech_start','speech_end','silent_beats'])),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 }
 public function audioDirection(array $scene,string $language):string{
  $lang=match(strtolower($language)){'zh-my','zh','zh-cn'=>'Mandarin Chinese','ms-my','ms','malay'=>'Malay',default=>$language?:'English'};
  return ' Natural '.$lang.'. '.(!empty($scene['presenter_required'])?'Visible presenter with synchronized presenter lips.':'Use off-camera narration, no speaking mouth animation.').' Speak ONLY this exact script: '.json_encode($scene['voiceover']??'',JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 }
}
