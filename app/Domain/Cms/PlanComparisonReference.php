<?php
namespace App\Domain\Cms;
final class PlanComparisonReference
{
 public function resolve(array $reference,?array $document):array {
  $linked=($reference['planContentSource']??'manual')==='reference';$sections=collect($document['sections']??[]);$source=$sections->first(fn($s)=>($s['id']??'')===($reference['pricingSourceSectionId']??null)&&($s['type']??'')==='pricing_table'&&($s['pricingPreset']??'')==='saas-packages'&&($s['isVisible']??false));$terms=$linked?($sections->first(fn($s)=>($s['id']??'')===($reference['pricingSourceTermsSectionId']??null)&&($s['isVisible']??false))['content']??''):($reference['planTerms']??'');$plans=[];
  foreach($reference['items']??[] as $own){$item=$linked?collect($source['items']??[])->firstWhere('id',$own['sourceId']??null):$own;if(!$item||$linked&&(!$source||!$terms)){$plans[]=['id'=>$own['id'],'status'=>'unavailable'];continue;}
   $base=app(StarterPricingReference::class)->resolve(['planContentSource'=>'manual','planName'=>$item['title']??'','planPricingMode'=>($item['pricingMode']??'amount')==='contact'?'contact':'amount','planPriceLabel'=>$item['pricingLabel']??'','planAmount'=>$item['price']??null,'planCurrency'=>$linked?($source['pricingCurrency']??''):($reference['pricingCurrency']??''),'planPeriod'=>$item['billingLabel']??'','planTerms'=>$terms,'planDescription'=>$item['description']??''],null);
   $plans[]=array_merge($base,['id'=>$own['id'],'features'=>$item['features']??[],'badge'=>$item['badge']??'','isHighlighted'=>(bool)($item['isHighlighted']??false),'highlightLabel'=>$item['highlightLabel']??'']);
  }return ['status'=>count($plans)>0&&collect($plans)->every(fn($p)=>$p['status']==='ready')?'ready':'unavailable','terms'=>$terms,'plans'=>$plans];
 }
}
