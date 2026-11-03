<?php
namespace App\Services\Promotions;
use App\Models\{Product,PromotionOffer};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
class StorefrontOfferService {
 public function active():Builder {return PromotionOffer::query()->where('enabled',true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()));}
 public function forProduct(Product $product):Collection {
  if($product->status!=='active'||!$product->isBuyMode())return collect();
  return $this->active()->whereHas('products',fn($q)=>$q->whereKey($product->id))->orderBy('id')->get();
 }
 public function forItems(Collection $items):Collection {
  $items=$items->filter(fn($i)=>$i['variant']->enabled&&$i['variant']->product?->status==='active'&&$i['variant']->product?->isBuyMode());
  $ids=$items->pluck('variant.product_id')->unique()->all();if(!$ids)return collect();
  return $this->active()->whereHas('products',fn($q)=>$q->whereIn('products.id',$ids))->with('products:id')->orderBy('id')->get()->map(function($offer)use($items){
   $count=(int)$items->filter(fn($i)=>$offer->products->contains('id',$i['variant']->product_id))->sum('quantity');
   $remaining=(!$offer->repeat&&$count>=$offer->quantity)?0:($count%$offer->quantity===0?0:$offer->quantity-$count%$offer->quantity);
   return ['offer'=>$offer,'selected'=>$count,'remaining'=>$remaining];
  });
 }
}
