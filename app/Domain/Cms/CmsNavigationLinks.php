<?php
namespace App\Domain\Cms;
use App\Models\PageDocument;

/** Resolve section/page ownership for shell links; public routes never expose drafts. */
final class CmsNavigationLinks
{
 public function forPayload(?array $payload,array $urls):array {
  $preview=($payload['context']['mode']??'public')==='preview';$wanted=[];$paths=[];
  foreach($urls as $url){if(!is_string($url))continue;if(preg_match('/^#section-([A-Za-z0-9][A-Za-z0-9_-]*)$/D',$url,$m))$wanted[$m[1]]=$url;elseif($preview&&preg_match('~^/pages/([A-Za-z0-9][A-Za-z0-9_-]*)$~D',$url,$m))$paths[$m[1]]=$url;}
  $map=[];foreach($payload['page']['document']['sections']??[] as $section)if(($section['isVisible']??false)&&isset($wanted[$section['id']??'']))unset($wanted[$section['id']]);
  if(!$wanted&&!$paths)return $map;
  $documents=PageDocument::query()->where('stage',$preview?'draft':'published')->whereHas('page',fn($q)=>$q->whereIn('status',$preview?['draft','published']:['published']))->get(['page_id','slug','document']);$candidates=[];$pageOwners=[];
  foreach($documents as $document){if(isset($paths[$document->slug]))$pageOwners[$document->slug][(string)$document->page_id]=(string)$document->page_id;foreach($document->document['sections']??[] as $section){$id=$section['id']??'';if(isset($wanted[$id])&&($section['isVisible']??false))$candidates[$id][(string)$document->page_id]=['id'=>(string)$document->page_id,'slug'=>$document->slug];}}
  foreach($paths as $slug=>$path){$matches=$pageOwners[$slug]??[];if(count($matches)===1)$map[$path]='/admin/pages/'.reset($matches).'/preview';}
  $home=app(HomepageSelection::class)->id();foreach($wanted as $id=>$anchor){$matches=$candidates[$id]??[];$owner=$matches[$home??'']??(count($matches)===1?reset($matches):null);if(!$owner)continue;
   $base=$preview?'/admin/pages/'.$owner['id'].'/preview':($owner['id']===$home?'/':(!empty($owner['slug'])?'/pages/'.rawurlencode($owner['slug']):null));if($base!==null)$map[$anchor]=$base.$anchor;
  }return $map;
 }
}
