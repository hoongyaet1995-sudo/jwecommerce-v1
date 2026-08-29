<?php

namespace App\Services\Shipping;

use App\Models\ShippingProvider;
use App\Models\ShippingProviderAuthorizationTransaction;
use App\Models\ShippingProviderCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class EasyParcelOAuthAuthorizationService
{
    private const MAX_AUTHORIZATION_CODE_LENGTH = 4096;

    private const MAX_STATE_LENGTH = 512;

    private const TRANSACTION_TTL_SECONDS = 600;

    public function __construct(
        private readonly EasyParcelOpenApiClient $client,
        private readonly ShippingProviderConfigurationService $configuration,
    ) {}

    public function begin(int $userId, string $sessionBinding, string $redirectUri): ?string
    {
        $sessionFingerprint = $this->sessionFingerprint($sessionBinding);
        if ($userId < 1 || $sessionFingerprint === null) {
            return null;
        }

        $state = bin2hex(random_bytes(32));
        $stateHash = hash('sha256', $state);

        return DB::transaction(function () use (
            $userId,
            $sessionFingerprint,
            $state,
            $stateHash,
            $redirectUri,
        ): ?string {
            $credentials = $this->configuration->platformCredentials();
            if ($credentials === null) {
                return null;
            }
            $provider = ShippingProvider::query()->firstOrCreate(
                ['provider' => ShippingProviderConfigurationService::PROVIDER, 'environment' => ShippingProviderConfigurationService::ENVIRONMENT],
                ['enabled' => false, 'connection_state' => 'unverified', 'configuration_version' => 0],
            );
            $provider = ShippingProvider::query()->lockForUpdate()->findOrFail($provider->id);

            $now = now();
            ShippingProviderAuthorizationTransaction::query()
                ->where('shipping_provider_id', $provider->id)
                ->where('user_id', $userId)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => $now,
                    'updated_at' => $now,
                ]);

            ShippingProviderAuthorizationTransaction::query()->create([
                'shipping_provider_id' => $provider->id,
                'user_id' => $userId,
                'state_hash' => $stateHash,
                'session_fingerprint' => $sessionFingerprint,
                'configuration_version' => $provider->configuration_version,
                'expires_at' => $now->copy()->addSeconds(self::TRANSACTION_TTL_SECONDS),
                'consumed_at' => null,
            ]);

            return $this->client->authorizationUrl($credentials['client_id'], $redirectUri, $state);
        });
    }

    public function complete(
        int $userId,
        string $sessionBinding,
        ?string $state,
        ?string $authorizationCode,
        string $redirectUri,
    ): bool {
        $configurationVersion = $this->consume($userId, $sessionBinding, $state);

        if ($configurationVersion === null
            || $authorizationCode === null
            || $authorizationCode === ''
            || strlen($authorizationCode) > self::MAX_AUTHORIZATION_CODE_LENGTH) {
            return false;
        }

        return $this->configuration->testConnection(
            $authorizationCode,
            $redirectUri,
            $configurationVersion,
        )['verified'];
    }

    /**
     * Makes a provider-denied OAuth callback terminal without exchanging a code.
     */
    public function cancel(int $userId, string $sessionBinding, ?string $state): bool
    {
        return $this->consume($userId, $sessionBinding, $state) !== null;
    }

    public function consume(int $userId, string $sessionBinding, ?string $state): ?int
    {
        if (DB::connection()->transactionLevel() !== 0) {
            return $this->reject('transaction_already_open');
        }

        $sessionFingerprint = $this->sessionFingerprint($sessionBinding);
        if ($userId < 1) return $this->reject('user_missing');
        if ($sessionFingerprint === null) return $this->reject('session_binding_missing');
        if ($state === null || $state === '') return $this->reject('state_missing');
        if (strlen($state) > self::MAX_STATE_LENGTH) return $this->reject('state_too_long');

        $stateHash = hash('sha256', $state);
        // Resolve the provider before the transaction so every writer can lock provider -> OAuth row.
        $candidate = DB::table('shipping_provider_authorization_transactions')
            ->select(['id', 'shipping_provider_id'])
            ->where('state_hash', $stateHash)
            ->first();

        if ($candidate === null) {
            return $this->reject('state_transaction_missing');
        }

        return DB::transaction(function () use (
            $candidate,
            $userId,
            $sessionFingerprint,
            $stateHash,
        ): ?int {
            $provider = ShippingProvider::query()
                ->lockForUpdate()
                ->find($candidate->shipping_provider_id);
            $transaction = ShippingProviderAuthorizationTransaction::query()
                ->whereKey($candidate->id)
                ->where('state_hash', $stateHash)
                ->lockForUpdate()
                ->first();

            if ($provider === null) return $this->reject('provider_missing');
            if ($transaction === null) return $this->reject('transaction_missing');
            if ($transaction->shipping_provider_id !== $provider->id) return $this->reject('provider_mismatch');
            if ($provider->provider !== ShippingProviderConfigurationService::PROVIDER) return $this->reject('provider_type_mismatch');
            if ($provider->environment !== ShippingProviderConfigurationService::ENVIRONMENT) return $this->reject('provider_environment_mismatch');
            if ($transaction->user_id !== $userId) return $this->reject('user_mismatch');
            if (! hash_equals($transaction->session_fingerprint, $sessionFingerprint)) return $this->reject('session_binding_mismatch');
            if ($transaction->configuration_version !== $provider->configuration_version) return $this->reject('configuration_version_mismatch');
            if ($transaction->consumed_at !== null) return $this->reject('transaction_already_consumed');
            if ($transaction->expires_at === null || ! $transaction->expires_at->isFuture()) return $this->reject('transaction_expired');

            $consumedAt = now();
            // The conditional update is the compare-and-set boundary if a caller re-enters on one connection.
            $consumed = ShippingProviderAuthorizationTransaction::query()
                ->whereKey($transaction->id)
                ->where('state_hash', $stateHash)
                ->where('user_id', $userId)
                ->where('session_fingerprint', $sessionFingerprint)
                ->where('configuration_version', $provider->configuration_version)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', $consumedAt)
                ->update([
                    'consumed_at' => $consumedAt,
                    'updated_at' => $consumedAt,
                ]);

            if ($consumed !== 1) {
                return $this->reject('transaction_compare_and_set_failed');
            }

            return $transaction->configuration_version;
        });
    }

    private function sessionFingerprint(string $sessionBinding): ?string
    {
        $applicationKey = (string) config('app.key');

        if ($sessionBinding === '' || $applicationKey === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionBinding, $applicationKey);
    }

    private function reject(string $reason): ?int
    {
        Log::warning('EasyParcel OAuth callback rejected', ['reason' => $reason]);

        return null;
    }
}
