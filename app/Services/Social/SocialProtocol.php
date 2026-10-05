<?php

namespace App\Services\Social;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SocialProtocol
{
    private static function key(string $secret, string $direction): string
    {
        return hash_hkdf('sha256', $secret, 32, 'jw-social-v1:'.$direction, '');
    }

    public static function headers(string $client, string $key, string $secret, string $direction, string $method, string $path, string $body): array
    {
        $h = ['X-Social-Client' => $client, 'X-Social-Key-Id' => $key, 'X-Social-Timestamp' => (string) time(), 'X-Social-Nonce' => bin2hex(random_bytes(16))];
        $h['X-Social-Signature'] = self::signature($h, $secret, $direction, $method, $path, $body);

        return $h;
    }

    private static function signature(array $h, string $secret, string $direction, string $method, string $path, string $body): string
    {
        $raw = implode("\n", ['JW-SOCIAL-V1', $direction, $h['X-Social-Client'] ?? '', $h['X-Social-Key-Id'] ?? '', $h['X-Social-Timestamp'] ?? '', $h['X-Social-Nonce'] ?? '', strtoupper($method), $path, hash('sha256', $body)]);

        return hash_hmac('sha256', $raw, self::key($secret, $direction));
    }

    public static function verify(array $h, string $secret, string $direction, string $method, string $path, string $body): bool
    {
        return $secret !== '' && ctype_digit($h['X-Social-Timestamp'] ?? '') && abs(time() - (int) $h['X-Social-Timestamp']) <= 300
            && preg_match('/^[a-f0-9]{32}$/D', $h['X-Social-Nonce'] ?? '') && preg_match('/^[a-f0-9]{64}$/D', $h['X-Social-Signature'] ?? '')
            && hash_equals(self::signature($h, $secret, $direction, $method, $path, $body), $h['X-Social-Signature']);
    }

    public static function requestHeaders(Request $request): array
    {
        $h = [];
        foreach (['Client', 'Key-Id', 'Timestamp', 'Nonce', 'Signature'] as $name) {
            $h['X-Social-'.$name] = (string) $request->header('X-Social-'.$name);
        }

return $h;
    }

    public static function responseSignature(array $h, string $secret, string $direction, int $status, string $body): string
    {
        return hash_hmac('sha256', implode("\n", ['JW-SOCIAL-V1-RESPONSE', $direction, $h['X-Social-Client'], $h['X-Social-Key-Id'], $h['X-Social-Nonce'], (string) $status, hash('sha256', $body)]), self::key($secret, $direction));
    }

    public static function response(array $payload, array $h, string $secret, string $direction, int $status = 200): JsonResponse
    {
        $r = response()->json($payload,$status)->header('Cache-Control','no-store');

        return $r->header('X-Social-Response-Signature',self::responseSignature($h,$secret,$direction,$status,$r->getContent()));
    }
}
