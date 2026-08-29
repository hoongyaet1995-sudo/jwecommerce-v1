<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;

class EasyParcelPlatformOauthTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_platform_environment_credentials_start_authorization_without_a_browser_configuration_payload(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $response = $this->actingAs($admin)
            ->post('/admin/api/shipping/providers/easyparcel/authorize');

        $response->assertRedirect();
        $this->assertStringContainsString('client_id=platform-client', (string) $response->headers->get('Location'));
        $this->assertDatabaseCount('shipping_providers', 1);
        $this->assertDatabaseCount('shipping_provider_credentials', 0);

        $this->actingAs($admin)
            ->getJson('/admin/api/shipping/providers/easyparcel')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonMissing(['clientSecret' => 'platform-secret']);
    }

    public function test_super_admin_can_disconnect_a_connected_store_without_removing_platform_configuration(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel', 'environment' => 'sandbox', 'enabled' => true,
            'connection_state' => 'connected', 'configuration_version' => 0,
        ]);
        ShippingProviderCredential::query()->create([
            'shipping_provider_id' => $provider->id, 'provider' => 'easyparcel', 'environment' => 'sandbox',
            'credentials' => ['access_token' => 'token'],
        ]);

        $this->actingAs($admin)
            ->deleteJson('/admin/api/shipping/providers/easyparcel')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified');

        $this->assertDatabaseCount('shipping_provider_credentials', 0);
    }

    public function test_retired_browser_token_exchange_endpoint_is_not_routable_and_cannot_mutate_connection_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'unbound-browser-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ])->assertNotFound();

        $this->assertDatabaseCount('shipping_providers', 0);
        $this->assertDatabaseCount('shipping_provider_credentials', 0);
    }

    public function test_provider_cancellation_returns_only_the_safe_cancelled_oauth_result(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/shipping/easyparcel/callback?error=access_denied&error_description=private-provider-detail');

        $response->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection', [
                'state' => 'cancelled',
                'message' => 'EasyParcel authorization was not completed.',
            ]);
        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
        $this->assertDatabaseCount('shipping_providers', 0);
    }

    public function test_cancelled_issued_state_is_terminal_and_cannot_later_complete_a_connection(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get('/admin/shipping/easyparcel/callback?error=access_denied&state='.$query['state'])
            ->assertSessionHas('easyparcelConnection.state', 'cancelled');
        $this->get('/admin/shipping/easyparcel/callback?code=late-code&state='.$query['state'])
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        $this->assertSame(1, \App\Models\ShippingProviderAuthorizationTransaction::query()->whereNotNull('consumed_at')->count());
        $this->assertDatabaseCount('shipping_provider_credentials', 0);
    }

    public function test_oauth_service_exception_is_shielded_by_a_safe_failed_redirect(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        Event::listen('eloquent.creating: '.ShippingProvider::class, function (): void {
            throw new RuntimeException('provider-private-detail');
        });
        try {
            $response = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
            $response->assertRedirect('/admin/shipping')->assertSessionHas('easyparcelConnection.state', 'failed');
            $this->assertStringNotContainsString('provider-private-detail', $response->getContent());
        } finally {
            Event::forget('eloquent.creating: '.ShippingProvider::class);
        }
    }

    public function test_callback_diagnostics_record_only_the_received_parameter_names(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        Log::spy();

        $this->actingAs($admin)->get('/admin/shipping/easyparcel/callback?authorization_code=private-code&state=private-state');

        Log::shouldHaveReceived('info')->once()->with(
            'EasyParcel OAuth callback received',
            [
                'parameter_names' => ['authorization_code', 'state'],
                'has_authorization_code' => true,
                'has_code' => false,
            ],
        );
    }

    public function test_callback_completion_exception_logs_only_its_safe_classification(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $sessionBinding = 'safe-test-session-binding';
        $state = app(\App\Services\Shipping\EasyParcelOAuthAuthorizationService::class)->begin(
            $admin->id,
            $sessionBinding,
            'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        );
        $this->assertNotNull($state);
        Log::spy();
        Event::listen('eloquent.creating: '.ShippingProviderCredential::class, function (): void {
            throw new RuntimeException('provider-private-detail');
        });
        try {
            $this->actingAs($admin)->withSession(['easyparcel.oauth.session_binding' => $sessionBinding])
                ->get('/admin/shipping/easyparcel/callback?code=private-code&state='.$state)
                ->assertRedirect('/admin/shipping')
                ->assertSessionHas('easyparcelConnection.state', 'failed');
        } finally {
            Event::forget('eloquent.creating: '.ShippingProviderCredential::class);
        }

        Log::shouldHaveReceived('warning')->once()->with(
            'EasyParcel OAuth completion failed',
            ['exception_class' => RuntimeException::class],
        );
    }

    public function test_callback_accepts_easyparcel_wrapped_state_without_persisting_the_provider_envelope(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'provider-token',
                'expires_in' => 3600,
            ]),
            'https://api.easyparcel.com/open_api/2026-06/account/get_account_information' => Http::response([
                'status_code' => 200,
                'data' => ['account' => ['name' => 'Sandbox Account']],
            ]),
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $start = $this->actingAs($admin)->post('/admin/api/shipping/providers/easyparcel/authorize');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        $providerState = base64_encode(json_encode(['state' => $query['state']], JSON_THROW_ON_ERROR));

        $this->get('/admin/shipping/easyparcel/callback?code=provider-code&state='.urlencode($providerState))
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'connected');

        $this->assertDatabaseHas('shipping_providers', [
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'connection_state' => 'connected',
        ]);
    }
}
