<?php
namespace Tests\Feature;

use App\Services\Marketplace\LazadaClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LazadaClientTest extends TestCase
{
    public function test_stock_write_requires_its_own_explicit_gate_and_posts_a_non_negative_absolute_quantity(): void
    {
        config(['marketplace.lazada.enabled'=>true, 'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.lazada.com.my/rest/product/price_quantity/update*'=>Http::response(['code'=>'0','data'=>[]])]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marketplace stock synchronization is disabled.');
        app(LazadaClient::class)->updateAbsoluteQuantity('fixture-token', '1001', '2001', 7);
    }
    public function test_token_exchange_and_refresh_use_signed_official_requests(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture-secret']);
        $this->travelTo(now()->setTimestamp(1800000000)->startOfSecond());
        Http::preventStrayRequests();
        Http::fake(['auth.lazada.com/rest/auth/token/*'=>Http::response([
            'code'=>'0','access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_in'=>3600,
            'refresh_expires_in'=>86400,'country'=>'my','account_platform'=>'seller_center',
            'country_user_info'=>[['country'=>'my','seller_id'=>'2001','user_id'=>9999]],
        ])]);
        $this->assertSame('2001',app(LazadaClient::class)->exchange('fixture-code')['seller_id']);
        $this->assertSame('fixture-access',app(LazadaClient::class)->refresh('fixture-refresh')['access_token']);
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(),PHP_URL_QUERY),$query);
            return str_starts_with($request->url(),'https://auth.lazada.com/rest/auth/token/create?')
                && $query['code']==='fixture-code' && $query['timestamp']==='1800000000000'
                && $query['sign']===strtoupper(hash_hmac('sha256',
                    '/auth/token/createapp_key139209codefixture-codesign_methodsha256timestamp1800000000000','fixture-secret'));
        });
        Http::assertSent(fn($request)=>str_contains($request->url(),'/auth/token/refresh?')
            && $request['refresh_token']==='fixture-refresh');
        Http::assertSentCount(2);
    }

    public function test_disabled_provider_sends_nothing(): void
    {
        Http::fake();
        config(['marketplace.lazada.enabled'=>false]);
        try { app(LazadaClient::class)->exchange('fixture-code'); $this->fail('Disabled provider used.'); }
        catch (RuntimeException) { Http::assertNothingSent(); }
    }

    public function test_http_failure_does_not_leak_provider_message_or_retry_token_exchange(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::fake(['*'=>Http::response(['message'=>'fixture-secret fixture-code'],500)]);
        try { app(LazadaClient::class)->exchange('fixture-code'); $this->fail('Failed exchange accepted.'); }
        catch (RuntimeException $error) {
            $this->assertSame('Lazada token request failed. Start authorization again or retry later.',$error->getMessage());
            $this->assertNull($error->getPrevious());
        }
        Http::assertSentCount(1);
    }
}
