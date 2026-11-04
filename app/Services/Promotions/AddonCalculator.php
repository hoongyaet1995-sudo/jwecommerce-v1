<?php
namespace App\Services\Promotions;
/** Integer minor units. One qualifying unit unlocks one explicitly selected extra. */
final class AddonCalculator {
 public function calculate(int $mainQuantity,int $cap,array $lines):array {
  // Allocate limited slots by original unit price, never by the largest saving.
  usort($lines,fn($a,$b)=>($a['unit_price']<=>$b['unit_price'])?:strcmp($a['key'],$b['key']));
  $remaining=max(0,min($mainQuantity,$cap));$discount=0;$allocations=[];
  foreach($lines as $line){$take=min($remaining,max(0,$line['quantity']));$saving=max(0,$line['unit_price']-$line['addon_price']);if($take&&$saving){$allocations[$line['key']]=$take*$saving;$discount+=$take*$saving;$remaining-=$take;}}
  return compact('discount','allocations');
 }
}
