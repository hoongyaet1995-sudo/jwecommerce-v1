<?php
namespace App\Domain\Cms;
use App\Models\PageDocument;

/** Resolve section ownership once per Header; never expose drafts on public routes. */
final class CmsNavigationLinks
{
 public function forPayload(?array $payload,array $urls):array {
  $wanted=[];foreach($urls as $url)if(is_string($url)&&preg_match('/^#section-([A-Za-z0-9][A-Za-z0-9_-]*)$/D',$url,$m))$wanted[$m[1]]=$url;
  if(!$wanted)return [];
  $map=[];foreach($payload['page']['document']['sections']??[] as $section)if(($section['isVisible']??false)&&isset($wanted[$section['id']??''])){unset($wanted[$section['id']]);}
  if(!$wanted)return $map;
  $preview=($payload['context']['mode']??'public')==='preview';$candidates=[];
  $documents=PageDocument::query()->where('stage',$preview?'draft':'published')->whereHas('page',fn($q)=>$q->whereIn('status',$preview?['draft','published']:['published']))->get(['page_id','slug','document']);
  foreach($documents as $document){foreach($document->document['sections']??[] as $section){$id=$section['id']??'';if(isset($wanted[$id])&&($section['isVisible']??false))$candidates[$id][(string)$document->page_id]=['id'=>(string)$document->page_id,'slug'=>$document->slug];}}
  $home=app(HomepageSelection::class)->id();
  foreach($wanted as $id=>$anchor){$matches=$candidates[$id]??[];$owner=$matches[$home??'']??(count($matches)===1?reset($matches):null);if(!$owner)continue;
   if($preview)$base='/admin/pages/'.$owner['id'].'/preview';
   else $base=$owner['id']===$home?'/':(!empty($owner['slug'])?'/pages/'.rawurlencode($owner['slug']):null);
   if($base!==null)$map[$anchor]=$base.$anchor;
  }return $map;
 }
}
