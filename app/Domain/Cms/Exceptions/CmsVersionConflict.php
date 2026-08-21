<?php

namespace App\Domain\Cms\Exceptions;

use RuntimeException;

final class CmsVersionConflict extends RuntimeException
{
    private function __construct(public readonly int $currentLockVersion)
    {
        parent::__construct('The page draft was changed by another session.');
    }

    public static function for(int $currentLockVersion): self
    {
        return new self($currentLockVersion);
    }
}
