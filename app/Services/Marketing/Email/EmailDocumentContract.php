<?php
namespace App\Services\Marketing\Email;
use Illuminate\Validation\ValidationException;
final class EmailDocumentContract {
 public function validate(array $doc):void {
  $fail=fn()=>throw ValidationException::withMessages(['editor_json'=>'The email design contains an unsupported block, unsafe link or invalid section reference.']);
  if(count($doc)>500||($doc['root']['type']??null)!=='EmailLayout')$fail();
  $edges=[];
  $walk=function($v,$key='',$depth=0)use(&$walk,$fail){if($depth>30)$fail();if(is_array($v)){foreach($v as $k=>$value)$walk($value,(string)$k,$depth+1);return;}if(!is_string($v))return;
   if(in_array(strtolower($key),['url','href','src','linkhref','imageurl'],true)&&$v!==''&&!in_array($v,['{{unsubscribe_url}}','#'],true)&&!preg_match('~^https?://[^\s]+$~i',$v))$fail();
   if(preg_match('/(?:javascript\s*:|vbscript\s*:|<\s*(?:script|iframe|object|embed|svg)\b|on[a-z]+\s*=|expression\s*\()/i',$v))$fail();
  };
  foreach($doc as $id=>$block){if(!is_array($block)||!in_array($block['type']??null,['Avatar','Button','ColumnsContainer','Container','Divider','EmailLayout','Heading','Html','Image','Spacer','Text'],true)||!is_array($block['data']??null))$fail();$walk($block['data']);$edges[$id]=[];$refs=function($v)use(&$refs,&$edges,$id,$doc,$fail){if(!is_array($v))return;foreach($v as $key=>$value){if($key==='childrenIds'){if(!is_array($value))$fail();foreach($value as $child){if(!is_string($child)||!isset($doc[$child]))$fail();$edges[$id][]=$child;}}else $refs($value);}};$refs($block['data']);}
  $visit=function($id,$path=[])use(&$visit,$edges,$fail){if(isset($path[$id])||count($path)>30)$fail();$path[$id]=true;foreach($edges[$id]??[] as $child)$visit($child,$path);};foreach(array_keys($doc) as $id)$visit($id);
 }
}
