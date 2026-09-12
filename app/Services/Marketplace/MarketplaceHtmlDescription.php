<?php
namespace App\Services\Marketplace;
use App\Domain\Cms\MediaUrlPolicy;
final class MarketplaceHtmlDescription {
 public function render(string $html):string {
  abort_if(strlen($html)>50000,422,'Channel description exceeds the supported import size.');
  if($html==='')return '';
  $doc=new \DOMDocument();$previous=libxml_use_internal_errors(true);
  try {$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
  finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
  $render=function(\DOMNode $node)use(&$render):string {
   if($node instanceof \DOMText)return htmlspecialchars($node->nodeValue,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
   if(!$node instanceof \DOMElement)return '';
   $tag=strtolower($node->tagName);
   if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','template','head'],true))return '';
   $inside='';foreach($node->childNodes as $child)$inside.=$render($child);
   if(!in_array($tag,['p','div','span','br','h1','h2','h3','h4','h5','h6','ul','ol','li','strong','b','em','i','u','table','thead','tbody','tr','td','th','blockquote','a','img'],true))return $inside;
   $attrs='';
   foreach(($tag==='img'?['src','alt']:($tag==='a'?['href','title']:[])) as $attribute){
    if(!$node->hasAttribute($attribute))continue;$value=$node->getAttribute($attribute);
    if(in_array($attribute,['src','href'],true)&&($value===''||!MediaUrlPolicy::isSafe($value)))continue;
    $attrs.=' '.$attribute.'="'.htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
   }
   if($tag==='img'&&!str_contains($attrs,' src='))return '';
   return '<'.$tag.$attrs.'>'.(in_array($tag,['img','br'],true)?'':$inside.'</'.$tag.'>');
  };
  $result='';foreach($doc->getElementsByTagName('body') as $body){foreach($body->childNodes as $node)$result.=$render($node);}
  abort_if(strlen($result)>50000,422,'Channel description exceeds the supported import size.');return $result;
 }
}
