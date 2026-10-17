<?php
namespace App\Services\Support;
use App\Models\Product;
class SupportActionResolver
{
 public function resolve(array $references,string $content,bool $legacy=false):array
 {
  $trusted=[];
  if($legacy){
   preg_match_all('~https?://[^\s<>]+~u',$content,$matches);
   foreach($matches[0] as $url){
    $url=rtrim($url,".,;)");$parts=parse_url($url);
    if(($parts['host']??'')!==request()->getHost() || isset($parts['user']) || isset($parts['pass']))continue;
    if(!preg_match('~^/products/([^/]+)$~',$parts['path']??'',$m))continue;
    $product=Product::where('status','active')->where('handle',rawurldecode($m[1]))->first();
    if($product){$references[]=['type'=>'view_product','resource_id'=>$product->id];$trusted[]=$url;}
    if(count($references)>=5)break;
   }
  }
  $actions=[];
  foreach(array_slice($references,0,5) as $ref){
   if (($ref['type']??null)==='request_human' && in_array($ref['reason']??null,['unanswered_twice','unresolved','service_unavailable','human_required','custom_keyword','explicit_request'],true)) {
    $actions['handoff']=['type'=>'request_human'];continue;
   }
   if(($ref['type']??null)==='view_order_tracking'){$actions['order_tracking']=['type'=>'view_order_tracking','resource_id'=>0,'label'=>'Order Tracking','title'=>'Verify or view your order','url'=>route('order-tracking.show',[],false)];continue;}
   if(in_array($ref['type']??null,['view_category','view_collection'],true)&&ctype_digit((string)($ref['resource_id']??''))){
    $category=$ref['type']==='view_category';$model=$category?\App\Models\Category::class:\App\Models\Collection::class;
    $record=$model::where('is_active',true)->find($ref['resource_id']);if(!$record)continue;
    $actions[$ref['type'].':'.$record->id]=['type'=>$ref['type'],'resource_id'=>$record->id,'label'=>$category?'View Category':'View Collection','title'=>$category?$record->name:$record->title,'url'=>route($category?'storefront.categories.show':'storefront.collections.show',[$category?'category':'collection'=>$record->slug],false)];continue;
   }
   if(($ref['type']??null)!=='view_product'||!ctype_digit((string)($ref['resource_id']??'')))continue;
   $product=Product::where('status','active')->with(['images'=>fn($q)=>$q->orderBy('position')->orderBy('id')])->find($ref['resource_id']);if(!$product)continue;
   $actions['product:'.$product->id]=['type'=>'view_product','resource_id'=>$product->id,'label'=>'View Product','title'=>$product->title,'image'=>$product->images->first()?->path,'price'=>((app(\App\Services\SupportFaqService::class)->read()['productCatalog']['canCheckLiveStock']??false)&&$product->showsPrice())?app(\App\Services\ProductPurchaseRules::class)->price($product,$product->base_price):null,'url'=>route('storefront.products.show',['handle'=>$product->handle],false)];
  }
  if($trusted)$content=str_replace($trusted,'',$content);
  if($actions)$content=preg_replace('/(?:产品链接|商品链接|Product link|Link)\s*[:：]\s*(?=\n|$)/iu','',$content);
  return ['content'=>trim($content),'actions'=>array_values($actions)];
 }
 public function references(array $sourceIds):array {
  $refs=[];foreach($sourceIds as $id)if(preg_match('/^(product|category|collection):(\d+)$/',(string)$id,$m))$refs[]=['type'=>'view_'.$m[1],'resource_id'=>(int)$m[2]];
  return array_slice($refs,0,5);
 }
}
