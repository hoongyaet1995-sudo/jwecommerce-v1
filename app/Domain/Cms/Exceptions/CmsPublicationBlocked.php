<?php

namespace App\Domain\Cms\Exceptions;

use Illuminate\Validation\ValidationException;

final class CmsPublicationBlocked extends ValidationException
{
    public const CODE = 'cms_publication_blocked';

    public function machineCode(): string
    {
        return self::CODE;
    }
}
