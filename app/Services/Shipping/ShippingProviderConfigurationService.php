<?php

namespace App\Services\Shipping;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use Illuminate\Support\Facades\DB;

final class ShippingProviderConfigurationService
{
    public const PROVIDER = 'easyparcel';

    public const ENVIRONMENT = 'sandbox';

    public function __construct(private readonly EasyParcelOpenApiClient $client) {}

    /** @return array<string, bool|string|null> */
    public function status(): array
    {
        return $this->dto($this->findProvider());
    }

    /** @return array<string, bool|string|null> */
    public function disconnect(): array
    {
        $provider = DB::transaction(function (): ShippingProvider {
            $provider = $this->providerForUpdate();
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

        return $this->dto($provider);
    }

    /** @return array{client_id: string, client_secret: string}|null */
    public function platformCredentials(): ?array
    {
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
        $provider = DB::transaction(function () use ($clientId, $clientSecret, $enabled): ShippingProvider {
            $createdProvider = ShippingProvider::query()->firstOrCreate(
                ['provider' => self::PROVIDER, 'environment' => self::ENVIRONMENT],
                ['enabled' => false, 'connection_state' => 'unverified', 'configuration_version' => 0],
            );
            $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($createdProvider->id);

            $credential = ShippingProviderCredential::query()->where([
                'provider' => self::PROVIDER,
                'environment' => self::ENVIRONMENT,
            ])->lockForUpdate()->first();
            $stored = $credential?->credentials ?? [];
            $credentialsChanged = ! hash_equals((string) ($stored['client_id'] ?? ''), $clientId)
                || ! hash_equals((string) ($stored['client_secret'] ?? ''), $clientSecret);

            ShippingProviderCredential::query()->updateOrCreate(
                ['provider' => self::PROVIDER, 'environment' => self::ENVIRONMENT],
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

        return $this->dto($provider);
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
        ?int $expectedConfigurationVersion = null,
    ): array {
        $snapshot = $this->verificationSnapshot($expectedConfigurationVersion);
        $platformCredentials = $this->platformCredentials();

        if ($snapshot === null) {
            return ['verified' => false, 'status' => $this->status()];
        }

        if ($platformCredentials === null) {
            return ['verified' => false, 'status' => $this->applyFailure($snapshot)];
        }

        try {
            $tokens = $this->client->verifyAuthorizationCode($platformCredentials, $authorizationCode, $redirectUri);
        } catch (EasyParcelVerificationException) {
            return ['verified' => false, 'status' => $this->applyFailure($snapshot)];
        }

        return $this->applySuccess($snapshot, $tokens);
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
    private function applySuccess(array $snapshot, array $tokens): array
    {
        return DB::transaction(function () use ($snapshot, $tokens): array {
            [$provider, $credential] = $this->lockSnapshotRows($snapshot);

            if (! $this->snapshotStillCurrent($provider, $credential, $snapshot)) {
                return ['verified' => false, 'status' => $this->dtoWithCredential($provider, $credential)];
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

            return ['verified' => true, 'status' => $this->dtoWithCredential($provider, $credential)];
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
    private function applyFailure(array $snapshot): array
    {
        return DB::transaction(function () use ($snapshot): array {
            [$provider, $credential] = $this->lockSnapshotRows($snapshot);

            if (! $this->snapshotStillCurrent($provider, $credential, $snapshot)) {
                return $this->dtoWithCredential($provider, $credential);
            }

            $credential->forceFill(['credentials' => []])->save();

            $provider->forceFill([
                'enabled' => false,
                'connection_state' => 'failed',
                'configuration_version' => $provider->configuration_version + 1,
                'last_verified_at' => null,
                'last_error' => 'verification_failed',
            ])->save();

            return $this->dtoWithCredential($provider, $credential);
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
    private function verificationSnapshot(?int $expectedConfigurationVersion = null): ?array
    {
        return DB::transaction(function () use ($expectedConfigurationVersion): ?array {
            $provider = $this->providerForUpdate();

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
                    'environment' => self::ENVIRONMENT,
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
    ): array {
        $provider->setRelation('credential', $credential);

        return $this->dto($provider);
    }

    private function findProvider(): ?ShippingProvider
    {
        return ShippingProvider::query()
            ->with('credential')
            ->where('provider', self::PROVIDER)
            ->where('environment', self::ENVIRONMENT)
            ->first();
    }

    private function providerForUpdate(): ShippingProvider
    {
        $created = ShippingProvider::query()->firstOrCreate(
            ['provider' => self::PROVIDER, 'environment' => self::ENVIRONMENT],
            ['enabled' => false, 'connection_state' => 'unverified', 'configuration_version' => 0],
        );

        return ShippingProvider::query()->lockForUpdate()->findOrFail($created->id);
    }

    /** @return array<string, bool|string|null> */
    private function dto(?ShippingProvider $provider): array
    {
        return [
            'provider' => self::PROVIDER,
            'environment' => self::ENVIRONMENT,
            'configured' => $this->platformCredentials() !== null,
            'enabled' => $provider?->enabled ?? false,
            'connectionState' => $provider?->connection_state ?? 'unconfigured',
            'lastVerifiedAt' => $provider?->last_verified_at?->toIso8601String(),
            'lastError' => $provider?->last_error,
        ];
    }
}
