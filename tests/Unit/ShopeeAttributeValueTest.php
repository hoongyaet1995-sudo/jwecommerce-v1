<?php

namespace Tests\Unit;

use App\Services\Marketplace\ShopeeAttributeValue;
use PHPUnit\Framework\TestCase;

class ShopeeAttributeValueTest extends TestCase
{
    public function test_numeric_values_and_units_follow_platform_metadata(): void
    {
        $info=['input_validation_type'=>1,'format_type'=>2,'attribute_unit_list'=>['g','kg']];
        ShopeeAttributeValue::validate('100','g',$info);
        foreach ([['1.5','g'],['100','lb'],['100','']] as [$value,$unit]) {
            try {ShopeeAttributeValue::validate($value,$unit,$info);$this->fail('Invalid quantity accepted.');}
            catch (\DomainException $error) {$this->assertNotEmpty($error->getMessage());}
        }
        ShopeeAttributeValue::validate('1.5','kg',array_replace($info,['input_validation_type'=>3]));
    }

    public function test_dates_must_match_exact_platform_format_and_calendar(): void
    {
        $info=['input_validation_type'=>4,'format_type'=>1,'date_format_type'=>0];
        ShopeeAttributeValue::validate('29/02/2024','',$info);
        foreach (['29/02/2025','31/04/2025','2025-01-01','01/2025'] as $value) {
            try {ShopeeAttributeValue::validate($value,'',$info);$this->fail('Invalid date accepted.');}
            catch (\DomainException $error) {$this->assertNotEmpty($error->getMessage());}
        }
        ShopeeAttributeValue::validate('01/2025','',array_replace($info,['date_format_type'=>1]));
    }

    public function test_unknown_metadata_is_not_treated_as_unrestricted_text(): void
    {
        $this->expectException(\RuntimeException::class);
        ShopeeAttributeValue::validate('anything','',[]);
    }
}
