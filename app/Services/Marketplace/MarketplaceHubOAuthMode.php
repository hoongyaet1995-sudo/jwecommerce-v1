<?php

namespace App\Services\Marketplace;

final class MarketplaceHubOAuthMode
{
    public static function enabled(string $provider, string $environment): bool
    {
        return config("marketplace.hub.oauth_routes.$provider.$environment",
            config('marketplace.hub.oauth_routing_enabled', false)) === true;
    }
}
