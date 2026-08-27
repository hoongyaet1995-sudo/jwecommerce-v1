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

    public function authorizationUrl(string $state, string $redirectUri): ?string
    {
        $provider = $this->findProvider();
        $credentials = $provider?->credential?->credentials ?? [];
        $clientId = is_array($credentials) ? ($credentials['client_id'] ?? null) : null;

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        return $this->client->authorizationUrl($clientId, $redirectUri, $state);
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
    public function testConnection(string $authorizationCode, string $redirectUri): array
    {
        $snapshot = $this->verificationSnapshot();

        if ($snapshot === null) {
            return ['verified' => false, 'status' => $this->dto(null)];
        }

        $stored = $snapshot['credentials'];
        if (! is_string($stored['client_id'] ?? null)
            || ! is_string($stored['client_secret'] ?? null)
            || $stored['client_id'] === ''
            || $stored['client_secret'] === '') {
            return ['verified' => false, 'status' => $this->applyFailure($snapshot)];
        }

        try {
            $tokens = $this->client->verifyAuthorizationCode([
                'client_id' => $stored['client_id'],
                'client_secret' => $stored['client_secret'],
            ], $authorizationCode, $redirectUri);
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

            $credential->forceFill(['credentials' => [
                'client_id' => $snapshot['credentials']['client_id'],
                'client_secret' => $snapshot['credentials']['client_secret'],
                ...$tokens,
            ]])->save();

            $provider->forceFill([
                'enabled' => true,
                'connection_state' => 'connected',
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

            $credential->forceFill(['credentials' => [
                'client_id' => $snapshot['credentials']['client_id'] ?? null,
                'client_secret' => $snapshot['credentials']['client_secret'] ?? null,
            ]])->save();

            $provider->forceFill([
                'enabled' => false,
                'connection_state' => 'failed',
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
    private function verificationSnapshot(): ?array
    {
        return DB::transaction(function (): ?array {
            $provider = ShippingProvider::query()
                ->where('provider', self::PROVIDER)
                ->where('environment', self::ENVIRONMENT)
                ->lockForUpdate()
                ->first();

            if ($provider === null) {
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
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');

        return hash('sha256', strlen($clientId).':'.$clientId.'|'.strlen($clientSecret).':'.$clientSecret);
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

    /** @return array<string, bool|string|null> */
    private function dto(?ShippingProvider $provider): array
    {
        return [
            'provider' => self::PROVIDER,
            'environment' => self::ENVIRONMENT,
            'configured' => $provider?->credential !== null,
            'enabled' => $provider?->enabled ?? false,
            'connectionState' => $provider?->connection_state ?? 'unconfigured',
            'lastVerifiedAt' => $provider?->last_verified_at?->toIso8601String(),
            'lastError' => $provider?->last_error,
        ];
    }
}
