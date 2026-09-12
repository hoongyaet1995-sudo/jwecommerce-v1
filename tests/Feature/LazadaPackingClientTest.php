<?php
namespace Tests\Feature;
use App\Services\Marketplace\LazadaPackingClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaPackingClientTest extends TestCase
{
    public function test_gate_prevents_network_and_enabled_request_signs_pack_envelope():void
    {
        config(['marketplace.fulfillment_operations_enabled'=>false,'marketplace.lazada.enabled'=>true,
            'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','result'=>['success'=>true,'data'=>['pack_order_list'=>[
            ['order_id'=>'100','order_item_list'=>[['order_item_id'=>'101','item_err_code'=>'0','package_id'=>'PK1']]]
        ]]]])]);
        try { app(LazadaPackingClient::class)->pack('fixture-token','100',['101'],'TFS'); $this->fail('Gate bypassed'); }
        catch (\RuntimeException $error) { $this->assertSame('Marketplace fulfillment is disabled.',$error->getMessage()); }
        Http::assertNothingSent();
        config(['marketplace.fulfillment_operations_enabled'=>true]);
        $this->assertTrue(app(LazadaPackingClient::class)->pack('fixture-token','100',['101'],'TFS')['complete']);
        Http::assertSent(function($request) {
            $data=$request->data(); $sign=$data['sign']; unset($data['sign']); ksort($data,SORT_STRING);
            $base='/order/fulfill/pack'; foreach ($data as $key=>$value) $base.=$key.$value;
            return $request->method()==='POST'&&$request->url()==='https://api.lazada.com.my/rest/order/fulfill/pack'
                &&json_decode($data['packReq'],true)['pack_order_list'][0]['order_item_list']===['101']
                &&hash_equals(strtoupper(hash_hmac('sha256',$base,'fixture-secret')),$sign);
        });
        Http::assertSentCount(1);
    }
}
