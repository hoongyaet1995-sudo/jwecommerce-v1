<?php
namespace Tests\Feature;
use App\Services\Marketplace\ShopeeListingPreflight;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class ShopeeListingPreflightTest extends TestCase
{
    public function test_logistics_preflight_combines_store_channel_and_package_validation(): void
    {
        config(['marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture-key']);
        Http::preventStrayRequests();
        Http::fake(['*/get_channel_list*'=>Http::response(['response'=>['logistics_channel_list'=>[
            ['logistics_channel_id'=>24000,'logistics_channel_name'=>'Sandbox Courier','enabled'=>true,'fee_type'=>'SIZE_INPUT',
                'weight_limit'=>['item_min_weight'=>0,'item_max_weight'=>20],
                'item_max_dimension'=>['height'=>30,'width'=>30,'length'=>30,'unit'=>'cm','dimension_sum'=>0]],
        ]]])]);
        $selection=[['logistic_id'=>24000,'is_free'=>false]];
        $dimensions=['height'=>10,'width'=>10,'length'=>10];
        $this->assertSame([['logistic_id'=>24000,'enabled'=>true,'is_free'=>false]],
            app(ShopeeListingPreflight::class)->logistics('access','456',$selection,1,$dimensions));
        $this->expectException(\DomainException::class);
        app(ShopeeListingPreflight::class)->logistics('access','456',$selection,21,$dimensions);
    }

    public function test_brand_selection_uses_platform_identity_and_enforces_mandatory_brand(): void
    {
        config(['marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture-key']);
        Http::preventStrayRequests();
        Http::fake(['*/get_brand_list*'=>Http::response(['response'=>[
            'brand_list'=>[['brand_id'=>42,'original_brand_name'=>'Official Brand','display_brand_name'=>'Official Brand']],
            'has_next_page'=>false,'next_offset'=>0,'is_mandatory'=>true,'input_type'=>'DROP_DOWN',
        ]])]);
        $this->assertSame(['brand_id'=>42,'original_brand_name'=>'Official Brand'],
            app(ShopeeListingPreflight::class)->brand('access','456',101,42));
        foreach ([null,999] as $id) {
            try {app(ShopeeListingPreflight::class)->brand('access','456',101,$id);$this->fail('Invalid brand accepted.');}
            catch (\DomainException $exception) {$this->assertNotEmpty($exception->getMessage());}
        }
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'add_item'));
    }

    public function test_searchable_values_are_resolved_from_platform_pages_not_client_names(): void
    {
        config(['marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*/get_category*'=>Http::response(['response'=>['category_list'=>[
                ['category_id'=>101,'parent_category_id'=>100,'original_category_name'=>'Consoles','display_category_name'=>'Consoles','has_children'=>false],
            ]]]),
            '*/get_attribute_tree*'=>Http::response(['response'=>['list'=>[['category_id'=>101,'attribute_tree'=>[
                ['attribute_id'=>10,'mandatory'=>true,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1,'support_search_value'=>true],
                    'attribute_value_list'=>[]],
            ]]]]]),
            '*/search_attribute_value_list*'=>Http::sequence()
                ->push(['response'=>['value_list'=>[['value_id'=>6,'value_name'=>'Other']], 'page_info'=>['cursor'=>6,'has_next'=>true]]])
                ->push(['response'=>['value_list'=>[['value_id'=>7,'value_name'=>'Platform Metal']], 'page_info'=>['cursor'=>7,'has_next'=>false]]]),
        ]);
        $result=app(ShopeeListingPreflight::class)->attributes('access','456',101,
            [['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>7,'original_value_name'=>'Forged']]]]);
        $this->assertSame('Platform Metal',$result[0]['attribute_value_list'][0]['original_value_name']);
        Http::assertSentCount(4);
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'add_item'));
    }

    public function test_attributes_are_validated_against_fresh_store_metadata(): void
    {
        config(['marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*/get_category*'=>Http::response(['response'=>['category_list'=>[
                ['category_id'=>101,'parent_category_id'=>100,'original_category_name'=>'Consoles','display_category_name'=>'Consoles','has_children'=>false],
            ]]]),
            '*/get_attribute_tree*'=>Http::response(['response'=>['list'=>[['category_id'=>101,'attribute_tree'=>[
                ['attribute_id'=>10,'mandatory'=>true,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1],
                    'attribute_value_list'=>[['value_id'=>7,'name'=>'Metal']]],
            ]]]]]),
        ]);
        $result=app(ShopeeListingPreflight::class)->attributes('access','456',101,
            [['attribute_id'=>10,'attribute_value_list'=>[['value_id'=>7,'original_value_name'=>'Forged']]]]);
        $this->assertSame('Metal',$result[0]['attribute_value_list'][0]['original_value_name']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn($request)=>$request->method()!=='GET');
        $this->expectException(\DomainException::class);
        app(ShopeeListingPreflight::class)->attributes('access','456',101,[]);
    }
}
