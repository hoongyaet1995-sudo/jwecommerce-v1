<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class MarketplaceHubOAuthIntent
{
    public function issue(string $provider, string $environment, string $localState): string
    {
        $base = rtrim((string) config('marketplace.hub.url'), '/');
        $clientId = (string) config('marketplace.hub.client_id');
        $secret = (string) config('marketplace.hub.client_secret');
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! MarketplaceHubOAuthMode::enabled($provider, $environment)
            || $base !== 'https://marketplace.jwaistudio.online'
            || $clientId === '' || $secret === '' || ! is_string($domain)
            || ! in_array($provider, ['shopee', 'lazada'], true)
            || ! in_array($environment, ['sandbox', 'production'], true)
            || ! preg_match('/^[a-f0-9]{64}$/D', $localState)) {
            throw new RuntimeException('Marketplace Hub authorization is unavailable.');
        }

        $body = json_encode(['domain' => strtolower($domain), 'provider' => $provider,
            'environment' => $environment, 'localState' => $localState], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(32);
        try {
            $response = Http::timeout(10)->withoutRedirecting()->withHeaders([
                'X-Hub-Client' => $clientId,
                'X-Hub-Timestamp' => $timestamp,
                'X-Hub-Nonce' => $nonce,
                'X-Hub-Signature' => hash_hmac('sha256', "$timestamp.$nonce.$body", $secret),
            ])->withBody($body, 'application/json')->post($base.'/api/oauth/intents');
        } catch (\Throwable) {
            throw new RuntimeException('Marketplace Hub authorization is unavailable.');
        }
        $state = $response->json('state');
        if ($response->status() !== 201 || ! is_string($state) || ! preg_match('/^[a-f0-9]{64}$/D', $state)) {
            throw new RuntimeException('Marketplace Hub authorization is unavailable.');
        }

        return $state;
    }
}
