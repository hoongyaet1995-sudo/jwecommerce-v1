<?php
namespace App\Services\Promotions;
use App\Models\{TieredDiscount,Product};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class TieredOfferService {
 public function active():Collection {
  $q=TieredDiscount::where('enabled',true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('id');
  if(DB::transactionLevel()>0)$q->lockForUpdate();return $q->get();
 }
 public function forProduct(Product $p):Collection {return $p->status==='active'&&$p->isBuyMode()?$this->active()->filter(fn($o)=>$o->scope==='all'||in_array($p->id,$o->product_ids)):collect();}
 public function progress(Collection $items):Collection {
  $lines=$items->filter(fn($i)=>$i['variant']->enabled&&$i['variant']->product?->status==='active'&&$i['variant']->product?->isBuyMode());
  return $this->active()->map(function($o)use($lines){$eligible=$lines->filter(fn($i)=>$o->scope==='all'||in_array($i['variant']->product_id,$o->product_ids))->map(fn($i)=>['key'=>(string)$i['variant']->id,'unit_price'=>(int)$i['variant']->price,'quantity'=>(int)$i['quantity']])->values()->all();
   if(!$eligible)return null;$r=app(TieredCalculator::class)->calculate($eligible,$o->tiers,$o->discount_type);$tiers=collect($o->tiers)->sortBy('spend');return ['offer'=>$o,'next'=>$tiers->first(fn($t)=>$t['spend']>$r['subtotal']),...$r];
  })->filter()->values();
 }
 public function quote(Collection $items):array {
  $best=['version'=>1,'type'=>null,'discount'=>0,'allocations'=>[]];
  foreach($this->progress($items) as $r)if($r['discount']>$best['discount'])$best=['version'=>1,'type'=>'tiered','offer_id'=>$r['offer']->id,'name'=>$r['offer']->name,'discount_type'=>$r['offer']->discount_type,'tier'=>$r['tier'],'discount'=>$r['discount'],'allocations'=>$r['allocations']];
  return $best;
 }
}
