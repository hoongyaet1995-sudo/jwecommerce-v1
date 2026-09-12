<?php
namespace Tests\Feature;

use App\Services\Marketplace\LazadaClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LazadaClientTest extends TestCase
{
    public function test_document_envelope_and_failed_result_are_checked():void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::sequence()->push(['code'=>'0','result'=>['success'=>true,'data'=>['doc_type'=>'PDF','pdf_url'=>'https://sellercenter.lazada.com.my/test']]])
            ->push(['code'=>'0','result'=>['success'=>false,'data'=>['doc_type'=>'PDF']]])]);
        $parameters=['getDocumentReq'=>json_encode(['doc_type'=>'PDF','packages'=>[['package_id'=>'PK1']]])];
        $this->assertSame('PDF',app(LazadaClient::class)->read('/order/package/document/get','fixture-token',$parameters)['doc_type']);
        Http::assertSent(function($request)use($parameters){parse_str(parse_url($request->url(),PHP_URL_QUERY),$query);return $query['getDocumentReq']===$parameters['getDocumentReq'];});
        $this->expectException(RuntimeException::class);
        app(LazadaClient::class)->read('/order/package/document/get','fixture-token',$parameters);
    }
    public function test_shipment_provider_query_uses_verified_envelope_and_requires_success():void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::sequence()->push(['code'=>'0','result'=>['success'=>true,'data'=>['platform_default'=>1,'shipping_allocate_type'=>'TFS']]])
            ->push(['code'=>'0','result'=>['success'=>false,'data'=>[]]])]);
        $parameters=['getShipmentProvidersReq'=>json_encode(['orders'=>[['order_id'=>'100','order_item_ids'=>['101']]],'shipping_allocate_type'=>'TFS'])];
        $this->assertSame(1,app(LazadaClient::class)->read('/order/shipment/providers/get','fixture-token',$parameters)['platform_default']);
        Http::assertSent(function($request)use($parameters){parse_str(parse_url($request->url(),PHP_URL_QUERY),$query);return $query['getShipmentProvidersReq']===$parameters['getShipmentProvidersReq'];});
        $this->expectException(RuntimeException::class);
        app(LazadaClient::class)->read('/order/shipment/providers/get','fixture-token',$parameters);
    }
    public function test_fractional_quantity_is_rejected_before_provider_request(): void
    {
        config(['marketplace.publish_operations_enabled'=>true]);
        Http::preventStrayRequests();
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019',
            'Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],
            'Skus'=>['Sku'=>[['SellerSku'=>'TEST','quantity'=>'-0.5','price'=>'10.00']]]]]];
        try { app(LazadaClient::class)->createProduct('fixture-token',$payload); $this->fail('Invalid quantity accepted'); }
        catch (RuntimeException $error) { $this->assertSame('Invalid Lazada product SKU payload.',$error->getMessage()); }
        Http::assertNothingSent();
    }

    public function test_duplicate_provider_sku_identity_cannot_confirm_two_skus(): void
    {
        config(['marketplace.publish_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>['item_id'=>1001,'sku_list'=>[
            ['seller_sku'=>'A','sku_id'=>2001],['seller_sku'=>'B','sku_id'=>2001]]]])]);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019',
            'Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'Test'],'Skus'=>['Sku'=>[
                ['SellerSku'=>'A','quantity'=>'0','price'=>'10.00'],['SellerSku'=>'B','quantity'=>'0','price'=>'10.00']]]]]];
        $this->expectException(\App\Services\Marketplace\PublishingResultUncertain::class);
        app(LazadaClient::class)->createProduct('fixture-token',$payload);
    }
    public function test_uncertain_publish_exposes_only_bounded_provider_diagnostics(): void
    {
        $error=new \App\Services\Marketplace\PublishingResultUncertain('Reconcile', [
            'http_status'=>200,'provider_code'=>'501','request_id'=>'210176f717888495722931192',
            'message'=>'secret-token','payload'=>['access_token'=>'secret-token'],
        ]);
        $this->assertSame(['http_status'=>200,'provider_code'=>'501','request_id'=>'210176f717888495722931192'],$error->diagnostics());
        $invalid=new \App\Services\Marketplace\PublishingResultUncertain('Reconcile',[
            'http_status'=>999,'provider_code'=>'secret-token','request_id'=>'https://example.com/?token=secret',
        ]);
        $this->assertSame([],$invalid->diagnostics());
    }

    public function test_stock_request_signs_sellable_payload_for_the_selected_warehouse(): void
    {
        config(['marketplace.stock_sync_operations_enabled'=>true,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0'])]);
        app(LazadaClient::class)->updateAbsoluteQuantity('fixture-token','1001','2001',7,'MY-WH-2');
        Http::assertSent(function ($request) {
            $parameters=$request->data();
            if (!is_string($parameters['payload'] ?? null)) return false;
            $xml=simplexml_load_string($parameters['payload'] ?? '<invalid/>');
            $signature=$parameters['sign'] ?? ''; unset($parameters['sign']);
            ksort($parameters,SORT_STRING);
            $base='/product/stock/sellable/update';
            foreach ($parameters as $name=>$value) $base.=$name.$value;
            return $request->url()==='https://api.lazada.com.my/rest/product/stock/sellable/update'
                && (string)$xml->Product->Skus->Sku->MultiWarehouseInventories->MultiWarehouseInventory->WarehouseCode==='MY-WH-2'
                && (string)$xml->Product->Skus->Sku->MultiWarehouseInventories->MultiWarehouseInventory->SellableQuantity==='7'
                && !isset($xml->Product->Skus->Sku->Quantity)
                && hash_equals(strtoupper(hash_hmac('sha256',$base,'fixture-secret')),$signature);
        });
    }

    public function test_stock_write_requires_its_own_explicit_gate_and_posts_a_non_negative_absolute_quantity(): void
    {
        config(['marketplace.lazada.enabled'=>true, 'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.lazada.com.my/rest/product/price_quantity/update*'=>Http::response(['code'=>'0','data'=>[]])]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marketplace stock synchronization is disabled.');
        app(LazadaClient::class)->updateAbsoluteQuantity('fixture-token', '1001', '2001', 7);
    }
    public function test_product_creation_requires_the_publish_gate_and_confirms_provider_ids(): void
    {
        config(['marketplace.lazada.enabled'=>true, 'marketplace.lazada.app_key'=>'123', 'marketplace.lazada.app_secret'=>'fixture-secret']);
        $payload=['Request'=>['Product'=>['PrimaryCategory'=>'10002019',
            'Images'=>['Image'=>['https://my-live-02.slatic.net/p/test.jpg']],
            'Attributes'=>['name'=>'JW-SBX Product'],
            'Skus'=>['Sku'=>[['SellerSku'=>'JW-SBX-001','quantity'=>'2','price'=>'10.00']]]]]];
        Http::preventStrayRequests();
        Http::fake(['api.lazada.com.my/rest/product/create*'=>Http::response(['code'=>'0','data'=>['item_id'=>1001,
            'sku_list'=>[['seller_sku'=>'JW-SBX-001','sku_id'=>2001]]]])]);

        try { app(LazadaClient::class)->createProduct('fixture-token',$payload); $this->fail('Publish gate was bypassed.'); }
        catch (RuntimeException $error) { $this->assertSame('Marketplace publishing is disabled.',$error->getMessage()); }
        Http::assertNothingSent();

        config(['marketplace.publish_operations_enabled'=>true]);
        $result=app(LazadaClient::class)->createProduct('fixture-token',$payload);
        $this->assertSame('1001',$result['item_id']);
        $this->assertSame('2001',$result['sku_ids']['JW-SBX-001']);
        Http::assertSent(function ($request) use ($payload) {
            $parameters=$request->data();
            if (!is_string($parameters['payload'] ?? null)) return false;
            $signature=$parameters['sign'] ?? ''; unset($parameters['sign']);
            ksort($parameters,SORT_STRING); $base='/product/create';
            foreach ($parameters as $name=>$value) $base.=$name.$value;
            return $request->url()==='https://api.lazada.com.my/rest/product/create'
                && json_decode($parameters['payload'],true,512,JSON_THROW_ON_ERROR)===$payload
                && hash_equals(strtoupper(hash_hmac('sha256',$base,'fixture-secret')),$signature);
        });
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['api.lazada.com.my/rest/product/create*'=>Http::response(['code'=>'501','request_id'=>'210176f717888495722931192',
            'message'=>'fixture-token fixture-secret','detail'=>['credentials'=>'fixture-token']],200)]);
        try {
            app(LazadaClient::class)->createProduct('fixture-token',$payload);
            $this->fail('Provider rejection was accepted.');
        } catch (\App\Services\Marketplace\PublishingResultUncertain $error) {
            $this->assertSame(['http_status'=>200,'provider_code'=>'501','request_id'=>'210176f717888495722931192'],$error->diagnostics());
            $this->assertStringNotContainsString('fixture-token',$error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_warehouse_read_uses_verified_result_module_envelope(): void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::sequence()->push(['code'=>'0','result'=>['success'=>true,'module'=>[['code'=>'dropshipping','status'=>'ACTIVE']]]])->push(['code'=>'0','result'=>['success'=>false,'module'=>[]]])]);
        $this->assertSame([['code'=>'dropshipping','status'=>'ACTIVE']],app(LazadaClient::class)->read('/rc/warehouse/get','fixture-token'));
        $this->expectException(RuntimeException::class);
        app(LazadaClient::class)->read('/rc/warehouse/get','fixture-token');
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
