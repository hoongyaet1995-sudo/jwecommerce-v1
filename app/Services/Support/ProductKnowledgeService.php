<?php
namespace App\Services\Support;
use App\Models\Product;
use App\Services\Inventory\WebsiteInventoryAvailability;
use App\Support\StorefrontCurrencyContext;
class ProductKnowledgeService {
 public function facts(string $question,array $scope):array {
 if(!($scope['isEnabled']??false))return [];
 if($question==='__catalogue_count__') {
   if(!($scope['canRecommendUpsells']??false))return [];
   return [['id'=>'catalogue:count','question'=>'Published catalogue count','answer'=>json_encode(['published_product_count'=>Product::where('status','active')->count(),'scope'=>'whole published online catalogue, not one collection'])]];
 }
 $list=$question==='__catalogue_list__';

 $terms=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($question),-1,PREG_SPLIT_NO_EMPTY);
 $stop=['what','which','have','does','this','that','with','about','please','price','stock','available','recommend','products','product','show','find','want','need','your','you','can','the','for','are','how','much','any','and','tell','sell'];
 $terms=array_slice(array_values(array_filter(array_unique($terms),fn($t)=>mb_strlen($t)>2&&!in_array($t,$stop,true))),0,8);if(!$terms&&!$list)return [];
 $query=Product::where('status','active'); if(!$list)$query->where(function($q)use($terms,$scope){foreach($terms as $term){$escaped=addcslashes($term,'\\%_');$q->orWhere('title','like','%'.$escaped.'%')->orWhere('handle','like','%'.$escaped.'%')->orWhere('brand','like','%'.$escaped.'%');if($scope['canExplainSizingAndMaterials']??false)$q->orWhere('description','like','%'.$escaped.'%');}});
 $limit=max(1,min(5,(int)($scope['maxRecommendations']??3)));
 $facts=$query->with(['variants','pdpContent'])->orderBy('id')->limit(50)->get()->filter(fn($p)=>($scope['canRecommendUpsells']??false)||mb_stripos($question,$p->title)!==false||trim($question)===$p->handle)->take($limit)->map(function($p)use($scope){
 $data=['name'=>$p->title,'url'=>route('storefront.products.show',['handle'=>$p->handle])];
 if($scope['canExplainSizingAndMaterials']??false){$data['description']=mb_substr(trim(strip_tags($p->description??'')),0,1800);$data['features']=$p->features;$data['specifications']=$p->pdpContent?->specifications;}
 if($scope['canCheckLiveStock']??false){$currency=app(StorefrontCurrencyContext::class);$data['base_price']=$currency->format($p->base_price);$variants=$p->variants->filter(fn($v)=>$v->enabled!==false);$data['variants_complete']=$variants->count()<=12;$data['variants']=$variants->take(12)->map(function($v)use($currency,$p){$quantity=app(WebsiteInventoryAvailability::class)->quantity($v);return ['name'=>$v->title,'options'=>$v->options,'size'=>$v->size,'material'=>$v->material,'price'=>$currency->format($v->price??$p->base_price),'availability'=>$quantity===null?'unverified':($quantity>0?'in_stock':'out_of_stock')];})->values()->all();if(!$variants->count())$data['availability']='unverified';}
 $encoded=json_encode($data,JSON_UNESCAPED_UNICODE);if(strlen($encoded)>10000)return null;return ['id'=>'product:'.$p->id,'question'=>$p->title,'answer'=>$encoded];})->filter()->values()->all();
 if(!$facts && ($scope['canRecommendUpsells']??false))return [['id'=>'catalogue:search','question'=>'Catalogue search result','answer'=>json_encode(['query'=>$question,'matches'=>0,'scope'=>'No matching published product found by searched terms; not proof that the store never sells it.'])]];
 return $facts;
 }
}
