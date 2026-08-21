<?php

namespace App\Domain\Cms;

final class MediaUrlPolicy
{
    private const MANAGED_URL_PATTERN = '#\A/storage/cms/media/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp|gif|mp4|webm)\z#D';

    /** @var list<string> */
    private const RESERVED_HOST_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.lan',
        '.home',
        '.corp',
        '.test',
        '.invalid',
        '.example',
    ];

    /** @var list<string> */
    private const RESERVED_IP_RANGES = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '2001:db8::/32',
    ];

    public static function isManaged(string $url): bool
    {
        return preg_match(self::MANAGED_URL_PATTERN, $url) === 1;
    }

    public static function isSafe(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (self::isManaged($url)) {
            return true;
        }

        if (str_starts_with($url, '//')
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1
            || self::containsEncodedControlOrBackslash($url)) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return false;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $ipHost = trim($host, '[]');

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        if (filter_var($ipHost, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($ipHost);
        }

        if (self::isNumericLookingHost($host)
            || ! str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return false;
        }

        foreach (self::RESERVED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return ! in_array($host, ['example.com', 'example.net', 'example.org'], true);
    }

    private static function containsEncodedControlOrBackslash(string $url): bool
    {
        $candidate = $url;

        for ($depth = 0; $depth < 5; $depth++) {
            if (preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i', $candidate) === 1) {
                return true;
            }

            $decoded = rawurldecode($candidate);

            if ($decoded === $candidate) {
                return false;
            }

            if (preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) === 1) {
                return true;
            }

            $candidate = $decoded;
        }

        return preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|5c|7f)/i', $candidate) === 1;
    }

    private static function isNumericLookingHost(string $host): bool
    {
        if (preg_match('/\A[0-9.]+\z/D', $host) === 1) {
            return true;
        }

        return preg_match('/\A(?:0x[0-9a-f]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\z/iD', $host) === 1;
    }

    private static function isPublicIp(string $ip): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16
            && substr($packed, 0, 10) === str_repeat("\0", 10)
            && substr($packed, 10, 2) === "\xff\xff") {
            $mapped = inet_ntop(substr($packed, 12));

            return is_string($mapped) && self::isPublicIp($mapped);
        }

        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        foreach (self::RESERVED_IP_RANGES as $range) {
            if (self::ipInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function ipInRange(string $ip, string $range): bool
    {
        [$network, $prefix] = explode('/', $range, 2);
        $packedIp = inet_pton($ip);
        $packedNetwork = inet_pton($network);

        if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }

        $prefixLength = (int) $prefix;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }
}
