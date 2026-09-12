<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LazadaOAuthRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_authorize_and_callback_receipt_contains_no_credentials(): void
    {
        config(['app.url'=>'https://example.test','marketplace.lazada.enabled'=>true,'marketplace.lazada.app_key'=>'139209',
            'marketplace.lazada.app_secret'=>'fixture-secret','marketplace.lazada.callback_url'=>'https://example.test/admin/marketplace/oauth/lazada/callback']);
        Http::fake(['auth.lazada.com/*'=>Http::response(['code'=>'0','country'=>'my','account_platform'=>'seller_center',
            'access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','expires_in'=>3600,'refresh_expires_in'=>86400,
            'country_user_info'=>[['country'=>'my','seller_id'=>'2001']]])]);
        $this->postJson('/admin/api/marketplace/lazada/authorize')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $url=$this->postJson('/admin/api/marketplace/lazada/authorize')->assertOk()->json('url');
        parse_str(parse_url($url,PHP_URL_QUERY),$query);
        $response=$this->get('/admin/marketplace/oauth/lazada/callback?'.http_build_query(['state'=>$query['state'],'code'=>'fixture-code']));
        $response->assertRedirect('/admin/marketplace/auth?connection=success');
        $this->getJson('/admin/api/marketplace/oauth-result')->assertOk()->assertJsonPath('result.status','success')
            ->assertJsonPath('result.store.shopId','2001')->assertDontSee('fixture-access')->assertDontSee('fixture-refresh');
        $this->assertDatabaseCount('marketplace_stores',1);
        $this->assertDatabaseCount('marketplace_catalog_runs',0);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status','connected')->assertJsonPath('capabilities.lazada.oauth',true)
            ->assertJsonPath('capabilities.lazada.catalog',false)->assertJsonPath('capabilities.lazada.orders',false)
            ->assertDontSee('fixture-access')->assertDontSee('fixture-secret');
        config(['marketplace.read_operations_enabled'=>true,'marketplace.shopee.enabled'=>false,'marketplace.shopee.partner_id'=>null,'marketplace.shopee.partner_key'=>null]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('capabilities.lazada.catalog',true)->assertJsonPath('capabilities.lazada.orders',true);
        config(['marketplace.lazada.app_key'=>'999999']);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status','reauthorization_required');
    }

    public function test_disabled_authorization_does_not_create_attempt(): void
    {
        config(['marketplace.lazada.enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->postJson('/admin/api/marketplace/lazada/authorize')->assertConflict();
        $this->assertDatabaseCount('marketplace_oauth_states',0);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('capabilities.lazada.oauth',false);
    }
}
