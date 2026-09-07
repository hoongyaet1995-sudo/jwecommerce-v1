<?php
namespace Tests\Unit;
use App\Services\Marketplace\ShopeeLogisticsSelection;
use PHPUnit\Framework\TestCase;
class ShopeeLogisticsSelectionTest extends TestCase
{
    public function test_disabled_or_non_boolean_flags_cannot_be_silently_normalized_into_a_listing(): void
    {
        $channel=['logistics_channel_id'=>10,'enabled'=>true,'fee_type'=>'SIZE_INPUT','block_seller_cover_shipping_fee'=>false];
        foreach ([['enabled'=>false],['enabled'=>null],['enabled'=>'true'],['is_free'=>null],['is_free'=>0]] as $flags) {
            try {
                ShopeeLogisticsSelection::validate($channel,['logistic_id'=>10]+$flags);
                $this->fail('Malformed or disabled selection was accepted.');
            } catch (\DomainException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        ShopeeLogisticsSelection::validate($channel,['logistic_id'=>10,'enabled'=>true,'is_free'=>false]);
    }
    public function test_size_selection_must_use_an_enabled_channel_and_existing_size(): void
    {
        $channel=['logistics_channel_id'=>10,'enabled'=>true,'fee_type'=>'SIZE_SELECTION','size_list'=>[['size_id'=>'2']]];
        ShopeeLogisticsSelection::validate($channel,['logistic_id'=>10,'size_id'=>'2']);
        foreach ([['logistic_id'=>10,'size_id'=>'9'],['logistic_id'=>11,'size_id'=>'2'],['logistic_id'=>10]] as $selection) {
            try {ShopeeLogisticsSelection::validate($channel,$selection);$this->fail('Invalid logistics selection accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
    public function test_custom_price_and_free_shipping_restrictions_are_enforced(): void
    {
        $channel=['logistics_channel_id'=>10,'enabled'=>true,'fee_type'=>'CUSTOM_PRICE','block_seller_cover_shipping_fee'=>true];
        ShopeeLogisticsSelection::validate($channel,['logistic_id'=>10,'shipping_fee'=>5.5,'is_free'=>false]);
        foreach ([['logistic_id'=>10],['logistic_id'=>10,'shipping_fee'=>-1],['logistic_id'=>10,'shipping_fee'=>5,'is_free'=>true]] as $selection) {
            try {ShopeeLogisticsSelection::validate($channel,$selection);$this->fail('Invalid shipping fee accepted.');}
            catch (\DomainException $e) {$this->assertNotEmpty($e->getMessage());}
        }
    }
}
