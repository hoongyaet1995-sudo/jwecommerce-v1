<?php
namespace Tests\Feature;

use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LazadaTokenManagerTest extends TestCase
{
    use RefreshDatabase;

    private function store(): int
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        $id=DB::table('marketplace_stores')->insertGetId(['platform'=>'lazada','environment'=>'live','external_shop_id'=>'2001',
            'shop_name'=>'Fixture','region'=>'MY','status'=>'connected','created_at'=>now(),'updated_at'=>now()]);
        MarketplaceStoreToken::create(['store_id'=>$id,'application_id'=>'139209','access_token'=>'old-access',
            'refresh_token'=>'old-refresh','access_expires_at'=>now()->subMinute(),'refresh_expires_at'=>now()->addDay()]);
        return $id;
    }

    public function test_refresh_rotates_both_tokens_and_subsequent_read_reuses_access(): void
    {
        $id=$this->store();
        Http::fake(['auth.lazada.com/*'=>Http::response(['code'=>'0','country'=>'my','account_platform'=>'seller_center',
            'access_token'=>'new-access','refresh_token'=>'new-refresh','expires_in'=>3600,'refresh_expires_in'=>86400,
            'country_user_info'=>[['country'=>'my','seller_id'=>'2001']]])]);
        $manager=app(LazadaTokenManager::class);
        $this->assertSame('new-access',$manager->accessToken($id));
        $this->assertSame('new-access',$manager->accessToken($id));
        $token=MarketplaceStoreToken::where('store_id',$id)->firstOrFail();
        $this->assertSame('new-refresh',$token->refresh_token);
        $this->assertTrue($token->access_expires_at->isFuture());
        Http::assertSentCount(1);
    }

    public function test_disconnected_store_never_refreshes(): void
    {
        $id=$this->store();
        Http::fake();
        DB::table('marketplace_stores')->where('id',$id)->update(['status'=>'disconnected']);
        $error=null;
        try { app(LazadaTokenManager::class)->accessToken($id); } catch (RuntimeException $caught) { $error=$caught; }
        $this->assertInstanceOf(RuntimeException::class,$error);
        Http::assertNothingSent();
    }

    public function test_wrong_seller_refresh_cannot_overwrite_existing_credentials(): void
    {
        $id=$this->store();
        Http::fake(['auth.lazada.com/*'=>Http::response(['code'=>'0','country'=>'my','account_platform'=>'seller_center',
            'access_token'=>'wrong-access','refresh_token'=>'wrong-refresh','expires_in'=>3600,'refresh_expires_in'=>86400,
            'country_user_info'=>[['country'=>'my','seller_id'=>'2002']]])]);
        $error=null;
        try { app(LazadaTokenManager::class)->accessToken($id); } catch (RuntimeException $caught) { $error=$caught; }
        $this->assertInstanceOf(RuntimeException::class,$error);
        $this->assertSame('old-access',MarketplaceStoreToken::where('store_id',$id)->firstOrFail()->access_token);
    }
}
