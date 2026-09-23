<?php

namespace App\Services\Marketplace;

final readonly class ShopeeApplicationContext
{
    public function __construct(
        public string $environment,
        public string $partnerId,
        #[\SensitiveParameter] public string $partnerKey,
        public string $callbackUrl,
        public string $oauthHost,
        public string $apiHost,
    ) {}
}
