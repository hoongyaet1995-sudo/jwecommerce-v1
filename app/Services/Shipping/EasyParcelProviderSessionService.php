<?php

namespace App\Services\Shipping;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EasyParcelProviderSessionService
{
    public function __construct(
        private readonly ShippingProviderConfigurationService $configuration,
        private readonly EasyParcelOpenApiClient $client,
    ) {}

    public function connected(): ?EasyParcelProviderSession
    {
        try {
            $provider = ShippingProvider::query()
                ->with('credential')
                ->where('provider', ShippingProviderConfigurationService::PROVIDER)
                ->where('environment', ShippingProviderConfigurationService::operationalEnvironment())
                ->first();

            if ($provider === null || ! $this->isConnectedProvider($provider)) {
                return null;
            }

            $credential = $provider->credential;
            if (! $credential instanceof ShippingProviderCredential) {
                throw new EasyParcelTrackingException;
            }

            [$credentials, $expiresAt] = $this->validatedAccessCredentials($provider, $credential);
            if ($expiresAt->isAfter(now()->addMinute())) {
                return $this->session($provider, $credential, $credentials);
            }

            return Cache::lock('easyparcel-oauth-refresh-'.$provider->id, 15)
                ->block(5, fn (): EasyParcelProviderSession => $this->refresh($provider->id));
        } catch (EasyParcelTrackingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }
    }

    public function assertCurrent(EasyParcelProviderSession $session): void
    {
        $provider = ShippingProvider::query()->lockForUpdate()->find($session->providerId);
        $credential = ShippingProviderCredential::query()->lockForUpdate()->find($session->credentialId);

        if (! $provider instanceof ShippingProvider
            || ! $credential instanceof ShippingProviderCredential
            || ! $this->snapshotIsCurrent($provider, $credential, $session)) {
            throw new EasyParcelTrackingException;
        }
    }

    private function refresh(int $providerId): EasyParcelProviderSession
    {
        $provider = ShippingProvider::query()->with('credential')->find($providerId);
        if (! $provider instanceof ShippingProvider || ! $this->isConnectedProvider($provider)) {
            throw new EasyParcelTrackingException;
        }

        $credential = $provider->credential;
        if (! $credential instanceof ShippingProviderCredential) {
            throw new EasyParcelTrackingException;
        }

        [$credentials, $accessExpiresAt] = $this->validatedAccessCredentials($provider, $credential);
        if ($accessExpiresAt->isAfter(now()->addMinute())) {
            return $this->session($provider, $credential, $credentials);
        }

        $fingerprint = $this->credentialFingerprint($credentials);
        $platformCredentials = $this->configuration->platformCredentials($provider->environment);
        $refreshToken = $credentials['refresh_token'] ?? null;

        if ($platformCredentials === null
            || ! is_string($refreshToken)
            || $refreshToken === ''
            || ! $this->refreshTokenIsValid($credentials['refresh_token_expires_at'] ?? null)) {
            $this->markRefreshFailure($provider, $credential, $fingerprint);
            throw new EasyParcelTrackingException;
        }

        try {
            $tokens = $this->client->refreshAccessToken($platformCredentials, $refreshToken, $provider->environment);
        } catch (EasyParcelVerificationException) {
            $this->markRefreshFailure($provider, $credential, $fingerprint);
            throw new EasyParcelTrackingException;
        }

        if (($tokens['refresh_token'] ?? null) === null) {
            $tokens['refresh_token'] = $refreshToken;
            $tokens['refresh_token_expires_at'] = $credentials['refresh_token_expires_at'];
        }

        $rotatedCredentials = array_replace($credentials, $tokens);
        try {
            $rotatedAccessExpiresAt = $this->validatedCredentialValues($rotatedCredentials);
            if (! $rotatedAccessExpiresAt->isAfter(now()->addMinute())) {
                throw new EasyParcelTrackingException;
            }
        } catch (EasyParcelTrackingException $exception) {
            $this->markRefreshFailure($provider, $credential, $fingerprint);
            throw $exception;
        }

        return DB::transaction(function () use (
            $provider,
            $credential,
            $fingerprint,
            $rotatedCredentials,
        ): EasyParcelProviderSession {
            $currentProvider = ShippingProvider::query()->lockForUpdate()->find($provider->id);
            $currentCredential = ShippingProviderCredential::query()->lockForUpdate()->find($credential->id);

            if (! $currentProvider instanceof ShippingProvider
                || ! $currentCredential instanceof ShippingProviderCredential
                || ! $this->snapshotMatches(
                    $currentProvider,
                    $currentCredential,
                    $provider->configuration_version,
                    $fingerprint,
                )) {
                throw new EasyParcelTrackingException;
            }

            $currentCredential->forceFill(['credentials' => $rotatedCredentials])->save();
            if ($currentProvider->last_error !== null) {
                $currentProvider->forceFill(['last_error' => null])->save();
            }

            return $this->session($currentProvider, $currentCredential, $rotatedCredentials);
        });
    }

    /**
     * @return array{array<string, mixed>, Carbon}
     */
    private function validatedAccessCredentials(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
    ): array {
        if ($credential->shipping_provider_id !== $provider->id
            || $credential->provider !== ShippingProviderConfigurationService::PROVIDER
            || $credential->environment !== $provider->environment) {
            throw new EasyParcelTrackingException;
        }

        $credentials = $credential->credentials;
        if (! is_array($credentials)) {
            throw new EasyParcelTrackingException;
        }

        return [$credentials, $this->validatedCredentialValues($credentials)];
    }

    /** @param array<string, mixed> $credentials */
    private function validatedCredentialValues(array $credentials): Carbon
    {
        if (($credentials['token_type'] ?? null) !== 'Bearer'
            || ! is_string($credentials['access_token'] ?? null)
            || $credentials['access_token'] === ''
            || ! is_string($credentials['access_token_expires_at'] ?? null)) {
            throw new EasyParcelTrackingException;
        }

        try {
            return Carbon::parse($credentials['access_token_expires_at']);
        } catch (Throwable) {
            throw new EasyParcelTrackingException;
        }
    }

    private function refreshTokenIsValid(mixed $expiresAt): bool
    {
        if (! is_string($expiresAt)) {
            return false;
        }

        try {
            return Carbon::parse($expiresAt)->isAfter(now());
        } catch (Throwable) {
            return false;
        }
    }

    private function markRefreshFailure(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        string $fingerprint,
    ): void {
        DB::transaction(function () use ($provider, $credential, $fingerprint): void {
            $currentProvider = ShippingProvider::query()->lockForUpdate()->find($provider->id);
            $currentCredential = ShippingProviderCredential::query()->lockForUpdate()->find($credential->id);

            if ($currentProvider instanceof ShippingProvider
                && $currentCredential instanceof ShippingProviderCredential
                && $this->snapshotMatches(
                    $currentProvider,
                    $currentCredential,
                    $provider->configuration_version,
                    $fingerprint,
                )) {
                $currentProvider->forceFill(['last_error' => 'token_refresh_failed'])->save();
            }
        });
    }

    /** @param array<string, mixed> $credentials */
    private function session(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        array $credentials,
    ): EasyParcelProviderSession {
        return new EasyParcelProviderSession(
            providerId: $provider->id,
            credentialId: $credential->id,
            configurationVersion: $provider->configuration_version,
            credentialFingerprint: $this->credentialFingerprint($credentials),
            accessToken: $credentials['access_token'],
        );
    }

    private function snapshotIsCurrent(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        EasyParcelProviderSession $session,
    ): bool {
        return $this->isConnectedProvider($provider)
            && $this->snapshotMatches(
                $provider,
                $credential,
                $session->configurationVersion,
                $session->credentialFingerprint,
            );
    }

    private function snapshotMatches(
        ShippingProvider $provider,
        ShippingProviderCredential $credential,
        int $configurationVersion,
        string $credentialFingerprint,
    ): bool {
        return $credential->shipping_provider_id === $provider->id
            && $credential->provider === ShippingProviderConfigurationService::PROVIDER
            && $credential->environment === $provider->environment
            && $provider->configuration_version === $configurationVersion
            && hash_equals(
                $credentialFingerprint,
                $this->credentialFingerprint($credential->credentials ?? []),
            );
    }

    private function isConnectedProvider(ShippingProvider $provider): bool
    {
        return $provider->provider === ShippingProviderConfigurationService::PROVIDER
            && $provider->environment === ShippingProviderConfigurationService::operationalEnvironment()
            && $provider->enabled
            && $provider->connection_state === 'connected';
    }

    /** @param array<string, mixed> $credentials */
    private function credentialFingerprint(array $credentials): string
    {
        $canonical = [];
        foreach ([
            'client_id',
            'client_secret',
            'token_type',
            'access_token',
            'access_token_expires_at',
            'refresh_token',
            'refresh_token_expires_at',
        ] as $key) {
            $canonical[$key] = $credentials[$key] ?? null;
        }

        return hash('sha256', serialize($canonical));
    }
}
