<?php
namespace App\Services\Marketing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Read-only estimates. Legacy absence is advisory and never silently rewrites a scene. */
class MarketingSpeechRhythm
{
 public function schema():array {
  $num=['type'=>'number','minimum'=>0];$obj=fn($p)=>['type'=>'object','properties'=>$p,'required'=>array_keys($p),'additionalProperties'=>false];
  return $obj(['speech_start'=>$num,'speech_end'=>$num,'silent_beats'=>['type'=>'array','maxItems'=>20,'items'=>$obj(['start'=>$num,'end'=>$num,'purpose'=>['type'=>'string','minLength'=>1,'maxLength'=>600]])],'reason'=>['type'=>'string','minLength'=>1,'maxLength'=>600]]);
 }
 public function validate(array $scene):void {
  if(!array_key_exists('rhythm',$scene))return;
  $r=Validator::make($scene,['rhythm'=>'required|array:speech_start,speech_end,silent_beats,reason','rhythm.speech_start'=>'required|numeric|min:0','rhythm.speech_end'=>'required|numeric|min:0','rhythm.reason'=>'required|string|max:600','rhythm.silent_beats'=>'present|array|list|max:20','rhythm.silent_beats.*'=>'required|array:start,end,purpose','rhythm.silent_beats.*.start'=>'required|numeric|min:0','rhythm.silent_beats.*.end'=>'required|numeric|min:0','rhythm.silent_beats.*.purpose'=>'required|string|max:600'])->validate()['rhythm'];
  $duration=(float)($scene['seconds']??$scene['duration']??0);$start=(float)$r['speech_start'];$end=(float)$r['speech_end'];$spoken=trim($scene['voiceover']??'')!=='';
  $fail=fn()=>throw ValidationException::withMessages(['rhythm'=>'口播与留白需按本段时间完整编排，不能重叠、越界或缺少用途。']);
  if($end<$start||$end>$duration||$start>$duration||($spoken&&$end===$start)||(!$spoken&&$end!==$start))$fail();
  $intervals=$end>$start?[[$start,$end]]:[];
  foreach($r['silent_beats'] as $beat){if($beat['end']<=$beat['start']||trim($beat['purpose'])==='')$fail();$intervals[]=[(float)$beat['start'],(float)$beat['end']];}
  usort($intervals,fn($a,$b)=>$a[0]<=>$b[0]);$cursor=0.0;
  foreach($intervals as [$a,$b]){if(abs($a-$cursor)>0.01||$b>$duration)$fail();$cursor=$b;}
  if(abs($cursor-$duration)>0.01)$fail();
 }
 public function analyze(array $scene):array {
  $duration=(int)($scene['seconds']??$scene['duration']??0);$estimate=app(MarketingStoryboardSaveScope::class)->speechSeconds($scene['voiceover']??'');$remaining=max(0,$duration-$estimate);$r=$scene['rhythm']??null;$warnings=[];
  if($estimate>$duration)$warnings[]='speech_too_long';
  if(!$r&&$remaining>=max(2,$duration*.3))$warnings[]='silence_unplanned';
  if($r){try{$this->validate($scene);if($estimate>$r['speech_end']-$r['speech_start'])$warnings[]='speech_window_short';}catch(ValidationException){$warnings[]='rhythm_needs_review';}}
  // Advisory heuristics only: never impose a narration quota or change saved content/readiness.
  if(trim($scene['voiceover']??'')!==''&&$duration>=8&&$estimate/$duration<.4)$warnings[]='speech_density_low';
  $reading=app(MarketingStoryboardSaveScope::class)->speechSeconds($scene['subtitle']??'');
  if($reading>$duration)$warnings[]='subtitle_reading_overload';
  if(is_array($r)&&!in_array('rhythm_needs_review',$warnings,true)){
   $window=(float)$r['speech_end']-(float)$r['speech_start'];
   if($estimate>0&&$window>$estimate*1.6+1)$warnings[]='speech_window_inflated';
   foreach($r['silent_beats'] as $beat)if($beat['end']-$beat['start']>=3&&preg_match('/^(留白|停顿|氛围|慢节奏|观察|等待|pause|hold|atmosphere|silence)[。.!！\s]*$/iu',trim($beat['purpose'])))$warnings[]='silence_purpose_generic';
  }
  return ['scene_id'=>$scene['scene_id']??null,'estimated_speech_seconds'=>$estimate,'estimated_subtitle_reading_seconds'=>$reading,'scene_seconds'=>$duration,'estimated_non_speech_seconds'=>$remaining,'measured'=>false,'plan'=>$r,'warnings'=>array_values(array_unique($warnings))];
 }
 public function review(array $scenes):array {
  $rows=array_map(fn($s)=>$this->analyze($s),$scenes);$seconds=array_sum(array_column($rows,'scene_seconds'));$speech=array_sum(array_column($rows,'estimated_speech_seconds'));
  $warnings=[];if($seconds>=20&&$speech>0&&$speech/$seconds<.4)$warnings[]='film_speech_density_low';
  return ['version'=>'pacing-coordination-v1','measured'=>false,'advisory_only'=>true,'scene_seconds'=>$seconds,'estimated_speech_seconds'=>$speech,'warnings'=>$warnings,'scenes'=>$rows];
 }
}
