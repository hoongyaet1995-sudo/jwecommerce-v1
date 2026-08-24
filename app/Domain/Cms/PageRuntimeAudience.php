<?php

namespace App\Domain\Cms;

final class PageRuntimeAudience
{
    public const PREVIEW = 'admin-preview';

    public const CANARY = 'protected-canary';

    public const PUBLIC = 'public-storefront';

    public static function isKnown(mixed $audience): bool
    {
        return is_string($audience) && in_array($audience, [
            self::PREVIEW,
            self::CANARY,
            self::PUBLIC,
        ], true);
    }
}
