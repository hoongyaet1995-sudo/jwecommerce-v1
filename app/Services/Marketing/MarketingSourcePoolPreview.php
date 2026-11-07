<?php
namespace App\Services\Marketing;

/** Bounded read-only illustration of an allowed range, never AI's actual selection. */
class MarketingSourcePoolPreview
{
 public function read(array $input):array {
  $sources=app(MarketingAutomationSources::class);$settings=$sources->rangeInput($input);$modes=$sources->modes($settings);$groups=[];
  foreach($modes as $kind=>$mode){
   if($mode==='none')continue;$query=$sources->rangeQuery($kind,$settings);if($kind==='pages')$query->whereHas('publishedDocument');
   $count=(clone $query)->count();$limit=$kind==='products'?3:2;$offset=$count?(((int)($settings['preview_page'][$kind]??0))*$limit)%$count:0;
   if($kind==='products')$query->with(['images'=>fn($q)=>$q->orderBy('position')]);
   $query->orderBy('id');$rows=(clone $query)->offset($offset)->limit(min($limit,$count))->get();
   if($rows->count()<min($limit,$count))$rows=$rows->concat((clone $query)->limit(min($limit,$count)-$rows->count())->get());
   $items=$rows->map(function($row)use($kind,$sources){
    $detail=match($kind){'products'=>'Catalog product #'.$row->id,'categories'=>$sources->query('products')->where('category_id',$row->id)->count().' eligible products','collections'=>$row->type==='dynamic'?'Rule-based collection · membership rechecked at generation':$row->products()->where('products.status','active')->count().' eligible products','pages'=>'Published CMS page','promotions'=>$this->promotion($row)};
    return ['id'=>$row->id,'label'=>$sources->labelValue($kind,$row),'detail'=>$detail,'image'=>$kind==='products'?$this->image($row->images->first()?->path):null];
   })->values()->all();
   $groups[]=['kind'=>$kind,'mode'=>$mode,'count'=>$count,'configured_count'=>$mode==='items'?count($settings['sources'][$kind]):null,'unavailable_count'=>$mode==='items'?max(0,count($settings['sources'][$kind])-$count):0,'items'=>$items,'more'=>$count>$limit];
  }
  return ['groups'=>$groups,'as_of'=>now()->toIso8601String(),'scope'=>$settings['source_scope'],'note'=>'Preview of your allowed range, not the sources AI has selected. Availability is rechecked at generation. Category and collection products may overlap.'];
 }
 private function image(?string $path):?string {
  if(!$path||str_contains($path,'..')||!preg_match('~^/?[a-zA-Z0-9_./-]+\.(png|jpe?g|webp|gif)$~i',$path))return null;
  return str_starts_with($path,'/storage/')||str_starts_with($path,'/images/')?$path:'/storage/'.ltrim($path,'/');
 }
 private function promotion($p):string {
  $benefit=match($p->discount_type){'percentage'=>$p->value.'% off','fixed_amount'=>'RM '.number_format($p->value/100,2).' off','free_shipping'=>'Free shipping',default=>'See voucher terms'};
  $dates=($p->starts_at?$p->starts_at->timezone('Asia/Kuala_Lumpur')->format('d M Y'):'No start date').' – '.($p->ends_at?$p->ends_at->timezone('Asia/Kuala_Lumpur')->format('d M Y'):'No end date');
  return $benefit.' · '.$dates.' (UTC+8). Voucher conditions apply.';
 }
}
