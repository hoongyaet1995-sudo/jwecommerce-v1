<?php

namespace App\Services\Marketplace;

/** Scalar validation only; allowed option IDs and input cardinality are validated separately. */
final class ShopeeAttributeValue
{
    public static function validate(string $value, string $unit, array $info): void
    {
        $type=$info['input_validation_type'] ?? null;
        $format=$info['format_type'] ?? null;
        if (!in_array($type,[0,1,2,3,4],true) || !in_array($format,[1,2],true)) {
            throw new \RuntimeException('Shopee attribute validation metadata is unavailable.');
        }
        if (trim($value)==='') throw new \DomainException('Attribute value cannot be empty.');
        if ($format===2) {
            if (!is_array($info['attribute_unit_list'] ?? null) || !$info['attribute_unit_list']) {
                throw new \RuntimeException('Shopee attribute units are unavailable.');
            }
            if ($unit==='' || !in_array($unit,$info['attribute_unit_list'],true)) {
                throw new \DomainException('Select an allowed unit for this attribute.');
            }
        } elseif ($unit!=='') {
            throw new \DomainException('This attribute does not accept a unit.');
        }
        if ($type===1 && !preg_match('/^[+-]?\d+$/D',$value)) {
            throw new \DomainException('This attribute requires an integer.');
        }
        if ($type===3 && (!preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/D',$value) || !is_finite((float)$value))) {
            throw new \DomainException('This attribute requires a finite decimal number.');
        }
        if ($type===4) {
            $dateType=$info['date_format_type'] ?? null;
            if (!in_array($dateType,[0,1],true)) throw new \RuntimeException('Shopee attribute date format is unavailable.');
            $pattern=$dateType===0?'d/m/Y':'m/Y';
            $date=\DateTimeImmutable::createFromFormat('!'.$pattern,$value);
            $errors=\DateTimeImmutable::getLastErrors();
            if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format($pattern)!==$value) {
                throw new \DomainException('Enter a valid date using the required attribute format.');
            }
        }
    }
}
