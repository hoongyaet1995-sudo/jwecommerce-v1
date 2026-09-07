<?php

namespace App\Services\Marketplace;

use RuntimeException;

final class LazadaAuthorizationUrl
{
    /** The caller issues and persists the one-time state before returning this URL. */
    public static function build(string $appKey, string $callback, string $deployment, string $state): string
    {
        $target = parse_url($callback);
        $base = parse_url($deployment);
        if (!preg_match('/^[1-9][0-9]*$/D', $appKey)
            || !preg_match('/^[a-f0-9]{64}$/D', $state)
            || !is_array($target) || !is_array($base)
            || ($target['scheme'] ?? '') !== 'https' || ($base['scheme'] ?? '') !== 'https'
            || empty($base['host']) || ($target['host'] ?? '') !== $base['host']
            || ($target['port'] ?? 443) !== ($base['port'] ?? 443)
            || isset($target['user']) || isset($target['pass']) || isset($target['query']) || isset($target['fragment'])
            || isset($base['user']) || isset($base['pass']) || isset($base['query']) || isset($base['fragment'])
            || !in_array($base['path'] ?? '', ['', '/'], true)
            || ($target['path'] ?? '') !== '/admin/marketplace/oauth/lazada/callback') {
            throw new RuntimeException('Lazada authorization configuration is invalid.');
        }

        // This does not assert that the callback has been registered with Lazada.
        return 'https://auth.lazada.com/oauth/authorize?'.http_build_query([
            'response_type'=>'code', 'force_auth'=>'true', 'redirect_uri'=>$callback,
            'client_id'=>$appKey, 'state'=>$state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
