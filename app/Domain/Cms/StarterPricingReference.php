<?php
namespace App\Domain\Cms;
use App\Models\Page;

/** Reads current canonical source documents; never stores a second price. */
final class StarterPricingReference
{
 public function forDocument(array $document,bool $preview):array {
  $references=array_filter($document['sections']??[],fn($s)=>(($s['type']??'')==='faq_accordion'&&in_array($s['faqPreset']??'',['corporate-starter-faq','pricing-card-faq','pricing-band-faq'],true)||($s['type']??'')==='pricing_table'&&($s['pricingPreset']??'')==='plan-comparison')&&($s['isVisible']??false));
  if(!$references)return [];
  $linked=array_filter($references,fn($s)=>($s['planContentSource']??(isset($s['pricingSourcePageId'])?'reference':'manual'))==='reference');
  $ids=array_values(array_unique(array_filter(array_column($linked,'pricingSourcePageId'),fn($id)=>is_string($id)&&ctype_digit($id))));
  $pages=Page::with($preview?'draftDocument':'publishedDocument')->whereIn('id',$ids)->get()->keyBy('id');$facts=[];
  foreach($references as $index=>$s){$page=$pages->get($s['pricingSourcePageId']??'');$source=null;
   if($page&&($preview||$page->status==='published'))$source=($preview?$page->draftDocument:$page->publishedDocument)?->document;
   $facts[$index]=($s['pricingPreset']??'')==='plan-comparison'?['planComparison'=>app(PlanComparisonReference::class)->resolve($s,$source)]:['starterPricing'=>$this->resolve($s,$source)];
  }return $facts;
 }
 public function resolve(array $reference,?array $document):array {
  if(($reference['planContentSource']??(isset($reference['pricingSourcePageId'])?'reference':'manual'))==='manual'){
   $missing=['status'=>'unavailable'];$name=$reference['planName']??'';if(!is_string($name)||trim($name)==='')return $missing;
   $terms=$reference['planTerms']??'';
   if(($reference['planPricingMode']??'amount')==='contact')return trim($reference['planPriceLabel']??'')!==''?['status'=>'ready','name'=>$name,'priceLabel'=>$reference['planPriceLabel'],'terms'=>$terms,'description'=>$reference['planDescription']??'']:$missing;
   $price=$reference['planAmount']??null;if(!is_numeric($price)||!is_finite((float)$price)||$price<0||!preg_match('/^[A-Z]{3}$/',$reference['planCurrency']??''))return $missing;
   return ['status'=>'ready','name'=>$name,'price'=>$price,'currency'=>$reference['planCurrency'],'billingLabel'=>$reference['planPeriod']??'','terms'=>$terms,'description'=>$reference['planDescription']??''];
  }
  $missing=['status'=>'unavailable'];$sections=collect($document['sections']??[]);
  $plan=$sections->first(fn($s)=>($s['id']??'')===($reference['pricingSourceSectionId']??null)&&((($s['type']??'')==='pricing_table'&&($s['pricingPreset']??'')==='saas-packages')||(($s['type']??'')==='feature_cards'&&($s['featureCardsPreset']??'')==='saas-addons'))&&($s['isVisible']??false));
  $item=collect($plan['items']??[])->firstWhere('id',$reference['pricingSourceItemId']??null);
  $terms=$sections->first(fn($s)=>($s['id']??'')===($reference['pricingSourceTermsSectionId']??null)&&($s['isVisible']??false));
  if(($plan['type']??'')==='feature_cards'&&($plan['featureCardsPreset']??'')==='saas-addons'){
   $lines=preg_split('/\\r?\\n/',$item['description']??'');$priceLabel=array_shift($lines);
   return !empty($item['title'])&&trim($priceLabel??'')!==''&&!empty($terms['content'])?['status'=>'ready','kind'=>'addon','label'=>$plan['title']??'','icon'=>$item['icon']??'','name'=>$item['title'],'priceLabel'=>$priceLabel,'description'=>implode("\n",$lines),'terms'=>$terms['content']]:$missing;
  }
  if(!$item||empty($item['title'])||($item['pricingMode']??'')==='contact'||!is_numeric($item['price']??null)||!is_finite((float)$item['price'])||$item['price']<0||empty($item['billingLabel'])||!preg_match('/^[A-Z]{3}$/',$plan['pricingCurrency']??'')||empty($terms['content']))return $missing;
  return ['status'=>'ready','name'=>$item['title'],'price'=>$item['price'],'currency'=>$plan['pricingCurrency'],'billingLabel'=>$item['billingLabel'],'terms'=>$terms['content'],'description'=>$item['description']??''];
 }
}
