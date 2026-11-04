<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\Promotions\AddonCalculator;
class AddonCalculatorTest extends TestCase {
 public function test_allowance_cap_selects_cheapest_original_price_extra():void {
  $result=(new AddonCalculator)->calculate(2,1,[['key'=>'a','quantity'=>2,'unit_price'=>2000,'addon_price'=>1000],['key'=>'b','quantity'=>1,'unit_price'=>3000,'addon_price'=>500]]);
  $this->assertSame(1000,$result['discount']);$this->assertSame(['a'=>1000],$result['allocations']);
 }
 public function test_no_main_means_no_discount_and_price_never_increases():void {
  $c=new AddonCalculator;$lines=[['key'=>'a','quantity'=>1,'unit_price'=>500,'addon_price'=>1000]];
  $this->assertSame(0,$c->calculate(0,2,$lines)['discount']);$this->assertSame(0,$c->calculate(2,2,$lines)['discount']);
 }
 public function test_multiple_allowances_use_cheapest_units_then_leave_excess_at_regular_price():void {
  $lines=[['key'=>'container','quantity'=>1,'unit_price'=>5000000,'addon_price'=>1000],['key'=>'ram','quantity'=>2,'unit_price'=>5900,'addon_price'=>1000],['key'=>'mouse','quantity'=>1,'unit_price'=>8900,'addon_price'=>1000]];
  $result=(new AddonCalculator)->calculate(4,3,$lines);
  $this->assertSame(['ram'=>9800,'mouse'=>7900],$result['allocations']);
  $this->assertSame(17700,$result['discount']);
  $this->assertSame($result,(new AddonCalculator)->calculate(4,3,array_reverse($lines)));
 }
}
