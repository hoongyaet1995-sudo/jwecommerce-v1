<?php

namespace App\Services\Shipping;

final readonly class EasyParcelProviderSession
{
    public function __construct(
        public int $providerId,
        public int $credentialId,
        public int $configurationVersion,
        public string $credentialFingerprint,
        public string $accessToken,
        /** @var array<string, mixed> */
        public array $senderProfile,
    ) {}
}
