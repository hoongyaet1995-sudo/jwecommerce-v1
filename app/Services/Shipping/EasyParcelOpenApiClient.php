<?php

namespace App\Services\Shipping;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

final class EasyParcelOpenApiClient
{
    public const AUTHORIZATION_URL = 'https://api.easyparcel.com/oauth/login';

    public const API_VERSION = '2026-06';

    public const TOKEN_URL = 'https://api.easyparcel.com/oauth/token';

    public const ACCOUNT_INFORMATION_URL = 'https://api.easyparcel.com/open_api/'.self::API_VERSION.'/account/get_account_information';

    public function authorizationUrl(string $clientId, string $redirectUri, string $state): string
    {
        return self::AUTHORIZATION_URL.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array{client_id: string, client_secret: string}  $credentials
     * @return array<string, int|string|null>
     */
    public function verifyAuthorizationCode(array $credentials, string $authorizationCode, string $redirectUri, ?string $state): array
    {
        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::TOKEN_URL, [
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
                throw new EasyParcelVerificationException;
            }

            $accountResponse = Http::acceptJson()
                ->withToken($tokenPayload['access_token'])
                ->connectTimeout(5)
                ->timeout(10)
                ->post(self::ACCOUNT_INFORMATION_URL);

            $accountPayload = $accountResponse->json();
            if (! $accountResponse->successful()
                || ! is_array($accountPayload)
                || ($accountPayload['status_code'] ?? null) !== 200
                || ! is_array(data_get($accountPayload, 'data.account'))) {
                throw new EasyParcelVerificationException;
            }

            return $this->tokenCache($tokenPayload);
        } catch (EasyParcelVerificationException $exception) {
            throw $exception;
        } catch (Throwable) {
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
}
