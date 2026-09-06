<?php

namespace Tests\Feature;

use App\Services\Marketplace\ShopeeClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeClientTest extends TestCase
{
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
        $this->expectExceptionMessage('Shopee did not return every requested order.');
        $client->orderDetails('access', '456', ['ORDER1']);
    }
}
