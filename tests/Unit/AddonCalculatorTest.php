<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\Promotions\AddonCalculator;
class AddonCalculatorTest extends TestCase {
 public function test_allowance_cap_and_best_savings_are_allocated_to_extra_only():void {
  $result=(new AddonCalculator)->calculate(2,1,[['key'=>'a','quantity'=>2,'unit_price'=>2000,'addon_price'=>1000],['key'=>'b','quantity'=>1,'unit_price'=>3000,'addon_price'=>500]]);
  $this->assertSame(2500,$result['discount']);$this->assertSame(['b'=>2500],$result['allocations']);
 }
 public function test_no_main_means_no_discount_and_price_never_increases():void {
  $c=new AddonCalculator;$lines=[['key'=>'a','quantity'=>1,'unit_price'=>500,'addon_price'=>1000]];
  $this->assertSame(0,$c->calculate(0,2,$lines)['discount']);$this->assertSame(0,$c->calculate(2,2,$lines)['discount']);
 }
}
