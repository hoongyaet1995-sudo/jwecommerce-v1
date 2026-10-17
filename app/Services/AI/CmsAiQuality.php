<?php
namespace App\Services\AI;
use App\Models\{Product,Category,Collection,Page};
use Illuminate\Support\Facades\Route;
final class CmsAiQuality
{
 public function review(array $document):array{
  $links=[];foreach($document['sections'] as $s){if(($s['isVisible']??true)===false)continue;foreach(array_merge([$s['buttonLink']??null,$s['secondaryButtonLink']??null],array_column($s['items']??[],'linkUrl')) as $url)if(is_string($url)&&$url!=='')$links[]=['section'=>$s['id'],'url'=>$url];}
  $groups=[];$parsed=[];foreach($links as $entry){$url=$entry['url'];if(!str_starts_with($url,'/')||str_starts_with($url,'//'))continue;$path=parse_url($url,PHP_URL_PATH)?:'/';if(preg_match('~^/(products|categories|collections|pages)/([^/]+)/?$~',$path,$m)){$groups[$m[1]][]=rawurldecode($m[2]);$parsed[]=$entry+['kind'=>$m[1],'slug'=>rawurldecode($m[2]),'path'=>$path];}else $parsed[]=$entry+['kind'=>null,'path'=>$path];}
  $valid=[];foreach($groups as $kind=>$slugs){$slugs=array_values(array_unique($slugs));$valid[$kind]=match($kind){'products'=>Product::where('status','active')->whereIn('handle',$slugs)->pluck('handle')->all(),'categories'=>Category::where('is_active',true)->whereIn('slug',$slugs)->pluck('slug')->all(),'collections'=>Collection::where('is_active',true)->whereIn('slug',$slugs)->pluck('slug')->all(),'pages'=>Page::where('status','published')->whereHas('publishedDocument')->whereIn('slug',$slugs)->pluck('slug')->all()};}
  $issues=[];foreach($parsed as $e){$message=null;$severity='warning';if(preg_match('~^/(admin|api)(/|$)~',$e['path'])){$message='Use a customer-facing destination instead of '.$e['path'];$severity='block';}elseif($e['kind']&&!in_array($e['slug'],$valid[$e['kind']],true))$message='Destination is missing, inactive or unpublished: '.$e['path'];elseif(!$e['kind'])try{Route::getRoutes()->match(\Illuminate\Http\Request::create($e['path'],'GET'));}catch(\Symfony\Component\HttpKernel\Exception\HttpException){$message='No storefront route found: '.$e['path'];}if($message)$issues[]=['id'=>'link-'.sha1($e['section'].$e['url']),'sectionId'=>$e['section'],'severity'=>$severity,'message'=>$message];}
  $ids=[];foreach($document['sections'] as $s)foreach($s['productIds']??[] as $id)$ids[]=$id;
  if($ids){$active=array_map('strval',Product::where('status','active')->whereIn('id',array_unique($ids))->pluck('id')->all());foreach($document['sections'] as $s)if(array_diff(array_map('strval',$s['productIds']??[]),$active))$issues[]=['id'=>'catalog-'.$s['id'],'sectionId'=>$s['id'],'severity'=>'warning','message'=>'Some selected catalog products are missing or inactive. Review this listing.'];}
  return array_values(array_column($issues,null,'id'));
 }
}
