<?php

namespace App\Services\Shipping;

use RuntimeException;

final class EasyParcelVerificationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('EasyParcel connection verification failed.');
    }
}
