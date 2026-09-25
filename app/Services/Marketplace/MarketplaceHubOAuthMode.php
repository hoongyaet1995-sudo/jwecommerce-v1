<?php

namespace App\Services\Marketplace;

final class MarketplaceHubOAuthMode
{
    public static function authorizationDeployment(string $provider, string $environment): string
    {
        return self::enabled($provider, $environment)
            ? 'https://marketplace.jwaistudio.online'
            : (string) config('app.url');
    }

    public static function enabled(string $provider, string $environment): bool
    {
        return config("marketplace.hub.oauth_routes.$provider.$environment",
            config('marketplace.hub.oauth_routing_enabled', false)) === true;
    }
}
