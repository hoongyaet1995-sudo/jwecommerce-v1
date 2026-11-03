<?php

namespace App\Services\Promotions;

use InvalidArgumentException;

/** Theme-independent, integer minor-unit calculation. No database or provider effects. */
final class MixMatchCalculator
{
    /**
     * Highest-priced eligible units form combinations first. Leftovers remain full price.
     * @param array<int,array{key:string,unit_price:int,quantity:int}> $lines
     * @return array{discount:int,combinations:int,allocations:array<string,int>}
     */
    public function calculate(array $lines, int $size, int $price, bool $repeat): array
    {
        if ($size < 2 || $price < 1) throw new InvalidArgumentException('Invalid combination settings.');
        $seen=[];
        foreach ($lines as $line) {
            if (!is_string($line['key']) || isset($seen[$line['key']]) || !is_int($line['unit_price']) || !is_int($line['quantity']) || $line['unit_price']<0 || $line['quantity']<0) {
                throw new InvalidArgumentException('Invalid or duplicate pricing line.');
            }
            $seen[$line['key']]=true;
        }
        usort($lines, fn($a,$b)=>($b['unit_price']<=>$a['unit_price']) ?: strcmp($a['key'],$b['key']));
        $allocations=array_fill_keys(array_keys($seen),0);
        $group=[]; $filled=0; $discount=0; $combinations=0;
        foreach ($lines as $line) {
            $remaining=$line['quantity'];
            while ($remaining>0) {
                if (!$repeat && $combinations>0) break 2;
                // Batch homogeneous combinations; memory and time do not grow per unit.
                if ($filled===0 && $remaining >= $size) {
                    $count=$repeat ? intdiv($remaining,$size) : 1;
                    $saving=max(0,$line['unit_price']*$size-$price)*$count;
                    $allocations[$line['key']]+=$saving;
                    $discount+=$saving; $combinations+=$count; $remaining-=$count*$size;
                    continue;
                }
                $take=min($remaining,$size-$filled);
                $group[$line['key']]=($group[$line['key']]??0)+$take*$line['unit_price'];
                $filled+=$take; $remaining-=$take;
                if ($filled===$size) {
                    $value=array_sum($group); $saving=max(0,$value-$price); $assigned=0; $parts=[];
                    foreach ($group as $key=>$amount) {
                        $share=$value>0 ? intdiv($saving*$amount,$value) : 0;
                        $parts[$key]=['share'=>$share,'remainder'=>$value>0 ? ($saving*$amount)%$value : 0];
                        $assigned+=$share;
                    }
                    uksort($parts,fn($a,$b)=>($parts[$b]['remainder']<=>$parts[$a]['remainder']) ?: strcmp($a,$b));
                    foreach ($parts as $key=>$part) {
                        $allocations[$key]+=$part['share']+($assigned<$saving ? 1 : 0);
                        if ($assigned<$saving) $assigned++;
                    }
                    $discount+=$saving; $combinations++; $group=[]; $filled=0;
                }
            }
        }
        return compact('discount','combinations','allocations');
    }
}
