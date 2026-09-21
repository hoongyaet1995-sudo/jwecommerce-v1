<?php

namespace Tests\Feature;

use App\Services\Marketplace\ShopeeClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeClientTest extends TestCase
{
    public function test_pickup_warehouses_preserve_stock_location_identity_without_address_data(): void
    {
        Http::fake(['*'=>Http::sequence()->push(['error'=>'','response'=>[
            ['warehouse_id'=>6,'warehouse_name'=>'Pickup','warehouse_type'=>1,'location_id'=>'MYZ','region'=>'MY','holiday_mode_state'=>0,'address'=>'PRIVATE ADDRESS'],
        ]])->push(['error'=>'warehouse.error_not_in_whitelist','message'=>'not enabled','response'=>null])]);
        $rows=app(ShopeeClient::class)->pickupWarehouses('access','456');
        $this->assertSame('MYZ',$rows[0]['location_id']);
        $this->assertSame(6,$rows[0]['warehouse_id']);
        $this->assertArrayNotHasKey('address',$rows[0]);
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(),PHP_URL_QUERY),$q);
            return $request->method()==='GET' && str_contains($request->url(),'/shop/get_warehouse_detail?')
                && ($q['warehouse_type'] ?? null)==='1' && !isset($q['region']);
        });
        $this->expectException(\RuntimeException::class);
        app(ShopeeClient::class)->pickupWarehouses('access','456');
    }

    public function test_searchable_attribute_values_use_signed_post_and_validate_pagination(): void
    {
        Http::fake(['*'=>Http::sequence()->push(['error'=>'','response'=>[
            'value_list'=>[['value_id'=>42,'value_name'=>'Example']], 'page_info'=>['cursor'=>42,'has_next'=>true],
        ]])->push(['error'=>'','response'=>['value_list'=>[], 'page_info'=>['cursor'=>42,'has_next'=>true]]])]);
        $result=app(ShopeeClient::class)->searchAttributeValues('access','456',123,'Example',0);
        $this->assertSame(42,$result['value_list'][0]['value_id']);
        Http::assertSent(fn ($request)=>$request->method()==='POST'
            && str_contains($request->url(),'/product/search_attribute_value_list?')
            && str_contains($request->url(),'access_token=access')
            && $request['attribute_id']===123 && $request['value_name']==='Example'
            && $request['cursor']===0 && $request['limit']===100);
        $this->expectException(\RuntimeException::class);
        app(ShopeeClient::class)->searchAttributeValues('access','456',123,'Example',42);
    }

    public function test_create_item_is_gated_and_requires_confirmed_external_id(): void
    {
        $this->freezeTime();
        config(['marketplace.publish_operations_enabled'=>false]);
        Http::fake(['*'=>Http::sequence()->push(['error'=>'','response'=>['item_id'=>846055463]])
            ->push(['error'=>'','response'=>[]])]);
        try {app(ShopeeClient::class)->createItem('access','456',['item_name'=>'Test']);$this->fail('Write gate must block.');}
        catch (\RuntimeException $exception) {$this->assertNotEmpty($exception->getMessage());}
        Http::assertNothingSent();
        config(['marketplace.publish_operations_enabled'=>true]);
        $this->assertSame('846055463',app(ShopeeClient::class)->createItem('access','456',['item_name'=>'Test']));
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(),PHP_URL_QUERY),$query);
            return $request->method()==='POST' && $request['item_name']==='Test'
                && parse_url($request->url(),PHP_URL_PATH)==='/api/v2/product/add_item'
                && $query['shop_id']==='456' && $query['access_token']==='access'
                && $query['sign']===hash_hmac('sha256','123/api/v2/product/add_item'.now()->timestamp.'access456','test-secret');
        });
        $this->expectException(\App\Services\Marketplace\PublishingResultUncertain::class);
        app(ShopeeClient::class)->createItem('access','456',['item_name'=>'Test']);
    }

    public function test_create_timeout_does_not_retry_or_expose_credentials(): void
    {
        config(['marketplace.publish_operations_enabled'=>true]);
        $calls=0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new \Illuminate\Http\Client\ConnectionException('access_token=private');
        });
        try {app(ShopeeClient::class)->createItem('access','456',['item_name'=>'Test']);$this->fail('Timeout must not succeed.');}
        catch (\App\Services\Marketplace\PublishingResultUncertain $exception) {
            $this->assertStringNotContainsString('private',$exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame(1,$calls);
    }

    public function test_image_upload_requires_valid_image_and_returns_confirmed_id(): void
    {
        $this->freezeTime();
        Http::fake(['*'=>Http::response(['error'=>'','response'=>['image_info'=>['image_id'=>'confirmed-image']]])]);
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->assertSame('confirmed-image',app(ShopeeClient::class)->uploadImage($png));
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(),PHP_URL_QUERY),$query);
            return $request->method()==='POST'
                && parse_url($request->url(),PHP_URL_PATH)==='/api/v2/media_space/upload_image'
                && $query['partner_id']==='123' && (int)$query['timestamp']===now()->timestamp
                && $query['sign']===hash_hmac('sha256','123/api/v2/media_space/upload_image'.now()->timestamp,'test-secret')
                && !array_key_exists('access_token',$query) && !array_key_exists('shop_id',$query);
        });
        Http::fake();
        try {app(ShopeeClient::class)->uploadImage('<svg></svg>');$this->fail('Unsupported file must fail.');}
        catch (\RuntimeException $exception) {$this->assertNotEmpty($exception->getMessage());}
        Http::assertNothingSent();
    }

    public function test_image_upload_timeout_is_sanitized_and_not_retried(): void
    {
        $calls=0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new \Illuminate\Http\Client\ConnectionException('Sensitive signed request URL');
        });
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        try {app(ShopeeClient::class)->uploadImage($png);$this->fail('Timeout must not succeed.');}
        catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('Sensitive',$exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame(1,$calls);
    }

    public function test_logistics_read_preserves_disabled_channels_and_package_constraints(): void
    {
        $channels = [['logistics_channel_id'=>40018, 'logistics_channel_name'=>'Fixture carrier', 'enabled'=>false,
            'fee_type'=>'SIZE_INPUT', 'weight_limit'=>['item_max_weight'=>50, 'item_min_weight'=>0],
            'item_max_dimension'=>['height'=>150, 'width'=>150, 'length'=>150, 'unit'=>'cm'], 'mask_channel_id'=>4000]];
        Http::fake(['*'=>Http::response(['response'=>['logistics_channel_list'=>$channels]])]);
        $this->assertSame($channels, app(ShopeeClient::class)->logisticsChannels('access', '456'));
        Http::assertSent(fn ($request)=>$request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v2/logistics/get_channel_list');
    }

    public function test_logistics_read_does_not_treat_incomplete_response_as_no_channels(): void
    {
        Http::fake(['*'=>Http::response(['response'=>[]])]);
        $this->expectException(\RuntimeException::class);
        app(ShopeeClient::class)->logisticsChannels('access', '456');
    }

    public function test_brand_page_preserves_pagination_and_category_requirements(): void
    {
        $page = ['brand_list'=>[['brand_id'=>2500139861, 'original_brand_name'=>'Example', 'display_brand_name'=>'Example']],
            'has_next_page'=>true, 'next_offset'=>100, 'is_mandatory'=>true, 'input_type'=>'DROP_DOWN'];
        Http::fake(['*'=>Http::response(['response'=>$page])]);
        $this->assertSame($page, app(ShopeeClient::class)->brandPage('access', '456', 106511, 0));
        Http::assertSent(fn ($request)=>$request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v2/product/get_brand_list'
            && (int)$request['category_id'] === 106511 && (int)$request['status'] === 1
            && (int)$request['page_size'] === 100 && (int)$request['offset'] === 0);
    }

    public function test_brand_page_rejects_missing_requirements_and_nonadvancing_cursor(): void
    {
        $valid = ['brand_list'=>[], 'has_next_page'=>false, 'next_offset'=>0, 'is_mandatory'=>false, 'input_type'=>'DROP_DOWN'];
        foreach ([[], array_replace($valid, ['has_next_page'=>true]), array_replace($valid, ['is_mandatory'=>null])]
            as $page) {
            Http::fake(['*'=>Http::response(['response'=>$page])]);
            try {
                app(ShopeeClient::class)->brandPage('access', '456', 106511, 0);
                $this->fail('Incomplete brand response accepted');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('brand', $error->getMessage());
            }
        }
    }

    public function test_attribute_tree_preserves_conditional_requirements_and_queries_selected_category(): void
    {
        $tree = [['attribute_id'=>10, 'mandatory'=>true, 'name'=>'Material',
            'attribute_info'=>['input_type'=>1, 'attribute_unit_list'=>[], 'max_value_count'=>1],
            'attribute_value_list'=>[['value_id'=>20, 'name'=>'Metal', 'value_unit'=>'',
                'child_attribute_list'=>[['attribute_id'=>11, 'name'=>'Weight', 'mandatory'=>true,
                    'attribute_info'=>['input_type'=>3, 'attribute_unit_list'=>['g']], 'attribute_value_list'=>[]]]]]]];
        Http::fake(['*'=>Http::response(['response'=>['list'=>[['category_id'=>106511, 'attribute_tree'=>$tree]]]])]);
        $result = app(ShopeeClient::class)->attributeTree('access', '456', 106511);
        $tree[0]['attribute_info']['name']='Material';
        $tree[0]['attribute_value_list'][0]['child_attribute_list'][0]['attribute_info']['name']='Weight';
        $this->assertSame($tree, $result);
        Http::assertSent(fn ($request)=>$request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v2/product/get_attribute_tree'
            && $request['category_id_list'] === '106511' && $request['language'] === 'en');
    }

    public function test_optional_free_text_attributes_without_options_are_usable(): void
    {
        Http::fake(['*'=>Http::response(['response'=>['list'=>[['category_id'=>301082,'attribute_tree'=>[
            ['attribute_id'=>200555,'mandatory'=>false,'name'=>'Game Genre','attribute_info'=>['input_type'=>2,'input_validation_type'=>2,'format_type'=>1]],
        ]]]]])]);
        $tree=app(ShopeeClient::class)->attributeTree('access','456',301082);
        $this->assertSame([], $tree[0]['attribute_value_list']);
        $this->assertSame('Game Genre', $tree[0]['attribute_info']['name']);
        $this->assertSame([], \App\Services\Marketplace\ShopeeListingAttributes::normalize($tree,[]));
    }

    public function test_attribute_tree_rejects_wrong_category_and_partial_results(): void
    {
        foreach ([[], [['category_id'=>999, 'attribute_tree'=>[]]], [['category_id'=>106511]],
            [['category_id'=>106511, 'attribute_tree'=>[], 'warning'=>'Category unavailable']]] as $rows) {
            Http::fake(['*'=>Http::response(['response'=>['list'=>$rows]])]);
            try {
                app(ShopeeClient::class)->attributeTree('access', '456', 106511);
                $this->fail('Incomplete attribute response accepted');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('attribute', $error->getMessage());
            }
        }
    }

    public function test_category_tree_preserves_platform_hierarchy_and_uses_shop_bound_read(): void
    {
        $this->freezeTime();
        Http::fake(['*'=>Http::response(['error'=>'', 'response'=>['category_list'=>[
            ['category_id'=>100, 'parent_category_id'=>0, 'original_category_name'=>'Electronics', 'display_category_name'=>'Electronics', 'has_children'=>true],
            ['category_id'=>101, 'parent_category_id'=>100, 'original_category_name'=>'Consoles', 'display_category_name'=>'Consoles', 'has_children'=>false],
        ]]])]);
        $rows = app(ShopeeClient::class)->categoryTree('access', '456');
        $this->assertCount(2, $rows);
        $this->assertSame(100, $rows[1]['parent_category_id']);
        $this->assertFalse($rows[1]['has_children']);
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return $request->method() === 'GET'
                && parse_url($request->url(), PHP_URL_PATH) === '/api/v2/product/get_category'
                && $query['language'] === 'en' && $query['shop_id'] === '456'
                && $query['sign'] === hash_hmac('sha256', '123/api/v2/product/get_category'.now()->timestamp.'access456', 'test-secret');
        });
    }

    public function test_category_tree_rejects_missing_or_malformed_data_instead_of_empty_success(): void
    {
        foreach ([[], ['category_list'=>null], ['category_list'=>[['category_id'=>100]]]] as $response) {
            Http::fake(['*'=>Http::response(['error'=>'', 'response'=>$response])]);
            try {
                app(ShopeeClient::class)->categoryTree('access', '456');
                $this->fail('Incomplete category response accepted');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('category', $error->getMessage());
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.shopee.enabled' => true, 'marketplace.shopee.environment' => 'sandbox',
            'marketplace.shopee.partner_id' => '123', 'marketplace.shopee.partner_key' => 'test-secret',
            'marketplace.shopee.callback_url' => 'https://example.test/callback']);
        Http::preventStrayRequests();
    }

    public function test_authorization_url_uses_signed_official_host_and_encoded_state(): void
    {
        $this->freezeTime();
        $url = app(ShopeeClient::class)->authorizationUrl(str_repeat('a', 64));
        $this->assertSame('openplatform.sandbox.test-stable.shopee.sg', parse_url($url, PHP_URL_HOST));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(hash_hmac('sha256', '123/api/v2/shop/auth_partner'.now()->timestamp, 'test-secret'), $query['sign']);
        $this->assertSame('https://example.test/callback?state='.str_repeat('a', 64), $query['redirect']);
        $this->assertStringNotContainsString('test-secret', $url);
    }

    public function test_live_authorization_host_is_unchanged(): void
    {
        config(['marketplace.shopee.environment'=>'production']);
        $url = app(ShopeeClient::class)->authorizationUrl(str_repeat('b', 64));
        $this->assertSame('partner.shopeemobile.com', parse_url($url, PHP_URL_HOST));
    }

    public function test_sandbox_v2_token_exchange_uses_official_sandbox_gateway(): void
    {
        Http::fake(['*'=>Http::response(['access_token'=>'access', 'refresh_token'=>'refresh', 'expire_in'=>14400])]);
        app(ShopeeClient::class)->exchange('sandbox-code', '456');
        Http::assertSent(fn ($request) => parse_url($request->url(), PHP_URL_HOST) === 'openplatform.sandbox.test-stable.shopee.sg');
    }

    public function test_token_exchange_requires_complete_success_and_sends_signed_request(): void
    {
        Http::fake(['*' => Http::response(['access_token'=>'access', 'refresh_token'=>'refresh', 'expire_in'=>14400])]);
        $result = app(ShopeeClient::class)->exchange('code', '456');
        $this->assertSame('access', $result['access_token']);
        Http::assertSent(fn ($request) => $request['shop_id'] === 456 && $request['partner_id'] === 123 && $request['code'] === 'code' && str_contains($request->url(), '/api/v2/auth/token/get?'));
    }

    public function test_provider_error_is_sanitized_not_reported_as_success(): void
    {
        Http::fake(['*' => Http::response(['error'=>'bad_code', 'message'=>'secret-value', 'access_token'=>'secret-value'])]);
        $this->expectExceptionMessage('Shopee token exchange failed. Re-authorize the store.');
        app(ShopeeClient::class)->exchange('code', '456');
    }

    public function test_disabled_provider_does_not_send_requests(): void
    {
        config(['marketplace.shopee.enabled'=>false]);
        try { app(ShopeeClient::class)->exchange('code', '456'); $this->fail('Expected disabled provider'); }
        catch (\RuntimeException $e) { $this->assertSame('Shopee is not configured for authorization.', $e->getMessage()); }
        Http::assertNothingSent();
    }

    public function test_shop_identity_is_loaded_with_shop_bound_signature(): void
    {
        $this->freezeTime();
        Http::fake(['*' => Http::response(['shop_name'=>'Real store', 'region'=>'MY', 'shop_id'=>456])]);
        $this->assertSame(['shop_id'=>'456', 'shop_name'=>'Real store', 'region'=>'MY'], app(ShopeeClient::class)->shopInfo('access', '456'));
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return $query['sign'] === hash_hmac('sha256', '123/api/v2/shop/get_shop_info'.now()->timestamp.'access456', 'test-secret');
        });
    }

    public function test_shop_identity_mismatch_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['shop_name'=>'Other store', 'region'=>'MY', 'shop_id'=>999])]);
        $this->expectExceptionMessage('Shopee store identity could not be verified.');
        app(ShopeeClient::class)->shopInfo('access', '456');
    }

    public function test_refresh_uses_the_refresh_endpoint_and_rotated_credentials(): void
    {
        Http::fake(['*'=>Http::response(['access_token'=>'new-access', 'refresh_token'=>'new-refresh', 'expire_in'=>7200])]);
        $result = app(ShopeeClient::class)->refresh('old-refresh', '456');
        $this->assertSame('new-refresh', $result['refresh_token']);
        $this->assertSame(7200, $result['expire_in']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v2/auth/access_token/get?')
            && $request['refresh_token'] === 'old-refresh' && $request['shop_id'] === 456);
    }

    public function test_catalog_page_preserves_pagination_and_does_not_invent_empty_success(): void
    {
        Http::fake(['*'=>Http::sequence()
            ->push(['response'=>['item'=>[['item_id'=>100]], 'has_next_page'=>true, 'next_offset'=>50]])
            ->push(['message'=>'unexpected'])]);
        $page = app(ShopeeClient::class)->catalogPage('access', '456', 0);
        $this->assertSame(100, $page['item'][0]['item_id']);
        $this->assertTrue($page['has_next_page']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'page_size=50') && str_contains($r->url(), 'item_status=NORMAL'));
        $this->expectExceptionMessage('Shopee catalog response was incomplete.');
        app(ShopeeClient::class)->catalogPage('access', '456', 0);
    }

    public function test_catalog_accepts_confirmed_empty_response_without_item_field(): void
    {
        Http::fake(['*'=>Http::response(['error'=>'', 'response'=>['total_count'=>0, 'has_next_page'=>false, 'next'=>0]])]);
        $page = app(ShopeeClient::class)->catalogPage('access', '456', 0, 'UNLIST');
        $this->assertSame([], $page['item']);
        $this->assertFalse($page['has_next_page']);
    }

    public function test_catalog_rejects_missing_items_without_unambiguous_empty_evidence(): void
    {
        foreach ([
            ['has_next_page'=>false],
            ['total_count'=>1, 'has_next_page'=>false],
            ['total_count'=>0, 'has_next_page'=>true],
            ['total_count'=>0, 'has_next_page'=>false, 'item'=>'invalid'],
        ] as $response) {
            Http::fake(['*'=>Http::response(['error'=>'', 'response'=>$response])]);
            try {
                app(ShopeeClient::class)->catalogPage('access', '456', 0);
                $this->fail('Malformed catalog must not become empty success.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Shopee catalog response was incomplete.', $exception->getMessage());
            }
        }
    }

    public function test_product_details_require_all_requested_items_and_models_are_read_separately(): void
    {
        Http::fake(['*'=>Http::sequence()
            ->push(['response'=>['item_list'=>[['item_id'=>100, 'has_model'=>true]]]])
            ->push(['response'=>['model'=>[['model_id'=>200, 'model_sku'=>'BLUE']]]])
            ->push(['response'=>['item_list'=>[]]])]);
        $client = app(ShopeeClient::class);
        $this->assertTrue($client->itemDetails('access', '456', ['100'])[0]['has_model']);
        $this->assertSame('BLUE', $client->models('access', '456', '100')[0]['model_sku']);
        $this->expectExceptionMessage('Shopee did not return every requested product.');
        $client->itemDetails('access', '456', ['100']);
    }

    public function test_order_page_uses_fixed_time_window_and_rejects_stuck_cursor(): void
    {
        Http::fake(['*'=>Http::sequence()
            ->push(['response'=>['order_list'=>[['order_sn'=>'ORDER1']], 'more'=>true, 'next_cursor'=>'next']])
            ->push(['response'=>['order_list'=>[], 'more'=>true, 'next_cursor'=>'next']])]);
        $client = app(ShopeeClient::class);
        $page = $client->orderPage('access', '456', 1000, 2000);
        $this->assertSame('ORDER1', $page['order_list'][0]['order_sn']);
        Http::assertSent(fn ($r)=>str_contains($r->url(), 'time_from=1000') && str_contains($r->url(), 'time_to=2000'));
        $this->expectExceptionMessage('Shopee order pagination did not advance.');
        $client->orderPage('access', '456', 1000, 2000, 'next');
    }

    public function test_order_details_reject_partial_results(): void
    {
        Http::fake(['*'=>Http::sequence()
            ->push(['response'=>['order_list'=>[['order_sn'=>'ORDER1', 'order_status'=>'READY_TO_SHIP']]]])
            ->push(['response'=>['order_list'=>[]]])]);
        $client = app(ShopeeClient::class);
        $this->assertSame('READY_TO_SHIP', $client->orderDetails('access', '456', ['ORDER1'])[0]['order_status']);
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return in_array('fulfillment_flag', explode(',', $query['response_optional_fields'] ?? ''), true);
        });
        $this->expectExceptionMessage('Shopee did not return every requested order.');
        $client->orderDetails('access', '456', ['ORDER1']);
    }
}
