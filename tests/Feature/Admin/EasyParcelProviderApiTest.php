<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;

class EasyParcelProviderApiTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_platform_credentials_are_never_bootstrapped_and_the_retired_browser_exchange_route_is_absent(): void
    {
        $this->platform();
        $admin = $this->superAdmin();
        $surface = $this->actingAs($admin)->get('/admin/shipping')->assertOk()->getContent();

        $this->assertStringNotContainsString('platform-client', $surface);
        $this->assertStringNotContainsString('platform-secret', $surface);
        $this->assertStringNotContainsString('easyParcelProviderTestConnectionUrl', $surface);
        $this->actingAs($admin)->postJson('/admin/api/shipping/providers/easyparcel/test-connection')->assertNotFound();
    }

    public function test_callback_exchanges_tokens_only_after_the_session_bound_authorization_state_and_stores_no_platform_credentials(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer', 'access_token' => 'connected-token', 'expires_in' => 3600,
            ]),
            'https://api.easyparcel.com/open_api/2026-06/account/get_account_information' => Http::response([
                'status_code' => 200,
                'data' => ['account' => ['name' => 'Sandbox Account', 'account_type' => 'Personal']],
            ]),
        ]);

        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize')->assertRedirect();
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        $response = $this->get('/admin/shipping/easyparcel/callback?code=provider-code&state='.$query['state']);

        $response->assertRedirect('/admin/shipping')->assertSessionHas('easyparcelConnection.state', 'connected');
        $provider = ShippingProvider::query()->sole();
        $this->assertTrue($provider->enabled);
        $this->assertSame('connected', $provider->connection_state);
        $stored = ShippingProviderCredential::query()->sole()->credentials;
        $this->assertSame('connected-token', $stored['access_token']);
        $this->assertArrayNotHasKey('client_id', $stored);
        $this->assertArrayNotHasKey('client_secret', $stored);
        $raw = (string) DB::table('shipping_provider_credentials')->value('credentials');
        $this->assertStringNotContainsString('connected-token', $raw);
        $this->assertStringNotContainsString('platform-secret', $raw);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.easyparcel.com/oauth/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['state'] === $query['state']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.easyparcel.com/open_api/2026-06/account/get_account_information'
            && $request->hasHeader('Authorization', 'Bearer connected-token'));
    }

    public function test_replayed_callback_state_never_repeats_the_token_exchange_or_disconnects_the_store(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response(['token_type' => 'Bearer', 'access_token' => 'once-token']),
            'https://api.easyparcel.com/open_api/2026-06/account/get_account_information' => Http::response(['status_code' => 200, 'data' => ['account' => ['name' => 'Sandbox Account']]]),
        ]);
        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get('/admin/shipping/easyparcel/callback?code=one&state='.$query['state'])->assertSessionHas('easyparcelConnection.state', 'connected');
        $this->get('/admin/shipping/easyparcel/callback?code=two&state='.$query['state'])->assertSessionHas('easyparcelConnection.state', 'failed');
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);
        Http::assertSentCount(2);
    }

    public function test_token_exchange_logs_only_its_safe_http_failure_classification(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response(['message' => 'private provider detail'], 401),
        ]);
        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        Log::spy();

        $this->get('/admin/shipping/easyparcel/callback?code=provider-code&state='.$query['state'])
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        Log::shouldHaveReceived('warning')->once()->with(
            'EasyParcel OAuth verification rejected',
            ['stage' => 'token', 'http_status' => 401],
        );
    }

    public function test_token_exchange_logs_only_the_safe_transport_exception_classification(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $admin = $this->superAdmin();
        Http::fake(fn () => throw new \RuntimeException('private transport detail'));
        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        Log::spy();

        $this->get('/admin/shipping/easyparcel/callback?code=provider-code&state='.$query['state'])
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        Log::shouldHaveReceived('warning')->once()->with(
            'EasyParcel OAuth verification failed',
            ['exception_class' => \RuntimeException::class],
        );
    }

    public function test_permissions_and_disconnect_only_apply_to_the_connected_store_token(): void
    {
        $this->platform();
        $ordinary = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
        $this->actingAs($ordinary)->getJson('/admin/api/shipping/providers/easyparcel')->assertForbidden();
        $this->actingAs($ordinary)->post('/admin/api/shipping/providers/easyparcel/authorize')->assertForbidden();
        $this->actingAs($ordinary)->deleteJson('/admin/api/shipping/providers/easyparcel')->assertForbidden();

        $provider = ShippingProvider::query()->create(['provider' => 'easyparcel', 'environment' => 'sandbox', 'enabled' => true, 'connection_state' => 'connected', 'configuration_version' => 0]);
        ShippingProviderCredential::query()->create(['shipping_provider_id' => $provider->id, 'provider' => 'easyparcel', 'environment' => 'sandbox', 'credentials' => ['access_token' => 'token']]);
        $this->actingAs($this->superAdmin())->deleteJson('/admin/api/shipping/providers/easyparcel')->assertOk()->assertJsonPath('data.configured', true);
        $this->assertDatabaseCount('shipping_provider_credentials', 0);
    }

    private function platform(): void { config()->set('services.easyparcel.client_id', 'platform-client'); config()->set('services.easyparcel.client_secret', 'platform-secret'); }
    private function superAdmin(): User { return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]); }
}
