<?php

namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceListingMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazada_categories_use_saved_credentials_and_preserve_leaf_selection(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','shop_name'=>'Test','external_shop_id'=>'2001',
            'region'=>'MY','environment'=>'live','status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'private-token','access_expires_at'=>now()->addHour()]);
        Http::fake(fn($request)=>str_contains($request->url(),'/category/attributes/get')
            ? Http::response(['code'=>'0','data'=>[['name'=>'brand','label'=>'Brand','is_mandatory'=>1,
                'input_type'=>'singleSelect','options'=>[['name'=>'No Brand']]]]])
            : Http::response(['code'=>'0','data'=>[['category_id'=>10,'name'=>'Parent','leaf'=>false,
                'children'=>[['category_id'=>11,'name'=>'Child','leaf'=>true]]]]]));
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson("/admin/api/marketplace/stores/{$store}/listing-categories")->assertOk()
            ->assertJsonPath('data.0.has_children',true)->assertJsonPath('data.1.parent_category_id',10)
            ->assertJsonPath('data.1.has_children',false)->assertJsonPath('data.1.display_category_name','Child')
            ->assertDontSee('private-token');
        Http::assertSent(fn($request)=>str_contains($request->url(),'/category/tree/get'));
        $this->getJson("/admin/api/marketplace/stores/{$store}/listing-attributes?categoryId=11")->assertOk()
            ->assertJsonPath('platform','lazada')->assertJsonPath('data.0.name','brand')
            ->assertJsonPath('data.0.is_mandatory',1)->assertJsonPath('data.0.options.0.name','No Brand');
        $this->getJson("/admin/api/marketplace/stores/{$store}/listing-attributes?categoryId=99")->assertUnprocessable();
    }

    public function test_attribute_search_requires_admin_connected_store_and_valid_inputs(): void
    {
        $url='/admin/api/marketplace/stores/1/listing-attribute-values?attributeId=123&q=Blue&cursor=0';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->getJson($url)->assertNotFound();
        $this->store();
        $this->getJson('/admin/api/marketplace/stores/1/listing-attribute-values?attributeId=0')->assertUnprocessable();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'disconnected']);
        $this->getJson($url)->assertConflict();
        Http::assertNothingSent();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'connected']);
        Http::fake(['*'=>Http::response(['response'=>['value_list'=>[['value_id'=>42,'value_name'=>'Blue']],
            'page_info'=>['cursor'=>42,'has_next'=>false]],'debug_message'=>'private-access'])]);
        $response=$this->getJson($url)->assertOk()->assertJsonPath('data.value_list.0.value_name','Blue')
            ->assertJsonPath('attributeId',123);
        $this->assertStringNotContainsString('private-access',$response->getContent());
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.operations_enabled'=>false, 'marketplace.read_operations_enabled'=>true,
            'marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'fixture-key',
            'marketplace.shopee.callback_url'=>'https://example.test/callback']);
        Http::preventStrayRequests();
    }

    public function test_categories_require_admin_read_gate_and_connected_supported_store(): void
    {
        $url = '/admin/api/marketplace/stores/1/listing-categories';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
        config(['marketplace.read_operations_enabled'=>true]);
        $this->getJson($url)->assertNotFound();
        $this->store();
        foreach ([['status'=>'disconnected'], ['platform'=>'lazada'], ['environment'=>'production']] as $change) {
            DB::table('marketplace_stores')->where('id', 1)->update(array_merge([
                'status'=>'connected', 'platform'=>'shopee', 'environment'=>'sandbox'], $change));
            $this->getJson($url)->assertConflict();
        }
        Http::assertNothingSent();
    }

    public function test_categories_use_saved_shop_and_encrypted_token_without_exposing_credentials(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $category = ['category_id'=>101, 'parent_category_id'=>0, 'original_category_name'=>'Games',
            'display_category_name'=>'Games', 'has_children'=>false];
        Http::fake(['*'=>Http::response(['response'=>['category_list'=>[$category]]])]);
        $response = $this->getJson('/admin/api/marketplace/stores/1/listing-categories')->assertOk()
            ->assertExactJson(['storeId'=>1, 'data'=>[$category]]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private-access', $response->getContent());
        $this->assertNotSame('private-access', DB::table('marketplace_store_tokens')->value('access_token'));
        Http::assertSent(fn ($request)=>$request['shop_id'] === '456' && $request['access_token'] === 'private-access');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('marketplace_variant_mappings', 0);
    }

    public function test_category_provider_failure_is_explicit_and_sanitized(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        Http::fake(['*'=>Http::response(['error'=>'invalid_access_token', 'message'=>'private-access'], 403)]);
        $response = $this->getJson('/admin/api/marketplace/stores/1/listing-categories')->assertStatus(502);
        $this->assertStringNotContainsString('private-access', $response->getContent());
    }

    public function test_attributes_require_explicit_category_and_return_only_selected_tree(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $url = '/admin/api/marketplace/stores/1/listing-attributes';
        $this->getJson($url)->assertUnprocessable();
        $this->getJson($url.'?categoryId=0')->assertUnprocessable();
        $this->getJson($url.'?categoryId=abc')->assertUnprocessable();
        Http::assertNothingSent();
        $tree = [['attribute_id'=>10, 'mandatory'=>true, 'name'=>'Material', 'attribute_value_list'=>[],
            'attribute_info'=>['input_type'=>3, 'attribute_unit_list'=>['g']]]];
        Http::fake([
            '*/api/v2/product/get_category*'=>Http::response(['response'=>['category_list'=>[
                ['category_id'=>106511,'parent_category_id'=>100,'original_category_name'=>'Consoles','display_category_name'=>'Consoles','has_children'=>false],
            ]]]),
            '*/api/v2/product/get_attribute_tree*'=>Http::response(['response'=>['list'=>[['category_id'=>106511, 'attribute_tree'=>$tree]]]]),
        ]);
        $response = $this->getJson($url.'?categoryId=106511')->assertOk()
            ->assertExactJson(['storeId'=>1, 'categoryId'=>106511, 'data'=>$tree]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('private-access', $response->getContent());
    }

    public function test_parent_category_is_rejected_before_requesting_attributes(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        Http::fake(['*'=>Http::response(['response'=>['category_list'=>[
            ['category_id'=>100,'parent_category_id'=>0,'original_category_name'=>'Electronics','display_category_name'=>'Electronics','has_children'=>true],
        ]]])]);
        $this->getJson('/admin/api/marketplace/stores/1/listing-attributes?categoryId=100')->assertUnprocessable();
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request)=>str_contains($request->url(),'get_attribute_tree'));
    }

    public function test_attributes_obey_read_gate_and_store_connection_and_sanitize_provider_failure(): void
    {
        $url = '/admin/api/marketplace/stores/1/listing-attributes?categoryId=106511';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
        config(['marketplace.read_operations_enabled'=>true]);
        $this->getJson($url)->assertNotFound();
        $this->store();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'disconnected']);
        $this->getJson($url)->assertConflict();
        Http::assertNothingSent();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'connected']);
        Http::fake(['*'=>Http::response(['error'=>'invalid', 'message'=>'private-access'])]);
        $response = $this->getJson($url)->assertStatus(502);
        $this->assertStringNotContainsString('private-access', $response->getContent());
    }

    public function test_brands_endpoint_preserves_cursor_and_requirements_with_validated_input(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $url = '/admin/api/marketplace/stores/1/listing-brands';
        $this->getJson($url)->assertUnprocessable();
        $this->getJson($url.'?categoryId=106511&offset=-1')->assertUnprocessable();
        Http::assertNothingSent();
        $page = ['brand_list'=>[['brand_id'=>42, 'original_brand_name'=>'Example', 'display_brand_name'=>'Example']],
            'has_next_page'=>true, 'next_offset'=>200, 'is_mandatory'=>true, 'input_type'=>'DROP_DOWN'];
        Http::fake(['*'=>Http::response(['response'=>$page])]);
        $response = $this->getJson($url.'?categoryId=106511&offset=100')->assertOk()
            ->assertExactJson(['storeId'=>1, 'categoryId'=>106511, 'data'=>$page]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSent(fn ($request)=>(int)$request['offset'] === 100 && (int)$request['category_id'] === 106511);
    }

    public function test_brands_endpoint_rejects_unauthorized_disabled_disconnected_and_provider_failure(): void
    {
        $url = '/admin/api/marketplace/stores/1/listing-brands?categoryId=106511';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
        config(['marketplace.read_operations_enabled'=>true]);
        $this->store();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'disconnected']);
        $this->getJson($url)->assertConflict();
        Http::assertNothingSent();
        DB::table('marketplace_stores')->where('id',1)->update(['status'=>'connected']);
        Http::fake(['*'=>Http::response(['error'=>'error', 'message'=>'private-access'])]);
        $response = $this->getJson($url)->assertStatus(502);
        $this->assertStringNotContainsString('private-access', $response->getContent());
    }

    public function test_logistics_returns_store_channels_without_exposing_provider_extras(): void
    {
        $this->store();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $channel = ['logistics_channel_id'=>10, 'logistics_channel_name'=>'Test Courier',
            'enabled'=>false, 'fee_type'=>'SIZE_INPUT', 'weight_limit'=>['item_max_weight'=>30],
            'compulsory_channel'=>true,'block_seller_cover_shipping_fee'=>true,
            'channel_relation_rules'=>['related_enabled_channels'=>[11],'related_disabled_channels'=>[], 'related_dependent_block_channels'=>[]]];
        Http::fake(['*'=>Http::response(['response'=>['logistics_channel_list'=>[
            $channel + ['private_debug'=>'private-access']]]])]);
        $response = $this->getJson('/admin/api/marketplace/stores/1/listing-logistics')->assertOk()
            ->assertExactJson(['storeId'=>1, 'data'=>[$channel]]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSent(fn ($request)=>$request->method() === 'GET' && $request['shop_id'] === '456');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_logistics_enforces_read_access_connection_and_sanitizes_failure(): void
    {
        $url = '/admin/api/marketplace/stores/1/listing-logistics';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        config(['marketplace.read_operations_enabled'=>false]);
        $this->getJson($url)->assertStatus(503);
        config(['marketplace.read_operations_enabled'=>true]);
        $this->getJson($url)->assertNotFound();
        $this->store();
        foreach ([['status'=>'disconnected'], ['platform'=>'lazada'], ['environment'=>'production']] as $change) {
            DB::table('marketplace_stores')->where('id',1)->update(array_merge([
                'status'=>'connected', 'platform'=>'shopee', 'environment'=>'sandbox'], $change));
            $this->getJson($url)->assertConflict();
        }
        Http::assertNothingSent();
        DB::table('marketplace_stores')->where('id',1)->update(['environment'=>'sandbox']);
        Http::fake(['*'=>Http::response(['error'=>'error', 'message'=>'private-access'])]);
        $response = $this->getJson($url)->assertStatus(502);
        $this->assertStringNotContainsString('private-access', $response->getContent());
    }

    private function store(): void
    {
        DB::table('marketplace_stores')->insert(['id'=>1, 'platform'=>'shopee', 'shop_name'=>'Fixture',
            'external_shop_id'=>'456', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'connected']);
        MarketplaceStoreToken::create(['store_id'=>1, 'application_id'=>'123', 'access_token'=>'private-access',
            'refresh_token'=>'private-refresh', 'access_expires_at'=>now()->addHours(2)]);
    }
}
