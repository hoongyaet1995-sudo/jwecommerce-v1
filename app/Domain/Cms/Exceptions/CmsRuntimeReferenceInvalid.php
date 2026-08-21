<?php

namespace App\Domain\Cms\Exceptions;

use Illuminate\Validation\ValidationException;

final class CmsRuntimeReferenceInvalid extends ValidationException
{
    public const CODE = 'cms_runtime_reference_invalid';

    public function machineCode(): string
    {
        return self::CODE;
    }
}
