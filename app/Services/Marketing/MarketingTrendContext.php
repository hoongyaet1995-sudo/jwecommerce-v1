<?php
namespace App\Services\Marketing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache,Http};

/** Public search-interest signals, NOT verified news facts. Never sends merchant data. */
class MarketingTrendContext
{
 public function read(string $market,bool $enabled):array {
  if(!$enabled)return ['status'=>'off','items'=>[]];
  $geo=['Malaysia'=>'MY','Singapore'=>'SG','United Kingdom'=>'GB'][$market]??null;
  if(!$geo)return ['status'=>'unsupported_market','items'=>[]];
  $result=Cache::remember('marketing-trends-v1:'.$geo,now()->addMinutes(30),function()use($geo){
   $source='https://trends.google.com/trending/rss?geo='.$geo;$checked=now()->toIso8601String();
   try{
    $response=Http::connectTimeout(3)->timeout(8)->withoutRedirecting()->withOptions(['stream'=>true])->get($source);
    if(!$response->successful())throw new \RuntimeException('Feed unavailable');
    $body=$response->toPsrResponse()->getBody()->read(524289);
    if(strlen($body)>524288||preg_match('/<!DOCTYPE|<!ENTITY/i',$body))throw new \RuntimeException('Invalid feed');
    $previous=libxml_use_internal_errors(true);try{$xml=simplexml_load_string($body,\SimpleXMLElement::class,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    if(!$xml||!isset($xml->channel))throw new \RuntimeException('Invalid feed');
    $items=[];foreach($xml->channel->item as $item){
     if(count($items)>=10)break;$title=trim(strip_tags((string)$item->title));$date=trim((string)$item->pubDate);if(!$title||!$date||mb_strlen($title)>160)continue;
     try{$published=CarbonImmutable::parse($date);}catch(\Throwable){continue;}
     if($published->isFuture()||$published->lt(now()->subHours(48)))continue;
     $items[]=['id'=>substr(hash('sha256',$title.$date),0,16),'title'=>$title,'published_at'=>$published->toIso8601String(),'source'=>$source];
    }
    return ['status'=>$items?'available':'no_recent_items','checked_at'=>$checked,'source'=>$source,'items'=>$items,'meaning'=>'Google search-interest signal only; not evidence that a news claim is true.'];
   }catch(\Throwable){return ['status'=>'unavailable','checked_at'=>$checked,'source'=>$source,'items'=>[]];}
  });
  $result['items']=array_values(array_filter($result['items'],fn($item)=>CarbonImmutable::parse($item['published_at'])->gte(now()->subHours(48))));
  if($result['status']==='available'&&!$result['items'])$result['status']='no_recent_items';
  return $result;
 }
}
