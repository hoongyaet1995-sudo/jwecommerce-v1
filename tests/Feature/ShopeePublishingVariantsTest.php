<?php
namespace Tests\Feature;

use App\Models\{Product, User, MarketplaceStoreToken};
use App\Services\Marketplace\{PublishingTasks, MarketplacePublishingDispatchService, PublishingResultUncertain};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ShopeePublishingVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_selection_enforces_cardinality_duplicates_units_dates_and_active_dependencies(): void
    {
        $multi=['attribute_id'=>10,'mandatory'=>true,'attribute_info'=>['input_type'=>5,'max_value_count'=>2,'input_validation_type'=>2,'format_type'=>1],
            'attribute_value_list'=>[['value_id'=>1,'name'=>'Mobile'],['value_id'=>2,'name'=>'Laptop']]];
        $date=['attribute_id'=>11,'mandatory'=>true,'attribute_info'=>['input_type'=>3,'max_value_count'=>1,'input_validation_type'=>4,'date_format_type'=>0,'format_type'=>1],
            'attribute_value_list'=>[]];
        $capacity=['attribute_id'=>12,'mandatory'=>true,'attribute_info'=>['input_type'=>3,'max_value_count'=>1,'input_validation_type'=>1,'format_type'=>2,'attribute_unit_list'=>['mAh']],
            'attribute_value_list'=>[]];
        $this->assertCount(2,\App\Services\Marketplace\ShopeeAttributeSelection::normalize($multi,[['value_id'=>1],['value_id'=>2]]));
        $this->assertSame('20/09/2026',\App\Services\Marketplace\ShopeeAttributeSelection::normalize($date,[['value_id'=>0,'original_value_name'=>'20/09/2026','value_unit'=>'']])[0]['original_value_name']);
        $this->assertSame('mAh',\App\Services\Marketplace\ShopeeAttributeSelection::normalize($capacity,[['value_id'=>0,'original_value_name'=>'800','value_unit'=>'mAh']])[0]['value_unit']);

        foreach ([
            [$multi,[['value_id'=>1],['value_id'=>1]]],
            [$date,[['value_id'=>0,'original_value_name'=>'31/02/2026','value_unit'=>'']]],
            [$capacity,[['value_id'=>0,'original_value_name'=>'8.5','value_unit'=>'kg']]],
        ] as [$definition,$invalid]) {
            try {
                \App\Services\Marketplace\ShopeeAttributeSelection::normalize($definition,$invalid);
                $this->fail('Invalid Shopee attribute selection was accepted.');
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $child=['attribute_id'=>20,'mandatory'=>false,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1],
            'attribute_value_list'=>[['value_id'=>200,'name'=>'Child']]];
        $parent=['attribute_id'=>19,'mandatory'=>true,'attribute_info'=>['input_type'=>1,'input_validation_type'=>2,'format_type'=>1],
            'attribute_value_list'=>[['value_id'=>190,'name'=>'Other branch'],['value_id'=>191,'name'=>'Active','child_attribute_list'=>[$child]]]];
        $this->expectException(\DomainException::class);
        \App\Services\Marketplace\ShopeeListingAttributes::normalize([$parent],[
            ['attribute_id'=>19,'attribute_value_list'=>[['value_id'=>190]]],
            ['attribute_id'=>20,'attribute_value_list'=>[['value_id'=>200]]],
        ]);
    }

    public static function results(): array { return [[true,true], [true,false], [false,false]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('results')]
    public function test_parent_is_durable_before_models_and_partial_creation_is_never_retryable(bool $complete,bool $readable): void
    {
        config(['marketplace.basic_wms_enabled'=>false,'marketplace.publish_operations_enabled'=>true,
            'marketplace.shopee.enabled'=>true,'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123','marketplace.shopee.partner_key'=>'fixture-secret']);
        Sleep::fake();
        $actor=User::factory()->create(['is_admin'=>true]);
        $product=Product::create(['title'=>'Test','handle'=>'shopee-models','base_price'=>1000]);
        $red=$product->variants()->create(['title'=>'Red','sku'=>'RED','price'=>1000,'stock'=>17,'enabled'=>true]);
        $blue=$product->variants()->create(['title'=>'Blue','sku'=>'BLUE','price'=>1200,'stock'=>21,'enabled'=>true]);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee','shop_name'=>'Test',
            'external_shop_id'=>'2001','region'=>'MY','environment'=>'sandbox','status'=>'connected']);
        DB::table('marketplace_store_capabilities')->insert(['store_id'=>$store,'catalog_read'=>true,
            'product_publish'=>true,'order_receive'=>false,'inventory_update'=>false,'fulfillment_write'=>false,
            'created_at'=>now(),'updated_at'=>now()]);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        $models=[['tier_index'=>[0],'model_sku'=>'RED','original_price'=>10,'seller_stock'=>[['stock'=>0]]],
            ['tier_index'=>[1],'model_sku'=>'BLUE','original_price'=>12,'seller_stock'=>[['stock'=>0]]]];
        $payload=['item_name'=>'Test','original_stock'=>0,'jw_variations'=>[
            'tier_variation'=>[['name'=>'Color','option_list'=>[['option'=>'Red'],['option'=>'Blue']]]],'model'=>$models]];
        $source=app(\App\Services\Marketplace\MarketplacePublishingSource::class)->read($product->id);
        $task=app(PublishingTasks::class)->create($store,$product->id,$actor->id,'7285db80-2222-4111-8111-123456789012',$payload,$source);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($task,$complete,$readable) {
            if (str_contains($request->url(),'/get_item_base_info?')) {
                return $readable?Http::response(['response'=>['item_list'=>[['item_id'=>1001,'has_model'=>true,'item_name'=>'Test','item_status'=>'UNLIST']]]]):Http::response([],503);
            }
            if (str_contains($request->url(),'/get_model_list?')) return Http::response(['response'=>['model'=>[
                ['model_id'=>2001,'model_sku'=>'RED'],['model_id'=>2002,'model_sku'=>'BLUE']]]]);
            if (str_contains($request->url(),'/add_item?')) {
                foreach (array_keys($request->data()) as $key) $this->assertFalse(str_starts_with((string)$key,'jw_'));
                return Http::response(['error'=>'','response'=>['item_id'=>1001]]);
            }
            $this->assertStringContainsString('/init_tier_variation?',$request->url());
            $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>'processing','external_item_id'=>'1001']);
            $this->assertSame(1001,$request['item_id']);
            return Http::response(['error'=>'','response'=>['model'=>$complete ? [
                ['model_id'=>2001,'model_sku'=>'RED','tier_index'=>[0]],
                ['model_id'=>2002,'model_sku'=>'BLUE','tier_index'=>[1]],
            ] : [['model_id'=>2001,'model_sku'=>'RED','tier_index'=>[0]]]]]);
        });
        try {
            app(MarketplacePublishingDispatchService::class)->dispatch($task);
            $this->assertTrue($complete);
        } catch (PublishingResultUncertain) { $this->assertFalse($complete); }
        $this->assertDatabaseHas('marketplace_publishing_tasks',['id'=>$task,'status'=>$complete?'succeeded':'uncertain','external_item_id'=>'1001']);
        if ($complete) {
            $result=json_decode(DB::table('marketplace_publishing_tasks')->where('id',$task)->value('confirmed_result'),true);
            $this->assertSame(['RED'=>'2001','BLUE'=>'2002'],$result['model_ids']);
        }
        if ($complete&&$readable) {
            $this->assertDatabaseHas('marketplace_variant_mappings',['store_id'=>$store,'product_variant_id'=>$red->id]);
            $this->assertDatabaseHas('marketplace_variant_mappings',['store_id'=>$store,'product_variant_id'=>$blue->id]);
        } else $this->assertDatabaseCount('marketplace_variant_mappings',0);
        $this->assertSame(17,$red->fresh()->stock);$this->assertSame(21,$blue->fresh()->stock);
        try { app(MarketplacePublishingDispatchService::class)->dispatch($task); $this->fail('Duplicate dispatched'); }
        catch (\RuntimeException) {}
        Http::assertSentCount($complete?($readable?4:3):2);
        Sleep::assertSleptTimes(1);
    }
}
