<?php
namespace App\Services\Marketplace;

/** Weight and dimension validation only; volume and channel selection rules are separate. */
final class ShopeePackageLimits
{
    public static function validate(array $channel,float $weightKg,array $centimetres): void
    {
        if (!is_finite($weightKg) || $weightKg<=0) throw new \DomainException('Package weight must be positive.');
        foreach (['height','width','length'] as $axis) {
            if (!is_int($centimetres[$axis] ?? null) || $centimetres[$axis]<=0) {
                throw new \DomainException('Package dimensions must be positive integer centimetres.');
            }
        }
        $weight=$channel['weight_limit'] ?? null;$dimensions=$channel['item_max_dimension'] ?? null;
        if (!is_array($weight) || !is_array($dimensions) || ($dimensions['unit'] ?? null)!=='cm') {
            throw new \RuntimeException('Channel package constraints are incomplete or use an unsupported unit.');
        }
        $bound=static function(array $values,string $key): float {
            if (!array_key_exists($key,$values)) throw new \RuntimeException('Channel package limit is missing.');
            $value=$values[$key];
            if ($value===null) return 0;
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value<0) {
                throw new \RuntimeException('Channel package limit is invalid.');
            }
            return (float)$value;
        };
        $min=$bound($weight,'item_min_weight');$max=$bound($weight,'item_max_weight');
        if (($min>0 && $weightKg<$min) || ($max>0 && $weightKg>$max)) {
            throw new \DomainException('Package weight is outside the selected channel limits.');
        }
        foreach (['height','width','length'] as $axis) {
            $limit=$bound($dimensions,$axis);
            if ($limit>0 && $centimetres[$axis]>$limit) throw new \DomainException('Package dimensions exceed the selected channel limits.');
        }
        $sum=$bound($dimensions,'dimension_sum');
        if ($sum>0 && array_sum(array_intersect_key($centimetres,array_flip(['height','width','length'])))>$sum) {
            throw new \DomainException('Package dimension sum exceeds the selected channel limit.');
        }
    }
}
