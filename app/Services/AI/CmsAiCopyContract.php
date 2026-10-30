<?php
namespace App\Services\AI;
use Illuminate\Validation\ValidationException;
final class CmsAiCopyContract {
 private ?array $manifest=null;
 public function fields(array $section,?array $item=null):array {
  $m=$this->manifest??=json_decode(file_get_contents(resource_path('cms/ai-copy-capabilities.v1.json')),true,512,JSON_THROW_ON_ERROR);
  $type=$section['type'];$variants=$m['sections'][$type]??[];$selector=$m['selectors'][$type]??null;$preset=$selector?($section[$selector]??''):'';
  $flag=fn($k)=>array_key_exists($k,$section)?($section[$k]?'true':'false'):'unset';
  $keys=($variants[$preset]??$variants['']??[])[$flag('showSectionHeading').':'.$flag('valuesShowHeading')][$item===null?'section':'items']??[];
  $record=$item??$section;return array_values(array_filter($keys,function($key)use($record,$item){$link=['buttonText'=>$item===null?'buttonLink':'linkUrl','secondaryButtonText'=>'secondaryButtonLink','thirdButtonText'=>'thirdButtonLink'][$key]??null;if(!$link)return true;$v=$record[$link]??'';return is_string($v)&&preg_match('~^(?:/(?!/)|https?://|mailto:|tel:|#)~i',$v)&&!preg_match('/[\x00-\x20]/',$v);}));
 }
 public function apply(array $section,array $patch):array {
  $allowed=$this->fields($section);$this->keys($patch,array_merge(['id','items'],$allowed));
  foreach($allowed as $field)if(array_key_exists($field,$patch))$section[$field]=$this->text($patch[$field],$field,$section);
  if(array_key_exists('items',$patch)){
   if(!is_array($patch['items'])||!array_is_list($patch['items']))$this->fail();$seen=[];
   foreach($patch['items'] as $change){if(!is_array($change)||!is_string($change['id']??null)||isset($seen[$change['id']]))$this->fail();$seen[$change['id']]=true;$index=array_search($change['id'],array_column($section['items']??[],'id'),true);if($index===false)$this->fail();$item=$section['items'][$index];$fields=$this->fields($section,$item);$this->keys($change,array_merge(['id'],$fields));foreach($fields as $field)if(array_key_exists($field,$change))$section['items'][$index][$field]=$this->text($change[$field],$field,$section);}
  }return $section;
 }
 private function keys(array $patch,array $allowed):void {if(array_diff(array_keys($patch),$allowed))$this->fail();}
 private function text($value,string $field,array $section):string {$max=match($field){'title'=>80,'subtitle'=>120,'buttonText','secondaryButtonText','thirdButtonText'=>40,'description'=>240,'content'=>in_array($section['type'],['hero_banner','split_hero'],true)?280:500,'leadText'=>500,default=>240};if(!is_string($value)||mb_strlen($value)>$max||strip_tags($value)!==$value)$this->fail();return $value;}
 private function fail():never {throw ValidationException::withMessages(['generation'=>'AI returned content outside this section’s supported elements. Your draft has not changed.']);}
}
