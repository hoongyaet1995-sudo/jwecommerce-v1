<?php

namespace App\Services\Shipping;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EasyParcelOpenApiClient
{
    public const AUTHORIZATION_URL = 'https://api.easyparcel.com/oauth/login';

    public const API_VERSION = '2026-06';

    public const ACCOUNT_INFORMATION_URL = 'https://api.easyparcel.com/open_api/'.self::API_VERSION.'/account/get_account_information';

    public const TOKEN_URL = 'https://api.easyparcel.com/oauth/token';

    public const WALLET_URL = 'https://api.easyparcel.com/open_api/'.self::API_VERSION.'/wallet';

    public function authorizationUrl(string $environment, string $clientId, string $redirectUri, string $state): string
    {
        return $this->endpoint($environment, 'authorization_url', self::AUTHORIZATION_URL).'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array{client_id: string, client_secret: string}  $credentials
     * @return array<string, mixed>
     */
    public function refreshAccessToken(array $credentials, string $refreshToken, string $environment): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
                ->connectTimeout(5)
                ->timeout(10)
                ->post($this->endpoint($environment, 'token_url', self::TOKEN_URL), [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            $payload = $response->json();
            if (! $response->successful()
                || ! is_array($payload)
                || ($payload['token_type'] ?? null) !== 'Bearer'
                || ! is_string($payload['access_token'] ?? null)
                || $payload['access_token'] === '') {
                $this->logVerificationRejection('refresh', $response->status());
                throw new EasyParcelVerificationException;
            }

            return $this->tokenCache($payload);
        } catch (EasyParcelVerificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('EasyParcel OAuth verification failed', [
                'exception_class' => $exception::class,
            ]);

            throw new EasyParcelVerificationException;
        }
    }

    /**
     * @param  array{client_id: string, client_secret: string}  $credentials
     * @return array<string, mixed>
     */
    public function verifyAuthorizationCode(array $credentials, string $authorizationCode, string $redirectUri, ?string $state, string $environment): array
    {
        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
                ->connectTimeout(5)
                ->timeout(10)
                ->post($this->endpoint($environment, 'token_url', self::TOKEN_URL), [
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code' => $authorizationCode,
                    'state' => $state,
                ]);

            $tokenPayload = $tokenResponse->json();
            if (! $tokenResponse->successful()
                || ! is_array($tokenPayload)
                || ($tokenPayload['token_type'] ?? null) !== 'Bearer'
                || ! is_string($tokenPayload['access_token'] ?? null)
                || $tokenPayload['access_token'] === '') {
                $this->logVerificationRejection('token', $tokenResponse->status());
                throw new EasyParcelVerificationException;
            }

            $walletResponse = Http::acceptJson()
                ->withToken($tokenPayload['access_token'])
                ->connectTimeout(5)
                ->timeout(10)
                ->get($this->endpoint($environment, 'wallet_url', self::WALLET_URL));

            $walletPayload = $walletResponse->json();
            if (! $walletResponse->successful()
                || ! is_array($walletPayload)
                || ($walletPayload['status_code'] ?? null) !== 200
                || ! is_array(data_get($walletPayload, 'data.wallet'))) {
                $this->logVerificationRejection('wallet', $walletResponse->status());
                throw new EasyParcelVerificationException;
            }

            return array_replace(
                $this->tokenCache($tokenPayload),
                $this->authorizedAccountProfile((string) $tokenPayload['access_token']),
            );
        } catch (EasyParcelVerificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('EasyParcel OAuth verification failed', [
                'exception_class' => $exception::class,
            ]);

            throw new EasyParcelVerificationException;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int|string|null>
     */
    private function tokenCache(array $payload): array
    {
        $expiresIn = is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : null;
        $refreshExpiresIn = is_numeric($payload['refresh_token_expires_in'] ?? null)
            ? (int) $payload['refresh_token_expires_in']
            : null;

        return [
            'token_type' => 'Bearer',
            'access_token' => (string) $payload['access_token'],
            'access_token_expires_at' => $this->expiry($payload['expires_at'] ?? null, $expiresIn),
            'refresh_token' => is_string($payload['refresh_token'] ?? null) ? $payload['refresh_token'] : null,
            'refresh_token_expires_at' => $this->expiry($payload['refresh_token_expires_at'] ?? null, $refreshExpiresIn),
        ];
    }

    /** @return array{account_profile: array{name: string, account_type: string|null}, sender_profile: array<string, string>} */
    private function authorizedAccountProfile(string $accessToken): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::ACCOUNT_INFORMATION_URL);
            $payload = $response->json();
            $account = data_get($payload, 'data.account');
            $pickup = data_get($payload, 'data.address.pickup_address');

            if (! $response->successful()
                || ! is_array($payload)
                || ($payload['status_code'] ?? null) !== 200
                || ! is_array($account)
                || ! is_array($pickup)) {
                $this->logVerificationRejection('account_information', $response->status());
                throw new EasyParcelVerificationException;
            }

            $sender = [
                'name' => $this->requiredProfileValue($pickup, 'name'),
                'phone_number_country_code' => $this->requiredProfileValue($pickup, 'country_code'),
                'phone_number' => $this->requiredProfileValue($pickup, 'phone_number'),
                'email' => $this->optionalProfileValue($pickup, 'email'),
                'company' => $this->optionalProfileValue($pickup, 'company_name'),
                'address_1' => $this->requiredProfileValue($pickup, 'address1'),
                'address_2' => $this->optionalProfileValue($pickup, 'address2'),
                'postcode' => $this->requiredProfileValue($pickup, 'postcode'),
                'city' => $this->requiredProfileValue($pickup, 'city'),
                'subdivision_code' => $this->requiredProfileValue($pickup, 'province_code'),
                'country_code' => $this->requiredProfileValue($pickup, 'country_code'),
            ];

            return [
                'account_profile' => [
                    'name' => $this->requiredProfileValue($account, 'name'),
                    'account_type' => $this->optionalProfileValue($account, 'account_type'),
                ],
                'sender_profile' => array_filter($sender, fn (mixed $value): bool => $value !== null),
            ];
        } catch (EasyParcelVerificationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('EasyParcel OAuth verification failed', [
                'exception_class' => $exception::class,
            ]);

            throw new EasyParcelVerificationException;
        }
    }

    /** @param array<string, mixed> $profile */
    private function requiredProfileValue(array $profile, string $key): string
    {
        $value = $profile[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new EasyParcelVerificationException;
        }

        return trim($value);
    }

    /** @param array<string, mixed> $profile */
    private function optionalProfileValue(array $profile, string $key): ?string
    {
        $value = $profile[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new EasyParcelVerificationException;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function expiry(mixed $absoluteExpiry, ?int $expiresIn): ?string
    {
        if (is_string($absoluteExpiry) && $absoluteExpiry !== '') {
            try {
                return Carbon::parse($absoluteExpiry)->utc()->toIso8601String();
            } catch (Throwable) {
                // Fall back to the documented relative lifetime when the absolute value is malformed.
            }
        }

        return $expiresIn !== null && $expiresIn > 0
            ? now()->addSeconds($expiresIn)->utc()->toIso8601String()
            : null;
    }

    private function endpoint(string $environment, string $key, string $fallback): string
    {
        $configured = config('services.easyparcel.environments.'.$environment.'.'.$key);

        return is_string($configured) && $configured !== '' ? $configured : $fallback;
    }

    private function logVerificationRejection(string $stage, int $httpStatus): void
    {
        Log::warning('EasyParcel OAuth verification rejected', [
            'stage' => $stage,
            'http_status' => $httpStatus,
        ]);
    }
}
