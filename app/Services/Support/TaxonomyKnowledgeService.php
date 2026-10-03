<?php
namespace App\Services\Support;
use App\Models\Category;
use App\Models\Collection;
class TaxonomyKnowledgeService {
 public function facts(string $query,array $scope):array{
 if(!($scope['isEnabled']??false)||!($scope['canRecommendUpsells']??false))return [];
 $terms=array_slice(array_filter(preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($query),-1,PREG_SPLIT_NO_EMPTY),fn($t)=>mb_strlen($t)>2&&!in_array($t,['category','categories','collection','collections','show','list','what','have','your','the','and','can','browse','store','available','all','our','which','catalog','catalogue'])),0,5);
 $facts=[];
 foreach(['category'=>[Category::class,'name'],'collection'=>[Collection::class,'title']] as $type=>[$model,$name]){
 $q=$model::where('is_active',true);if($terms)$q->where(function($q)use($terms,$name){foreach($terms as $term){$term=addcslashes($term,'\\%_');$q->orWhere($name,'like','%'.$term.'%')->orWhere('slug','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%');}});
 foreach($q->withCount(['products'=>fn($q)=>$q->where('status','active')])->with(['products'=>fn($q)=>$q->where('status','active')->select('products.id','products.title','products.category_id')->orderBy('products.id')->limit(3)])->orderBy($name)->limit(5)->get() as $record){$facts[]=['id'=>$type.':'.$record->id,'question'=>$record->$name,'answer'=>json_encode(['type'=>$type,'name'=>$record->$name,'description'=>mb_substr(strip_tags($record->description??''),0,600),'published_product_count'=>$record->products_count,'products'=>$record->products->take(3)->map(fn($p)=>['id'=>$p->id,'name'=>$p->title])->all(),'membership'=>'Current storefront published association; product sample is partial.'],JSON_UNESCAPED_UNICODE)];}
 }
 return $facts;
 }
}
