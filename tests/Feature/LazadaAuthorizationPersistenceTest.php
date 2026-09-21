<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketplaceStoreToken;
use App\Services\Marketplace\LazadaAuthorizationService;
use App\Services\Marketplace\OAuthStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LazadaAuthorizationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__.'/../../app/Services/Marketplace/LazadaAuthorizationService.php';

        if (!Schema::hasTable('marketplace_store_capabilities')) {
            (require __DIR__.'/../../database/migrations/2026_09_18_000001_add_account_mode_and_store_capabilities.php')->up();
        }
    }

    private function setupProvider(string $seller='2001'): int
    {
        config(['marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209','marketplace.lazada.app_secret'=>'fixture-secret']);
        Http::preventStrayRequests();
        Http::fake(['auth.lazada.com/*'=>Http::response(['code'=>'0','country'=>'my','account_platform'=>'seller_center',
            'access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_in'=>3600,'refresh_expires_in'=>86400,
            'country_user_info'=>[['country'=>'my','seller_id'=>$seller,'user_id'=>9999]]])]);
        return User::factory()->create()->id;
    }

    private function authorize(int $actor, ?int $reconnect=null): int
    {
        $state=app(OAuthStateService::class)->issue($actor,'lazada',config('app.url'),$reconnect);
        return app(LazadaAuthorizationService::class)->complete($state,$actor,'fixture-code');
    }

    public function test_configuration_changed_during_exchange_cannot_save_tokens_under_another_app(): void
    {
        $actor=$this->setupProvider();
        Http::fake(['auth.lazada.com/*'=>function () {
            config(['marketplace.lazada.app_key'=>'999999']);
            return Http::response(['code'=>'0','country'=>'my','account_platform'=>'seller_center',
                'access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_in'=>3600,'refresh_expires_in'=>86400,
                'country_user_info'=>[['country'=>'my','seller_id'=>'2001','user_id'=>9999]]]);
        }]);
        try { $this->authorize($actor); $this->fail('Changed application accepted.'); }
        catch (RuntimeException) {
            $this->assertDatabaseCount('marketplace_stores',0);
            $this->assertDatabaseCount('marketplace_store_tokens',0);
        }
    }

    public function test_authorization_persists_real_identity_and_encrypted_expiring_credentials(): void
    {
        $actor=$this->setupProvider();
        $id=$this->authorize($actor);
        $this->assertDatabaseHas('inventory_store_accounts',[
            'marketplace_store_id'=>$id,'management_status'=>'setup_required',
        ]);
        $this->assertDatabaseHas('marketplace_stores',['id'=>$id,'platform'=>'lazada','external_shop_id'=>'2001','region'=>'MY','status'=>'connected']);
        $this->assertDatabaseHas('marketplace_stores',['id'=>$id,'account_mode'=>'production']);
        $this->assertDatabaseHas('marketplace_store_capabilities',['store_id'=>$id,'catalog_read'=>true,'product_publish'=>false,
            'order_receive'=>false,'inventory_update'=>false,'fulfillment_write'=>false]);
        $raw=DB::table('marketplace_store_tokens')->where('store_id',$id)->first();
        $this->assertNotSame('fixture-access',$raw->access_token);
        $this->assertNotSame('fixture-refresh',$raw->refresh_token);
        $token=MarketplaceStoreToken::where('store_id',$id)->firstOrFail();
        $this->assertSame('fixture-access',$token->access_token);
        $this->assertSame('139209',$token->application_id);
        $this->assertEqualsWithDelta(3600,now()->diffInSeconds($token->access_expires_at),2);
        $this->assertEqualsWithDelta(86400,now()->diffInSeconds($token->refresh_expires_at),2);
        $this->assertArrayNotHasKey('access_token',$token->toArray());
    }

    public function test_replayed_state_does_not_exchange_again(): void
    {
        $actor=$this->setupProvider();
        $state=app(OAuthStateService::class)->issue($actor,'lazada',config('app.url'),null);
        app(LazadaAuthorizationService::class)->complete($state,$actor,'fixture-code');
        try { app(LazadaAuthorizationService::class)->complete($state,$actor,'fixture-code'); $this->fail('Replay accepted.'); }
        catch (RuntimeException) { $this->assertDatabaseCount('marketplace_stores',1); }
        Http::assertSentCount(1);
    }

    public function test_duplicate_store_requires_reconnect_and_wrong_store_cannot_replace_it(): void
    {
        $actor=$this->setupProvider();
        $id=$this->authorize($actor);
        try { $this->authorize($actor); $this->fail('Duplicate accepted.'); }
        catch (RuntimeException) { $this->assertDatabaseCount('marketplace_stores',1); }
        $this->assertSame($id,$this->authorize($actor,$id));
        $this->setupProvider('2002');
        try { $this->authorize($actor,$id); $this->fail('Wrong store accepted.'); }
        catch (RuntimeException) { $this->assertDatabaseHas('marketplace_stores',['id'=>$id,'external_shop_id'=>'2001']); }
        $this->assertDatabaseCount('marketplace_store_tokens',1);
    }
}
