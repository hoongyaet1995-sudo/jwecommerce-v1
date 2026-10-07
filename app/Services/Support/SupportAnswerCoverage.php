<?php
namespace App\Services\Support;
use Illuminate\Support\Facades\Validator;
class SupportAnswerCoverage
{
 public function inspect(array $result,array $questions,array $sourceIds,array $tools):array
 {
  $coverage=$result['coverage']??null;
  $valid=is_array($coverage)&&count($coverage)===count($questions);
  $seen=[];$clean=[];
  if($valid)foreach($coverage as $part){
   $index=$part['index']??null;$status=$part['status']??null;$ids=$part['source_ids']??null;
   if(!is_int($index)||!array_key_exists($index,$questions)||in_array($index,$seen,true)||!in_array($status,['answered','missing'],true)||!is_array($ids)||array_filter($ids,fn($id)=>!is_string($id))||array_diff($ids,$sourceIds)||($status==='answered'&&(!$ids||array_diff($ids,$result['faq_ids']??[])))){$valid=false;break;}
   $seen[]=$index;$clean[]=['index'=>$index,'status'=>$status,'source_ids'=>array_values(array_unique($ids))];
  }
  if(!$valid)return ['valid'=>false,'coverage'=>[],'retry'=>null];
  $retry=null;$follow=$result['follow_up']??null;
  $public=array_diff(array_keys($tools),['order.lookup']);
  if(collect($clean)->contains(fn($p)=>$p['status']==='missing')&&is_array($follow)&&$public){
   $validator=Validator::make($follow,['name'=>'required|in:'.implode(',',$public),'query'=>'required|string|max:160','target'=>'sometimes|string|max:80']);
   if(!$validator->fails())$retry=$validator->validated();
  }
  return ['valid'=>true,'coverage'=>$clean,'retry'=>$retry];
 }
}
