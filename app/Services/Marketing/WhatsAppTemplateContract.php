<?php
namespace App\Services\Marketing;
/** Supported CRM editor subset, not a claim to implement every Meta template feature. */
class WhatsAppTemplateContract {
 public function normalize(array $d,bool $submit=false):array {
  $meta=$d['meta_name']??$d['name'];abort_unless(is_string($meta)&&preg_match('/^[a-z][a-z0-9_]{0,149}$/D',$meta),422,'Use lowercase letters, numbers and underscores for the Meta template name.');$category=$d['category']??'MARKETING';abort_unless(in_array($category,['MARKETING','UTILITY'],true),422,'Choose Marketing or Utility.');$header=$d['header_type']??'NONE';abort_unless(in_array($header,['NONE','TEXT'],true),422,'Choose no header or a text header.');$text=$header==='TEXT'?($d['header_text']??''):'';$examples=$header==='TEXT'?array_map(fn($x)=>(string)$x,$d['header_examples']??[]):[];
  abort_if(mb_strlen($text)>60||($header==='TEXT'&&trim($text)==='')||str_contains($text,"\n"),422,'Use a text header of up to 60 characters.');$this->variables($text,$examples,$submit,1);$bodyExamples=array_map(fn($x)=>(string)$x,$d['examples']??[]);$this->variables($d['body'],$bodyExamples,$submit,20);
  if($submit&&preg_match('/^\s*\{\{|\}\}\s*$/u',$d['body']))abort(422,'Add fixed wording before and after the message body variables.');
  $buttons=$d['buttons']??((($d['button']??'')!==''&&($d['url']??'')!=='')?[['type'=>'URL','text'=>$d['button'],'value'=>$d['url'],'example'=>'']]:[]);abort_if(count($buttons)>3,422,'This editor supports up to three buttons.');$clean=[];$types=[];
  foreach($buttons as $b){$type=$b['type']??'';$label=trim((string)($b['text']??''));$value=trim((string)($b['value']??''));$example=trim((string)($b['example']??''));abort_unless(in_array($type,['URL','PHONE_NUMBER','QUICK_REPLY'],true)&&$label!==''&&mb_strlen($label)<=25&&!preg_match('/[\r\n]/',$label),422,'Choose a supported button type and label of up to 25 characters.');
   if($type==='URL'){$dynamic=str_contains($value,'{{');abort_if($dynamic&&(!str_ends_with($value,'{{1}}')||substr_count($value,'{{')!==1),422,'A dynamic website button may use only {{1}} at the end of the URL.');$resolved=str_replace('{{1}}',rawurlencode($example?:'example'),$value);$p=parse_url($resolved);abort_unless(strlen($value)<=2000&&filter_var($resolved,FILTER_VALIDATE_URL)&&($p['scheme']??'')==='https'&&!isset($p['user'])&&!isset($p['pass']),422,'Use a valid HTTPS website button destination.');if($submit&&$dynamic&&$example==='')abort(422,'Add an example for the dynamic website button.');}
   elseif($type==='PHONE_NUMBER'){abort_unless(preg_match('/^\+[1-9][0-9]{7,14}$/D',$value),422,'Use an international phone number including its country code.');$example='';}
   else{$value='';$example='';}$clean[]=['type'=>$type,'text'=>$label,'value'=>$value,'example'=>$example];$types[$type]=($types[$type]??0)+1;
  }
  abort_if(($types['URL']??0)>2||($types['PHONE_NUMBER']??0)>1,422,'Use at most two website buttons and one phone button.');
  return ['meta_name'=>$meta,'category'=>$category,'header_type'=>$header,'header_text'=>$text,'header_examples'=>$examples,'examples'=>$bodyExamples,'buttons'=>$clean];
 }
 private function variables(string $text,array $examples,bool $submit,int $max):void {
  preg_match_all('/\{\{([0-9]+)\}\}/',$text,$m);$n=array_values(array_unique(array_map('intval',$m[1])));sort($n);abort_unless(!$n||($n===range(1,count($n))&&count($n)<=$max),422,'Number variables consecutively from {{1}} within the supported limit.');abort_if(str_contains(preg_replace('/\{\{[0-9]+\}\}/','',$text),'{{'),422,'Use numbered variables such as {{1}}.');if($submit){abort_unless(count($examples)===count($n),422,'Provide an example for each variable.');foreach($examples as $e)abort_if(trim($e)==='',422,'Provide a realistic example for every variable.');}
 }
 public function components(array $doc,bool $examples=true):array {
  $parts=[];if(($doc['header_type']??'NONE')==='TEXT'){$h=['type'=>'HEADER','format'=>'TEXT','text'=>$doc['header_text']];if($examples&&!empty($doc['header_examples']))$h['example']=['header_text'=>$doc['header_examples']];$parts[]=$h;}$b=['type'=>'BODY','text'=>$doc['body']];if($examples&&!empty($doc['examples']))$b['example']=['body_text'=>[$doc['examples']]];$parts[]=$b;
  if(($doc['footer']??'')!=='')$parts[]=['type'=>'FOOTER','text'=>$doc['footer']];$buttons=$doc['buttons']??((($doc['button']??'')!==''&&($doc['url']??'')!=='')?[['type'=>'URL','text'=>$doc['button'],'value'=>$doc['url'],'example'=>'']]:[]);$out=[];
  foreach($buttons as $x){$b=['type'=>$x['type'],'text'=>$x['text']];if($x['type']==='URL'){$b['url']=$x['value'];if($examples&&str_contains($x['value'],'{{1}}'))$b['example']=[str_replace('{{1}}',rawurlencode($x['example']),$x['value'])];}if($x['type']==='PHONE_NUMBER')$b['phone_number']=$x['value'];$out[]=$b;}
  if($out)$parts[]=['type'=>'BUTTONS','buttons'=>$out];return $parts;
 }
}
