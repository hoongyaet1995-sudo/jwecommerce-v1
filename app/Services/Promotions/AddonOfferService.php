<?php
namespace App\Services\Promotions;
use App\Models\{AddonDeal,Product};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class AddonOfferService {
 protected function kind():string {return 'addon';}
 public function active():Collection {
  $q=AddonDeal::where('kind',$this->kind())->where('enabled',true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('id');
  if(DB::transactionLevel()>0)$q->lockForUpdate();return $q->get();
 }
 public function forProduct(Product $product):Collection {
  if($product->status!=='active'||$product->purchase_mode!=='buy')return collect();
  $offers=$this->active()->filter(fn($d)=>in_array($product->id,$d->product_ids));
  $ids=$offers->flatMap(fn($d)=>array_column($d->addons,'product_id'))->unique();
  $products=Product::with(['images','variants'])->whereIn('id',$ids)->where('status','active')->where('purchase_mode','buy')->get()->keyBy('id');
  return $offers->map(function($d)use($products){$d->setRelation('extras',collect($d->addons)->map(function($a)use($products){$p=$products->get($a['product_id']);if(!$p)return null;$variants=$p->variants->filter(fn($v)=>$v->enabled&&$v->price>$a['price']&&((app(\App\Services\Inventory\WebsiteInventoryAvailability::class)->quantity($v)??0)>0));return $variants->isEmpty()?null:['product'=>$p,'variants'=>$variants,'price'=>$a['price']];})->filter()->values());return $d;})->filter(fn($d)=>$d->extras->isNotEmpty());
 }
 /** Active offers grouped by qualifying product, using the same purchasable extras as PDP. */
 public function cartOffers(Collection $items):array {
  $offers=$this->active();$result=[];
  $products=$items->map(fn($i)=>$i['variant']->product)->unique('id');
  foreach($products as $product){
   if($product->status!=='active'||$product->purchase_mode!=='buy')continue;
   if($offers->contains(fn($d)=>in_array($product->id,$d->product_ids)))$result[$product->id]=$this->forProduct($product);
  }
  return $result;
 }
 /** Reminder capacity is derived from the applied offer, never another campaign. */
 public function hasRemainingSlots(\App\Models\AddonDeal $deal,Collection $items,array $snapshot):bool {
  $main=(int)$items->filter(fn($i)=>in_array($i['variant']->product_id,$deal->product_ids))->sum('quantity');
  $capacity=$deal->kind==='gift'
   ? min($deal->per_order_cap,intdiv($main,max(1,(int)$deal->buy_quantity))*(int)$deal->gift_quantity)
   : min($main,$deal->per_order_cap);
  if($capacity<=0)return false;
  if(($snapshot['type']??null)!==$this->kind()||(int)($snapshot['offer_id']??0)!==$deal->id)return true;
  $prices=array_column($deal->addons,'price','product_id');$used=0;
  foreach($items as $item){$v=$item['variant'];$saving=(int)$v->price-(int)($prices[$v->product_id]??$v->price);
   if($saving>0)$used+=intdiv((int)($snapshot['allocations'][(string)$v->id]??0),$saving);
  }
  return $used<$capacity;
 }
 public function quote(Collection $items,array $selections):array {
  $best=['version'=>1,'type'=>null,'discount'=>0,'allocations'=>[]];
  foreach($this->active() as $d){
   $main=$items->filter(fn($i)=>in_array($i['variant']->product_id,$d->product_ids))->sum('quantity');$prices=array_column($d->addons,'price','product_id');$lines=[];
   foreach($items as $i){$v=$i['variant'];$selected=$selections[$d->id][$v->id]??null;if(!isset($prices[$v->product_id])||!is_array($selected)||($selected['price']??null)!==$prices[$v->product_id])continue;
    $lines[]=['key'=>(string)$v->id,'quantity'=>min((int)$i['quantity'],(int)($selected['quantity']??0)),'unit_price'=>(int)$v->price,'addon_price'=>(int)$prices[$v->product_id]];
   }
   $result=app(AddonCalculator::class)->calculate((int)$main,$d->per_order_cap,$lines);
   if($result['discount']>$best['discount'])$best=['version'=>1,'type'=>'addon','offer_id'=>$d->id,'name'=>$d->name,...$result];
  }return $best;
 }
}
