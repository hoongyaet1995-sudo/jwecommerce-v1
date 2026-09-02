<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

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
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 200,
                'data' => ['wallet' => [['balance' => 100, 'currency' => 'MYR']]],
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
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.easyparcel.com/open_api/2026-06/wallet'
            && $request->hasHeader('Authorization', 'Bearer connected-token'));
    }

    public function test_replayed_callback_state_never_repeats_the_token_exchange_or_disconnects_the_store(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $admin = $this->superAdmin();
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response(['token_type' => 'Bearer', 'access_token' => 'once-token']),
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response(['status_code' => 200, 'data' => ['wallet' => []]]),
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

    public function test_unreadable_imported_credentials_do_not_hide_the_easyparcel_integration(): void
    {
        $this->platform();
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 3,
        ]);
        DB::table('shipping_provider_credentials')->insert([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => 'unreadable-imported-ciphertext',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/shipping/providers/easyparcel')
            ->assertOk()
            ->assertJsonPath('data.provider', 'easyparcel')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified')
            ->assertJsonPath('data.lastError', 'credentials_unreadable');
    }

    public function test_starting_a_new_authorization_explicitly_resets_unreadable_imported_credentials(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->platform();
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 3,
        ]);
        DB::table('shipping_provider_credentials')->insert([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => 'unreadable-imported-ciphertext',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->post('/admin/api/shipping/providers/easyparcel/authorize')
            ->assertRedirect();

        $this->assertDatabaseCount('shipping_provider_credentials', 0);
        $this->assertSame('unverified', $provider->fresh()->connection_state);
        $this->assertFalse($provider->fresh()->enabled);
        $this->assertSame('credentials_reset_for_reauthorization', $provider->fresh()->last_error);
    }

    public function test_super_admin_can_save_a_manual_sender_profile_without_replacing_oauth_tokens(): void
    {
        $this->platform();
        $admin = $this->superAdmin();
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 3,
            'last_verified_at' => now(),
        ]);
        ShippingProviderCredential::query()->create([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => [
                'token_type' => 'Bearer',
                'access_token' => 'connected-token',
                'sender_profile' => [],
            ],
        ]);

        $response = $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'senderProfile' => [
                'name' => 'JW Studio Dispatch',
                'company' => 'JW Studio',
                'phone_number_country_code' => 'MY',
                'phone_number' => '0142244200',
                'email' => 'support@jwaistudio.online',
                'address_1' => '18 Test Industry Road',
                'address_2' => 'Sandbox Fulfillment',
                'postcode' => '47600',
                'city' => 'Subang Jaya',
                'subdivision_code' => 'MY-10',
                'country_code' => 'MY',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.senderProfile.name', 'JW Studio Dispatch')
            ->assertJsonPath('data.senderProfileComplete', true);

        $stored = ShippingProviderCredential::query()->sole()->credentials;
        $this->assertSame('connected-token', $stored['access_token']);
        $this->assertSame('JW Studio Dispatch', $stored['sender_profile']['name']);
        $this->assertSame(4, $provider->fresh()->configuration_version);

        $raw = (string) DB::table('shipping_provider_credentials')->value('credentials');
        $this->assertStringNotContainsString('support@jwaistudio.online', $raw);
        $this->assertStringNotContainsString('18 Test Industry Road', $raw);
    }

    public function test_sender_profile_requires_a_connected_environment_and_complete_pickup_data(): void
    {
        $this->platform();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'senderProfile' => ['name' => 'Incomplete'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'senderProfile.phone_number_country_code',
                'senderProfile.phone_number',
                'senderProfile.address_1',
                'senderProfile.postcode',
                'senderProfile.city',
                'senderProfile.subdivision_code',
                'senderProfile.country_code',
            ]);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->putJson('/admin/api/shipping/providers/easyparcel', [
                'environment' => 'sandbox',
                'senderProfile' => [],
            ])->assertForbidden();
    }

    private function platform(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }
}
