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
    public function save(string $clientId, string $clientSecret, bool $enabled): array
    {
        $provider = DB::transaction(function () use ($clientId, $clientSecret, $enabled): ShippingProvider {
            $provider = ShippingProvider::query()->firstOrCreate(
                ['provider' => self::PROVIDER, 'environment' => self::ENVIRONMENT],
                ['enabled' => false, 'connection_state' => 'unverified'],
            );

            $credential = ShippingProviderCredential::query()->where([
                'provider' => self::PROVIDER,
                'environment' => self::ENVIRONMENT,
            ])->first();
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
                    'last_verified_at' => null,
                    'last_error' => null,
                ])->save();
            } else {
                $provider->forceFill([
                    'enabled' => $enabled && $provider->connection_state === 'connected',
                ])->save();
            }

            return $provider->fresh();
        });

        return $this->dto($provider);
    }

    /** @return array<string, bool|string|null> */
    public function testConnection(string $authorizationCode, string $redirectUri): array
    {
        $provider = $this->findProvider();
        $credential = $provider?->credential;

        if ($provider === null || $credential === null) {
            return $this->failed($provider);
        }

        $stored = $credential->credentials ?? [];
        if (! is_string($stored['client_id'] ?? null)
            || ! is_string($stored['client_secret'] ?? null)
            || $stored['client_id'] === ''
            || $stored['client_secret'] === '') {
            return $this->failed($provider);
        }

        try {
            $tokens = $this->client->verifyAuthorizationCode([
                'client_id' => $stored['client_id'],
                'client_secret' => $stored['client_secret'],
            ], $authorizationCode, $redirectUri);
        } catch (EasyParcelVerificationException) {
            return $this->failed($provider);
        }

        $provider = DB::transaction(function () use ($provider, $credential, $stored, $tokens): ShippingProvider {
            $credential->forceFill(['credentials' => [
                'client_id' => $stored['client_id'],
                'client_secret' => $stored['client_secret'],
                ...$tokens,
            ]])->save();

            $provider->forceFill([
                'enabled' => true,
                'connection_state' => 'connected',
                'public_metadata' => ['apiVersion' => EasyParcelOpenApiClient::API_VERSION],
                'last_verified_at' => now(),
                'last_error' => null,
            ])->save();

            return $provider->fresh();
        });

        return $this->dto($provider);
    }

    /** @return array<string, bool|string|null> */
    private function failed(?ShippingProvider $provider): array
    {
        if ($provider === null) {
            return $this->dto(null);
        }

        DB::transaction(function () use ($provider): void {
            $credential = $provider->credential;
            if ($credential !== null) {
                $stored = $credential->credentials ?? [];
                $credential->forceFill(['credentials' => [
                    'client_id' => $stored['client_id'] ?? null,
                    'client_secret' => $stored['client_secret'] ?? null,
                ]])->save();
            }

            $provider->forceFill([
                'enabled' => false,
                'connection_state' => 'failed',
                'last_verified_at' => null,
                'last_error' => 'verification_failed',
            ])->save();
        });

        return $this->dto($provider->fresh());
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
