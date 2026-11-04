<?php
namespace App\Services\Promotions;
use Illuminate\Support\Collection;
class FreeGiftOfferService extends AddonOfferService {
 protected function kind():string{return 'gift';}
 public function quote(Collection $items,array $selections):array {
  $best=['version'=>1,'type'=>null,'discount'=>0,'allocations'=>[]];
  foreach($this->active() as $d){
   $main=$items->filter(fn($i)=>in_array($i['variant']->product_id,$d->product_ids))->sum('quantity');
   $allow=min($d->per_order_cap,intdiv((int)$main,max(1,(int)$d->buy_quantity))*(int)$d->gift_quantity);$lines=[];$ids=array_column($d->addons,'product_id');
   foreach($items as $i){$v=$i['variant'];$n=$selections[$d->id][$v->id]??0;if(in_array($v->product_id,$ids)&&$n>0)$lines[]=['key'=>(string)$v->id,'quantity'=>min((int)$i['quantity'],(int)$n),'unit_price'=>(int)$v->price,'addon_price'=>0];}
   $r=app(AddonCalculator::class)->calculate($allow,$allow,$lines);
   if($r['discount']>$best['discount'])$best=['version'=>1,'type'=>'gift','offer_id'=>$d->id,'name'=>$d->name,...$r];
  }return $best;
 }
}
