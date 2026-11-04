<?php
namespace App\Services\Promotions;
use App\Models\PromotionOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class AutomaticPromotionService {
 public function quote(Collection $items):array {
  $lines=$items->filter(fn($i)=>$i['variant']->enabled&&$i['variant']->product?->status==='active'&&$i['variant']->product?->purchase_mode==='buy');
  $ids=$lines->pluck('variant.product_id')->unique()->all();
  $query=PromotionOffer::where('enabled',true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->whereHas('products',fn($q)=>$q->whereIn('products.id',$ids))->orderBy('id');
  if(DB::transactionLevel()>0)$query->lockForUpdate();
  $best=['version'=>1,'type'=>null,'discount'=>0,'allocations'=>[]];
  foreach($query->with('products:id')->get() as $offer){
   $eligible=$lines->filter(fn($i)=>$offer->products->contains('id',$i['variant']->product_id))->map(fn($i)=>['key'=>(string)$i['variant']->id,'unit_price'=>(int)$i['variant']->price,'quantity'=>(int)$i['quantity']])->values()->all();
   $result=app(MixMatchCalculator::class)->calculate($eligible,$offer->quantity,$offer->price,$offer->repeat);
   if($result['discount']>$best['discount'])$best=['version'=>1,'type'=>'mix_match','offer_id'=>$offer->id,'name'=>$offer->name,'quantity'=>$offer->quantity,'price'=>$offer->price,'repeat'=>$offer->repeat,...$result];
  }
  $selected=request()->hasSession()?request()->session()->get('addon_selections',[]):[];
  $addon=app(AddonOfferService::class)->quote($lines,$selected);
  $best=$addon['discount']>$best['discount']?$addon:$best;
  $gift=app(FreeGiftOfferService::class)->quote($lines,request()->hasSession()?request()->session()->get('gift_selections',[]):[]);
  $best=$gift['discount']>$best['discount']?$gift:$best;
  $tiered=app(TieredOfferService::class)->quote($lines);
  return $tiered['discount']>$best['discount']?$tiered:$best;
 }
}
