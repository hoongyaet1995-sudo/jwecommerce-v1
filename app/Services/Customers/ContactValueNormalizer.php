<?php
namespace App\Services\Customers;
class ContactValueNormalizer {
 public static function email(?string $value):?string { $v=mb_strtolower(trim($value??''));return filter_var($v,FILTER_VALIDATE_EMAIL)?$v:null; }
 public static function phone(?string $value,?string $country=null):?string {
  $v=trim($value??'');if(!preg_match('/^[+0-9 ().-]+$/D',$v))return null;
  $v=preg_replace('/[ ().-]/','',$v);if(str_starts_with($v,'00'))$v='+'.substr($v,2);
  if(!str_starts_with($v,'+'))return null;
  $digits=ltrim($v,'+');return preg_match('/^[1-9][0-9]{7,14}$/D',$digits)?'+'.$digits:null;
 }
}
