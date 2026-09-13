<?php
namespace Tests\Unit;

use App\Services\Marketplace\LazadaAttributeValue;
use Tests\TestCase;

class LazadaAttributeValueTest extends TestCase
{
    public function test_enumerations_use_english_values_and_custom_input_stays_supported(): void
    {
        $attribute=['name'=>'color','input_type'=>'singleSelect','options'=>[['name'=>'蓝','en_name'=>'Blue'],['name'=>'红','en_name'=>'Red']]];
        LazadaAttributeValue::validate($attribute,'Blue');
        LazadaAttributeValue::validate([...$attribute,'input_type'=>'multiSelect'],'Blue,Red');
        LazadaAttributeValue::validate([...$attribute,'input_type'=>'enumInput'],'Custom color');
        foreach (['蓝','Unknown',['Blue']] as $invalid) {
            try {LazadaAttributeValue::validate($attribute,$invalid);$this->fail('Invalid enumeration accepted');}
            catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('attributes',$e->errors());}
        }
    }

    public function test_numeric_zero_is_valid_but_text_and_nonfinite_numbers_are_rejected(): void
    {
        $attribute=['name'=>'quantity','input_type'=>'numeric'];
        LazadaAttributeValue::validate($attribute,'0');
        foreach (['many',INF,true,[]] as $invalid) {
            try {LazadaAttributeValue::validate($attribute,$invalid);$this->fail('Invalid number accepted');}
            catch (\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('attributes',$e->errors());}
        }
    }

    public function test_optional_values_are_not_exempt_from_type_validation(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        LazadaAttributeValue::validate(['name'=>'description','input_type'=>'richText'],['unexpected'=>'object']);
    }
}
