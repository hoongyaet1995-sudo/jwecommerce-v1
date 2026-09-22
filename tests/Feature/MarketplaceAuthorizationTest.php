<?php

namespace Tests\Feature;

use App\Services\Marketplace\ShopeeAuthorizationService;
use App\Services\Marketplace\OAuthStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.oauth_acceptance_enabled'=>false,
            'marketplace.read_operations_enabled'=>false, 'marketplace.operations_enabled'=>false]);
        config(['app.url'=>'https://example.test', 'marketplace.shopee.enabled'=>true, 'marketplace.shopee.environment'=>'sandbox',
            'marketplace.shopee.main_account_enabled'=>true,
            'marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.partner_key'=>'secret',
            'marketplace.shopee.callback_url'=>'https://example.test/admin/marketplace/oauth/shopee/callback']);
        Http::preventStrayRequests();
        Http::fake([
            '*/auth/token/get*'=>fn($request)=>Http::response(isset($request['main_account_id'])
                ? ['access_token'=>'seed-access','refresh_token'=>'seed-refresh','expire_in'=>14400,'shop_id_list'=>[101,202],'merchant_id_list'=>[9]]
                : ['access_token'=>'access', 'refresh_token'=>'refresh', 'expire_in'=>14400]),
            '*/shop/get_shop_info*'=>Http::response(['shop_name'=>'Verified', 'region'=>'MY', 'shop_id'=>456]),
        ]);
    }

    public function test_main_account_callback_creates_independent_stores_and_batch_result(): void
    {
        $user=\App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $client=\Mockery::mock(\App\Services\Marketplace\ShopeeClient::class);
        $client->shouldReceive('exchangeMainAccountCode')->once()->with('one-use','77')->andReturn([
            'access_token'=>'seed-access','refresh_token'=>'seed-refresh','expire_in'=>14400,
            'shop_id_list'=>['101','202'],'merchant_id_list'=>['9'],
        ]);
        foreach (['101','202'] as $shop) {
            $client->shouldReceive('refreshShopCredential')->once()->with('seed-refresh',$shop)->andReturn([
                'access_token'=>'access-'.$shop,'refresh_token'=>'refresh-'.$shop,'expire_in'=>14400]);
            $client->shouldReceive('shopInfo')->once()->with('access-'.$shop,$shop)->andReturn([
                'shop_id'=>$shop,'shop_name'=>'Shop '.$shop,'region'=>'MY']);
        }
        $this->app->instance(\App\Services\Marketplace\ShopeeClient::class,$client);
        $state=app(OAuthStateService::class)->issue($user->id,'shopee',config('app.url'),null);
        $batch=app(ShopeeAuthorizationService::class)->completeMainAccount($state,$user->id,'one-use','77');
        app(\App\Services\Marketplace\ShopeeOAuthBatchProcessor::class)->process($batch->id);
        $this->assertDatabaseCount('marketplace_stores',2);
        $this->assertDatabaseCount('marketplace_store_tokens',2);
        $this->assertSame(2,DB::table('inventory_store_accounts')->whereNotNull('marketplace_store_id')->count());
        $response=$this->getJson('/admin/api/marketplace/oauth-batches/'.$batch->public_id)->assertOk()
            ->assertJsonPath('batch.status','success')->assertJsonPath('batch.total',2)
            ->assertJsonPath('batch.connected',2);
        $this->assertArrayNotHasKey('seed_refresh_token',$response->json('batch'));
        $tokens=\App\Models\MarketplaceStoreToken::orderBy('store_id')->get();
        $this->assertNotSame($tokens[0]->access_token,$tokens[1]->access_token);
    }

    public function test_callback_requires_exactly_one_shop_or_main_account_identity(): void
    {
        $user=\App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        foreach ([[],['shop_id'=>'1','main_account_id'=>'2']] as $identity) {
            $state=app(OAuthStateService::class)->issue($user->id,'shopee',config('app.url'),null);
            $this->get('/admin/marketplace/oauth/shopee/callback?'.http_build_query(['state'=>$state,'code'=>'code']+$identity))
                ->assertRedirect('/admin/marketplace/auth?connection=failed');
        }
        Http::assertNothingSent();
    }

    public function test_partial_batch_retry_processes_only_failed_store_and_preserves_success(): void
    {
        $user=\App\Models\User::factory()->create(['is_admin'=>true]);
        $client=\Mockery::mock(\App\Services\Marketplace\ShopeeClient::class);
        $client->shouldReceive('exchangeMainAccountCode')->once()->andReturn(['access_token'=>'seed','refresh_token'=>'seed-refresh',
            'expire_in'=>14400,'shop_id_list'=>['101','202'],'merchant_id_list'=>[]]);
        $client->shouldReceive('refreshShopCredential')->once()->with('seed-refresh','101')->andReturn([
            'access_token'=>'access-101','refresh_token'=>'refresh-101','expire_in'=>14400]);
        $client->shouldReceive('shopInfo')->once()->andReturn(['shop_id'=>'101','shop_name'=>'First','region'=>'MY']);
        $client->shouldReceive('refreshShopCredential')->once()->with('seed-refresh','202')->andThrow(new \RuntimeException('temporary'));
        $this->app->instance(\App\Services\Marketplace\ShopeeClient::class,$client);
        $state=app(OAuthStateService::class)->issue($user->id,'shopee',config('app.url'),null);
        $batch=app(ShopeeAuthorizationService::class)->completeMainAccount($state,$user->id,'code','77');
        $this->assertSame('partial',$batch->fresh()->status);
        $firstToken=\App\Models\MarketplaceStoreToken::firstOrFail()->access_token;

        $retry=\Mockery::mock(\App\Services\Marketplace\ShopeeClient::class);
        $retry->shouldReceive('refreshShopCredential')->once()->with('seed-refresh','202')->andReturn([
            'access_token'=>'access-202','refresh_token'=>'refresh-202','expire_in'=>14400]);
        $retry->shouldReceive('shopInfo')->once()->andReturn(['shop_id'=>'202','shop_name'=>'Second','region'=>'MY']);
        $processor=new \App\Services\Marketplace\ShopeeOAuthBatchProcessor($retry,app(\App\Services\Inventory\StoreManagement\StoreEnrollmentService::class));
        $processor->process($batch->id,true);
        $this->assertSame('success',$batch->fresh()->status);
        $this->assertSame(1,\App\Models\MarketplaceOAuthBatchStore::where('external_shop_id','101')->value('attempt_count'));
        $this->assertSame($firstToken,\App\Models\MarketplaceStoreToken::orderBy('store_id')->first()->access_token);
        $this->assertDatabaseCount('marketplace_stores',2);
    }

    public function test_verified_connection_is_saved_and_replay_cannot_exchange_again(): void
    {
        $state = app(OAuthStateService::class)->issue(42, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456');
        $this->assertDatabaseHas('inventory_store_accounts', [
            'marketplace_store_id' => $id,
            'management_status' => 'setup_required',
        ]);
        $this->assertDatabaseHas('marketplace_stores', ['id'=>$id, 'external_shop_id'=>'456', 'shop_name'=>'Verified', 'status'=>'connected']);
        $this->assertDatabaseCount('marketplace_store_tokens', 1);
        $this->assertDatabaseCount('marketplace_listing_variants', 0);
        try { app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456'); $this->fail('Replay accepted'); }
        catch (\RuntimeException $e) { $this->assertSame('Authorization expired or invalid. Start again.', $e->getMessage()); }
        Http::assertSentCount(2);
    }

    public function test_authorization_is_unavailable_when_callback_targets_another_deployment_or_route(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        foreach (['https://other.test/admin/marketplace/oauth/shopee/callback', 'https://example.test/dashboard/callback'] as $callback) {
            config(['marketplace.shopee.callback_url'=>$callback]);
            $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('capabilities.shopee.oauth', false);
            $this->postJson('/admin/api/marketplace/shopee/authorize')->assertConflict();
        }
        $this->assertDatabaseCount('marketplace_oauth_states', 0);
        Http::assertNothingSent();
    }

    public function test_reconnect_cannot_replace_the_original_store(): void
    {
        $id = DB::table('marketplace_stores')->insertGetId(['platform'=>'shopee', 'shop_name'=>'Original',
            'external_shop_id'=>'999', 'region'=>'MY', 'environment'=>'sandbox', 'status'=>'disconnected',
            'created_at'=>now(), 'updated_at'=>now()]);
        $state = app(OAuthStateService::class)->issue(42, 'shopee', config('app.url'), $id);
        try { app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456'); $this->fail('Wrong store accepted'); }
        catch (\RuntimeException $e) { $this->assertSame('Reconnect must authorize the original store.', $e->getMessage()); }
        $this->assertDatabaseHas('marketplace_stores', ['id'=>$id, 'external_shop_id'=>'999', 'status'=>'disconnected']);
        $this->assertDatabaseCount('marketplace_store_tokens', 0);
    }

    public function test_changing_partner_id_prevents_reuse_of_existing_store_token(): void
    {
        $state = app(OAuthStateService::class)->issue(42, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456');
        $manager = app(\App\Services\Marketplace\ShopeeTokenManager::class);
        $this->assertSame('access', $manager->accessToken($id));
        config(['marketplace.shopee.partner_id'=>'999']);
        try {
            $manager->accessToken($id);
            $this->fail('Token from another application was reused.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Store application has changed. Re-authorize the store.', $e->getMessage());
        }
        Http::assertSentCount(2);
    }

    public function test_http_authorization_and_callback_do_not_return_tokens(): void
    {
        $this->postJson('/admin/api/marketplace/shopee/authorize')->assertUnauthorized();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $url = $this->postJson('/admin/api/marketplace/shopee/authorize')->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        parse_str(parse_url($query['redirect_uri'], PHP_URL_QUERY), $callback);
        $response = $this->get('/admin/marketplace/oauth/shopee/callback?'.http_build_query($callback+['code'=>'code','shop_id'=>'456']));
        $response->assertRedirect('/admin/marketplace/auth?connection=success');
        $this->assertStringNotContainsString('access', $response->getContent());
        $this->assertDatabaseCount('marketplace_store_tokens', 1);
    }

    public function test_cancel_consumes_state_without_calling_provider(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        $this->get('/admin/marketplace/oauth/shopee/callback?state='.$state.'&error=access_denied')
            ->assertRedirect('/admin/marketplace/auth?connection=cancelled');
        $this->assertNull(app(OAuthStateService::class)->consume($state, $user->id, 'shopee', config('app.url')));
        Http::assertNothingSent();
    }

    public function test_disconnect_preserves_store_but_removes_credentials_and_pending_reconnect(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($state, $user->id, 'code', '456');
        $pending = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), $id);
        $this->postJson('/admin/api/marketplace/stores/'.$id.'/disconnect')->assertOk()->assertJsonPath('status', 'disconnected');
        $this->assertDatabaseHas('marketplace_stores', ['id'=>$id, 'external_shop_id'=>'456', 'status'=>'disconnected']);
        $this->assertDatabaseCount('marketplace_store_tokens', 0);
        $this->assertNull(app(OAuthStateService::class)->consume($pending, $user->id, 'shopee', config('app.url')));
        $this->postJson('/admin/api/marketplace/stores/'.$id.'/disconnect')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_disconnect_during_provider_exchange_prevents_late_reconnection(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $initial = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($initial, $user->id, 'code', '456');
        $pending = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), $id);
        Http::fake(function ($request) use ($id) {
            if (str_contains($request->url(), '/auth/token/get')) {
                $this->postJson('/admin/api/marketplace/stores/'.$id.'/disconnect')->assertOk();
                return Http::response(['access_token'=>'late', 'refresh_token'=>'late-refresh', 'expire_in'=>14400]);
            }
            return Http::response(['shop_name'=>'Verified', 'region'=>'MY', 'shop_id'=>456]);
        });
        try { app(ShopeeAuthorizationService::class)->complete($pending, $user->id, 'code', '456'); $this->fail('Late reconnect accepted'); }
        catch (\RuntimeException $e) { $this->assertSame('Authorization expired or invalid. Start again.', $e->getMessage()); }
        $this->assertDatabaseHas('marketplace_stores', ['id'=>$id, 'status'=>'disconnected']);
        $this->assertDatabaseCount('marketplace_store_tokens', 0);
    }

    public function test_connection_status_reports_expiry_without_exposing_credentials(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        app(ShopeeAuthorizationService::class)->complete($state, $user->id, 'code', '456');
        $result = $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status', 'connected')->assertJsonPath('capabilities.shopee.catalog', false);
        $this->assertArrayNotHasKey('access_token', $result->json('stores.0'));
        $this->assertArrayNotHasKey('refresh_token', $result->json('stores.0'));
        $this->assertStringEndsWith('Z', $result->json('stores.0.tokenExpiresAt'));
        $this->assertNull($result->json('stores.0.lastSyncAt'));
        $storeId=(int)$result->json('stores.0.id');
        DB::table('marketplace_catalog_runs')->insert(['store_id'=>$storeId,'status'=>'succeeded','finished_at'=>'2026-09-06 09:00:00']);
        DB::table('marketplace_order_runs')->insert(['store_id'=>$storeId,'status'=>'succeeded','time_from'=>1,'time_to'=>2,'finished_at'=>'2026-09-06 10:00:00']);
        DB::table('marketplace_catalog_runs')->insert(['store_id'=>$storeId,'status'=>'failed','finished_at'=>'2026-09-06 11:00:00']);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.lastSyncAt','2026-09-06T10:00:00.000000Z');
        $this->travel(5)->hours();
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.status', 'connected');
        // Metadata must not refresh tokens or expose their encrypted storage.
        Http::assertSentCount(2);
        DB::table('marketplace_store_tokens')->update(['refresh_expires_at'=>now()->subSecond()]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.status', 'reauthorization_required');
        DB::table('marketplace_store_tokens')->update(['refresh_expires_at'=>null,'refresh_token'=>null]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.status', 'reauthorization_required');
    }

    public function test_connection_metadata_detects_application_and_environment_changes_without_leaking_binding(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        app(ShopeeAuthorizationService::class)->complete($state, $user->id, 'code', '456');
        config(['marketplace.shopee.partner_id'=>'999']);
        $response = $this->getJson('/admin/api/marketplace/connections')->assertOk()
            ->assertJsonPath('stores.0.status', 'reauthorization_required');
        $this->assertArrayNotHasKey('application_id', $response->json('stores.0'));
        config(['marketplace.shopee.partner_id'=>'123', 'marketplace.shopee.environment'=>'production']);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.status', 'reauthorization_required');
        config(['marketplace.shopee.environment'=>'sandbox']);
        DB::table('marketplace_store_tokens')->update(['application_id'=>null]);
        $this->getJson('/admin/api/marketplace/connections')->assertOk()->assertJsonPath('stores.0.status', 'reauthorization_required');
    }

    public function test_token_manager_refreshes_once_then_reuses_and_rejects_disconnected_store(): void
    {
        $state = app(OAuthStateService::class)->issue(42, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456');
        $this->travel(5)->hours();
        Http::fake(['*'=>Http::response(['access_token'=>'rotated', 'refresh_token'=>'rotated-refresh', 'expire_in'=>14400])]);
        $manager = app(\App\Services\Marketplace\ShopeeTokenManager::class);
        $this->assertSame('rotated', $manager->accessToken($id));
        $this->assertSame('rotated', $manager->accessToken($id));
        Http::assertSentCount(1);
        DB::table('marketplace_stores')->where('id', $id)->update(['status'=>'disconnected']);
        try { $manager->accessToken($id); $this->fail('Disconnected token returned'); }
        catch (\RuntimeException $e) { $this->assertSame('Store is not connected to the configured Shopee environment.', $e->getMessage()); }
        Http::assertSentCount(1);
    }

    public function test_failed_refresh_preserves_credentials_and_explicit_retry_rotates_them(): void
    {
        $state = app(OAuthStateService::class)->issue(42, 'shopee', config('app.url'), null);
        $id = app(ShopeeAuthorizationService::class)->complete($state, 42, 'code', '456');
        $this->travel(5)->hours();
        $before = DB::table('marketplace_store_tokens')->where('store_id', $id)->first();
        Http::fake(['*/auth/access_token/get*'=>Http::sequence()
            ->push(['error'=>'temporary_error'], 503)
            ->push(['access_token'=>'retry-access', 'refresh_token'=>'retry-refresh', 'expire_in'=>14400])]);
        $manager = app(\App\Services\Marketplace\ShopeeTokenManager::class);
        try { $manager->accessToken($id); $this->fail('Failed refresh accepted'); }
        catch (\RuntimeException $e) { $this->assertSame('Shopee token exchange failed. Re-authorize the store.', $e->getMessage()); }
        Http::assertSentCount(1);
        $after = DB::table('marketplace_store_tokens')->where('store_id', $id)->first();
        $this->assertSame($before->access_token, $after->access_token);
        $this->assertSame($before->refresh_token, $after->refresh_token);
        $this->assertSame($before->access_expires_at, $after->access_expires_at);
        $this->assertDatabaseHas('marketplace_stores', ['id'=>$id, 'status'=>'connected']);
        $this->assertSame('retry-access', $manager->accessToken($id));
        $this->assertSame('retry-access', $manager->accessToken($id));
        Http::assertSentCount(2);
        $token = \App\Models\MarketplaceStoreToken::where('store_id', $id)->firstOrFail();
        $this->assertSame('retry-refresh', $token->refresh_token);
        $this->assertTrue($token->access_expires_at->gt(now()));
    }

    public function test_isolated_preview_is_not_available_in_production(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->app->instance('env', 'production');
        $this->get('/admin/marketplace/oauth-preview')->assertNotFound();
    }

    public function test_sandbox_acceptance_entry_is_opt_in_admin_only_and_never_live(): void
    {
        $this->get('/admin/marketplace/oauth-acceptance')->assertRedirect('/admin/login');
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>false]));
        // Existing web middleware clears non-admin customer sessions before admin authentication.
        $this->get('/admin/marketplace/oauth-acceptance')->assertRedirect('/admin/login');
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->app->instance('env', 'production');
        config(['marketplace.oauth_acceptance_enabled'=>false]);
        $this->get('/admin/marketplace/oauth-acceptance')->assertNotFound();
        config(['marketplace.oauth_acceptance_enabled'=>true]);
        $this->get('/admin/marketplace/oauth-acceptance')->assertOk()->assertSee('SANDBOX ACCEPTANCE');
        config(['marketplace.shopee.environment'=>'production']);
        $this->get('/admin/marketplace/oauth-acceptance')->assertNotFound();
    }

    public function test_sandbox_callback_returns_to_approved_official_entry_even_with_legacy_acceptance_enabled(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        config(['marketplace.oauth_acceptance_enabled'=>true]);
        $this->get('/admin/marketplace/oauth/shopee/callback')->assertRedirect('/admin/marketplace/auth?connection=failed');
        $this->getJson('/admin/api/marketplace/oauth-result')->assertJsonPath('result.status','failed');
    }

    public function test_callback_result_comes_from_verified_session_not_query_parameters(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $this->getJson('/admin/api/marketplace/oauth-result?connection=success&storeId=456')
            ->assertOk()->assertExactJson(['result'=>null]);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        $this->get('/admin/marketplace/oauth/shopee/callback?state='.$state.'&code=code&shop_id=456')->assertRedirect();
        $response = $this->getJson('/admin/api/marketplace/oauth-result')->assertOk()
            ->assertJsonPath('result.status', 'success')->assertJsonPath('result.store.shopId', '456')
            ->assertJsonPath('result.store.shopName', 'Verified');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(['id', 'platform', 'shopId', 'shopName'], array_keys($response->json('result.store')));
        $this->getJson('/admin/api/marketplace/oauth-result')->assertJsonPath('result.status', 'success');
        $this->travel(11)->minutes();
        $this->getJson('/admin/api/marketplace/oauth-result')->assertExactJson(['result'=>null]);
    }

    public function test_cancel_and_failed_callback_replace_previous_success_without_leaking_store(): void
    {
        $user = \App\Models\User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user);
        $state = app(OAuthStateService::class)->issue($user->id, 'shopee', config('app.url'), null);
        $this->get('/admin/marketplace/oauth/shopee/callback?state='.$state.'&error=access_denied');
        $this->getJson('/admin/api/marketplace/oauth-result')->assertExactJson(['result'=>['status'=>'cancelled', 'store'=>null]]);
        $this->get('/admin/marketplace/oauth/shopee/callback?connection=success');
        $this->getJson('/admin/api/marketplace/oauth-result')->assertExactJson(['result'=>['status'=>'failed', 'store'=>null]]);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->getJson('/admin/api/marketplace/oauth-result')->assertExactJson(['result'=>null]);
        Http::assertNothingSent();
    }

    public function test_callback_handles_concurrent_store_unique_conflict_without_exposing_sql(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        // At the controller boundary a competing connection may win after the service precheck.
        // Keep the real callback/result flow; replace only the raced database failure boundary.
        $service = \Mockery::mock(ShopeeAuthorizationService::class);
        $service->shouldReceive('complete')->once()->andThrow(new \Illuminate\Database\UniqueConstraintViolationException(
            'mysql', 'INSERT PRIVATE SQL', [], new \PDOException('duplicate private details')
        ));
        $this->app->instance(ShopeeAuthorizationService::class, $service);
        $response = $this->get('/admin/marketplace/oauth/shopee/callback?state='.str_repeat('a',64).'&code=code&shop_id=456');
        $response->assertRedirect('/admin/marketplace/auth?connection=failed');
        $this->assertStringNotContainsString('PRIVATE SQL', $response->getContent());
        $this->getJson('/admin/api/marketplace/oauth-result')->assertExactJson(['result'=>['status'=>'failed', 'store'=>null]]);
        $this->assertDatabaseCount('marketplace_store_tokens', 0);
    }
}
