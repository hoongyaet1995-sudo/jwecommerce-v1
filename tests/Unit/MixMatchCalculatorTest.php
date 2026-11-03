<?php
namespace Tests\Unit;

use App\Services\Promotions\MixMatchCalculator;
use PHPUnit\Framework\TestCase;

class MixMatchCalculatorTest extends TestCase
{
    public function test_repeating_combinations_keep_leftovers_at_regular_price(): void
    {
        $result=(new MixMatchCalculator)->calculate([
            ['key'=>'a','unit_price'=>4500,'quantity'=>3],
            ['key'=>'b','unit_price'=>3900,'quantity'=>2],
            ['key'=>'c','unit_price'=>3500,'quantity'=>2],
        ],3,9900,true);
        $this->assertSame(3600+1400,$result['discount']);
        $this->assertSame(2,$result['combinations']);
        $this->assertSame($result['discount'],array_sum($result['allocations']));
    }
    public function test_once_only_and_never_charges_more_than_regular_price(): void
    {
        $calculator=new MixMatchCalculator;
        $this->assertSame(3600,$calculator->calculate([['key'=>'a','unit_price'=>4500,'quantity'=>6]],3,9900,false)['discount']);
        $this->assertSame(0,$calculator->calculate([['key'=>'a','unit_price'=>100,'quantity'=>6]],3,9900,true)['discount']);
    }
    public function test_large_quantities_do_not_require_expanding_units(): void
    {
        $result=(new MixMatchCalculator)->calculate([['key'=>'a','unit_price'=>100,'quantity'=>1000000]],3,250,true);
        $this->assertSame(16666650,$result['discount']);
        $this->assertSame(['a'=>16666650],$result['allocations']);
    }
    public function test_allocations_are_stable_and_bounded_by_line_value(): void
    {
        $result=(new MixMatchCalculator)->calculate([['key'=>'a','unit_price'=>101,'quantity'=>1],['key'=>'b','unit_price'=>100,'quantity'=>1]],2,100,false);
        $this->assertSame(101,$result['discount']);
        $this->assertSame(['a'=>51,'b'=>50],$result['allocations']);
    }

    public function test_reordering_lines_does_not_change_allocations(): void
    {
        $calculator=new MixMatchCalculator;
        $lines=[['key'=>'a','unit_price'=>101,'quantity'=>2],['key'=>'b','unit_price'=>100,'quantity'=>3]];
        $a=$calculator->calculate($lines,3,250,true);
        $b=$calculator->calculate(array_reverse($lines),3,250,true);
        $this->assertEquals($a,$b);
    }
    public function test_integer_allocations_never_exceed_paid_line_values(): void
    {
        $calculator=new MixMatchCalculator;
        for ($i=1;$i<=50;$i++) {
            $lines=[['key'=>'a','unit_price'=>101+$i,'quantity'=>$i],['key'=>'b','unit_price'=>65,'quantity'=>3]];
            $result=$calculator->calculate($lines,3,199,true);
            $this->assertSame($result['discount'],array_sum($result['allocations']));
            foreach($lines as $line) {
                $this->assertGreaterThanOrEqual(0,$result['allocations'][$line['key']]);
                $this->assertLessThanOrEqual($line['unit_price']*$line['quantity'],$result['allocations'][$line['key']]);
            }
        }
    }
    public function test_duplicate_lines_are_rejected_instead_of_double_counted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MixMatchCalculator)->calculate([['key'=>'a','unit_price'=>100,'quantity'=>2],['key'=>'a','unit_price'=>100,'quantity'=>2]],2,100,true);
    }
}
