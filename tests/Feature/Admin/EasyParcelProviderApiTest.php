<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EasyParcelProviderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_at_rest_and_browser_dtos_are_safe(): void
    {
        $response = $this->actingAs($this->superAdmin())->putJson(
            '/admin/api/shipping/providers/easyparcel',
            [
                'environment' => 'sandbox',
                'clientId' => 'client',
                'clientSecret' => 'secret',
                'enabled' => false,
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.provider', 'easyparcel')
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified')
            ->assertJsonMissing(['clientId' => 'client'])
            ->assertJsonMissing(['clientSecret' => 'secret']);

        $this->assertSafeResponse($response->getContent());

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertSame('client', $credential->credentials['client_id']);
        $this->assertSame('secret', $credential->credentials['client_secret']);

        $rawCredentials = DB::table('shipping_provider_credentials')->value('credentials');
        $this->assertIsString($rawCredentials);
        $this->assertStringNotContainsString('client', $rawCredentials);
        $this->assertStringNotContainsString('secret', $rawCredentials);

        $getResponse = $this->getJson('/admin/api/shipping/providers/easyparcel')->assertOk();
        $this->assertSafeResponse($getResponse->getContent());
    }

    public function test_only_super_admin_can_manage_easyparcel_provider_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->actingAs($admin)->getJson('/admin/api/shipping/providers/easyparcel')->assertForbidden();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'denied-client-id',
            'clientSecret' => 'denied-client-secret',
            'enabled' => false,
        ])->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'denied-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ])->assertForbidden();

        $this->assertDatabaseCount('shipping_providers', 0);
        $this->assertDatabaseCount('shipping_provider_credentials', 0);
    }

    public function test_guests_and_ordinary_users_cannot_read_provider_configuration(): void
    {
        $this->getJson('/admin/api/shipping/providers/easyparcel')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_super_admin' => false]))
            ->getJson('/admin/api/shipping/providers/easyparcel')
            ->assertUnauthorized();
    }

    public function test_only_sandbox_configuration_is_accepted_and_connection_inputs_are_required(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'production',
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'enabled' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('environment');

        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'enabled' => false,
        ])->assertOk();

        $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['authorizationCode', 'redirectUri']);
    }

    public function test_connection_rejects_an_https_redirect_uri_not_owned_by_the_server(): void
    {
        Http::fake();
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'redirect-client-id',
            'clientSecret' => 'redirect-client-secret',
            'enabled' => false,
        ])->assertOk();

        $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'redirect-one-time-code',
            'redirectUri' => 'https://unowned.example/callback',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('redirectUri');

        Http::assertNothingSent();
    }

    public function test_sensitive_provider_inputs_are_never_flashed_after_non_json_validation(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->from('/admin/settings/couriers')
            ->put('/admin/api/shipping/providers/easyparcel', [
                'environment' => 'production',
                'clientId' => 'non-flash-client-id',
                'clientSecret' => 'non-flash-client-secret',
                'enabled' => false,
            ])
            ->assertRedirect('/admin/settings/couriers')
            ->assertSessionHasInput('environment', 'production')
            ->assertSessionMissing('_old_input.clientId')
            ->assertSessionMissing('_old_input.clientSecret');

        $this->from('/admin/settings/couriers')
            ->post('/admin/api/shipping/providers/easyparcel/test-connection', [
                'authorizationCode' => 'non-flash-authorization-code',
                'redirectUri' => 'not-a-url',
            ])
            ->assertRedirect('/admin/settings/couriers')
            ->assertSessionMissing('_old_input.authorizationCode');
    }

    public function test_unexpected_configuration_exception_returns_a_fixed_response_without_sensitive_input(): void
    {
        config(['app.debug' => true]);
        Event::listen('eloquent.saving: '.ShippingProviderCredential::class, function (): void {
            throw new RuntimeException('unexpected-sensitive-value');
        });

        try {
            $response = $this->actingAs($this->superAdmin())->putJson('/admin/api/shipping/providers/easyparcel', [
                'environment' => 'sandbox',
                'clientId' => 'unexpected-client-id',
                'clientSecret' => 'unexpected-sensitive-value',
                'enabled' => false,
            ]);
        } finally {
            Event::forget('eloquent.saving: '.ShippingProviderCredential::class);
        }

        $response->assertInternalServerError()
            ->assertJsonPath('message', 'Provider configuration could not be saved.');
        $this->assertStringNotContainsString('unexpected-client-id', $response->getContent());
        $this->assertStringNotContainsString('unexpected-sensitive-value', $response->getContent());
    }

    public function test_unexpected_verification_exception_returns_a_fixed_response_without_authorization_code(): void
    {
        config(['app.debug' => true]);
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'unexpected-verification-client-id',
            'clientSecret' => 'unexpected-verification-client-secret',
            'enabled' => false,
        ])->assertOk();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'access_token' => 'unexpected-verification-access-token',
            ]),
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'wallet' => [['balance' => 1000.00, 'currency' => 'MYR']],
                    'free_credit_wallet' => [['balance' => 0, 'currency' => 'MYR']],
                ],
            ]),
        ]);
        Event::listen('eloquent.saving: '.ShippingProviderCredential::class, function (): void {
            throw new RuntimeException('unexpected-authorization-code-marker');
        });

        try {
            $response = $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
                'authorizationCode' => 'unexpected-authorization-code-marker',
                'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
            ]);
        } finally {
            Event::forget('eloquent.saving: '.ShippingProviderCredential::class);
        }

        $response->assertInternalServerError()
            ->assertJsonPath('message', 'Provider connection could not be verified.');
        $this->assertStringNotContainsString('unexpected-authorization-code-marker', $response->getContent());
    }

    public function test_unverified_credentials_cannot_be_enabled_by_the_configuration_write(): void
    {
        $this->actingAs($this->superAdmin())->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'unverified-client-id',
            'clientSecret' => 'unverified-client-secret',
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified');

        $this->assertDatabaseHas('shipping_providers', [
            'provider' => 'easyparcel',
            'environment' => 'sandbox',
            'enabled' => false,
            'connection_state' => 'unverified',
        ]);
    }

    public function test_documented_authorization_code_exchange_and_wallet_verification_enable_provider(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'verified-client-id',
            'clientSecret' => 'verified-client-secret',
            'enabled' => false,
        ])->assertOk();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'expires_at' => '2026-08-27T12:00:00.000Z',
                'access_token' => 'sandbox-access-token',
                'refresh_token' => 'sandbox-refresh-token',
                'refresh_token_expires_in' => 31557600,
                'refresh_token_expires_at' => '2027-08-27T12:00:00.000Z',
                'app' => [
                    'client_id' => 'verified-client-id',
                    'callbackUrls' => [],
                    'redirectUris' => ['https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'],
                ],
            ], 200),
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'wallet' => [['balance' => 1000.00, 'currency' => 'MYR']],
                    'free_credit_wallet' => [['balance' => 0, 'currency' => 'MYR']],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'one-time-authorization-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.connectionState', 'connected')
            ->assertJsonPath('data.configured', true);
        $this->assertSafeResponse($response->getContent());

        $provider = ShippingProvider::query()->sole();
        $this->assertTrue($provider->enabled);
        $this->assertNotNull($provider->last_verified_at);
        $this->assertNull($provider->last_error);

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertSame('sandbox-access-token', $credential->credentials['access_token']);
        $this->assertSame('sandbox-refresh-token', $credential->credentials['refresh_token']);

        $rawCredentials = DB::table('shipping_provider_credentials')->value('credentials');
        $this->assertStringNotContainsString('sandbox-access-token', $rawCredentials);
        $this->assertStringNotContainsString('sandbox-refresh-token', $rawCredentials);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.easyparcel.com/oauth/token'
                && $request->method() === 'POST'
                && $request['grant_type'] === 'authorization_code'
                && $request['redirect_uri'] === 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'
                && $request['code'] === 'one-time-authorization-code'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('verified-client-id:verified-client-secret'));
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.easyparcel.com/open_api/2026-06/wallet'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer sandbox-access-token'));
        Http::assertSentCount(2);
    }

    public function test_failed_verification_is_redacted_and_forces_provider_disabled(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'failed-client-id',
            'clientSecret' => 'failed-client-secret',
            'enabled' => false,
        ])->assertOk();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'provider-raw-secret-detail',
            ], 401),
        ]);

        $response = $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'rejected-authorization-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Connection verification failed.')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'failed')
            ->assertJsonPath('data.lastError', 'verification_failed');
        $this->assertStringNotContainsString('provider-raw-secret-detail', $response->getContent());
        $this->assertSafeResponse($response->getContent());

        $provider = ShippingProvider::query()->sole();
        $this->assertFalse($provider->enabled);
        $this->assertSame('failed', $provider->connection_state);

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertArrayNotHasKey('access_token', $credential->credentials);
        $this->assertArrayNotHasKey('refresh_token', $credential->credentials);
    }

    public function test_token_exchange_alone_cannot_enable_provider_when_wallet_capability_check_fails(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'wallet-check-client-id',
            'clientSecret' => 'wallet-check-client-secret',
            'enabled' => false,
        ])->assertOk();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'access_token' => 'wallet-check-access-token',
                'refresh_token' => 'wallet-check-refresh-token',
            ]),
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 401,
                'message' => 'wallet-provider-raw-error',
                'data' => null,
            ], 401),
        ]);

        $response = $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'wallet-check-one-time-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'failed');
        $this->assertStringNotContainsString('wallet-provider-raw-error', $response->getContent());

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertArrayNotHasKey('access_token', $credential->credentials);
        $this->assertArrayNotHasKey('refresh_token', $credential->credentials);
    }

    public function test_successful_verification_cannot_overwrite_credentials_changed_while_request_is_in_flight(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'original-client-id',
            'clientSecret' => 'original-client-secret',
            'enabled' => false,
        ])->assertOk();

        $configuration = app(ShippingProviderConfigurationService::class);
        Http::fake(function (Request $request) use ($configuration) {
            if ($request->url() === 'https://api.easyparcel.com/oauth/token') {
                $configuration->save('new-client-id', 'new-client-secret', false);

                return Http::response([
                    'token_type' => 'Bearer',
                    'expires_in' => 36000,
                    'access_token' => 'stale-access-token',
                    'refresh_token' => 'stale-refresh-token',
                ]);
            }

            return Http::response([
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'wallet' => [['balance' => 1000.00, 'currency' => 'MYR']],
                    'free_credit_wallet' => [['balance' => 0, 'currency' => 'MYR']],
                ],
            ]);
        });

        $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'stale-success-one-time-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ])->assertUnprocessable()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified');

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertSame('new-client-id', $credential->credentials['client_id']);
        $this->assertSame('new-client-secret', $credential->credentials['client_secret']);
        $this->assertArrayNotHasKey('access_token', $credential->credentials);
        $this->assertArrayNotHasKey('refresh_token', $credential->credentials);
    }

    public function test_failed_verification_cannot_mark_newer_credentials_failed(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'failure-original-client-id',
            'clientSecret' => 'failure-original-client-secret',
            'enabled' => false,
        ])->assertOk();

        $configuration = app(ShippingProviderConfigurationService::class);
        Http::fake(function (Request $request) use ($configuration) {
            if ($request->url() === 'https://api.easyparcel.com/oauth/token') {
                $configuration->save('failure-new-client-id', 'failure-new-client-secret', false);

                return Http::response(['error' => 'invalid_grant'], 401);
            }

            return Http::response([], 500);
        });

        $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'stale-failure-one-time-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ])->assertUnprocessable()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connectionState', 'unverified')
            ->assertJsonPath('data.lastError', null);

        $credential = ShippingProviderCredential::query()->sole();
        $this->assertSame('failure-new-client-id', $credential->credentials['client_id']);
        $this->assertSame('failure-new-client-secret', $credential->credentials['client_secret']);
    }

    public function test_resaving_unchanged_verified_credentials_preserves_the_server_token_cache(): void
    {
        $admin = $this->superAdmin();
        $credentials = [
            'environment' => 'sandbox',
            'clientId' => 'stable-client-id',
            'clientSecret' => 'stable-client-secret',
            'enabled' => false,
        ];
        $this->actingAs($admin)
            ->putJson('/admin/api/shipping/providers/easyparcel', $credentials)
            ->assertOk();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'access_token' => 'stable-access-token',
                'refresh_token' => 'stable-refresh-token',
                'refresh_token_expires_in' => 31557600,
            ]),
            'https://api.easyparcel.com/open_api/2026-06/wallet' => Http::response([
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'wallet' => [['balance' => 1000.00, 'currency' => 'MYR']],
                    'free_credit_wallet' => [['balance' => 0, 'currency' => 'MYR']],
                ],
            ]),
        ]);

        $this->postJson('/admin/api/shipping/providers/easyparcel/test-connection', [
            'authorizationCode' => 'stable-one-time-code',
            'redirectUri' => 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
        ])->assertOk();

        $credentials['enabled'] = true;
        $this->putJson('/admin/api/shipping/providers/easyparcel', $credentials)
            ->assertOk()
            ->assertJsonPath('data.connectionState', 'connected')
            ->assertJsonPath('data.enabled', true);

        $stored = ShippingProviderCredential::query()->sole()->credentials;
        $this->assertSame('stable-access-token', $stored['access_token'] ?? null);
        $this->assertSame('stable-refresh-token', $stored['refresh_token'] ?? null);
    }

    public function test_admin_bootstrap_exposes_only_provider_endpoint_urls(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/settings/couriers')
            ->assertOk()
            ->assertSee('easyParcelProviderUrl', false)
            ->assertSee('easyParcelProviderTestConnectionUrl', false)
            ->assertDontSee('clientSecret', false)
            ->assertDontSee('access_token', false);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function assertSafeResponse(string $content): void
    {
        foreach ([
            'clientId',
            'clientSecret',
            'client_id',
            'client_secret',
            'accessToken',
            'access_token',
            'refreshToken',
            'refresh_token',
            'authorizationCode',
        ] as $forbiddenKey) {
            $this->assertStringNotContainsString($forbiddenKey, $content);
        }
    }
}
