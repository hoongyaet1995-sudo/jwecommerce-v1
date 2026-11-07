<?php
namespace App\Services\Marketing;

use App\Models\MarketingDailyDraftRun;
use Illuminate\Support\Collection;

/** Read-only display projection; historical types come from the generation snapshot. */
final class MarketingPostTypes
{
 public const LABELS=['promotion'=>'Promotion / Sale','carousel'=>'Carousel / Swipable post','poster'=>'Poster','infographic'=>'Infographic','meme'=>'Meme','real-photo'=>'UG / Real Photo','storytelling'=>'Storytelling','education'=>'Educational / Case Study','checklist'=>'Checklist / Cheat Sheet','image'=>'Image post','multi-image'=>'Multi-image post','video'=>'Video / Reel','unspecified'=>'Not specified'];
 public function forPosts(Collection $posts):array {
  $ids=$posts->map(fn($p)=>$p->document['ai_context']['daily_draft_run']??null)->filter()->unique()->values();
  $runs=$ids->isEmpty()?collect():MarketingDailyDraftRun::whereIn('id',$ids)->whereIn('user_id',$posts->pluck('user_id')->unique())->where('merchant_key',app(MarketingPosts::class)->merchant())->get()->keyBy('id');
  return $posts->mapWithKeys(function($p)use($runs){
   $doc=$p->document;$context=$doc['ai_context']??[];$id=$context['daily_draft_run']??null;$r=$id?($runs[$id]??null):null;
   $type=$context['post_type']??null;
   if(!is_string($type)||!array_key_exists($type,self::LABELS))$type=$r&&$r->user_id===$p->user_id&&$r->merchant_key===$p->merchant_key?($r->snapshot['facts']['type']??null):null;
   if(!is_string($type)||!array_key_exists($type,self::LABELS))$type=$id?'unspecified':(($doc['type']??'')==='Reel'?'video':(($doc['format']??'')==='carousel'?'multi-image':'image'));
   return [$p->id=>$type];
  })->all();
 }
}
