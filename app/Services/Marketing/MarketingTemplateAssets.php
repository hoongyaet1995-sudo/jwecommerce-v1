<?php
namespace App\Services\Marketing;

use App\Models\Product;

class MarketingTemplateAssets
{
 public function primary(Product $product):array
 {
  $image=$product->images()->orderBy('position')->orderBy('id')->first();abort_unless($image,422,'请先提供商品图片。');$path=$image->path;
  if(preg_match('~^https?://~i',$path)){
   $url=parse_url($path);$origin=parse_url(config('app.url'));
   $hosts=array_unique(array_filter([$origin['host']??null,'127.0.0.1','localhost']));
   abort_unless(in_array(strtolower($url['host']??''),$hosts,true)&&empty($url['user'])&&empty($url['pass'])&&empty($url['query'])&&empty($url['fragment']),422,'商品图片需来自当前站点，暂不下载外部图片。');$path=rawurldecode($url['path']??'');
  }
  abort_if(str_contains($path,"\0")||str_contains($path,'\\')||in_array('..',explode('/',$path),true),422,'商品图片路径不可用。');
  $file=realpath(public_path(ltrim($path,'/')));$root=realpath(public_path());
  abort_unless($file&&$root&&str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))&&is_file($file)&&filesize($file)<=8*1024*1024,422,'商品图片暂不可用。');
  $size=@getimagesize($file);abort_unless($size&&in_array($size['mime'],['image/png','image/jpeg','image/webp'],true),422,'图片格式不支持。');
  return ['id'=>'primary','product_id'=>(string)$product->id,'path'=>$file,'sha256'=>hash_file('sha256',$file),'width'=>$size[0],'height'=>$size[1],'rights_confirmed'=>true,'provenance'=>str_contains($path,'concept')?'ai_concept':'merchant_photo'];
 }
}
