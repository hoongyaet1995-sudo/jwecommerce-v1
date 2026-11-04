<?php
namespace App\Services\Promotions;
final class TieredCalculator {
 public function calculate(array $lines,array $tiers,string $type):array {
  $weights=[];foreach($lines as $l)$weights[$l['key']]=max(0,(int)$l['unit_price'])*max(0,(int)$l['quantity']);
  $subtotal=array_sum($weights);$tier=null;
  usort($tiers,fn($a,$b)=>$a['spend']<=>$b['spend']);
  foreach($tiers as $t)if($subtotal>=$t['spend'])$tier=$t;
  $discount=$tier?min($subtotal,$type==='percentage'?intdiv($subtotal*$tier['discount']+50,100):(int)$tier['discount']):0;
  $allocations=array_fill_keys(array_keys($weights),0);$remainders=[];
  if($subtotal>0)foreach($weights as $key=>$w){$allocations[$key]=intdiv($discount*$w,$subtotal);$remainders[$key]=($discount*$w)%$subtotal;}
  arsort($remainders,SORT_NUMERIC);$left=$discount-array_sum($allocations);
  foreach($remainders as $key=>$_){if($left--<=0)break;$allocations[$key]++;}
  return compact('discount','allocations','tier','subtotal');
 }
}
