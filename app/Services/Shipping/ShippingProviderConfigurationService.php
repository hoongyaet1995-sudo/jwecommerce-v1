<?php

namespace App\Services\Shipping;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use Illuminate\Support\Facades\DB;

final class ShippingProviderConfigurationService
{
    public const PROVIDER = 'easyparcel';

    public const SANDBOX_ENVIRONMENT = 'sandbox';

    public const PRODUCTION_ENVIRONMENT = 'production';

    /** @deprecated Use operationalEnvironment() or an explicit environment. */
    public const ENVIRONMENT = self::SANDBOX_ENVIRONMENT;

    public function __construct(private readonly EasyParcelOpenApiClient $client) {}

    public static function operationalEnvironment(): string
    {
        $environment = config('services.easyparcel.operating_environment', self::SANDBOX_ENVIRONMENT);

        return is_string($environment) && self::isSupportedEnvironment($environment)
            ? $environment
            : self::SANDBOX_ENVIRONMENT;
    }

    public static function isSupportedEnvironment(string $environment): bool
    {
        return in_array($environment, [self::SANDBOX_ENVIRONMENT, self::PRODUCTION_ENVIRONMENT], true);
    }

    /** @return array<string, bool|string|null> */
    public function status(?string $requestedEnvironment = null): array
    {
        $environment = is_string($requestedEnvironment) && self::isSupportedEnvironment($requestedEnvironment)
            ? $requestedEnvironment
            : self::operationalEnvironment();

        return $this->dto($this->findProvider($environment), $environment);
    }

    /** @return array<string, bool|string|null> */
    public function disconnect(string $environment = self::SANDBOX_ENVIRONMENT): array
    {
        if (! self::isSupportedEnvironment($environment)) {
            return $this->status();
        }

        $provider = DB::transaction(function () use ($environment): ShippingProvider {
            $provider = $this->providerForUpdate($environment);
            ShippingProviderCredential::query()->where('shipping_provider_id', $provider->id)->delete();
            $provider->forceFill([
                'enabled' => false,
                'connection_state' => 'unverified',
                'configuration_version' => $provider->configuration_version + 1,
                'last_verified_at' => null,
                'last_error' => null,
            ])->save();

            return $provider->fresh();
        });

        return $this->dto($provider, $environment);
    }

    /** @return array{client_id: string, client_secret: string}|null */
    public function platformCredentials(string $environment = self::SANDBOX_ENVIRONMENT): ?array
    {
        if (! self::isSupportedEnvironment($environment)
            || ! $this->authorizationEnabled($environment)
            || ! $this->hasRequiredOAuthEndpoints($environment)) {
            return null;
        }

        $clientId = config('services.easyparcel.client_id');
        $clientSecret = config('services.easyparcel.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        return ['client_id' => $clientId, 'client_secret' => $clientSecret];
    }

    /** @return array<string, bool|string|null> */
    public function save(string $clientId, string $clientSecret, bool $enabled): array
    {
        $environment = self::operationalEnvironment();
        $provider = DB::transaction(function () use ($clientId, $clientSecret, $enabled, $environment): ShippingProvider {
            $createdProvider = ShippingProvider::query()->firstOrCreate(
                ['provider' => self::PROVIDER, 'environment' => $environment],
                ['enabled' => false, 'connection_state' => 'unverified', 'configuration_version' => 0],
            );
            $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($createdProvider->id);

            $credential = ShippingProviderCredential::query()->where([
                'provider' => self::PROVIDER,
                'environment' => $environment,
            ])->lockForUpdate()->first();
            $stored = $credential?->credentials ?? [];
            $credentialsChanged = ! hash_equals((string) ($stored['client_id'] ?? ''), $clientId)
                || ! hash_equals((string) ($stored['client_secret'] ?? ''), $clientSecret);

            ShippingProviderCredential::query()->updateOrCreate(
                ['provider' => self::PROVIDER, 'environment' => $environment],
                [
                    'shipping_provider_id' => $provider->id,
                    'credentials' => $credentialsChanged
                        ? ['client_id' => $clientId, 'client_secret' => $clientSecret]
                        : $stored,
                ],
            );

            if ($credentialsChanged) {
                $provider->forceFill([
                    'enabled' => false,
                    'connection_state' => 'unverified',
                    'configuration_version' => $provider->configuration_version + 1,
                    'last_verified_at' => null,
                    'last_error' => null,
                ])->save();
            } else {
                $provider->forceFill([
                    'enabled' => $enabled && $provider->connection_state === 'connected',
                    'configuration_version' => $provider->configuration_version + 1,
                ])->save();
            }

            return $provider->fresh();
        });

        return $this->dto($provider, $environment);
    }

    /** @param array<string, string|null> $senderProfile */
    public function saveSenderProfile(string $environment, array $senderProfile): ?array
    {
        if (! self::isSupportedEnvironment($environment)) {
            return null;
        }

        return DB::transaction(function () use ($environment, $senderProfile): ?array {
            $provider = $this->findProvider($environment);
            if ($provider === null || $provider->connection_state !== 'connected') {
                return null;
            }

            $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($provider->id);
            if ($provider->connection_state !== 'connected') {
                return null;
            }
            $credential = ShippingProviderCredential::query()
                ->where('shipping_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();
            if ($credential === null) {
                return null;
            }

            $credentials = $credential->credentials ?? [];
            $credentials['sender_profile'] = array_filter(
                $senderProfile,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            );
            $credential->forceFill(['credentials' => $credentials])->save();
            $provider->forceFill([
                'configuration_version' => $provider->configuration_version + 1,
            ])->save();

            return $this->dtoWithCredential($provider, $credential, $environment);
        });
    }

    /**
     * @return array{
     *     verified: bool,
     *     status: array<string, bool|string|null>
     * }
     */
    public function testConnection(
        string $authorizationCode,
        string $redirectUri,
        string $environment,
        ?int $expectedConfigurationVersion = null,
        ?string $state = null,
    ): array {
        if (! self::isSupportedEnvironment($environment)) {
            return ['verified' => false, 'status' => $this->status()];
        }

        $snapshot = $this->verificationSnapshot($environment, $expectedConfigurationVersion);
        $platformCredentials = $this->platformCredentials($environment);

        if ($snapshot === null) {
            return ['verified' => false, 'status' => $this->status()];
        }

        if ($platformCredentials === null) {
            return ['verified' => false, 'status' => $this->applyFailure($snapshot, $environment)];
        }

        try {
            $tokens = $this->client->verifyAuthorizationCode($platformCredentials, $authorizationCode, $redirectUri, $state, $environment);
        } catch (EasyParcelVerificationException) {
            return ['verified' => false, 'status' => $this->applyFailure($snapshot, $environment)];
        }

        return $this->applySuccess($snapshot, $tokens, $environment);
    }

    /**
     * @param  array{
     *     provider_id: int,
     *     credential_id: int,
     *     configuration_version: int,
     *     credential_fingerprint: string,
     *     credentials: array<string, mixed>
     * }  $snapshot
     * @param  array<string, int|string|null>  $tokens
     * @return array{verified: bool, status: array<string, bool|string|null>}
     */
    private function applySuccess(array $snapshot, array $tokens, string $environment): array
    {
        return DB::transaction(function () use ($snapshot, $tokens, $environment): array {
            [$provider, $credential] = $this->lockSnapshotRows($snapshot);

            if (! $this->snapshotStillCurrent($provider, $credential, $snapshot)) {
                return ['verified' => false, 'status' => $this->dtoWithCredential($provider, $credential, $environment)];
            }

            $stored = $credential->credentials ?? [];
            if (is_array($stored['sender_profile'] ?? null)) {
                $tokens['sender_profile'] = $stored['sender_profile'];
            }
            $credential->forceFill(['credentials' => $tokens])->save();

            $provider->forceFill([
                'enabled' => true,
                'connection_state' => 'connected',
                'configuration_version' => $provider->configuration_version + 1,
                'public_metadata' => ['apiVersion' => EasyParcelOpenApiClient::API_VERSION],
                'last_verified_at' => now(),
                'last_error' => null,
            ])->save();

            return ['verified' => true, 'status' => $this->dtoWithCredential($provider, $credential, $environment)];
        });
    }

    /**
     * @param  array{
     *     provider_id: int,
     *     credential_id: int,
     *     configuration_version: int,
     *     credential_fingerprint: string,
     *     credentials: array<string, mixed>
     * }  $snapshot
     * @return array<string, bool|string|null>
     */
    private function applyFailure(array $snapshot, string $environment): array
    {
        return DB::transaction(function () use ($snapshot, $environment): array {
            [$provider, $credential] = $this->lockSnapshotRows($snapshot);

            if (! $this->snapshotStillCurrent($provider, $credential, $snapshot)) {
                return $this->dtoWithCredential($provider, $credential, $environment);
            }

            $stored = $credential->credentials ?? [];
            $credential->forceFill(['credentials' => is_array($stored['sender_profile'] ?? null)
                ? ['sender_profile' => $stored['sender_profile']]
                : []])->save();

            $provider->forceFill([
                'enabled' => false,
                'connection_state' => 'failed',
                'configuration_version' => $provider->configuration_version + 1,
                'last_verified_at' => null,
                'last_error' => 'verification_failed',
            ])->save();

            return $this->dtoWithCredential($provider, $credential, $environment);
        });
    }

    /**
     * @return array{
     *     provider_id: int,
     *     credential_id: int,
     *     configuration_version: int,
     *     credential_fingerprint: string,
     *     credentials: array<string, mixed>
     * }|null
     */
    private function verificationSnapshot(string $environment, ?int $expectedConfigurationVersion = null): ?array
    {
        return DB::transaction(function () use ($environment, $expectedConfigurationVersion): ?array {
            $provider = $this->providerForUpdate($environment);

            if ($expectedConfigurationVersion !== null
                && $provider->configuration_version !== $expectedConfigurationVersion) {
                return null;
            }

            $credential = ShippingProviderCredential::query()
                ->where('shipping_provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            if ($credential === null) {
                $credential = ShippingProviderCredential::query()->create([
                    'shipping_provider_id' => $provider->id,
                    'provider' => self::PROVIDER,
                    'environment' => $environment,
                    'credentials' => [],
                ]);
            }

            $credentials = $credential->credentials ?? [];

            return [
                'provider_id' => $provider->id,
                'credential_id' => $credential->id,
                'configuration_version' => $provider->configuration_version,
                'credential_fingerprint' => $this->credentialFingerprint($credentials),
                'credentials' => $credentials,
            ];
        });
    }

    /**
     * @param  array{provider_id: int, credential_id: int}  $snapshot
     * @return array{ShippingProvider, ShippingProviderCredential}
     */
    private function lockSnapshotRows(array $snapshot): array
    {
        $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($snapshot['provider_id']);
        $credential = ShippingProviderCredential::query()->lockForUpdate()->findOrFail($snapshot['credential_id']);

        return [$provider, $credential];
    }

    /**
     * @param  array{configuration_version: int, credential_fingerprint: string}  $snapshot
     */
    private function snapshotStillCurrent(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        array $snapshot,
    ): bool {
        return $provider->configuration_version === $snapshot['configuration_version']
            && $credential->shipping_provider_id === $provider->id
            && hash_equals(
                $snapshot['credential_fingerprint'],
                $this->credentialFingerprint($credential->credentials ?? []),
            );
    }

    /** @param array<string, mixed> $credentials */
    private function credentialFingerprint(array $credentials): string
    {
        return hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR));
    }

    private function dtoWithCredential(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        string $environment,
    ): array {
        $provider->setRelation('credential', $credential);

        return $this->dto($provider, $environment);
    }

    private function findProvider(string $environment): ?ShippingProvider
    {
        return ShippingProvider::query()
            ->with('credential')
            ->where('provider', self::PROVIDER)
            ->where('environment', $environment)
            ->first();
    }

    private function providerForUpdate(string $environment): ShippingProvider
    {
        $created = ShippingProvider::query()->firstOrCreate(
            ['provider' => self::PROVIDER, 'environment' => $environment],
            ['enabled' => false, 'connection_state' => 'unverified', 'configuration_version' => 0],
        );

        return ShippingProvider::query()->lockForUpdate()->findOrFail($created->id);
    }

    /** @return array<string, bool|string|null> */
    private function dto(?ShippingProvider $provider, string $environment): array
    {
        $senderProfile = $provider?->credential?->credentials['sender_profile'] ?? [];
        $senderProfile = is_array($senderProfile) ? $senderProfile : [];

        return [
            'provider' => self::PROVIDER,
            'environment' => $environment,
            'configured' => $this->platformCredentials($environment) !== null,
            'enabled' => $provider?->enabled ?? false,
            'connectionState' => $provider?->connection_state ?? 'unconfigured',
            'lastVerifiedAt' => $provider?->last_verified_at?->toIso8601String(),
            'lastError' => $provider?->last_error,
            'senderProfile' => $senderProfile,
            'senderProfileComplete' => $this->senderProfileComplete($senderProfile),
            'environments' => [
                self::SANDBOX_ENVIRONMENT => $this->environmentStatus(self::SANDBOX_ENVIRONMENT),
                self::PRODUCTION_ENVIRONMENT => $this->environmentStatus(self::PRODUCTION_ENVIRONMENT),
            ],
        ];
    }

    /** @param array<string, mixed> $profile */
    private function senderProfileComplete(array $profile): bool
    {
        foreach (['name', 'phone_number_country_code', 'phone_number', 'address_1', 'postcode', 'city', 'subdivision_code', 'country_code'] as $key) {
            if (! is_string($profile[$key] ?? null) || trim($profile[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{authorizationEnabled: bool, configured: bool, connectionState: string, enabled: bool} */
    private function environmentStatus(string $environment): array
    {
        $provider = $this->findProvider($environment);

        return [
            'authorizationEnabled' => $this->authorizationEnabled($environment),
            'configured' => $this->platformCredentials($environment) !== null,
            'connectionState' => $provider?->connection_state ?? 'unconfigured',
            'enabled' => $provider?->enabled ?? false,
        ];
    }

    private function authorizationEnabled(string $environment): bool
    {
        if ($environment === self::SANDBOX_ENVIRONMENT) {
            return true;
        }

        return config('services.easyparcel.environments.'.$environment.'.authorization_enabled') === true;
    }

    private function hasRequiredOAuthEndpoints(string $environment): bool
    {
        if ($environment === self::SANDBOX_ENVIRONMENT) {
            return true;
        }

        foreach (['authorization_url', 'token_url', 'wallet_url'] as $key) {
            $value = config('services.easyparcel.environments.'.$environment.'.'.$key);
            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }
}
