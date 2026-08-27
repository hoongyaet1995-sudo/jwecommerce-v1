<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderAuthorizationTransaction;
use App\Models\ShippingProviderCredential;
use App\Models\User;
use App\Services\Shipping\EasyParcelOAuthAuthorizationService;
use App\Services\Shipping\ShippingProviderConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
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
        $this->postJson('/admin/api/shipping/providers/easyparcel/authorize')->assertUnauthorized();

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
        $this->actingAs($admin)
            ->postJson('/admin/api/shipping/providers/easyparcel/authorize')
            ->assertForbidden();

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

    public function test_super_admin_starts_a_short_lived_session_bound_authorization_transaction(): void
    {
        $now = Carbon::parse('2026-08-27T12:00:00+08:00');
        $this->travelTo($now);
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'start');
        Http::fake();

        $response = $this->post('/admin/api/shipping/providers/easyparcel/authorize');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame('api.easyparcel.com', parse_url($location, PHP_URL_HOST));
        $this->assertSame('/oauth/login', parse_url($location, PHP_URL_PATH));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame([
            'client_id',
            'redirect_uri',
            'state',
        ], array_keys($query));
        $this->assertSame(
            'https://api.easyparcel.com/oauth/login'
                .'?client_id=start-client-id'
                .'&redirect_uri=https%3A%2F%2Fecom.jwaistudio.online%2Fadmin%2Fshipping%2Feasyparcel%2Fcallback'
                .'&state='.$query['state'],
            $location,
        );
        $this->assertSame('start-client-id', $query['client_id']);
        $this->assertSame(
            'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
            $query['redirect_uri'],
        );
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $query['state']);

        $transaction = (array) DB::table('shipping_provider_authorization_transactions')->sole();
        $this->assertSame(hash('sha256', $query['state']), $transaction['state_hash']);
        $this->assertSame($admin->id, $transaction['user_id']);
        $this->assertSame(ShippingProvider::query()->sole()->configuration_version, $transaction['configuration_version']);
        $this->assertNull($transaction['consumed_at']);
        $this->assertTrue(Carbon::parse($transaction['expires_at'])->equalTo($now->addMinutes(10)));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $transaction['session_fingerprint']);
        $sessionBinding = session('easyparcel.oauth.session_binding');
        $this->assertIsString($sessionBinding);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $sessionBinding);
        $this->assertNotSame($sessionBinding, $transaction['session_fingerprint']);
        $this->assertStringNotContainsString($sessionBinding, json_encode($transaction, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($query['state'], json_encode($transaction, JSON_THROW_ON_ERROR));
        $this->assertNull(session('easyparcel.oauth.authorization'));
        $browserAndSessionSurface = $location
            .$response->getContent()
            .json_encode(session()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('start-client-secret', $browserAndSessionSurface);
        $this->assertStringNotContainsString($query['state'], json_encode(session()->all(), JSON_THROW_ON_ERROR));
        Http::assertNothingSent();
    }

    public function test_oauth_callback_rejects_unauthenticated_and_non_super_admin_sessions(): void
    {
        $owner = $this->superAdmin();
        $this->configureProvider($owner, 'unauthorized-callback');
        $state = $this->startAuthorizationState();
        $before = $this->providerStorageSnapshot();
        auth()->logout();
        Http::fake();

        $guestResponse = $this->get(
            '/admin/shipping/easyparcel/callback?code=guest-code&state='.$state
                .'&error=guest-error&error_description=guest-provider-detail',
        );
        $guestResponse->assertRedirect('/admin/login');
        $guestSurface = $guestResponse->getContent()
            .(string) $guestResponse->headers->get('Location')
            .json_encode(session()->all(), JSON_THROW_ON_ERROR);
        foreach (['guest-code', $state, 'guest-error', 'guest-provider-detail'] as $marker) {
            $this->assertStringNotContainsString($marker, $guestSurface);
        }
        $this->get('/admin/login')->assertOk();

        $adminResponse = $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]))
            ->get(
                '/admin/shipping/easyparcel/callback?code=ordinary-admin-code&state='.$state
                    .'&error=ordinary-error&error_description=ordinary-provider-detail',
            );
        $adminResponse->assertRedirect('/admin/shipping/easyparcel/callback/denied');
        $adminSurface = $adminResponse->getContent()
            .(string) $adminResponse->headers->get('Location')
            .json_encode(session()->all(), JSON_THROW_ON_ERROR);
        foreach (['ordinary-admin-code', $state, 'ordinary-error', 'ordinary-provider-detail'] as $marker) {
            $this->assertStringNotContainsString($marker, $adminSurface);
        }
        $this->get('/admin/shipping/easyparcel/callback/denied')->assertForbidden();

        $ordinaryUserResponse = $this->actingAs(User::factory()->create([
            'is_admin' => false,
            'is_super_admin' => false,
        ]))->get('/admin/shipping/easyparcel/callback?code=ordinary-user-code&state='.$state);
        $ordinaryUserResponse->assertRedirect('/admin/login');
        $this->assertStringNotContainsString(
            'ordinary-user-code',
            $ordinaryUserResponse->getContent()
                .(string) $ordinaryUserResponse->headers->get('Location')
                .json_encode(session()->all(), JSON_THROW_ON_ERROR),
        );
        $this->get('/admin/login')->assertOk();

        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
        $this->assertNull(DB::table('shipping_provider_authorization_transactions')->value('consumed_at'));
    }

    public function test_super_admin_oauth_callback_verifies_server_side_and_redirects_without_leaking_code(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'callback');
        $state = $this->startAuthorizationState();

        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'access_token' => 'callback-access-token',
                'refresh_token' => 'callback-refresh-token',
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

        $authorizationCode = 'callback-one-time-code-marker';
        $response = $this->get('/admin/shipping/easyparcel/callback?code='.$authorizationCode.'&state='.$state);

        $response->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection', [
                'state' => 'connected',
                'message' => 'EasyParcel connection verified.',
            ])
            ->assertSessionMissing('_old_input.code');
        $this->assertStringNotContainsString($authorizationCode, (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString($authorizationCode, $response->getContent());
        $this->assertStringNotContainsString(
            $authorizationCode,
            json_encode(session()->all(), JSON_THROW_ON_ERROR),
        );
        $response->assertSessionMissing('easyparcel.oauth.authorization');
        $authorizationTransaction = (array) DB::table('shipping_provider_authorization_transactions')->sole();
        $this->assertNotNull($authorizationTransaction['consumed_at']);
        $this->assertStringNotContainsString(
            $authorizationCode,
            json_encode($authorizationTransaction, JSON_THROW_ON_ERROR),
        );
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);

        Http::assertSent(function (Request $request) use ($authorizationCode): bool {
            return $request->url() === 'https://api.easyparcel.com/oauth/token'
                && $request['redirect_uri'] === 'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback'
                && $request['code'] === $authorizationCode;
        });
        Http::assertSentCount(2);
    }

    public function test_callback_missing_state_does_not_call_provider_or_mutate_configuration(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'missing-state');
        $this->startAuthorizationState();
        $before = $this->providerStorageSnapshot();
        Http::fake();

        $response = $this->get('/admin/shipping/easyparcel/callback?code=missing-state-code');

        $this->assertSafeCallbackFailure($response);
        $response->assertSessionMissing('easyparcel.oauth.authorization');
        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
    }

    public function test_callback_mismatched_state_does_not_call_provider_or_mutate_configuration(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'mismatched-state');
        $state = $this->startAuthorizationState();
        $mismatchedState = ($state[0] === 'a' ? 'b' : 'a').substr($state, 1);
        $before = $this->providerStorageSnapshot();
        Http::fake();

        $response = $this->get(
            '/admin/shipping/easyparcel/callback?code=mismatched-state-code&state='.$mismatchedState,
        );

        $this->assertSafeCallbackFailure($response);
        $response->assertSessionMissing('easyparcel.oauth.authorization');
        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
    }

    public function test_callback_expired_state_does_not_call_provider_or_mutate_configuration(): void
    {
        $this->travelTo(Carbon::parse('2026-08-27T12:00:00+08:00'));
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'expired-state');
        $state = $this->startAuthorizationState();
        $before = $this->providerStorageSnapshot();
        $this->travel(11)->minutes();
        Http::fake();

        $response = $this->get('/admin/shipping/easyparcel/callback?code=expired-state-code&state='.$state);

        $this->assertSafeCallbackFailure($response);
        $response->assertSessionMissing('easyparcel.oauth.authorization');
        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
    }

    public function test_callback_state_is_bound_to_the_initiating_user_and_session(): void
    {
        $owner = $this->superAdmin();
        $this->configureProvider($owner, 'binding');
        $state = $this->startAuthorizationState();
        $before = $this->providerStorageSnapshot();
        Http::fake();

        $otherSuperAdmin = $this->superAdmin();
        $this->actingAs($otherSuperAdmin)
            ->get('/admin/shipping/easyparcel/callback?code=other-user-code&state='.$state)
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        $this->assertNull(DB::table('shipping_provider_authorization_transactions')->value('consumed_at'));
        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());

        $this->actingAs($owner);
        session()->put('easyparcel.oauth.session_binding', str_repeat('f', 64));
        $this->get('/admin/shipping/easyparcel/callback?code=other-session-code&state='.$state)
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        $this->assertNull(DB::table('shipping_provider_authorization_transactions')->value('consumed_at'));
        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
    }

    public function test_configuration_change_invalidates_pending_callback_before_token_exchange(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'changed-while-authorizing');
        $state = $this->startAuthorizationState();

        $this->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => 'changed-while-authorizing-client-id',
            'clientSecret' => 'changed-while-authorizing-client-secret',
            'enabled' => false,
        ])->assertOk();
        $before = $this->providerStorageSnapshot();
        Http::fake();

        $this->get('/admin/shipping/easyparcel/callback?code=stale-config-code&state='.$state)
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'failed');

        Http::assertNothingSent();
        $this->assertSame($before, $this->providerStorageSnapshot());
    }

    public function test_valid_callback_state_is_one_time_and_replay_cannot_disable_connected_provider(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'replay');
        $state = $this->startAuthorizationState();
        $authorizationCode = 'replay-one-time-code';
        $this->fakeSuccessfulVerification('replay');

        $this->get('/admin/shipping/easyparcel/callback?code='.$authorizationCode.'&state='.$state)
            ->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection.state', 'connected')
            ->assertSessionMissing('easyparcel.oauth.authorization');

        $connected = $this->providerStorageSnapshot();
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);
        Http::fake();

        $replay = $this->get('/admin/shipping/easyparcel/callback?code='.$authorizationCode.'&state='.$state);

        $this->assertSafeCallbackFailure($replay);
        Http::assertNothingSent();
        $this->assertSame($connected, $this->providerStorageSnapshot());
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);
        $this->assertTrue(ShippingProvider::query()->sole()->enabled);
    }

    public function test_reentrant_atomic_consumption_has_one_winner_before_any_token_exchange(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'reentrant');
        $state = $this->startAuthorizationState();
        $sessionBinding = session('easyparcel.oauth.session_binding');
        $this->assertIsString($sessionBinding);

        $authorization = app(EasyParcelOAuthAuthorizationService::class);
        $reentered = false;
        $reentrantResult = null;

        Event::listen(
            'eloquent.retrieved: '.ShippingProviderAuthorizationTransaction::class,
            function () use (
                &$reentered,
                &$reentrantResult,
                $authorization,
                $admin,
                $sessionBinding,
                $state,
            ): void {
                if ($reentered) {
                    return;
                }

                $reentered = true;
                $reentrantResult = $authorization->consume(
                    $admin->id,
                    $sessionBinding,
                    $state,
                );
            },
        );

        try {
            $outerResult = $authorization->consume(
                $admin->id,
                $sessionBinding,
                $state,
            );
        } finally {
            Event::forget('eloquent.retrieved: '.ShippingProviderAuthorizationTransaction::class);
        }

        $this->assertTrue($reentered);
        $winners = array_values(array_filter(
            [$outerResult, $reentrantResult],
            static fn (mixed $result): bool => is_int($result),
        ));
        $this->assertCount(1, $winners);
        $this->assertNotNull(ShippingProviderAuthorizationTransaction::query()->sole()->consumed_at);
        Http::assertNothingSent();

        $this->fakeSuccessfulVerification('reentrant');
        $result = app(ShippingProviderConfigurationService::class)->testConnection(
            'reentrant-one-time-code',
            'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
            $winners[0],
        );

        $this->assertTrue($result['verified']);
        Http::assertSentCount(2);
        $this->assertCount(1, Http::recorded(
            static fn (Request $request): bool => $request->url() === 'https://api.easyparcel.com/oauth/token',
        ));
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);
    }

    public function test_reentrant_callback_completion_sends_one_token_exchange(): void
    {
        $admin = $this->superAdmin();
        $this->configureProvider($admin, 'reentrant-callback');
        $state = $this->startAuthorizationState();
        $sessionBinding = session('easyparcel.oauth.session_binding');
        $this->assertIsString($sessionBinding);
        $this->fakeSuccessfulVerification('reentrant-callback');

        $authorization = app(EasyParcelOAuthAuthorizationService::class);
        $reentered = false;
        $reentrantResult = null;
        $complete = function () use ($authorization, $admin, $sessionBinding, $state): bool {
            return $authorization->complete(
                $admin->id,
                $sessionBinding,
                $state,
                'reentrant-callback-one-time-code',
                'https://ecom.jwaistudio.online/admin/shipping/easyparcel/callback',
            );
        };

        Event::listen(
            'eloquent.retrieved: '.ShippingProviderAuthorizationTransaction::class,
            function () use (&$reentered, &$reentrantResult, $complete): void {
                if ($reentered) {
                    return;
                }

                $reentered = true;
                $reentrantResult = $complete();
            },
        );

        try {
            $outerResult = $complete();
        } finally {
            Event::forget('eloquent.retrieved: '.ShippingProviderAuthorizationTransaction::class);
        }

        $this->assertTrue($reentered);
        $this->assertCount(1, array_filter(
            [$outerResult, $reentrantResult],
            static fn (mixed $result): bool => $result === true,
        ));
        $this->assertCount(1, Http::recorded(
            static fn (Request $request): bool => $request->url() === 'https://api.easyparcel.com/oauth/token',
        ));
        Http::assertSentCount(2);
        $this->assertSame('connected', ShippingProvider::query()->sole()->connection_state);
        $this->assertTrue(ShippingProvider::query()->sole()->enabled);
    }

    public function test_oauth_callback_failure_redirects_with_only_a_bounded_safe_result(): void
    {
        Http::fake();
        $rawProviderMarker = 'raw-provider-callback-marker';

        $response = $this->actingAs($this->superAdmin())
            ->get('/admin/shipping/easyparcel/callback?error=access_denied&error_description='.$rawProviderMarker);

        $response->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection', [
                'state' => 'failed',
                'message' => 'EasyParcel connection could not be verified.',
            ]);

        $safeBrowserSurface = $response->getContent()
            .(string) $response->headers->get('Location')
            .json_encode(session()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($rawProviderMarker, $safeBrowserSurface);
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
        $configurationVersion = ShippingProvider::query()->sole()->configuration_version;

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
        $this->assertSame($configurationVersion + 1, $provider->configuration_version);
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
            ->assertSee('easyParcelProviderAuthorizeUrl', false)
            ->assertSee('easyParcelProviderTestConnectionUrl', false)
            ->assertDontSee('clientSecret', false)
            ->assertDontSee('access_token', false);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    private function configureProvider(User $admin, string $prefix): void
    {
        $this->actingAs($admin)->putJson('/admin/api/shipping/providers/easyparcel', [
            'environment' => 'sandbox',
            'clientId' => $prefix.'-client-id',
            'clientSecret' => $prefix.'-client-secret',
            'enabled' => false,
        ])->assertOk();
    }

    private function startAuthorizationState(): string
    {
        $response = $this->post('/admin/api/shipping/providers/easyparcel/authorize');
        $response->assertRedirect();
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertIsString($query['state'] ?? null);

        return $query['state'];
    }

    private function fakeSuccessfulVerification(string $prefix): void
    {
        Http::fake([
            'https://api.easyparcel.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'access_token' => $prefix.'-access-token',
                'refresh_token' => $prefix.'-refresh-token',
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
    }

    /** @return array{provider: array<string, mixed>, credentials: string} */
    private function providerStorageSnapshot(): array
    {
        return [
            'provider' => (array) DB::table('shipping_providers')->first(),
            'credentials' => (string) DB::table('shipping_provider_credentials')->value('credentials'),
        ];
    }

    private function assertSafeCallbackFailure(TestResponse $response): void
    {
        $response->assertRedirect('/admin/shipping')
            ->assertSessionHas('easyparcelConnection', [
                'state' => 'failed',
                'message' => 'EasyParcel connection could not be verified.',
            ]);
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
