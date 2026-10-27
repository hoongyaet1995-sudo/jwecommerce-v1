<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoFilm};use Illuminate\Validation\ValidationException;
class MarketingSemanticFacts {
 public const VERSION='semantic-facts-v2';
 public function catalogue(MarketingVideoFilm $film):array {
  $p=MarketingProject::with('currentRevision')->findOrFail($film->project_id);$r=$p->currentRevision;
  abort_unless($r&&($p->planning_state['status']??'')==='confirmed'&&($p->brief['source_confirmed']??false)&&($p->brief['facts_confirmed']??false),409,'请先确认当前商品资料。');
  abort_unless(($r->context['snapshot']??null)===$p->snapshot&&($r->context['brief']??null)===$p->brief,409,'已确认资料已变化。');
  $atoms=[];$add=function(string $path,string $text,int $scene=0)use(&$atoms,$r){
   $text=html_entity_decode(strip_tags(preg_replace('/<\/?(?:p|div|br|li)\b[^>]*>/iu',"\n",$text)),ENT_QUOTES|ENT_HTML5,'UTF-8');$text=trim(preg_replace('/[ \t]+/u',' ',$text));
   // A whole approved field is the smallest independently trusted unit. Never detach a condition in another sentence/paragraph.
   $line=trim(preg_replace('/\s+/u',' ',$text));if($line!==''){$digest=MarketingEditingDigest::hash([$r->id,$path,$line,$text]);$atoms[]=['id'=>'fact-'.substr($digest,0,16),'source_path'=>$path,'scene_number'=>$scene,'text'=>$line,'context'=>$text,'digest'=>$digest,'card_eligible'=>mb_strlen($line)<=40&&!preg_match('/[<>]/u',$line)];}
  };
  foreach(['title','description','subtitle'] as $key)$add('snapshot.'.$key,(string)($p->snapshot[$key]??''));
  $facts=$p->brief['facts']??'';if(is_string($facts))$add('brief.facts',$facts);
  foreach(app(MarketingSubtitleService::class)->scenes($film) as $row)$add('scene:'.$row['number'].'.spoken_text',$row['spoken_text'],$row['number']);
  $cta=(string)($p->brief['cta']??'');$titles=['purpose'=>'产品用途','feature'=>'产品特点','before'=>'购买前确认'];if($cta!==''&&mb_strlen($cta)<=18)$titles['cta']=$cta;
  $out=['version'=>self::VERSION,'revision_id'=>$r->id,'atoms'=>$atoms,'card_titles'=>$titles,'cta'=>$cta];$out['digest']=MarketingEditingDigest::hash([$out,$p->snapshot,$p->brief]);return $out;
 }
 public function assertFresh(MarketingVideoFilm $film,array $saved):void {abort_unless(hash_equals($saved['digest']??'', $this->catalogue($film)['digest']),409,'优化所用事实已变化。');}
 public function card(array $candidate,array $catalogue,int $scene):array {
  $fail=fn()=>throw ValidationException::withMessages(['semantic'=>'信息卡没有完整、有效的事实依据。']);
  if(array_diff(array_keys($candidate),['title_key','body_fact_id','position','start_frame','end_frame']))$fail();
  $a=collect($catalogue['atoms'])->firstWhere('id',$candidate['body_fact_id']??'');$title=$catalogue['card_titles'][$candidate['title_key']??'']??null;
  if(!$a||!$title||!$a['card_eligible']||($a['scene_number']!==0&&$a['scene_number']!==$scene))$fail();
  return ['title'=>$title,'body'=>$a['text'],'fact_ids'=>[$a['id']],'source_refs'=>['scene:'.$scene],'source'=>'已确认资料 · AI 自动整理'];
 }
}
