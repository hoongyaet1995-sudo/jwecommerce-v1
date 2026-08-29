<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderAuthorizationTransaction;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use App\Services\Shipping\EasyParcelOAuthAuthorizationService;
use App\Services\Shipping\EasyParcelOpenApiClient;
use App\Services\Shipping\EasyParcelProviderSessionService;
use App\Services\Shipping\EasyParcelTrackingException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\UsesIsolatedDatabaseMigrations;
use Tests\TestCase;

class EasyParcelPlatformOauthTest extends TestCase
{
    use UsesIsolatedDatabaseMigrations;

    public function test_expiring_access_token_refreshes_once_for_duplicate_session_reads_and_persists_rotation(): void
    {
        $provider = $this->connectedProviderWithTokens();
        $transactionLevels = [];
        Http::fake([
            EasyParcelOpenApiClient::TOKEN_URL => function (Request $request) use (&$transactionLevels) {
                $transactionLevels[] = DB::transactionLevel();

                return Http::response([
                    'token_type' => 'Bearer',
                    'access_token' => 'rotated-access-token',
                    'expires_in' => 3600,
                    'refresh_token' => 'rotated-refresh-token',
                    'refresh_token_expires_in' => 7200,
                ]);
            },
        ]);

        $sessions = app(EasyParcelProviderSessionService::class);
        $first = $sessions->connected();
        $second = $sessions->connected();

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('rotated-access-token', $first->accessToken);
        self::assertSame('rotated-access-token', $second->accessToken);
        self::assertSame([0], $transactionLevels);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === EasyParcelOpenApiClient::TOKEN_URL
            && $request->method() === 'POST'
            && $request->hasHeader(
                'Authorization',
                'Basic '.base64_encode('platform-client:platform-secret'),
            )
            && $request->data() === [
                'grant_type' => 'refresh_token',
                'refresh_token' => 'current-refresh-token',
            ]
        );

        $stored = $provider->credential()->sole()->credentials;
        self::assertSame('rotated-access-token', $stored['access_token']);
        self::assertSame('rotated-refresh-token', $stored['refresh_token']);
        self::assertNull($provider->fresh()->last_error);
        $raw = (string) DB::table('shipping_provider_credentials')->value('credentials');
        self::assertStringNotContainsString('rotated-access-token', $raw);
        self::assertStringNotContainsString('rotated-refresh-token', $raw);
        self::assertStringNotContainsString('platform-secret', $raw);
    }

    public function test_refresh_preserves_the_current_refresh_token_when_the_provider_omits_rotation(): void
    {
        $provider = $this->connectedProviderWithTokens();
        $oldRefreshExpiry = $provider->credential()->sole()->credentials['refresh_token_expires_at'];
        Http::fake([
            EasyParcelOpenApiClient::TOKEN_URL => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'fresh-access-token',
                'expires_in' => 3600,
            ]),
        ]);

        $session = app(EasyParcelProviderSessionService::class)->connected();

        self::assertNotNull($session);
        self::assertSame('fresh-access-token', $session->accessToken);
        $stored = $provider->credential()->sole()->credentials;
        self::assertSame('current-refresh-token', $stored['refresh_token']);
        self::assertSame($oldRefreshExpiry, $stored['refresh_token_expires_at']);
    }

    public function test_refresh_result_cannot_overwrite_a_reconfigured_provider_snapshot(): void
    {
        $provider = $this->connectedProviderWithTokens();
        Http::fake([
            EasyParcelOpenApiClient::TOKEN_URL => function () use ($provider) {
                $provider->forceFill(['configuration_version' => 8])->save();
                $provider->credential()->sole()->forceFill(['credentials' => [
                    'token_type' => 'Bearer',
                    'access_token' => 'replacement-access-token',
                    'access_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
                    'refresh_token' => 'replacement-refresh-token',
                    'refresh_token_expires_at' => now()->addHours(2)->utc()->toIso8601String(),
                ]])->save();

                return Http::response([
                    'token_type' => 'Bearer',
                    'access_token' => 'stale-rotated-access-token',
                    'expires_in' => 3600,
                    'refresh_token' => 'stale-rotated-refresh-token',
                    'refresh_token_expires_in' => 7200,
                ]);
            },
        ]);

        try {
            app(EasyParcelProviderSessionService::class)->connected();
            self::fail('A stale refresh response must be rejected.');
        } catch (EasyParcelTrackingException $exception) {
            self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
        }

        $stored = $provider->credential()->sole()->credentials;
        self::assertSame('replacement-access-token', $stored['access_token']);
        self::assertSame('replacement-refresh-token', $stored['refresh_token']);
        self::assertSame(8, $provider->fresh()->configuration_version);
    }

    public function test_refresh_rejects_an_already_expired_access_token_without_persisting_it(): void
    {
        $provider = $this->connectedProviderWithTokens();
        Http::fake([
            EasyParcelOpenApiClient::TOKEN_URL => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'already-expired-access-token',
                'expires_at' => now()->subMinute()->utc()->toIso8601String(),
                'refresh_token' => 'already-expired-rotated-refresh-token',
                'refresh_token_expires_in' => 7200,
            ]),
        ]);

        try {
            app(EasyParcelProviderSessionService::class)->connected();
            self::fail('An already-expired refreshed access token must not be persisted.');
        } catch (EasyParcelTrackingException $exception) {
            self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
        }

        self::assertSame('token_refresh_failed', $provider->fresh()->last_error);
        $stored = $provider->credential()->sole()->credentials;
        self::assertSame('expiring-access-token', $stored['access_token']);
        self::assertSame('current-refresh-token', $stored['refresh_token']);
    }

    public function test_expired_refresh_token_fails_safely_without_an_exchange_or_secret_exposure(): void
    {
        $provider = $this->connectedProviderWithTokens([
            'refresh_token_expires_at' => now()->subSecond()->utc()->toIso8601String(),
        ]);
        Http::fake();

        try {
            app(EasyParcelProviderSessionService::class)->connected();
            self::fail('An expired refresh token must not be exchanged.');
        } catch (EasyParcelTrackingException $exception) {
            self::assertSame('EasyParcel tracking synchronization failed.', $exception->getMessage());
            self::assertStringNotContainsString('current-refresh-token', $exception->getMessage());
            self::assertStringNotContainsString('platform-secret', $exception->getMessage());
        }

        Http::assertNothingSent();
        self::assertSame('token_refresh_failed', $provider->fresh()->last_error);
        self::assertSame(
            'expiring-access-token',
            $provider->credential()->sole()->credentials['access_token'],
        );
    }

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

        $this->assertSame(1, ShippingProviderAuthorizationTransaction::query()->whereNotNull('consumed_at')->count());
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
        $state = app(EasyParcelOAuthAuthorizationService::class)->begin(
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

    public function test_callback_logs_a_safe_reason_when_its_session_binding_is_missing(): void
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $state = app(EasyParcelOAuthAuthorizationService::class)->begin(
            $admin->id,
            'binding-that-is-not-in-the-callback-session',
            'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        );
        $this->assertNotNull($state);
        Log::spy();

        $this->actingAs($admin)->withSession([])
            ->get('/admin/shipping/easyparcel/callback?code=provider-code&state='.$state)
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        Log::shouldHaveReceived('warning')->once()->with(
            'EasyParcel OAuth callback rejected',
            ['reason' => 'session_binding_missing'],
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
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 200,
                'data' => ['wallet' => []],
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

    /** @param array<string, mixed> $overrides */
    private function connectedProviderWithTokens(array $overrides = []): ShippingProvider
    {
        config()->set('services.easyparcel.client_id', 'platform-client');
        config()->set('services.easyparcel.client_secret', 'platform-secret');
        $provider = ShippingProvider::query()->create([
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => true,
            'connection_state' => 'connected',
            'configuration_version' => 7,
            'last_verified_at' => now(),
        ]);
        ShippingProviderCredential::query()->create([
            'shipping_provider_id' => $provider->id,
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'credentials' => array_merge([
                'token_type' => 'Bearer',
                'access_token' => 'expiring-access-token',
                'access_token_expires_at' => now()->addSeconds(30)->utc()->toIso8601String(),
                'refresh_token' => 'current-refresh-token',
                'refresh_token_expires_at' => now()->addHour()->utc()->toIso8601String(),
            ], $overrides),
        ]);

        return $provider;
    }
}
