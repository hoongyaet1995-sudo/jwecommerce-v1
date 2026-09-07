<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeeAttributeSelection;
use PHPUnit\Framework\TestCase;
class ShopeeAttributeSelectionTest extends TestCase
{
    public function test_real_single_choice_metadata_does_not_require_multi_value_limit(): void
    {
        $definition=$this->definition();unset($definition['attribute_info']['max_value_count']);
        $this->assertSame(7,ShopeeAttributeSelection::normalize($definition,[['value_id'=>7]])[0]['value_id']);
    }
    private function definition(): array {return ['attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1,'max_value_count'=>1],
        'attribute_value_list'=>[['value_id'=>7,'name'=>'Blue','value_unit'=>'']]];}
    public function test_predefined_values_use_server_names_and_reject_unknown_or_excess_choices(): void
    {
        $this->assertSame([['value_id'=>7,'original_value_name'=>'Blue','value_unit'=>'']],
            ShopeeAttributeSelection::normalize($this->definition(),[['value_id'=>7,'original_value_name'=>'Forged']]));
        foreach ([[['value_id'=>8]],[['value_id'=>0,'original_value_name'=>'Custom']],[['value_id'=>7],['value_id'=>7]]] as $values) {
            try {ShopeeAttributeSelection::normalize($this->definition(),$values);$this->fail('Invalid selection accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
    public function test_combo_custom_values_still_obey_scalar_rules(): void
    {
        $definition=$this->definition();$definition['attribute_info']['input_type']=2;
        $definition['attribute_info']['input_validation_type']=1;
        $this->assertSame('12',ShopeeAttributeSelection::normalize($definition,[['value_id'=>0,'original_value_name'=>'12']])[0]['original_value_name']);
        $this->expectException(\DomainException::class);
        ShopeeAttributeSelection::normalize($definition,[['value_id'=>0,'original_value_name'=>'twelve']]);
    }
}
