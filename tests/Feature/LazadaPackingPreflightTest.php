<?php
namespace Tests\Feature;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaPackingPreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LazadaPackingPreflightTest extends TestCase
{
    use RefreshDatabase;
    public function test_cancelled_item_prevents_packing_preflight_and_provider_lookup():void
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'123','marketplace.lazada.app_secret'=>'fixture-secret']);
        $store=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','region'=>'MY','environment'=>'live','status'=>'connected','shop_name'=>'Test']);
        MarketplaceStoreToken::create(['store_id'=>$store,'application_id'=>'123','access_token'=>'fixture-token','access_expires_at'=>now()->addHour()]);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['code'=>'0','data'=>[['order_id'=>'100','order_item_id'=>'101','status'=>'canceled']]])]);
        try { app(LazadaPackingPreflight::class)->inspect($store,'100',['101'],'TFS'); $this->fail('Cancelled item accepted'); }
        catch (\RuntimeException $error) { $this->assertSame('Selected item is no longer eligible for packing.',$error->getMessage()); }
        Http::assertSentCount(1);
        Http::assertSent(fn($request)=>$request->method()==='GET');
    }
}
